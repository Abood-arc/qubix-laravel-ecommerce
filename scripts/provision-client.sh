#!/usr/bin/env bash
# Thin host-shell wrapper that onboards a brand-new client stack end to end.
#
# Run from the client's own dedicated checkout directory (e.g.
# /opt/qubix-acme, the same pattern /opt/qubix-sa already demonstrates as a
# separate checkout from /opt/qubix) — getting a fresh checkout onto that
# path is someone else's job (a human, or eventually n8n/Task 4.5), same
# precondition `qubix:install` makes about a pre-written `.env`.
#
# `docker compose`/`docker` commands in this codebase always run on the HOST
# shell, never from inside a container (every compose file's own header
# comments say so) — but there's no compose file yet for a brand-new client
# (you can't `docker compose exec app` before docker-compose.{slug}.yml,
# which defines app-{slug} not app, exists). So the actual orchestration
# (`qubix:provision`) has to run as a one-off container that's given the
# Docker CLI plus the host's own Docker socket — Docker-outside-of-Docker
# (DooD) — so the PHP code inside can drive sibling containers on the host.
# This script does exactly the three steps that requires, then forwards
# every one of its own arguments straight through to `php artisan
# qubix:provision` — no business logic lives here, all of it is in that
# command. This is the one command n8n (Task 4.5, later) or a human actually
# invokes.
#
# Usage:
#   scripts/provision-client.sh --slug=acme --client-name="Acme Corp" \
#     --business-name="Acme Store" --admin-email=owner@acme.example [...]
#
# All arguments are passed straight through to `php artisan qubix:provision`
# — see that command's --help for the full option list.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

err() { printf '%s\n' "$*" >&2; }

# Only this script's own bash logic needs the slug value up front (to name
# the base/provisioner images before docker-compose.{slug}.yml exists);
# `--slug=value` is the only form recognised here (matching how the smoke
# test in Task 4.3's brief invokes this script, and Task 4.1's own
# generator's primary form) — every argument, including --slug itself, is
# still forwarded to `php artisan qubix:provision` untouched below, which
# parses the full option set itself.
SLUG=""
for arg in "$@"; do
  case "$arg" in
    --slug=*) SLUG="${arg#--slug=}" ;;
  esac
done

if [[ -z "$SLUG" ]]; then
  err "Error: --slug=<slug> is required (e.g. --slug=acme)"
  exit 1
fi

echo "==> Building base app image qubix/app-${SLUG}"
docker build -t "qubix/app-${SLUG}" -f docker/8.3/Dockerfile --build-arg WWWGROUP=1000 .

echo "==> Building provisioner image qubix/provisioner (from qubix/app-${SLUG})"
docker build -t qubix/provisioner --build-arg "BASE_IMAGE=qubix/app-${SLUG}" -f docker/provisioner/Dockerfile .

echo "==> Running qubix:provision inside the provisioner container"
# Root-owned-file safety: the provisioner container needs the Docker socket,
# which is root-equivalent regardless of the container's declared user, so
# it runs as root (no -u flag — see docker/provisioner/Dockerfile). That
# means root-owned writes to the bind-mounted checkout (the generated
# compose file, .env) would otherwise break subsequent non-root access to
# those files. `set -e` is suspended around this one invocation so a
# non-zero exit here still falls through to the mandatory chown below
# instead of aborting the script immediately.
set +e
docker run --rm \
  -v "$REPO_ROOT:/var/www/html" \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -w /var/www/html \
  qubix/provisioner \
  php artisan qubix:provision "$@"
STATUS=$?
set -e

echo "==> Restoring host ownership of the checkout"
chown -R "$(id -u):$(id -g)" .

exit "$STATUS"
