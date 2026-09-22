#!/usr/bin/env bash
# Registers (or removes) one client's Caddy site block against the shared
# production Caddy — the ONE Caddy container that also serves jjbags.in and
# jj-bags.com (docker-compose.prod.yml, checkout /opt/qubix, branch
# `abood`). Task 4.4.
#
# This script itself is `fleet`-branch tooling and is never expected to be
# git-deployed INTO /opt/qubix (that checkout tracks `abood`, which receives
# bug fixes only — see CLAUDE.md's branch model). Instead it runs from
# wherever the fleet repo happens to be checked out (a developer's machine
# today, n8n's own execution environment later) and reaches its target
# purely over SSH: it writes/removes one file under
# <target-dir>/docker/caddy/clients/ and reloads the already-running caddy
# container. It never touches docker/caddy/Caddyfile itself or any other
# client's block.
#
# This makes the generated <slug>.caddy files themselves a second category
# of state that lives on the VPS outside /opt/qubix's own git history —
# alongside .env and admin-panel data, the only other state CLAUDE.md
# already permits to live outside git there. Unlike hand-edited app code,
# losing one is a non-event: it's byte-for-byte reproducible from
# generate-caddy-block.sh plus the same slug.
#
# Usage:
#   scripts/register-caddy-client.sh --slug <slug> [--backend <service>]
#                                     [--host <ssh-alias>] [--target-dir <path>]
#   scripts/register-caddy-client.sh --slug <slug> --remove
#                                     [--host <ssh-alias>] [--target-dir <path>]
#
# --host defaults to `hostinger-vps` (the alias in ~/.ssh/config), and
# --target-dir defaults to `/opt/qubix` (the checkout that owns the shared
# Caddy). Both are overridable for testing against a different target.
#
# Safety: before writing, any existing <slug>.caddy is backed up remotely.
# `caddy validate` runs against the live config before `caddy reload` is
# ever called; if validation fails, the change (write OR removal) is rolled
# back and the script exits non-zero — the running Caddy is never handed a
# config that hasn't already been proven to parse. This is deliberately
# stricter than a plain `caddy reload`, which would otherwise be the first
# thing to notice a bad block, on the container serving two live sites.
#
# Exit code contract (matches the rest of the fleet tooling):
#   0      success (including --remove of a block that was already absent)
#   1      invalid input (bad/missing slug, bad --target-dir)
#   2      config validation failed — rolled back, nothing changed
#   10     SSH/transfer failure, or validation passed but reload itself
#          failed (config on disk is valid; confirm the running config by
#          hand before retrying)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

err() { printf '%s\n' "$*" >&2; }

usage() {
  err "Usage: $0 --slug <slug> [--backend <service>] [--host <alias>] [--target-dir <path>] [--remove]"
}

SLUG=""
BACKEND=""
HOST="hostinger-vps"
TARGET_DIR="/opt/qubix"
MODE="register"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --slug)        [[ $# -ge 2 ]] || { err "Error: --slug requires a value"; usage; exit 1; }; SLUG="$2"; shift 2 ;;
    --slug=*)       SLUG="${1#--slug=}"; shift ;;
    --backend)     [[ $# -ge 2 ]] || { err "Error: --backend requires a value"; usage; exit 1; }; BACKEND="$2"; shift 2 ;;
    --backend=*)    BACKEND="${1#--backend=}"; shift ;;
    --host)        [[ $# -ge 2 ]] || { err "Error: --host requires a value"; usage; exit 1; }; HOST="$2"; shift 2 ;;
    --host=*)       HOST="${1#--host=}"; shift ;;
    --target-dir)  [[ $# -ge 2 ]] || { err "Error: --target-dir requires a value"; usage; exit 1; }; TARGET_DIR="$2"; shift 2 ;;
    --target-dir=*) TARGET_DIR="${1#--target-dir=}"; shift ;;
    --remove)       MODE="remove"; shift ;;
    *) err "Error: unknown argument: $1"; usage; exit 1 ;;
  esac
done

if [[ -z "$SLUG" ]]; then
  err "Error: --slug is required"
  usage
  exit 1
fi

if ! [[ "$SLUG" =~ ^[a-z][a-z0-9-]{1,20}$ ]]; then
  err "Error: invalid slug '$SLUG' — must match ^[a-z][a-z0-9-]{1,20}\$"
  exit 1
fi

# Subdomains that belong to shared fleet infrastructure, not any client —
# unlike generate-client-compose.sh's Docker-resource collision check
# (deliberately dynamic, because taken client slugs change over time), this
# list is static: these names can never become available to a client
# because they're permanently owned by infrastructure this same plan
# stands up (automation.digital-labs.ai — Task 4.5).
RESERVED=(automation www)
for reserved in "${RESERVED[@]}"; do
  if [[ "$SLUG" == "$reserved" ]]; then
    err "Error: '$SLUG' is a reserved subdomain (fleet infrastructure), not a valid client slug."
    exit 1
  fi
done

if [[ -z "$TARGET_DIR" || "$TARGET_DIR" =~ [[:space:]\'\"\$\`\\] ]]; then
  err "Error: --target-dir must be a non-empty path with no whitespace or shell metacharacters"
  exit 1
fi

# "NONE" rather than "" — ssh concatenates the remote command into one
# string and the remote shell re-tokenizes it, so a genuinely empty quoted
# argument doesn't survive the trip (adjacent spaces just collapse) and
# $4 ends up truly unset on the far side instead of set-but-empty. Found
# by actually running --remove, not by reading the code.
STAGED_REMOTE="NONE"
if [[ "$MODE" == "register" ]]; then
  GEN_ARGS=(--slug "$SLUG")
  [[ -n "$BACKEND" ]] && GEN_ARGS+=(--backend "$BACKEND")

  BLOCK_CONTENT="$("$SCRIPT_DIR/generate-caddy-block.sh" "${GEN_ARGS[@]}")"

  STAGED_REMOTE="/tmp/qubix-caddy-${SLUG}.staged.$$"
  if ! printf '%s' "$BLOCK_CONTENT" | ssh -o ConnectTimeout=15 "$HOST" "cat > '$STAGED_REMOTE'"; then
    err "Error: failed to stage the generated block on $HOST (SSH/transfer failure)."
    exit 10
  fi
fi

# The remote logic is staged as an actual file and executed by path, NOT
# piped in as `ssh host bash -s <<EOF` — that form was observed, empirically,
# to sometimes report success (exit 0, "Valid configuration" printed) while
# the reload it claimed to run never reached Caddy's admin API at all (no
# corresponding /load in `docker logs`, and the change never actually took
# effect on the running config). Confirmed by re-running the identical
# validate/reload logic staged as a file instead: reliable every time, full
# output present, change verifiably live. Root cause not fully isolated
# (suspected SSH/heredoc-stdin interaction with `docker compose exec -T`'s
# own I/O), so this script no longer depends on that path working at all,
# rather than trusting it.
LOCAL_STAGE="$(mktemp)"
trap 'rm -f "$LOCAL_STAGE"' EXIT

cat > "$LOCAL_STAGE" <<'REMOTE_SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
SLUG="$1"
TARGET_DIR="$2"
MODE="$3"
STAGED="$4"

CLIENTS_DIR="$TARGET_DIR/docker/caddy/clients"
FILE="$CLIENTS_DIR/$SLUG.caddy"
COMPOSE_FILE="$TARGET_DIR/docker-compose.prod.yml"

mkdir -p "$CLIENTS_DIR"

BACKUP=""
if [[ -f "$FILE" ]]; then
  BACKUP="$(mktemp "${FILE}.bak.XXXXXX")"
  cp "$FILE" "$BACKUP"
fi

rollback() {
  if [[ -n "$BACKUP" ]]; then
    mv -f "$BACKUP" "$FILE"
  else
    rm -f "$FILE"
  fi
}

if [[ "$MODE" == "remove" ]]; then
  if [[ ! -f "$FILE" ]]; then
    echo "No block for '$SLUG' at $FILE — nothing to remove." >&2
    exit 0
  fi
  rm -f "$FILE"
else
  mv "$STAGED" "$FILE"
fi

if ! docker compose -f "$COMPOSE_FILE" exec -T caddy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile; then
  echo "Error: Caddy config validation failed after this change — rolling back, nothing was reloaded." >&2
  rollback
  exit 2
fi

[[ -n "$BACKUP" ]] && rm -f "$BACKUP"

if ! docker compose -f "$COMPOSE_FILE" exec -T caddy caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile --force; then
  echo "Error: config validated but reload itself failed — the file on disk is valid, but confirm the RUNNING config by hand before retrying." >&2
  exit 10
fi

echo "OK: ${MODE} for '${SLUG}' applied and reloaded."
REMOTE_SCRIPT

REMOTE_SCRIPT_PATH="/tmp/qubix-caddy-op-${SLUG}.$$.sh"

if ! ssh -o ConnectTimeout=15 "$HOST" "cat > '$REMOTE_SCRIPT_PATH'" < "$LOCAL_STAGE"; then
  err "Error: failed to stage the operation script on $HOST (SSH/transfer failure)."
  exit 10
fi

set +e
ssh -o ConnectTimeout=30 "$HOST" \
  "chmod +x '$REMOTE_SCRIPT_PATH' && '$REMOTE_SCRIPT_PATH' '$SLUG' '$TARGET_DIR' '$MODE' '$STAGED_REMOTE'; rc=\$?; rm -f '$REMOTE_SCRIPT_PATH'; exit \$rc"
STATUS=$?
set -e

exit "$STATUS"
