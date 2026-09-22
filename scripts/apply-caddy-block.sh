#!/usr/bin/env bash
# Applies (or removes) one client's Caddy site block, LOCALLY — no SSH.
#
# This is the machine-local half of Task 4.4's Caddy automation: back up
# any existing block, write the new one (or remove it), validate the full
# Caddy config, and only reload if validation passed — rolling back on
# failure. It assumes it is already running on the host that owns
# <target-dir>/docker/caddy/ (e.g. /opt/qubix on hostinger-vps).
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
#   2   config validation failed — rolled back, nothing changed
#   10  validation passed but reload itself failed (config on disk is
#       valid; confirm the running config by hand before retrying)

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
  cp "$BLOCK_FILE" "$FILE"
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
