#!/usr/bin/env bash
# Applies (or removes) one client's Caddy site block, LOCALLY — no SSH.
#
# This is the machine-local half of Task 4.4's Caddy automation: validate the
# full Caddy config WITH the change applied, and only then put the change in
# place and reload. It assumes it is already running on the host that owns
# <target-dir>/docker/caddy/ (e.g. /opt/qubix on hostinger-vps).
#
# Atomicity (this Caddy serves the two live sites, and `restart:
# unless-stopped` re-reads the Caddyfile and every clients/*.caddy on ANY
# container restart): an unvalidated block must never be visible to the
# `import clients/*.caddy` glob, not even for the duration of the validation.
#   1. Stage the new block as clients/<slug>.caddy.new (the glob does not match it).
#   2. Build a candidate Caddyfile next to the real one whose single
#      `import clients/*.caddy` line is replaced by an explicit list: every
#      existing block except <slug>'s own, plus <slug>.caddy.new when registering.
#   3. `caddy validate` the candidate. On failure delete the staged files and
#      exit 2 — the live directory was never touched.
#   4. Commit with ONE atomic step (rename .new over <slug>.caddy, or unlink it
#      for --remove), then reload.
# A kill at any point (even SIGKILL) therefore leaves the directory either
# entirely as before or entirely as after; the only debris is an unloaded
# .caddy.new / Caddyfile.candidate.* file, swept on the next run. A flock on
# clients/.apply.lock serialises concurrent applies (two n8n runs, or n8n plus a
# hand-run register-caddy-client.sh) so one cannot validate against the other's
# half-finished state.
#
# Two callers, deliberately sharing this one script rather than duplicating
# the logic:
#   - scripts/register-caddy-client.sh, for a developer on a different
#     machine: it generates the block, stages this script plus the block
#     content onto the target over SSH, then runs this script there.
#   - The n8n onboarding workflow's Caddy SSH node: it's already running
#     directly on the target (same SSH session Deploy SSH used to `git
#     clone --branch fleet` the new client's own checkout, which is where
#     this script physically comes from), so it calls this script with no
#     SSH wrapper at all — the extra hop register-caddy-client.sh needs
#     would just be a loopback SSH-from-the-VPS-to-itself for no benefit.
#
# Usage:
#   apply-caddy-block.sh --slug <slug> --target-dir <path> --block-file <path>
#   apply-caddy-block.sh --slug <slug> --target-dir <path> --remove
#
# Exit code contract (matches the rest of the fleet tooling):
#   0   success (including --remove of a block that was already absent)
#   1   invalid input
#   2   config validation failed — nothing was changed
#   10  validation passed but reload itself failed (config on disk is
#       valid; confirm the running config by hand before retrying), or the
#       apply lock could not be taken within 120 s (nothing was changed)

set -euo pipefail

err() { printf '%s\n' "$*" >&2; }

usage() {
  err "Usage: $0 --slug <slug> --target-dir <path> (--block-file <path> | --remove)"
}

SLUG=""
TARGET_DIR=""
BLOCK_FILE=""
MODE="register"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --slug)          [[ $# -ge 2 ]] || { err "Error: --slug requires a value"; usage; exit 1; }; SLUG="$2"; shift 2 ;;
    --slug=*)         SLUG="${1#--slug=}"; shift ;;
    --target-dir)    [[ $# -ge 2 ]] || { err "Error: --target-dir requires a value"; usage; exit 1; }; TARGET_DIR="$2"; shift 2 ;;
    --target-dir=*)   TARGET_DIR="${1#--target-dir=}"; shift ;;
    --block-file)    [[ $# -ge 2 ]] || { err "Error: --block-file requires a value"; usage; exit 1; }; BLOCK_FILE="$2"; shift 2 ;;
    --block-file=*)   BLOCK_FILE="${1#--block-file=}"; shift ;;
    --remove)         MODE="remove"; shift ;;
    *) err "Error: unknown argument: $1"; usage; exit 1 ;;
  esac
done

if [[ -z "$SLUG" ]] || ! [[ "$SLUG" =~ ^[a-z][a-z0-9-]{1,20}$ ]]; then
  err "Error: --slug is required and must match ^[a-z][a-z0-9-]{1,20}\$"
  exit 1
fi

if [[ -z "$TARGET_DIR" || "$TARGET_DIR" =~ [[:space:]\'\"\$\`\\] ]]; then
  err "Error: --target-dir must be a non-empty path with no whitespace or shell metacharacters"
  exit 1
fi

if [[ "$MODE" == "register" ]]; then
  if [[ -z "$BLOCK_FILE" ]]; then
    err "Error: --block-file is required unless --remove is given"
    usage
    exit 1
  fi
  if [[ ! -f "$BLOCK_FILE" ]]; then
    err "Error: --block-file '$BLOCK_FILE' does not exist"
    exit 1
  fi
fi

CLIENTS_DIR="$TARGET_DIR/docker/caddy/clients"
CADDY_DIR="$TARGET_DIR/docker/caddy"
FILE="$CLIENTS_DIR/$SLUG.caddy"
STAGED="$CLIENTS_DIR/$SLUG.caddy.new"
COMPOSE_FILE="$TARGET_DIR/docker-compose.prod.yml"

mkdir -p "$CLIENTS_DIR"

# Serialise applies. The lock is released when this process exits, however it exits. fd 9 is closed for
# the docker children below (`9>&-`): they inherit the lock's open file description, so a killed apply's
# still-running child would otherwise keep holding the lock and block every later apply.
exec 9>"$CLIENTS_DIR/.apply.lock"
if ! flock -w 120 9; then
  err "Error: could not take $CLIENTS_DIR/.apply.lock within 120 s (another apply is running?) — nothing was changed."
  exit 10
fi

CANDIDATE=""
cleanup() {
  [[ -n "$CANDIDATE" ]] && rm -f "$CANDIDATE"
  rm -f "$STAGED"
}
trap cleanup EXIT
# Turn signals into a normal exit so the EXIT trap above runs (bash does not run it for an unhandled signal).
trap 'exit 130' INT TERM HUP

# Sweep debris from a previous run that was SIGKILLed between staging and cleanup. Safe: we hold the lock,
# and neither pattern is matched by the live `clients/*.caddy` glob.
rm -f "$CLIENTS_DIR"/*.caddy.new "$CADDY_DIR"/Caddyfile.candidate.*

if [[ "$MODE" == "remove" && ! -f "$FILE" ]]; then
  echo "No block for '$SLUG' at $FILE — nothing to remove." >&2
  exit 0
fi

# The candidate replaces the ONE import line; refuse to guess if the Caddyfile is not shaped as expected.
IMPORT_RE='^import clients/\*\.caddy[[:space:]]*$'
if [[ "$(grep -cE "$IMPORT_RE" "$CADDY_DIR/Caddyfile")" != "1" ]]; then
  err "Error: $CADDY_DIR/Caddyfile must contain exactly one line 'import clients/*.caddy' for atomic validation — nothing was changed."
  exit 1
fi

[[ "$MODE" == "register" ]] && cp "$BLOCK_FILE" "$STAGED"

# Every block the real glob would load, except this slug's own current one; plus the staged new one.
IMPORTS=""
shopt -s nullglob
for f in "$CLIENTS_DIR"/*.caddy; do
  name="${f##*/}"
  [[ "$name" == "$SLUG.caddy" ]] && continue
  if [[ ! "$name" =~ ^[A-Za-z0-9._-]+$ ]]; then
    err "Error: unexpected file name in $CLIENTS_DIR that Caddy would import: '$name' — nothing was changed."
    exit 1
  fi
  IMPORTS+="import clients/$name"$'\n'
done
shopt -u nullglob
[[ "$MODE" == "register" ]] && IMPORTS+="import clients/$SLUG.caddy.new"$'\n'

CANDIDATE="$(mktemp "$CADDY_DIR/Caddyfile.candidate.XXXXXX")"
chmod 644 "$CANDIDATE"
{
  while IFS= read -r line || [[ -n "$line" ]]; do
    if [[ "$line" =~ $IMPORT_RE ]]; then printf '%s' "$IMPORTS"; else printf '%s\n' "$line"; fi
  done < "$CADDY_DIR/Caddyfile"
} > "$CANDIDATE"

if ! docker compose -f "$COMPOSE_FILE" exec -T caddy caddy validate --config "/etc/caddy/${CANDIDATE##*/}" --adapter caddyfile 9>&-; then
  echo "Error: Caddy config validation failed with this change applied — nothing was changed, nothing was reloaded." >&2
  exit 2
fi

# Commit: a single atomic filesystem operation.
if [[ "$MODE" == "remove" ]]; then
  rm -f "$FILE"
else
  mv -f "$STAGED" "$FILE"
fi

if ! docker compose -f "$COMPOSE_FILE" exec -T caddy caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile --force 9>&-; then
  echo "Error: config validated but reload itself failed — the file on disk is valid, but confirm the RUNNING config by hand before retrying." >&2
  exit 10
fi

echo "OK: ${MODE} for '${SLUG}' applied and reloaded."
