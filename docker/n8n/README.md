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
`202` accepted, `422` invalid input (or reserved/legacy slug), `409` slug already exists / in progress / active,
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

Because each run writes its own claim before reading the others, two same-moment requests always see each other
and **both block** (fail closed) rather than both proceeding. That is the verified behaviour, not an aspiration.

**Residual window — stated honestly, not claimed closed:**

- A run whose execution dies between step 1 and the end (n8n restart, an unhandled node error) leaves a
  **stranded `provisioning` row**. It keeps charging one client's worth of reserve, and keeps returning 409 to a
  retry of the same slug, until its `updated_at` is 2 h old. There is no janitor. Clear it by hand from the n8n
  Data Table UI (set `status` to `failed_escalated` or delete the row) if you do not want to wait.
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
2. **Ownership marker (the TOCTOU defence).** Every deploy attempt's remote command writes this run's id to
   `<base_dir>/qubix-<slug>/.git/fleet-run-id` after the idempotent clone and before `provision-client.sh`
   (inside `.git`, so it never dirties the checkout). The teardown command's **first** action is
   `[ "$(cat <dir>/.git/fleet-run-id 2>/dev/null)" = '<run id>' ]`; on a mismatch it prints `NOT_OWNER`,
   does **not** run `down`, and the workflow records status `failed_teardown` and stops. It is never retried.
3. **Legacy slugs** (`jjbags-in`, `jj-bags-com`, `sa`, `qubix`, ...) are rejected in validation, so no write node
   can match a legacy row in `fleet_clients`.

If the teardown runs but cannot prove the project gone (`STILL_PRESENT`, a failing `down`, or an SSH error), the
run also stops as `failed_teardown` — retrying would hit the slug-collision check and burn an attempt on a
terminal exit 2.

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
- If the provisioning output succeeded but no password could be extracted at all, **no blank-credential email is
  sent**: the message becomes an `ACTION REQUIRED — provisioned WITHOUT a captured password` alert carrying no
  credential, the deploy row's note says so, and the password must be reset by hand.

Email itself is plaintext SMTP.

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

`fleet_clients` (slug, client_name, business_name, subdomain, site_url, admin_url, status, last_run_id,
est_ram_mib, legacy, read_only, updated_at) — seeded with `jjbags.in` (1633 MiB) and `jj-bags.com` (1599 MiB),
`legacy=true, read_only=true, status=live`. `provision_attempts` (run_id, slug, attempt_no, phase
`preflight|deploy|teardown|credentials|alert`, started_at, finished_at, exit_code, classification, decision,
capacity_json, output_tail, note): one row per pre-flight, deploy attempt, teardown, credential delivery, and
per undeliverable alert. The Data Table node references tables by id; after import re-select both tables in
every Data Table node (`Get Client`, `List Inflight`, `Mark *`, `Reset Claim`, `Rec *`).

`fleet_clients.status` values written by this workflow: `provisioning` (claimed, run in flight), `active`,
`failed_terminal`, `failed_escalated`, `failed_teardown`, `blocked_capacity`, `blocked_preflight_failed`,
`slug_taken`. `live` belongs to the two seeded legacy rows and is never written by the workflow.

### Re-import / recreate

1. n8n -> Import from file `client-onboarding.workflow.json`.
2. Create credentials with the names the workflow expects: `fleet-target-ssh` (SSH private key, host/user of the
   deploy target), `fleet-webhook-token` (Header Auth, header `X-Fleet-Token`, random 40+ chars),
   `fleet-alert-smtp` (SMTP). Local test: SMTP `host.docker.internal:1026` (Mailpit), no TLS/auth.
3. Create the two data tables above and seed the two legacy rows; re-select them in the nodes.
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
| `<slug>.down` | `fail` = the teardown `down` exits 1; `present` = `down` succeeds but the project survives (`STILL_PRESENT`) |
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
- [ ] Real SMTP / alert channel; set `alert_email` in Config (the credentials email carries a password).
      Monitor it: with execution data off, an undelivered alert is visible only as an `alert`/`alert_failed` row.
- [ ] n8n production compose service and Caddy access restriction (basic auth / IP allow-list) — Task 4.4.
- [ ] Execution pruning env vars (above, for the instance's other workflows), `backoff_seconds` = 60,
      real `repo_url`.
- [ ] Rotate `fleet-webhook-token`; keep the webhook reachable only from trusted callers.
- [ ] Decide who clears a stranded `provisioning` row (see "In-flight accounting") — today that is a human.

## Fleet dashboard (`fleet-dashboard.workflow.json`)

Workflow `Fleet — Dashboard`: `GET /webhook/fleet-dashboard` -> reads `fleet_clients` and `provision_attempts`
(get-only Data Table nodes; the workflow contains no write node) -> one Code node renders a self-contained,
JavaScript-free HTML page -> Respond to Webhook (`text/html`, `Cache-Control: no-store`,
`X-Content-Type-Options: nosniff`). It shows summary tiles (fleet clients split active vs failed, legacy sites, summed `est_ram_mib` over RUNNING stacks only
(`live`/`active`/`provisioning`; failed/blocked/rejected rows have no stack and are excluded), and "clients that still fit"
labelled as of the newest pre-flight with its timestamp and compose-project count - a stale value that does not
include clients provisioned since), a client table (status
badge, site and `/admin` links; the two legacy rows are badged "legacy - read-only" and have no actions), a
per-client `<details>` history of every attempt row, and a list of requests that never got a client record
(rejected/blocked). Every dynamic value goes through one `esc()` helper; link hrefs must start with `http(s)://`
or the link is dropped. Only summary numbers from `capacity_json` are shown (never the raw blob), plus the
already-redacted output tails (defensively re-scrubbed for `password/secret/token: value` patterns). It exposes no
secret, token or credential.

Note: n8n replaces the `Content-Security-Policy` response header on HTML webhook responses with its own `sandbox`
policy, so the header set in the workflow is not what the browser receives. The page therefore also carries the
same policy in a `<meta http-equiv>` tag, and Caddy must set the strict header (below).

**Access control (requirements for Task 4.4, NOT applied here):** n8n has no access control on webhooks, and the page
exposes client names, URLs and provisioning history. The Caddy block for the dashboard hostname must:

1. Proxy ONLY the exact path `/webhook/fleet-dashboard` to `n8n:5678` and return 404 for everything else. In particular
   it must not expose `/webhook/fleet-onboard`, `/webhook-test/*`, `/rest/*` or the n8n editor UI.
2. Set the strict CSP with the `>` override prefix: `header >Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'"`.
   n8n emits its own `sandbox` CSP on HTML webhook responses; a plain `header` would add a second policy instead of
   replacing it.
3. Require basic auth.
4. Until (2) is applied, the `<meta http-equiv="Content-Security-Policy">` tag in the page (same policy) is the only
   script-blocking control.

Separately: `POST /webhook/fleet-onboard` is protected ONLY by the `X-Fleet-Token` header credential if
`automation.digital-labs.ai` is publicly reachable. Recommend a Caddy-level restriction in front of it (source-IP
allowlist, or basic auth); the plan lists source-IP restriction as a follow-up.

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
- [ ] DNS: the `*.digital-labs.ai` wildcard from the plan covers `automation.digital-labs.ai`; add the Caddy site
      blocks (Task 4.4) for n8n (owner-only) and the basic-auth dashboard host.
- [ ] Open the editor and create the first owner account (strong password).
- [ ] Recreate credentials `fleet-target-ssh`, `fleet-webhook-token`, `fleet-alert-smtp` with real values.
- [ ] Create data tables `fleet_clients` and `provision_attempts` (columns above) and seed the two legacy rows.
- [ ] Import `client-onboarding.workflow.json` and `fleet-dashboard.workflow.json`; re-select credentials and both
      tables in every Data Table node; set Config (`repo_url`, `alert_email`, `backoff_seconds` = 60).
- [ ] Activate both workflows; verify the dashboard through Caddy with basic auth and confirm the CSP header.
