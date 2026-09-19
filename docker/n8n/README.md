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
fails or its output cannot be parsed the decision is `blocked_preflight_failed`. `debug_extra_reserve_mib` is
ADDED to `reserve_mib`, so it can only make the check stricter. Blocked = respond 503, record
`blocked — insufficient capacity`, email an alert; the only edge into any deploy node is the `Proceed?` true branch.

### Exit codes and classification (`scripts/provision-client.sh`)

`0` success; `1-9` terminal (never retried, never torn down); `10-19` transient. Any other value
(125/127/255, no code, signal) or an SSH error/timeout is treated as **transient** (default-to-transient, Task 4.3 ruling).
Transient failures below `max_attempts` are torn down (`docker compose down -v`, verified `GONE`), wait
`backoff_seconds`, and retry. If attempts are exhausted: teardown, status `failed_escalated`, alert with the redacted
output of every attempt. If teardown cannot prove the project gone: status `failed_teardown`, stop (a retry would
hit the slug-collision check, exit 2).

### Teardown ownership rule

Teardown runs only when the pre-flight proved `qubix-<slug>` did not exist before this run (`owned_by_run`).
A pre-existing project returns 409 `slug_taken` before anything is touched, and no `down` is ever issued.
Legacy slugs (`jjbags-in`, `jj-bags-com`, `sa`, `qubix`, ...) are rejected in validation, so no write node can
match a legacy row in `fleet_clients`.

### Secrets and execution data

The generated admin password is extracted from stdout, redacted (`[REDACTED]`) before anything is stored or
alerted, and emitted only in the single credentials email. The workflow sets `saveDataSuccessExecution: none` so
it is not retained in n8n execution history on success. Residual risk: failed executions are still saved by
default and may contain the pre-redaction output of the failing step; set execution pruning
(`EXECUTIONS_DATA_PRUNE=true`, `EXECUTIONS_DATA_MAX_AGE=720` hours) in production. Email itself is plaintext.

### Data tables

`fleet_clients` (slug, client_name, business_name, subdomain, site_url, admin_url, status, last_run_id,
est_ram_mib, legacy, read_only, updated_at) — seeded with `jjbags.in` (1633 MiB) and `jj-bags.com` (1599 MiB),
`legacy=true, read_only=true, status=live`. `provision_attempts` (run_id, slug, attempt_no, phase
`preflight|deploy|teardown`, started_at, finished_at, exit_code, classification, decision, capacity_json,
output_tail, note): one row per pre-flight, deploy attempt and teardown. The Data Table node references tables
by id; after import re-select both tables in the Data Table nodes (Rec *, Mark *, Get Client).

### Re-import / recreate

1. n8n -> Import from file `client-onboarding.workflow.json`.
2. Create credentials with the names the workflow expects: `fleet-target-ssh` (SSH private key, host/user of the
   deploy target), `fleet-webhook-token` (Header Auth, header `X-Fleet-Token`, random 40+ chars),
   `fleet-alert-smtp` (SMTP). Local test: SMTP `host.docker.internal:1026` (Mailpit), no TLS/auth.
3. Create the two data tables above and seed the two legacy rows; re-select them in the nodes.
4. Activate. Local target: `test-target/run.sh` (see its comments).

### Manual checklist for production (not done here)

- [ ] SSH deploy key for the VPS: dedicated key, authorized on the VPS deploy user, private key stored only in the
      n8n credential; user needs docker + write access to `base_dir`.
- [ ] Real SMTP / alert channel; set `alert_email` in Config (the credentials email carries a password).
- [ ] n8n production compose service and Caddy access restriction (basic auth / IP allow-list) — Task 4.4.
- [ ] Execution pruning env vars (above), `backoff_seconds` = 60, real `repo_url`.
- [ ] Rotate `fleet-webhook-token`; keep the webhook reachable only from trusted callers.

## Fleet dashboard (`fleet-dashboard.workflow.json`)

Workflow `Fleet — Dashboard`: `GET /webhook/fleet-dashboard` -> reads `fleet_clients` and `provision_attempts`
(get-only Data Table nodes; the workflow contains no write node) -> one Code node renders a self-contained,
JavaScript-free HTML page -> Respond to Webhook (`text/html`, `Cache-Control: no-store`,
`X-Content-Type-Options: nosniff`). It shows summary tiles (fleet clients, legacy sites, summed `est_ram_mib`,
newest pre-flight capacity check: available MiB / clients that still fit / decision), a client table (status
badge, site and `/admin` links; the two legacy rows are badged "legacy - read-only" and have no actions), a
per-client `<details>` history of every attempt row, and a list of requests that never got a client record
(rejected/blocked). Every dynamic value goes through one `esc()` helper; link hrefs must start with `http(s)://`
or the link is dropped. Only summary numbers from `capacity_json` are shown (never the raw blob), plus the
already-redacted output tails (defensively re-scrubbed for `password/secret/token: value` patterns). It exposes no
secret, token or credential.

Note: n8n replaces the `Content-Security-Policy` response header on HTML webhook responses with its own `sandbox`
policy, so the header set in the workflow is not what the browser receives. The page therefore also carries the
same policy in a `<meta http-equiv>` tag, and Caddy must set the strict header (below).

**Access control (requirement for Task 4.4, not applied here):** n8n has none for webhooks. Production must put a
Caddy `basic_auth` block on its own dashboard subdomain that proxies ONLY `/webhook/fleet-dashboard` to
`n8n:5678` (everything else 404) and sets `header >Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'"`.
The n8n editor and the onboarding webhook must not be reachable through that host.

## Production compose file (`docker-compose.n8n.yml`)

Definition only; never run against any host by this task. One `n8n` service, image pinned to `2.39.7`, no
published ports, joins external `qubix_qubix` so Caddy can reach `n8n:5678`, named volume `n8n-data`, log rotation,
pruning at 30 days, `N8N_ENCRYPTION_KEY` required from the environment. `mem_limit` is a placeholder: measure on
the VPS and count it against Task 2.4's capacity budget. Validate with
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
