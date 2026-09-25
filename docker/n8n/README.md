# Fleet automation (n8n)

Task 4.5. Local-scope build: the workflow is proven against the dry-run SSH target in `test-target/`.
Nothing here is deployed to production.

## Client onboarding workflow (`client-onboarding.workflow.json`)

Webhook `POST /webhook/fleet-onboard`, Header Auth (`X-Fleet-Token`, credential `fleet-webhook-token`).

```bash
curl -X POST http://localhost:5678/webhook/fleet-onboard \
  -H "X-Fleet-Token: <TOKEN>" -H 'Content-Type: application/json' \
  -d '{"slug":"acme","clientName":"Acme","businessName":"Acme Co","adminEmail":"owner@acme.example","brandColor":"#1a5c4a"}'
```

Body: `slug` (`^[a-z][a-z0-9-]{1,20}$`), `clientName`/`businessName` (<=80 chars, no control characters),
`adminEmail`, optional `brandColor` (`#rrggbb`), optional `debug_extra_reserve_mib` (number >= 0, test hook).
Responses (sent as soon as the pre-flight decision is known; the deploy then continues in the background):
`202` accepted, `422` invalid input (or reserved/legacy slug), `409` slug already exists (registry row `active`, `live`, a legacy/read-only row, or a run already in progress;
or the pre-flight found a compose project or a docker volume for that slug),
`503` blocked (`blocked_capacity` or `blocked_preflight_failed`).

### Config node (single place for thresholds)

| Key | Value | Meaning |
|---|---|---|
| `per_client_mib` | 441 | Task 2.4 conservative per-client RAM |
| `reserve_mib` | 1024 | RAM kept free for the box itself |
| `max_load_per_core` | 0.70 | block if 5-min load / nproc is higher |
| `min_free_disk_mib` | 3072 | block if `/var/lib/docker` (fallback `/`) has less free |
| `max_attempts` | 3 | hard bound on deploy attempts |
| `backoff_seconds` | 5 | local value; **use 60 in production** |
| `base_dir`, `repo_url`, `domain`, `alert_email` | `/opt`, repo URL, `digital-labs.ai`, alert address | placeholders, set per environment |
| `from_email` | `fleet-onboarding@qubix.local` | envelope sender for BOTH `Send Alert` and `Send Credentials`; placeholder, **must be a real deliverable address in production** (`.local` is mDNS-reserved and most relays reject it, which would silently lose the credentials email — the failure is recorded as a `credentials`/`delivery_failed` row and nowhere else) |

Capacity check: `free -m` "available" already reflects the two legacy stacks (Elasticsearch included) and the OS,
so Task 2.4's "subtract the legacy stacks" is satisfied by construction. RAM: `available - reserve >= per_client`;
CPU: `load5/nproc <= max_load_per_core`; disk: `free >= min_free_disk_mib`. It fails closed: if the pre-flight SSH
fails, its output cannot be parsed, or the docker project/volume listing itself failed, the decision is
`blocked_preflight_failed`. `debug_extra_reserve_mib` is ADDED to `reserve_mib`, so it can only make the check
stricter. Blocked = respond 503, record `blocked — insufficient capacity`, email an alert; the only edge into any
deploy node is the `Proceed?` true branch.

### In-flight accounting (concurrent onboardings)

`free -m` is a single sample, so two overlapping runs would otherwise each see the same free RAM and each
conclude it fits. The workflow therefore **writes before it reads**:

1. Right after validation and the 409 registry check — and **before** the pre-flight SSH — `Mark Provisioning`
   upserts this run's `fleet_clients` row with `status = provisioning`, `last_run_id = <run id>`, `updated_at = now`.
2. `List Inflight` then reads every `fleet_clients` row with `status = provisioning`, and `Evaluate` counts the
   ones that are **not** this slug and were updated in the last 2 h. Each one adds a full `per_client_mib` to the
   effective reserve. `capacity_json` records `inflight_count`, `inflight_slugs`, `inflight_reserve_mib` and
   `reserve_effective_mib` alongside the raw numbers.
3. Every outcome that ends the run **without deploying** (`blocked_capacity`, `blocked_preflight_failed`,
   `slug_taken`) goes through `Reset Claim`, which rewrites that row's status to the decision, so a blocked run
   never leaves a phantom `provisioning` reservation. `Reset Claim` is an **update** (not an upsert) matched on
   `slug` AND `last_run_id = this run id`: it cannot create a row and cannot touch a row belonging to another run
   or to a real client. Invalid input and the 409 paths are rejected before step 1, so they never create a row at all.

Because each run writes its own claim before it reads the in-flight set, **no two overlapping runs can both
proceed**. When their claims interleave closely, each sees the other and both block; otherwise the first
proceeds and the second blocks. Blocking both is the fail-closed outcome, not the only one.

This accounts for capacity, not for the same slug — two requests for the *same* slug are a different problem,
handled by the claim-once ownership marker below.

**Residual window — stated honestly, not claimed closed:**

- A run whose execution dies between step 1 and the end (n8n restart, an unhandled node error) leaves a
  **stranded `provisioning` row**. It keeps charging one client's worth of reserve, and keeps returning 409 to a
  retry of the same slug, until its `updated_at` is 2 h old. There is no janitor. Clear it by hand from the n8n
  Data Table UI (set `status` to `failed_escalated` or delete the row) if you do not want to wait.
- **A stale ownership marker** is the same class of problem on the host side. After a `failed_teardown`, or an
  execution that died between the clone and the teardown, `<base_dir>/qubix-<slug>/.git/fleet-run-id` still
  holds a run id that no longer owns anything. Every later run for that slug then ends `failed_terminal` with
  `FOREIGN_MARKER` and the note naming the file. That is deliberate — it is the only thing standing between a
  retry and another run's MySQL volume. **Clear it by hand once you have confirmed no stack is running for that
  slug**: `docker compose ls -a -q | grep qubix-<slug>` and `docker volume ls -q | grep qubix-<slug>` both
  empty, then `rm -f /opt/qubix-<slug>/.git/fleet-run-id`. The two are usually seen together: a stranded
  `provisioning` row on the n8n side and a stale marker on the host side.
- **A `provisioning` row can also survive a successful provision**: if `Mark Active` ultimately fails, the run
  still delivers the credentials email (with a warning appended) and records a `registry`/`mark_active_failed`
  row, but the registry row stays at `provisioning`. Set it to `active` by hand.
- The in-flight charge is a *model* (`per_client_mib` per run), not a measurement: a stack that is mid-build has
  not yet allocated its full RAM, so `free -m` and the model disagree during the overlap. The model is
  deliberately the pessimistic one.
- Nothing here is a distributed lock. It makes double-proceed impossible for runs that overlap the
  write-then-read window; it does not coordinate with anything provisioning on that host outside this workflow.

### Exit codes and classification (`scripts/provision-client.sh`)

`0` success; `1-9` terminal (never retried, never torn down); `10-19` transient. Any other value
(125/127/255, no code, signal) or an SSH error/timeout is treated as **transient** (default-to-transient, Task 4.3 ruling).
Transient failures below `max_attempts` are torn down (`docker compose down -v`, verified `GONE`), wait
`backoff_seconds`, and retry. If attempts are exhausted: teardown, status `failed_escalated`, alert with the redacted
output of every attempt. If teardown cannot prove the project gone: status `failed_teardown`, stop (a retry would
hit the slug-collision check, exit 2).

### Decommissioning a client that finished provisioning

The workflow's teardown only runs on the **failure** path. A client that provisioned successfully is removed
with `scripts/teardown-client.sh` (run on the VPS as root, from any checkout of this repo):

```bash
scripts/teardown-client.sh --slug=<slug> --confirm=<slug> --dry-run   # look first; changes nothing
scripts/teardown-client.sh --slug=<slug> --confirm=<slug>
```

It removes the Caddy block (validate → reload, rolled back on failure), then containers, named volumes and the
private network **selected by the Compose project label `qubix-<slug>`** (so it works even when the compose file
is missing), the `qubix/app-<slug>` image, and the `/opt/qubix-<slug>` checkout (compose file and `.env`
included). It refuses the reserved/live names (`sa`, `qubix`, `n8n`, `automation`, `fleet`, `www`, …) and any
project whose containers come from `docker-compose.{prod,sa,n8n}.yml`. It leaves the shared `qubix_qubix`
network, the `qubix/provisioner` image and Caddy's certificate store alone. Exit codes: 0 done/already gone,
1 bad input or `--confirm` mismatch, 2 refused, 10 a step failed (safe to re-run).

**It does not touch n8n.** The `fleet_clients` row keeps its old status, and the onboarding workflow's
"slug exists" check keeps returning 409 for that slug until the row is updated or deleted by hand.

Tests (no n8n, nothing outside a temp dir and `qubix-tdt*` throwaway resources): `docker/n8n/test-workflow-graph.py`
(workflow topology — the only cycle allowed is the retry loop), `docker/n8n/test-teardown-cmd.sh` (the workflow's
own teardown command against a stub `docker`), `scripts/test-teardown-client.sh` (the script above against real
local Docker, with a decoy client that must survive).

### Teardown ownership rule

`docker compose down -v` destroys a MySQL volume, so it is guarded three times over:

1. **Pre-flight ownership.** Teardown runs only when the pre-flight proved the slug was free
   (`owned_by_run`). "Free" means BOTH that `qubix-<slug>` is absent from `docker compose ls -a -q` AND that no
   docker volume matches `(^|_)qubix-<slug>-(mysql|redis)$`. The volume check exists because a client that was
   taken down for maintenance, or rebuilt, or hand-provisioned, has no compose project but still owns its data —
   `docker compose ls` cannot see it, and claiming that slug would put a real client's database one transient
   failure away from `down -v`. Either signal returns 409 `slug_taken` before anything is touched, and no `down`
   is ever issued. A checkout directory on its own is deliberately **not** evidence: a `failed_escalated` run
   keeps its checkout on purpose, and re-onboarding must stay possible once teardown has removed the volumes.
2. **Claim-once ownership marker (the TOCTOU defence).** `Check Existing` and `Mark Provisioning` are two
   separate Data Table calls with no compare-and-set, so two near-simultaneous requests for the *same* slug can
   both be admitted; both pre-flights then see the slug free and both get `owned_by_run`. The marker closes that
   at the point of destruction.

   Every deploy attempt's remote command **claims** `<base_dir>/qubix-<slug>/.git/fleet-run-id` (inside `.git`,
   so it never dirties the checkout) rather than overwriting it: if the file already exists holding a *different*
   run id, the command prints `FOREIGN_MARKER` and exits **2** — terminal, so the second run stops with no
   deploy, no teardown and no retry, and the first run's stack is untouched. Writing the same run id again is a
   no-op, so a run's own retry attempts are unaffected.

   The claim is **atomic**: `(set -C; echo "$RUNID" > "$MARKER")` makes the shell create the file with `O_EXCL`,
   so the existence check and the write are one syscall. The earlier `if [ -e … ]; then …; fi; echo > marker`
   form was check-then-write, and two same-slug runs that both reached it before either wrote could both pass
   the check and both proceed — the remaining route to one run's teardown destroying another's live stack. If
   the create fails, the marker is read back: absent ⇒ the write itself failed (unwritable `.git`, full disk),
   which prints `MARKER_WRITE_FAILED` and exits **10** (transient, retryable, nothing claimed); equal to this
   run's id ⇒ this run's own retry, proceed; anything else ⇒ `FOREIGN_MARKER`, exit 2.

   The teardown command's **first** action is to look at that file, and it distinguishes three cases, not two:

   - **absent** → `NO_MARKER`, exit 0, no `down`. Nothing was ever claimed, and since the claim happens before
     `provision-client.sh` can run, nothing was ever created — the usual cause is the `git clone` itself
     failing (exit 10). This is **not** an error: the run follows the same path an empty, successful teardown
     follows, i.e. it retries if attempts remain and otherwise ends `failed_escalated`. (It used to be reported
     as `NOT_OWNER`, which made a failed clone terminal, never retried, and attached a note falsely claiming
     another run owned the checkout.)
   - **holds another run's id** → `NOT_OWNER`, exit 0, no `down`; the workflow records `failed_teardown` and
     stops, never retried. This one is the real safety guard and is deliberately terminal.
   - **holds this run's id** → the teardown runs.

   When the teardown does run, `docker compose down -v` and the following `docker compose ls` **both** have
   their exit status captured (`DOWN_RC`, `LS_RC`) and both are checked, on the host and again in
   `Teardown Eval`. The project counts as gone — and the marker is released — only when the listing succeeded,
   does not contain the project, and `down -v` itself exited 0. Otherwise the command prints `LS_FAILED`,
   `STILL_PRESENT` or `DOWN_FAILED`, the marker survives, and the run ends `failed_teardown`. This matters
   because a docker daemon that is unreachable mid-teardown fails *both* commands: the listing's stdout is then
   empty, so a bare `grep -qx` matches nothing, which used to look exactly like "the project is gone" and
   released the marker although nothing had been torn down.

   Releasing the marker on a confirmed teardown is what keeps re-onboarding the same slug possible after a
   `failed_escalated` run. After a `failed_teardown` the marker deliberately survives and blocks later runs for
   that slug until a human clears it (see "In-flight accounting" → residual window).
3. **Legacy slugs** (`jjbags-in`, `jj-bags-com`, `sa`, `qubix`, ...) are rejected in validation, so no write node
   can match a legacy row in `fleet_clients`.

If the teardown runs but cannot prove the project gone (`STILL_PRESENT`, `DOWN_FAILED`, `LS_FAILED`, a missing
`DOWN_RC`/`LS_RC` line, or an SSH error), the run also stops as `failed_teardown` — retrying would hit the
slug-collision check and burn an attempt on a terminal exit 2.

### Secrets and execution data

The generated admin password is extracted from stdout and redacted (`[REDACTED]`) before anything is stored or
alerted. Redaction does **not** hinge on one exact sentence: ANSI escapes and CRs are stripped first, the known
wording is tried, then a generic `password...: <value>` form, and finally a blanket second pass scrubs every
remaining `password`/`secret`/`token`/`passphrase`/`api_key` assignment in the captured output. The password is
emitted in exactly one place, the credentials email.

**Execution data is never persisted.** The workflow sets **both** `saveDataSuccessExecution: none` and
`saveDataErrorExecution: none`. Without the second one, any error after the password was extracted — SMTP briefly
unreachable, a Data Table hiccup in `Mark Active` — would make n8n save every node's output, including the
plaintext password and the rendered email body, readable by anyone with n8n UI access until pruning, on the same
instance that holds the VPS deploy key.

**The trade-off:** there is no n8n execution history for this workflow at all, successful or failed. Debugging
happens through the `provision_attempts` table instead, which carries one row per pre-flight, deploy attempt,
teardown, credential delivery and failed alert, each with a redacted ≤4000-char output tail. When something goes
wrong, read the table, and reproduce against the dry-run target in `test-target/` — do not expect to open the
execution in the editor. (Execution pruning env vars are still worth setting in production for every *other*
workflow on the instance: `EXECUTIONS_DATA_PRUNE=true`, `EXECUTIONS_DATA_MAX_AGE=720` hours.)

**Delivery is failure-tolerant, and failures are recorded.** `Send Credentials` and `Send Alert` both retry 3
times and then continue rather than failing the run:

- After `Send Credentials`, a `provision_attempts` row is written with phase `credentials` and classification
  `delivered` or `delivery_failed` (no secret in it). On `delivery_failed` the note says the password was not
  delivered, is stored nowhere, and the client admin password must be reset by hand.
- After `Send Alert`, a row with phase `alert` and classification `alert_failed` is written **only when the alert
  could not be delivered** — so a failed run cannot end with nobody notified and no trace of it.
- `Mark Active` gets the same treatment (retry 3, then continue), because a Data Table failure there used to end
  the run **before** the credentials email — leaving a client provisioned with nobody told its password. It now
  continues to the email (which gains a warning line) and records a `registry` / `mark_active_failed` row.
  Detection detail: n8n strips the item-level `error` before a Code node can see it, so the failure is detected
  by whether `Mark Active` handed back the row it was supposed to write.
- The generated password is cleared from the loop state on any non-success classification, so it can never ride
  into `Backoff Wait` — n8n persists a waiting execution's data regardless of the save-execution settings.
- If the provisioning output succeeded but no password could be extracted at all, **no blank-credential email is
  sent**: the message becomes an `ACTION REQUIRED — provisioned WITHOUT a captured password` alert carrying no
  credential, the deploy row's note says so, and the password must be reset by hand.

Email itself is plaintext SMTP.

### Caddy site-block registration (Task 4.4)

`Prep Caddy` builds a command that runs `scripts/generate-caddy-block.sh` + `scripts/apply-caddy-block.sh`
**directly on the target** (no nested SSH — it's the same SSH session `Deploy SSH` already used to
`git clone --branch fleet` this client's own checkout, which is where both scripts physically come from),
`Caddy SSH` runs it, `Caddy Eval` classifies the exit code, and `Rec Caddy` records a `provision_attempts` row
with phase `caddy`.

**Chained after `Rec Delivery` (the credentials pipeline's true terminal node) — deliberately sequential, not
parallel.** An earlier design put this branch in parallel with `Build Credentials Email`, both firing directly
off `Mark Active`. That measured worse than the original serialized version it replaced: this n8n instance
does not run fan-out siblings concurrently — it resolves one to completion before starting the next, in an
order that is **not** determined by which order the connections were added via the API (reordering the
connection array had no effect, confirmed by two independent timed tests with an artificially slow Caddy
stub). Since concurrency isn't something this runtime reliably offers here, the only proven way to guarantee
credentials are never delayed is an explicit sequential dependency: `Prep Caddy` fires only after `Rec Delivery`
has already run, which only happens once `Send Credentials` has already completed. Verified with a 4-second
artificial delay in a stubbed `apply-caddy-block.sh`: the `credentials` provision_attempts row (and the actual
mailpit-received email) both land before the `caddy` row even starts.

**Non-fatal, but no longer surfaced in the credentials email.** A Caddy registration failure never triggers
teardown and never blocks credentials delivery — the app stack is already up and reachable either way. An
earlier version appended a warning to the credentials email when Caddy registration failed; that was removed
once Caddy registration stopped running before the email is built (see above) — `Caddy Eval` hasn't run yet at
that point, so checking its result there was already dead code reading data that didn't exist yet. The outcome
is still fully recorded via `Rec Caddy`'s `provision_attempts` row (`phase = 'caddy'`) — check there, or the
dashboard, rather than the email, if a subdomain doesn't come up.

**Reserved subdomains.** `automation`, `www` and `fleet` (the hostnames of the n8n editor and the dashboard,
see "Fleet-infrastructure Caddy blocks" below) can never be a client slug. The list exists in **three** places — 
`scripts/generate-caddy-block.sh`, `scripts/register-caddy-client.sh`, and the `LEGACY` array in the onboarding
workflow's `Validate` node (which rejects them before any deployment action, like the legacy-site names). The
automated path (`Prep Caddy`) calls `generate-caddy-block.sh` directly and never goes through
`register-caddy-client.sh`, which is why both scripts carry their own copy — see that script's header.
`docker/n8n/caddy/test-blocks.sh` fails if any of the three copies stops rejecting one of these names. Note that
`qubix:provision` itself has no reserved-name check: the workflow's `Validate` node is the gate on the automated path.

`apply-caddy-block.sh`'s own logic (stage the block as `<slug>.caddy.new`, `caddy validate` a candidate Caddyfile that
imports it, one atomic rename/unlink, then `reload`; serialised by a flock) is proven locally by
`docker/n8n/caddy/test-apply-atomic.sh`, and the earlier backup-and-rollback version was proven separately, live, against `hostinger-vps` production
(two full register/remove cycles, both live sites re-checked as 200 after every step) — see
`scripts/register-caddy-client.sh`'s header for why that script (a developer's own machine reaching the target
over SSH) and `apply-caddy-block.sh` (already-on-the-target logic, what this workflow calls) are two separate
files sharing the one real implementation.

**To register a real client's Caddy block by hand** (if this step failed, or before this workflow is wired to
production): `scripts/register-caddy-client.sh --slug <slug>` from any machine with the `fleet` repo checked out
and SSH access to `hostinger-vps`.

**Known follow-ups, not fixed here** (all Minor/non-blocking, recorded rather than silently left out):
temp file paths used by `Prep Caddy` and `register-caddy-client.sh` (`/tmp/qubix-caddy-<slug>...`) have no
run-id/PID disambiguator strong enough to rule out a collision if the same slug is re-registered while a prior
attempt for it is still in flight on the target; `apply-caddy-block.sh` takes a flock on the `clients/` directory (no lock file), so
concurrent applies (a retry racing a manual run) are serialised and cannot interleave; a
`caddy validate` failure caused by the container being transiently unavailable (mid-restart) is indistinguishable
from a real Caddyfile syntax error in the recorded note. None of these have a known live-production occurrence;
they're the kind of edge case this plan's own concurrency work (the deploy step's atomic ownership marker) was
built to close for the deploy path specifically, not yet replicated here.

### Resetting a client admin password by hand

Needed whenever the credentials email was not delivered, or the password was never captured. The generated
password is not recoverable — set a new one. This runs inside **that client's own app container**, on the host
that owns the stack, and mirrors exactly what `qubix:provision` does at the end of a provision
(`Provision.php` → `qubix:provision-admin`, run as `docker compose exec -T -u sail app-<slug>`):

```bash
NEW=$(LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 20); echo "$NEW"   # keep this somewhere safe
cd /opt/qubix-<slug>
docker compose -f docker-compose.<slug>.yml -p qubix-<slug> exec -T -u sail app-<slug> \
  php artisan qubix:provision-admin \
    --name='<Client Name>' --email='<the client admin email>' --password="$NEW"
```

`qubix:provision-admin` upserts the `id = 1` admin row (name, email, bcrypt-hashed password, `role_id = 1`,
`status = 1`) — the same row the installer's own admin step writes. It **hashes** the password; the plaintext is
never stored. Omit `--brand-color` to leave the client's branding untouched (passing it would rewrite
`general.design.storefront_branding.storefront_branding` on the `default` channel). Use `-u sail`, never a bare
`docker exec`, or you will leave root-owned files in `storage/`.

### Data tables

**Column types matter** — n8n data-table columns are typed at creation and cannot be retyped afterwards, and
the workflow depends on the types below (`est_ram_mib` is used in arithmetic, `legacy`/`read_only` are compared
with `=== true`, `updated_at` is parsed with `Date.parse`, `exit_code` is written as `null`, and `Registry Eval`
discriminates on `typeof row.id === 'number'`). Recreate them exactly, in this order:

`fleet_clients` — seeded with `jjbags.in` (1633 MiB) and `jj-bags.com` (1599 MiB), `legacy=true, read_only=true,
status=live`:

| # | Column | Type |
|---|---|---|
| 0 | `slug` | string |
| 1 | `client_name` | string |
| 2 | `business_name` | string |
| 3 | `subdomain` | string |
| 4 | `site_url` | string |
| 5 | `admin_url` | string |
| 6 | `status` | string |
| 7 | `last_run_id` | string |
| 8 | `est_ram_mib` | number |
| 9 | `legacy` | boolean |
| 10 | `read_only` | boolean |
| 11 | `updated_at` | date |

`provision_attempts` — one row per pre-flight, deploy attempt, teardown, credential delivery, and per
undeliverable alert:

| # | Column | Type |
|---|---|---|
| 0 | `run_id` | string |
| 1 | `slug` | string |
| 2 | `attempt_no` | number |
| 3 | `phase` | string (`preflight\|deploy\|caddy\|teardown\|credentials\|alert\|registry`) |
| 4 | `started_at` | date |
| 5 | `finished_at` | date |
| 6 | `exit_code` | number (nullable — written as `null` for non-SSH phases) |
| 7 | `classification` | string |
| 8 | `decision` | string |
| 9 | `capacity_json` | string |
| 10 | `output_tail` | string |
| 11 | `note` | string |

n8n adds `id`, `createdAt` and `updatedAt` itself; the workflow reads `id` and `updatedAt` but never writes
them. The Data Table node references tables by id; after import re-select both tables in every Data Table node
(`Get Client`, `List Inflight`, `Mark *`, `Reset Claim`, `Rec *`), and in the dashboard's two get-nodes.

`fleet_clients.status` values written by this workflow: `provisioning` (claimed, run in flight), `active`,
`failed_terminal`, `failed_escalated`, `failed_teardown`, `blocked_capacity`, `blocked_preflight_failed`,
`slug_taken`. `live` belongs to the two seeded legacy rows and is never written by the workflow.

### Re-import / recreate

1. n8n -> Import from file `client-onboarding.workflow.json`.
2. Create credentials with the names the workflows expect: `fleet-target-ssh` (SSH private key, host/user of the
   deploy target), `fleet-webhook-token` (Header Auth, header `X-Fleet-Token`, random 40+ chars),
   `fleet-alert-smtp` (SMTP), and — for the dashboard — `fleet-dashboard-basic-auth` (Basic Auth, any user name,
   long random password). Local test: SMTP `host.docker.internal:1026` (Mailpit), no TLS/auth.
3. Create the two data tables above, **with the column types listed above**, and seed the two legacy rows;
   re-select them in the nodes of both workflows.
4. Confirm the workflow settings carry **both** `saveDataSuccessExecution: none` and
   `saveDataErrorExecution: none` (Settings → "Save successful/failed production executions" = Do not save).
   An import that loses either one reintroduces the plaintext-password-in-execution-data problem.
5. Activate. Local target: `test-target/run.sh` — set `KEY_PUB` to an ssh public key path and read its header
   first; it never bind-mounts anything and copies the public key in with `docker cp`.

### Dry-run target scenario hooks (`test-target/`)

`test-target/run.sh` builds and starts the throwaway SSH target; `--reset` restores pristine state (seeded
compose projects **and** docker volumes, fresh scenarios, empty logs, no cloned dirs); `--down` removes it.
It never bind-mounts anything — the public key goes in with `docker cp`, because Docker materialises a missing
bind-mount source as a **root-owned** host path, and this key normally lives in a scratch directory under `/tmp`.
A missing `KEY_PUB` is a hard error instead.

Per-slug behaviour is scripted with files under `/opt/fleet-target/scenario/` (seeded from `scenarios/`):

| File | Effect |
|---|---|
| `<slug>` | space-separated exit codes, one consumed per `provision-client.sh` run (e.g. `10 10 0`) |
| `<slug>.down` | `fail` = the teardown `down` exits 1; `present` = `down` succeeds but the project survives (`STILL_PRESENT`); `daemon` = the docker daemon "goes away" at `down` time and stays away, so `down` **and** the project listing that follows both fail (`DOWN_RC=1`, `LS_RC=1`, `LS_FAILED`) |
| `<slug>.clone` | space-separated exit codes, one consumed per `git clone` (e.g. `1 0` = first clone fails, second succeeds). A failing clone creates nothing, like real git — so the deploy exits 10 before the ownership marker is claimed and the teardown that follows must report `NO_MARKER`, not `NOT_OWNER` |
| `<slug>.build` | space-separated exit codes, one consumed per `provision-client.sh` run (e.g. `10 0`). A non-zero code models a failed `docker build`: the shim exits with it **before creating anything** — no compose file, no project, no volumes — exactly as the real wrapper does before `qubix:provision` has generated `docker-compose.<slug>.yml`. The teardown that follows must report `GONE` and retry, not `DOWN_FAILED` |
| `<slug>.marker` | rewrites `<checkout>/.git/fleet-run-id` during provisioning — simulates another run claiming the checkout, so teardown hits `NOT_OWNER` |
| `<slug>.pwfmt` | `alt` = different password wording, `ansi` = ANSI-decorated line, `none` = no password printed at all |

The `docker` shim also answers `volume ls -q` from `state/volumes`, and its `down -v` removes only the target
project's own volumes. `state/volumes` is seeded with a `qubix-stray_qubix-stray-mysql` entry that has **no**
compose project — the "client whose containers were removed still owns its data" case. The
`provision-client.sh` shim mirrors the real wrapper's pre-validation (control characters, bare `--slug`, missing
slug, slug regex → exit 1 with zero state changes), because it builds a path from `--slug`.

### Manual checklist for production (not done here)

- [ ] SSH deploy key for the VPS: dedicated key, authorized on the VPS deploy user, private key stored only in the
      n8n credential; user needs docker + write access to `base_dir`.
- [ ] **Git credentials on the target host for `repo_url`.** The deploy command runs `git clone --branch fleet
      --depth 1 <repo_url> <dir>` **as the deploy user on the VPS**, non-interactively. A private repo therefore
      needs a deploy key in that user's `~/.ssh` (and an `ssh://git@…` `repo_url`), or a PAT in a credential
      helper / `~/.netrc` for the current `https://github.com/…` URL. Without it the very first real onboarding
      fails at the clone — the single most likely first-production failure. It is now handled gracefully (the
      run reports `NO_MARKER` and retries, and nothing is ever torn down), but it is still a failure: verify the
      clone by hand as the deploy user before the first real run. Note the branch **`fleet` is hardcoded** in
      `Prep Deploy`; change it there if a real deployment should track a different branch.
- [ ] Real SMTP / alert channel; set `alert_email` **and `from_email`** in Config (the credentials email carries
      a password). `from_email` defaults to a `.local` placeholder, which a real relay will reject — and a
      rejected credentials email means the generated admin password is gone, visible only as a
      `credentials`/`delivery_failed` row. Monitor the alert address too: with execution data off, an
      undelivered alert is visible only as an `alert`/`alert_failed` row.
- [ ] n8n production compose service on the VPS. (The Caddy blocks and their access restriction are built and locally
      tested — see "Fleet-infrastructure Caddy blocks" — but not yet applied.)
- [ ] Execution pruning env vars (above, for the instance's other workflows), `backoff_seconds` = 60,
      real `repo_url`.
- [ ] Rotate `fleet-webhook-token` and the dashboard's `fleet-dashboard-basic-auth` credential; keep both
      webhooks reachable only from trusted callers.
- [ ] Decide who clears a stranded `provisioning` row (see "In-flight accounting") — today that is a human.
- [ ] **Open risk, not fixed: a long provision can outlive its SSH connection.** `provision-client.sh` keeps
      running on the target after the SSH channel drops (an idle timeout during a long `docker build`, a network
      blip). n8n then sees a transient failure and, if attempts remain, retries — and that retry is *permitted*
      to `down -v`, because the marker holds its own run id. It would be tearing a stack down while the original
      process is still mid-build. For a brand-new client there is no customer data at risk yet, and no run of
      this workflow has ever hit it, but nothing prevents it either. Closing it means one of: an explicit long
      SSH keep-alive/timeout on the `Deploy SSH` node, or running the remote provision detached (`setsid`/`nohup`
      + a pid/status file) and polling for completion instead of holding the connection. Neither is implemented.

## Fleet dashboard (`fleet-dashboard.workflow.json`)

Workflow `Fleet — Dashboard`: `GET /webhook/fleet-dashboard`, **Basic Auth** (credential
`fleet-dashboard-basic-auth`) -> reads `fleet_clients` and `provision_attempts`
(get-only Data Table nodes; the workflow contains no write node) -> one Code node renders a self-contained,
JavaScript-free HTML page -> Respond to Webhook (`text/html`, `Cache-Control: no-store`,
`X-Content-Type-Options: nosniff`).

Summary tiles:

- **Fleet clients** — non-legacy rows that are `active` or `live`, with any `provisioning` (and any
  unrecognised status) called out underneath.
- **Failed deploys** — `failed_terminal` / `failed_escalated` / `failed_teardown`. Something was attempted, so a
  stack, volumes or a checkout may be left on the host.
- **Rejected requests** — `blocked_capacity` / `blocked_preflight_failed` / `slug_taken`. These rows exist
  because the claim is written *before* the pre-flight runs (see "In-flight accounting"), then rewritten by
  `Reset Claim`; **nothing was deployed for them and there is nothing to clean up on any host.** They used to be
  counted as failed clients, which badly overstated the failure count.
- **Legacy sites**, **Est. RAM footprint** (summed `est_ram_mib` over RUNNING stacks only — `live`/`active`/
  `provisioning`; failed and rejected rows have no stack and are excluded), and **clients that still fit**,
  labelled as of the newest pre-flight with its timestamp and compose-project count — a stale value that does
  not include clients provisioned since.

Below the tiles: a client table (status badge, site and `/admin` links; the two legacy rows are badged
"legacy - read-only" and have no actions), a per-client `<details>` history of every attempt row, and
"**Attempts with no client row**" — which today means input rejected in **validation** (422), before any client
row could be claimed, plus any attempt whose client row was deleted by hand. Blocked and rejected requests are
*not* in that list any more; they appear in the client table with their own status.

Status badges are coloured for every status the onboarding workflow can write (`live`/`active` green;
`provisioning` and the three rejected statuses amber; the three failed statuses red); anything else renders
uncoloured, which is the signal that a status was set outside the workflow.

Every dynamic value goes through one `esc()` helper; link hrefs must start with `http(s)://`
or the link is dropped. Only summary numbers from `capacity_json` are shown (never the raw blob), plus the
already-redacted output tails (defensively re-scrubbed for `password/secret/token: value` patterns). It exposes no
secret, token or credential.

Note: n8n replaces the `Content-Security-Policy` response header on HTML webhook responses with its own `sandbox`
policy, so the header set in the workflow is not what the browser receives. The page therefore also carries the
same policy in a `<meta http-equiv>` tag, and Caddy must set the strict header (below).

**Access control.** The webhook itself now requires **Basic Auth** (n8n credential `fleet-dashboard-basic-auth`,
header-level; an unauthenticated or wrong-password request gets `401 Authorization is required!` and neither
data table is read). That is the control that actually protects the page, because it travels with the workflow
export and does not depend on anything outside n8n — the page exposes every client's name, business name,
subdomain, site and admin URL and full provisioning history, and until this was added the page was open to
anyone who could reach the n8n port. Create the credential with a long random password and rotate it with the
webhook token.

### Fleet-infrastructure Caddy blocks (Task 4.4 piece 2)

The two hostnames that front n8n itself are **not** client blocks and cannot come from `generate-caddy-block.sh`
(its template is a plain reverse proxy, and it rejects these names). They are hand-written templates:

| Host | Template | What Caddy lets through |
|---|---|---|
| `automation.digital-labs.ai` | `docker/n8n/caddy/automation.caddy.tpl` | **Only** `POST /webhook/fleet-onboard` (64 KiB body cap, guarded by n8n's `X-Fleet-Token` credential) skips Caddy's password. **Everything else** — the editor, `/rest/*`, `/webhook-test/*`, `/api/*`, any other method or path variant — needs Caddy basic auth (plus n8n's own login). |
| `fleet.digital-labs.ai` | `docker/n8n/caddy/fleet.caddy.tpl` | **Only** `GET /webhook/fleet-dashboard`, with the strict CSP (`header >…` replaces n8n's own `sandbox` policy). Everything else is a 404 that never reaches n8n. |

Decisions worth knowing before you change either block:

- **The exemption is an exact comparison of the raw request path**, not Caddy's `path` matcher. That matcher is
  case-insensitive and merges `//`, so `//webhook/fleet-onboard` and `/WEBHOOK/fleet-onboard` would have skipped
  the password. Found by the local harness, not by inspection. A percent-encoded spelling that decodes to the exact
  path (`/webhook/%66leet-onboard`) is still the same endpoint and is allowed.
- **No Caddy basic auth on `fleet.`, deliberately** — this departs from the earlier "require basic auth of its own"
  requirement. The dashboard webhook already enforces Basic Auth inside n8n (credential
  `fleet-dashboard-basic-auth`), and Caddy's `basic_auth` reads and forwards the very same `Authorization`
  header. A browser sends one credential, so two layers with different passwords can never both pass, and two with
  the same password add nothing. n8n's check travels with the workflow export. On `automation.` the two do not
  collide: the editor authenticates with a cookie and the n8n API with `X-N8N-API-KEY`.
- The n8n editor host is public and protected by basic auth + n8n's login, not an IP allow-list (an allow-list
  locks the owner out whenever their ISP address changes). `POST /webhook/fleet-onboard` is therefore reachable by
  anyone and protected only by the `X-Fleet-Token` credential — rotate it like a password.

**Applying them** (from a `fleet` checkout — `/opt/qubix` tracks `abood` and has neither the apply script nor the
templates):

```bash
docker exec -it qubix-caddy-1 caddy hash-password | scripts/register-n8n-caddy-blocks.sh   # add --user, --host, --target-dir as needed
scripts/register-n8n-caddy-blocks.sh --remove                                              # take both down again
```

The bcrypt hash travels on stdin only, is rendered by `docker/n8n/caddy/render-block.sh` with plain bash
replacement (a `$2a$14$…` hash is mangled by `envsubst`), and reaches the VPS through SSH's stdin into a `mktemp`
(0600) file. The wrapper refuses to start unless both live sites already answer 200; snapshots the current blocks;
applies each through `apply-caddy-block.sh` (validate the whole config, then reload); re-checks the live sites;
then verifies over real TLS that `automation.` answers 401 and `fleet.` answers 404 (neither needs n8n running).
On any failure it **restores the previous block** (or removes one that did not exist) — it never deletes a block
that was working before the run. Exit codes are in the script header (`0/1/2/10/11/12`, and `14` = the rollback
itself failed, check the running config by hand).

**Tests, all local — nothing touches the VPS:**

- `docker/n8n/caddy/test-blocks.sh` — real Caddy + a stub n8n that emits n8n's own `sandbox` CSP and counts what it
  actually receives. Asserts the render, `caddy validate` against the real `docker/caddy/Caddyfile`, the path
  matrix (dot-segments, `%2e%2e`, `//`, trailing slash, sub-paths, case, HEAD/OPTIONS/PUT, the 64 KiB cap, a real
  `caddy hash-password` hash) and that the three reserved-slug lists agree.
- `docker/n8n/caddy/test-apply-atomic.sh` — `apply-caddy-block.sh` alone against a real Caddy: an unvalidated block is
  never visible to the `import clients/*.caddy` glob (SIGKILL mid-validation, then a Caddy restart still comes up), a
  duplicate site address across clients is rejected, re-registering over an existing block works, concurrent applies
  are serialised, and a Caddyfile without exactly one import line fails closed. Fails against the previous version.
- `docker/n8n/caddy/test-wrapper.sh` — the real wrapper and `apply-caddy-block.sh` against a throwaway Compose
  project shaped like the VPS one, with an `ssh` shim. Includes: a failed re-apply restores the previous bytes, a
  validation failure on the second block restores the first, and a live site being down blocks the run.

**Not done by this piece:** the blocks have not been applied to the VPS, and n8n is not running there. Until the
`n8n` container exists on `qubix_qubix` the hosts answer 401 / 404 as above, but an authenticated request to
`automation.` returns 502.

## Production compose file (`docker-compose.n8n.yml`)

Definition only; never run against any host by this task. One `n8n` service, image pinned to `2.39.7`, no
published ports, joins external `qubix_qubix` so Caddy can reach `n8n:5678`, named volume `n8n-data`, log rotation,
pruning at 30 days, `N8N_ENCRYPTION_KEY` required from the environment. `mem_limit` is a placeholder: measure on
the VPS and count it against Task 2.4's capacity budget. `N8N_PROXY_HOPS=1` makes n8n trust one reverse proxy (Caddy), so client IPs and rate limiting key off
`X-Forwarded-For` instead of Caddy's container IP. **Backup:** the `n8n-data` volume holds the SQLite database -
BOTH data tables (client registry and full attempt history) and the encrypted credentials - so include it in the VPS
backup routine (per-volume backup, like the other stacks). Losing `N8N_ENCRYPTION_KEY` makes stored credentials
unreadable. Validate with
`N8N_ENCRYPTION_KEY=x docker compose -f docker/n8n/docker-compose.n8n.yml config`.

### Manual checklist: standing n8n up in production (human steps)

- [ ] Generate the encryption key once (`openssl rand -hex 32`), keep it in the VPS secret store and a password
      manager; never change it.
- [ ] `docker compose -p qubix-n8n -f docker/n8n/docker-compose.n8n.yml up -d` on the VPS (after Caddy is ready).
- [ ] DNS: the `*.digital-labs.ai` wildcard already covers both hostnames (verified 2026-09-24: `automation.`,
      `fleet.` and an arbitrary name all resolve to the VPS; the zone is at GoDaddy). Apply the Caddy blocks with
      `scripts/register-n8n-caddy-blocks.sh` (see above) — owner go-ahead required, it edits the Caddy that serves
      both live sites.
- [ ] Open the editor and create the first owner account (strong password).
- [ ] Recreate credentials `fleet-target-ssh`, `fleet-webhook-token`, `fleet-alert-smtp` and
      `fleet-dashboard-basic-auth` with real values.
- [ ] Create data tables `fleet_clients` and `provision_attempts` (columns AND types above) and seed the two
      legacy rows.
- [ ] Import `client-onboarding.workflow.json` and `fleet-dashboard.workflow.json`; re-select credentials and both
      tables in every Data Table node; set Config (`repo_url`, `alert_email`, `from_email`,
      `backoff_seconds` = 60).
- [ ] Activate both workflows; confirm the dashboard webhook answers `401` without credentials, then verify it
      through Caddy (`https://fleet.digital-labs.ai/webhook/fleet-dashboard`, n8n's Basic Auth) and confirm the CSP header.
