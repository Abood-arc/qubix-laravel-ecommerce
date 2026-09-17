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
#
# SUPERVISOR_PHP_USER=root overrides the base image's baked-in default
# (docker/8.3/Dockerfile sets it to "sail" for the long-running app/queue/
# scheduler containers). vendor/laravel/sail/runtimes/8.3/start-container
# (this image's ENTRYPOINT) execs its CMD directly only when that variable
# is "root"; otherwise it runs `gosu $WWWUSER "$@"` — and since this
# one-off invocation never sets WWWUSER, that would make gosu try to
# switch to a user literally named "php" (the first word of the CMD) and
# fail with "failed switching to \"php\": unable to find user php" instead
# of ever reaching artisan. Discovered by running this script for real,
# not by reading start-container's own source ahead of time.
# Mount the checkout at the SAME absolute path inside this container as it
# has on the real Docker host, and run from there (-w), rather than at the
# app image's usual /var/www/html. This is load-bearing, not cosmetic:
# docker-compose.{slug}.yml itself has relative-path bind mounts
# (app-{slug}'s `.:/var/www/html`, mysql's `./docker/mysql/my.cnf:...`), and
# `docker compose`/`docker` inside this DooD container talk to the HOST's
# daemon over the bind-mounted socket — but any *relative* volume path in
# the compose file gets resolved using the calling CLI process's own cwd
# string, which is then sent to the host daemon completely uninterpreted.
# If that cwd were the usual /var/www/html, the host daemon would look for
# "/var/www/html/docker/mysql/my.cnf" on the REAL HOST, find nothing there,
# and (Docker's own well-known behaviour for a missing bind-mount source)
# silently create an empty directory in its place — which then fails to
# mount over a file target with a confusing "not a directory" OCI runtime
# error, discovered by actually running this end to end, not by reading the
# compose file first. Mirroring the real host path makes the relative paths
# the CLI resolves and the paths the host daemon understands the same
# string, so they resolve correctly on both sides of the socket.
REPO_ROOT_ON_HOST="$REPO_ROOT"

# Root-owned-file safety: the provisioner container needs the Docker socket,
# which is root-equivalent regardless of the container's declared user, so
# it runs as root (no -u flag — see docker/provisioner/Dockerfile). That
# means root-owned writes to the bind-mounted checkout (the generated
# compose file, .env) would otherwise break subsequent non-root access to
# those files. `set -e` is suspended around this one invocation so a
# non-zero exit here still falls through to the mandatory chown below
# instead of aborting the script immediately.
#
# SUPERVISOR_PHP_USER=root overrides the base image's baked-in default
# (docker/8.3/Dockerfile sets it to "sail" for the long-running app/queue/
# scheduler containers). vendor/laravel/sail/runtimes/8.3/start-container
# (this image's ENTRYPOINT) execs its CMD directly only when that variable
# is "root"; otherwise it runs `gosu $WWWUSER "$@"` — and since this
# one-off invocation never sets WWWUSER, that would make gosu try to
# switch to a user literally named "php" (the first word of the CMD) and
# fail with "failed switching to \"php\": unable to find user php" instead
# of ever reaching artisan. Discovered by running this script for real,
# not by reading start-container's own source ahead of time.
set +e
docker run --rm \
  -e SUPERVISOR_PHP_USER=root \
  -v "$REPO_ROOT_ON_HOST:$REPO_ROOT_ON_HOST" \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -w "$REPO_ROOT_ON_HOST" \
  qubix/provisioner \
  php artisan qubix:provision "$@"
STATUS=$?
set -e

echo "==> Restoring host ownership of the checkout"
# Best-effort: qubix:provision's own exit code ($STATUS) is what Task 4.5's
# retry logic keys off, and it must stay authoritative regardless of what
# happens here. Deliberately NOT `set -e`'d — under bash's errexit, a
# non-zero chown here would abort the script on this line and it would
# never reach `exit "$STATUS"` below, silently replacing a real 1/2/3/10
# exit code with whatever chown happened to return instead (observed for
# real when testing this script outside a genuine root context — chown
# partially fails there with "Operation not permitted" on files the
# one-off container wrote as root, but $STATUS from the actual
# provisioning run was still correctly 0). On the real VPS this runs as
# root already, so chowning root-owned files to root:root is a no-op that
# always succeeds; the guard exists for robustness, not because it's
# expected to fail in production.
if ! chown -R "$(id -u):$(id -g)" .; then
  err "Warning: restoring host ownership of the checkout failed for one or more paths (see above). If this script is not itself running as root, that's expected and does not indicate a provisioning failure — the exit code below reflects qubix:provision's own result, not this step."
fi

# The blanket chown above must NOT be the last word on storage/ and
# bootstrap/cache: qubix:provision itself deliberately chowns those two to
# 1337:1000 (see that command's own comment — every compose file's
# `WWWUSER: '1337'` assumes storage/bootstrap-cache already belong to that
# uid, which start-container's PHP-FPM worker runs as after dropping root).
# On a real root-run production invocation the blanket chown above would
# actually succeed (unlike in a non-root test), which would silently
# revert that ownership back to root and reintroduce the exact "Permission
# denied writing storage/framework/views" 500 this command's own fix
# exists to prevent. Re-asserting it here, after the blanket revert, is
# what keeps the two intentions from clobbering each other regardless of
# which order future edits to either step happen to run in.
if ! chown -R 1337:1000 storage bootstrap/cache; then
  err "Warning: could not re-assert sail (1337:1000) ownership on storage/ and bootstrap/cache after the blanket chown above — the new store may 500 on its first real request until this is fixed manually."
fi

exit "$STATUS"
