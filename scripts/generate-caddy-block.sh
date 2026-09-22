#!/usr/bin/env bash
# Renders a Caddy site block for a single client slug, to stdout.
#
# Usage: scripts/generate-caddy-block.sh --slug <slug> [--backend <service>]
#
# Pure text generation, no filesystem or Docker side effects — mirrors
# scripts/generate-client-compose.sh's contract (Task 4.1) so it composes
# the same way: pipe the output into a file, or into `diff`.
#
# The rendered block routes <slug>.digital-labs.ai to <backend>:80 over the
# shared `qubix_qubix` Docker network (docker-compose.client.yml.tpl already
# joins app-<slug> to it for exactly this purpose — see that template's own
# comment). --backend defaults to app-<slug>, the name every generated
# per-client compose file actually uses; only pass --backend to point a
# block at something else (e.g. n8n itself, which is not a per-client app
# service).
#
# Exit code contract (matches the rest of the fleet tooling, Task 4.3/4.1):
#   0   success
#   1   invalid input (bad/missing slug)
#
# All diagnostics go to stderr. Stdout carries ONLY the rendered block on
# success.

set -euo pipefail

DOMAIN="digital-labs.ai"

err() { printf '%s\n' "$*" >&2; }

usage() {
  err "Usage: $0 --slug <slug> [--backend <service>]"
}

SLUG=""
BACKEND=""
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
    --backend)
      if [[ $# -lt 2 ]]; then
        err "Error: --backend requires a value"
        usage
        exit 1
      fi
      BACKEND="$2"
      shift 2
      ;;
    --backend=*)
      BACKEND="${1#--backend=}"
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

# Same rule as generate-client-compose.sh and Provision.php: lowercase
# start, lowercase alphanumeric + hyphens only, 2-21 chars total.
if ! [[ "$SLUG" =~ ^[a-z][a-z0-9-]{1,20}$ ]]; then
  err "Error: invalid slug '$SLUG' — must match ^[a-z][a-z0-9-]{1,20}\$ (lowercase start, lowercase alphanumeric/hyphens, 2-21 chars total)"
  exit 1
fi

if [[ -z "$BACKEND" ]]; then
  BACKEND="app-${SLUG}"
fi

# Same shape as backend service names elsewhere in this codebase
# (app, app-sa): lowercase alphanumeric + hyphens, avoids Caddyfile
# metacharacters (braces, whitespace) leaking into generated config.
if ! [[ "$BACKEND" =~ ^[a-z][a-z0-9-]*$ ]]; then
  err "Error: invalid --backend '$BACKEND' — must match ^[a-z][a-z0-9-]*\$"
  exit 1
fi

cat <<EOF
${SLUG}.${DOMAIN} {
	encode gzip zstd
	reverse_proxy ${BACKEND}:80
}
EOF
