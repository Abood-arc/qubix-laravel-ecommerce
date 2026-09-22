#!/usr/bin/env bash
# Registers (or removes) one client's Caddy site block against the shared
# production Caddy — the ONE Caddy container that also serves jjbags.in and
# jj-bags.com (docker-compose.prod.yml, checkout /opt/qubix, branch
# `abood`). Task 4.4.
#
# For a developer on a DIFFERENT machine than the target: this script
# generates the block, then stages both it and scripts/apply-caddy-block.sh
# (the actual apply logic — backup/write/validate/reload/rollback) onto the
# target over SSH and runs the latter there. It never touches
# docker/caddy/Caddyfile itself or any other client's block.
#
# n8n's onboarding workflow does NOT use this script — it's already
# running directly on the target (the same SSH session Deploy SSH used to
# `git clone --branch fleet` the new client's own checkout, which is where
# apply-caddy-block.sh physically comes from), so it calls that script
# directly with no SSH wrapper. This script exists for the OTHER real
# caller: a developer proving/operating this by hand from a machine that
# isn't the target itself (exactly how Task 4.4's own live proof was run).
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
# Exit code contract — passed straight through from apply-caddy-block.sh,
# except 10 which this script also uses for its own SSH/transfer failures:
#   0      success (including --remove of a block that was already absent)
#   1      invalid input (bad/missing slug, bad --target-dir)
#   2      config validation failed — rolled back, nothing changed
#   10     SSH/transfer failure, or validation passed but reload itself
#          failed (config on disk is valid; confirm the running config by
#          hand before retrying)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APPLY_SCRIPT="$SCRIPT_DIR/apply-caddy-block.sh"

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

if [[ ! -f "$APPLY_SCRIPT" ]]; then
  err "Error: $APPLY_SCRIPT not found (expected alongside this script)"
  exit 1
fi

# Everything below is staged onto the target as real files and executed by
# path — NOT piped in as `ssh host bash -s <<EOF` — because that form was
# observed, empirically, to sometimes report success (exit 0, "Valid
# configuration" printed) while the reload it claimed to run never reached
# Caddy's admin API at all (no corresponding /load in `docker logs`, and
# the change never actually took effect on the running config). Confirmed
# by re-running the identical logic staged as files instead: reliable
# every time, full output present, change verifiably live. Root cause not
# fully isolated (suspected SSH/heredoc-stdin interaction with
# `docker compose exec -T`'s own I/O), so this script no longer depends on
# that path working at all, rather than trusting it.
REMOTE_APPLY_PATH="/tmp/qubix-apply-caddy-block.$$.sh"

if ! ssh -o ConnectTimeout=15 "$HOST" "cat > '$REMOTE_APPLY_PATH'" < "$APPLY_SCRIPT"; then
  err "Error: failed to stage apply-caddy-block.sh on $HOST (SSH/transfer failure)."
  exit 10
fi

REMOTE_ARGS="--slug '$SLUG' --target-dir '$TARGET_DIR'"
REMOTE_BLOCK_PATH=""

if [[ "$MODE" == "register" ]]; then
  GEN_ARGS=(--slug "$SLUG")
  [[ -n "$BACKEND" ]] && GEN_ARGS+=(--backend "$BACKEND")
  BLOCK_CONTENT="$("$SCRIPT_DIR/generate-caddy-block.sh" "${GEN_ARGS[@]}")"

  REMOTE_BLOCK_PATH="/tmp/qubix-caddy-${SLUG}.block.$$"
  if ! printf '%s' "$BLOCK_CONTENT" | ssh -o ConnectTimeout=15 "$HOST" "cat > '$REMOTE_BLOCK_PATH'"; then
    err "Error: failed to stage the generated block on $HOST (SSH/transfer failure)."
    ssh -o ConnectTimeout=15 "$HOST" "rm -f '$REMOTE_APPLY_PATH'" || true
    exit 10
  fi
  REMOTE_ARGS="$REMOTE_ARGS --block-file '$REMOTE_BLOCK_PATH'"
else
  REMOTE_ARGS="$REMOTE_ARGS --remove"
fi

set +e
ssh -o ConnectTimeout=30 "$HOST" \
  "chmod +x '$REMOTE_APPLY_PATH' && '$REMOTE_APPLY_PATH' $REMOTE_ARGS; rc=\$?; rm -f '$REMOTE_APPLY_PATH' '$REMOTE_BLOCK_PATH'; exit \$rc"
STATUS=$?
set -e

exit "$STATUS"
