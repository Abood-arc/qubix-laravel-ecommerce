#!/usr/bin/env bash
# Renders docker-compose.client.yml.tpl for a single client slug, to stdout.
#
# Usage: scripts/generate-client-compose.sh --slug <slug>
#
# Only {{CLIENT_SLUG}} is substituted (plain string replacement via `sed`,
# never `envsubst` — the template contains literal Compose interpolation
# syntax, e.g. ${DB_PASSWORD}, that must survive untouched for Compose
# itself to resolve at `up` time; envsubst would expand those from this
# script's own shell environment instead and corrupt the output).
#
# Exit code contract (shared with the rest of the fleet tooling, incl.
# Task 4.3):
#   0      success
#   1-9    terminal failure — will not succeed on retry
#     1      invalid input (bad/missing slug)
#     2      slug/resource collision against the live Docker daemon
#   10-19  transient failure — may succeed on retry
#     10     Docker/environment unreachable (a `docker` command itself
#            failed, rather than cleanly reporting "not found")
#
# All diagnostics go to stderr. Stdout carries ONLY the rendered compose
# file on success, so this script's output can be piped straight into
# `diff` or a file.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TEMPLATE="$REPO_ROOT/docker-compose.client.yml.tpl"

err() { printf '%s\n' "$*" >&2; }

usage() {
  err "Usage: $0 --slug <slug>"
}

SLUG=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --slug)
      if [[ $# -lt 2 ]]; then
        err "Error: --slug requires a value"
        usage
        exit 1
      fi
      SLUG="$2"
      shift 2
      ;;
    --slug=*)
      SLUG="${1#--slug=}"
      shift
      ;;
    *)
      err "Error: unknown argument: $1"
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$SLUG" ]]; then
  err "Error: --slug is required"
  usage
  exit 1
fi

# Lowercase start, lowercase alphanumeric + hyphens only, 2-21 chars total.
if ! [[ "$SLUG" =~ ^[a-z][a-z0-9-]{1,20}$ ]]; then
  err "Error: invalid slug '$SLUG' — must match ^[a-z][a-z0-9-]{1,20}\$ (lowercase start, lowercase alphanumeric/hyphens, 2-21 chars total)"
  exit 1
fi

if [[ ! -f "$TEMPLATE" ]]; then
  err "Error: template not found at $TEMPLATE"
  exit 1
fi

# --- Collision check, dynamic against the live Docker daemon ---
#
# Not a hardcoded reserved-word list: "sa" is itself a real existing client
# slug, and the set of taken slugs changes over time as clients onboard.
# Compose prefixes actual runtime resource names with the project name
# (e.g. the "sa" stack's mysql container is "qubix-sa-mysql-1", not
# "mysql"), so we check substring matches against the names this slug
# would actually produce, across every resource kind Compose creates.

CANDIDATES=(
  "app-${SLUG}"
  "queue-${SLUG}"
  "scheduler-${SLUG}"
  "qubix-${SLUG}"
  "qubix-${SLUG}-mysql"
  "qubix-${SLUG}-redis"
)

# Each helper prints one name per line and returns non-zero only when the
# underlying `docker` invocation itself failed (daemon unreachable, etc.) —
# not merely when there's nothing listed.
list_compose_projects() { docker compose ls -a -q; }
list_containers()       { docker ps -a --format '{{.Names}}'; }
list_volumes()          { docker volume ls --format '{{.Name}}'; }
list_networks()         { docker network ls --format '{{.Name}}'; }

ALL_NAMES=""
DOCKER_ERR_FILE="$(mktemp)"
trap 'rm -f "$DOCKER_ERR_FILE"' EXIT

for lister in list_compose_projects list_containers list_volumes list_networks; do
  if ! out="$("$lister" 2>"$DOCKER_ERR_FILE")"; then
    err "Error: '${lister#list_}' lookup failed — Docker daemon may be unreachable:"
    err "$(cat "$DOCKER_ERR_FILE" 2>/dev/null)"
    exit 10
  fi
  ALL_NAMES="${ALL_NAMES}
${out}"
done

for candidate in "${CANDIDATES[@]}"; do
  if grep -qF -- "$candidate" <<<"$ALL_NAMES"; then
    err "Error: slug '$SLUG' collides with an existing Docker resource matching '$candidate'."
    err "Choose a different slug."
    exit 2
  fi
done

# --- Render ---
sed "s|{{CLIENT_SLUG}}|${SLUG}|g" "$TEMPLATE"
