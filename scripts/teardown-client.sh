#!/usr/bin/env bash
# Completely removes ONE fleet client: its Caddy site block, containers, named volumes, private
# network, per-client image and checkout. The counterpart of provision-client.sh; without it,
# the only teardown in the fleet tooling is the onboarding workflow's failure-path `down -v`,
# which leaves the Caddy block, image, compose file, .env and checkout behind (Phase 4 Test T4.8
# needs all of them gone).
#
# Usage:
#   scripts/teardown-client.sh --slug=<slug> --confirm=<slug> [--base-dir=/opt]
#                              [--caddy-target-dir=/opt/qubix] [--keep-checkout] [--dry-run]
#
# --confirm must repeat the slug exactly. It exists so a typo or a wrong variable cannot delete
# a database volume; there is no interactive prompt (this must stay runnable from n8n over SSH).
# --dry-run prints everything that WOULD be removed and changes nothing, so it is also the safe
# way to look at a live host first.
#
# Safety model (this runs as root on the host that also serves the two live sites):
#   * Everything docker-side is selected by the Compose PROJECT LABEL `qubix-<slug>`, never by
#     name pattern or glob, and never needs the compose file (which does not exist if a
#     provision died early).
#   * The two live stacks, the n8n stack and the reserved hostnames are refused by name
#     (exit 2) AND any container carrying the project label whose compose config file is one
#     of docker-compose.{prod,sa,n8n}.yml is refused, so even a mislabelled or colliding
#     project cannot take a live stack down.
#   * The checkout is removed only if it is exactly <base-dir>/qubix-<slug>, is a real directory
#     (not a symlink) and looks like this repo (.git + artisan). It is never <base-dir>/qubix,
#     <base-dir>/qubix-sa or <base-dir>/qubix-n8n.
#   * Shared things are left alone on purpose: the external `qubix_qubix` network, the shared
#     `qubix/provisioner` image, and Caddy's certificate store.
#
# Idempotent: running it again on an already-removed client exits 0.
#
# Exit codes (same contract as the rest of the fleet tooling):
#   0   removed (or already absent)
#   1   invalid input, or --confirm missing/mismatched — terminal
#   2   refused: reserved/live name, or a live compose file is involved — terminal
#   10  a step failed (Docker unreachable, Caddy validate/reload, resource still present) —
#       transient; safe to re-run
#
# Not touched here: the n8n `fleet_clients` registry row. It keeps its old status, and n8n's
# "slug already exists" check (409) will keep blocking re-onboarding of this slug until that row
# is updated or removed by hand.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
err() { printf '%s\n' "$*" >&2; }

SLUG=""; CONFIRM=""; BASE_DIR="/opt"; CADDY_TARGET_DIR="/opt/qubix"; KEEP_CHECKOUT=0; DRY=0
for arg in "$@"; do
  if [[ "$arg" =~ [[:cntrl:]] ]]; then err "Error: an argument contains a control character — invalid input."; exit 1; fi
  case "$arg" in
    --slug=*)              SLUG="${arg#--slug=}" ;;
    --confirm=*)           CONFIRM="${arg#--confirm=}" ;;
    --base-dir=*)          BASE_DIR="${arg#--base-dir=}" ;;
    --caddy-target-dir=*)  CADDY_TARGET_DIR="${arg#--caddy-target-dir=}" ;;
    --keep-checkout)       KEEP_CHECKOUT=1 ;;
    --dry-run)             DRY=1 ;;
    *) err "Error: unknown or malformed argument (values must be given as --name=value): ${arg%%=*}"; exit 1 ;;
  esac
done

[[ -n "$SLUG" ]] || { err "Error: --slug=<slug> is required"; exit 1; }
[[ "$SLUG" =~ ^[a-z][a-z0-9-]{1,20}$ ]] || { err "Error: invalid --slug (must match ^[a-z][a-z0-9-]{1,20}\$)"; exit 1; }
[[ "$CONFIRM" == "$SLUG" ]] || { err "Error: --confirm=<slug> must repeat the slug exactly ('$SLUG'); nothing was changed."; exit 1; }
[[ "$BASE_DIR" =~ ^/[A-Za-z0-9._/-]*$ && "$CADDY_TARGET_DIR" =~ ^/[A-Za-z0-9._/-]*$ ]] \
  || { err "Error: --base-dir/--caddy-target-dir must be absolute paths of [A-Za-z0-9._/-] only"; exit 1; }

# Kept in step with the reserved lists in the n8n Validate node and the Caddy scripts, plus the
# n8n stack itself. Any slug on it maps onto (or shadows) a live or infrastructure project.
case "$SLUG" in
  jjbags-in|jj-bags-com|jjbags|jj-bags|sa|qubix|qubix-sa|qubix-n8n|n8n|automation|www|fleet)
    err "Refused: '$SLUG' is a reserved name (live site, n8n stack or infrastructure hostname). Nothing was changed."; exit 2 ;;
esac

PROJECT="qubix-$SLUG"
IMAGE="qubix/app-$SLUG"
DIR="${BASE_DIR%/}/qubix-$SLUG"
LABEL="com.docker.compose.project=$PROJECT"

step() { printf '==> %s\n' "$*"; }
# Run a mutating command, or only describe it under --dry-run.
do_() { if [[ "$DRY" == 1 ]]; then printf '[dry-run] %s\n' "$*"; else "$@" >/dev/null; fi; }

# --- discover (read-only) -------------------------------------------------------------------
docker info >/dev/null 2>&1 || { err "Error: cannot talk to the Docker daemon — transient."; exit 10; }

CONTAINERS="$(docker ps -aq --filter "label=$LABEL")" || { err "Error: docker ps failed — transient."; exit 10; }
VOLUMES="$(docker volume ls -q --filter "label=$LABEL")" || { err "Error: docker volume ls failed — transient."; exit 10; }
NETWORKS="$(docker network ls -q --filter "label=$LABEL")" || { err "Error: docker network ls failed — transient."; exit 10; }

# Belt and braces: a project carrying our label must not be one of the live compose files.
for c in $CONTAINERS; do
  cfg="$(docker inspect -f '{{index .Config.Labels "com.docker.compose.project.config_files"}}' "$c" 2>/dev/null || true)"
  case "$cfg" in
    *docker-compose.prod.yml*|*docker-compose.sa.yml*|*docker-compose.n8n.yml*)
      err "Refused: container $c under project '$PROJECT' belongs to a LIVE compose file ($cfg). Nothing was changed."; exit 2 ;;
  esac
done

CADDY_BLOCK="${CADDY_TARGET_DIR%/}/docker/caddy/clients/$SLUG.caddy"
IMAGE_ID="$(docker image ls -q "$IMAGE" 2>/dev/null || true)"

step "Client '$SLUG' (project $PROJECT)$([[ "$DRY" == 1 ]] && echo ' — DRY RUN')"
echo "  containers: $(echo $CONTAINERS | wc -w)   volumes: $(echo $VOLUMES | wc -w)   networks: $(echo $NETWORKS | wc -w)"
echo "  image:      ${IMAGE_ID:-none} ($IMAGE)"
echo "  caddy:      $([[ -f "$CADDY_BLOCK" ]] && echo "$CADDY_BLOCK" || echo "no block file")"
echo "  checkout:   $([[ -d "$DIR" ]] && echo "$DIR" || echo "none")$([[ "$KEEP_CHECKOUT" == 1 ]] && echo ' (kept: --keep-checkout)')"

# --- 1. Caddy first: stop routing traffic to a stack that is about to disappear ---------------
if [[ -f "$CADDY_BLOCK" ]]; then
  step "Removing Caddy site block"
  if [[ "$DRY" == 1 ]]; then
    echo "[dry-run] apply-caddy-block.sh --slug=$SLUG --target-dir=$CADDY_TARGET_DIR --remove"
  elif ! "$SCRIPT_DIR/apply-caddy-block.sh" --slug="$SLUG" --target-dir="$CADDY_TARGET_DIR" --remove; then
    err "Error: removing the Caddy block failed (validation or reload) — nothing else was removed; safe to re-run."; exit 10
  fi
fi

# --- 2. containers, volumes, network — by project label only ---------------------------------
if [[ -n "$CONTAINERS" ]]; then step "Removing containers"; do_ docker rm -f $CONTAINERS || { err "Error: docker rm failed — transient."; exit 10; }; fi
if [[ -n "$VOLUMES" ]];    then step "Removing volumes";    do_ docker volume rm $VOLUMES || { err "Error: docker volume rm failed — transient."; exit 10; }; fi
if [[ -n "$NETWORKS" ]];   then step "Removing network(s)"; do_ docker network rm $NETWORKS || { err "Error: docker network rm failed — transient."; exit 10; }; fi

# --- 3. per-client image (the shared qubix/provisioner tag is deliberately left alone) --------
if [[ -n "$IMAGE_ID" ]]; then step "Removing image $IMAGE"; do_ docker image rm "$IMAGE" || { err "Error: docker image rm $IMAGE failed — transient."; exit 10; }; fi

# --- 4. checkout (last: it holds this script, the compose file and .env) ----------------------
if [[ "$KEEP_CHECKOUT" == 0 && -e "$DIR" ]]; then
  if [[ -L "$DIR" || ! -d "$DIR" ]]; then err "Refused: $DIR is not a plain directory — left in place."; exit 2; fi
  [[ "$(cd "$DIR" && pwd -P)" == "$DIR" ]] || { err "Refused: $DIR does not resolve to itself (symlinked parent?) — left in place."; exit 2; }
  [[ -e "$DIR/.git" && -f "$DIR/artisan" ]] || { err "Refused: $DIR does not look like a Qubix checkout (.git + artisan) — left in place."; exit 2; }
  step "Removing checkout $DIR"
  cd /
  do_ rm -rf -- "$DIR"
fi

# --- verify -----------------------------------------------------------------------------------
if [[ "$DRY" == 0 ]]; then
  LEFT="$(docker ps -aq --filter "label=$LABEL") $(docker volume ls -q --filter "label=$LABEL") $(docker network ls -q --filter "label=$LABEL")" \
    || { err "Error: could not verify removal — transient."; exit 10; }
  if [[ -n "${LEFT// /}" ]]; then err "Error: resources still carry project label $PROJECT after removal: $LEFT — transient; re-run."; exit 10; fi
  step "Client '$SLUG' removed. (n8n's fleet_clients row is unchanged — update it by hand if the slug will be reused.)"
fi
exit 0
