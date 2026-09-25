#!/usr/bin/env bash
# Tests scripts/teardown-client.sh against REAL local Docker, using only throwaway resources
# named qubix-tdt<random>* (alpine:3, sleep). Caddy's `docker compose exec caddy` is stubbed via
# PATH so nothing needs a Caddy container; every other docker call is the real one. A "decoy"
# client must survive every teardown of its neighbour. Cleans up after itself, even on failure.
#
# Needs: docker, image alpine:3 (pulled if absent). Touches nothing outside a mktemp dir and
# the qubix-tdt* resources it creates. Never run against a production host.
#
# Usage: scripts/test-teardown-client.sh      (exit 0 = all pass)
set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SUT="$HERE/teardown-client.sh"
command -v docker >/dev/null && docker info >/dev/null 2>&1 || { echo "docker is required"; exit 2; }
docker image inspect alpine:3 >/dev/null 2>&1 || docker pull -q alpine:3 >/dev/null

R="$(head -c4 /dev/urandom | od -An -tx1 | tr -d ' \n')"
TARGET="tdtgt$R"; DECOY="tdtdc$R"; LIVEISH="tdtlv$R"; NOART="tdtna$R"
T="$(mktemp -d)"; BASE="$T/opt"; CADDY="$T/caddy"; STUBLOG="$T/caddy.log"
mkdir -p "$BASE" "$CADDY/docker/caddy/clients" "$T/bin"
# apply-caddy-block.sh validates a candidate built from this Caddyfile, so it must have the import line.
printf 'import clients/*.caddy\n' > "$CADDY/docker/caddy/Caddyfile"
FAILS=0
ok()   { printf 'PASS %s\n' "$1"; }
fail() { printf 'FAIL %s\n' "$1"; FAILS=$((FAILS+1)); }
check() { if eval "$2"; then ok "$1"; else fail "$1   [$2]"; fi; }

cleanup() {
  for s in $TARGET $DECOY $LIVEISH $NOART; do
    docker ps -aq --filter "label=com.docker.compose.project=qubix-$s" | xargs -r docker rm -f >/dev/null 2>&1
    docker volume ls -q --filter "label=com.docker.compose.project=qubix-$s" | xargs -r docker volume rm >/dev/null 2>&1
    docker network ls -q --filter "label=com.docker.compose.project=qubix-$s" | xargs -r docker network rm >/dev/null 2>&1
    docker image rm "qubix/app-$s" >/dev/null 2>&1
  done
  rm -rf "$T"
}
trap cleanup EXIT

# Stub only Caddy's exec; everything else is the real docker.
REAL_DOCKER="$(command -v docker)"
cat > "$T/bin/docker" <<STUB
#!/usr/bin/env bash
if [[ "\$*" == *"exec -T caddy caddy validate"* ]]; then echo validate >> "$STUBLOG"; exit 0; fi
if [[ "\$*" == *"exec -T caddy caddy reload"* ]];   then echo reload   >> "$STUBLOG"; exit 0; fi
exec "$REAL_DOCKER" "\$@"
STUB
chmod +x "$T/bin/docker"

# make_client <slug> : checkout dir + compose file + running project + image + caddy block
make_client() {
  local s=$1 d="$BASE/qubix-$1"
  mkdir -p "$d/.git"; : > "$d/artisan"
  cat > "$d/docker-compose.$s.yml" <<EOF
name: qubix-$s
services:
  cache:
    image: alpine:3
    command: sleep 600
    volumes: ["data:/d"]
    networks: [priv]
volumes: {data: {}}
networks: {priv: {}}
EOF
  (cd "$d" && docker compose -f "docker-compose.$s.yml" up -d >/dev/null 2>&1)
  docker tag alpine:3 "qubix/app-$s"
  echo "$s.example.test { respond \"hi\" }" > "$CADDY/docker/caddy/clients/$s.caddy"
}
count() { # count <kind> <slug>
  case $1 in
    c) docker ps -aq --filter "label=com.docker.compose.project=qubix-$2" | wc -l ;;
    v) docker volume ls -q --filter "label=com.docker.compose.project=qubix-$2" | wc -l ;;
    n) docker network ls -q --filter "label=com.docker.compose.project=qubix-$2" | wc -l ;;
  esac
}
sut() { PATH="$T/bin:$PATH" "$SUT" --base-dir="$BASE" --caddy-target-dir="$CADDY" "$@" >"$T/out" 2>&1; RC=$?; }

make_client "$TARGET"; make_client "$DECOY"
: > "$STUBLOG"
check "setup: target and decoy stacks are up" "[ \$(count c $TARGET) = 1 ] && [ \$(count c $DECOY) = 1 ] && [ \$(count v $TARGET) = 1 ] && [ \$(count n $TARGET) = 1 ]"

# --- refusals: exit code, and NOTHING changed ------------------------------------------------
sut --slug="$TARGET";                         check "no --confirm -> exit 1, nothing removed" "[ $RC = 1 ] && [ \$(count c $TARGET) = 1 ]"
sut --slug="$TARGET" --confirm="$DECOY";      check "mismatched --confirm -> exit 1, nothing removed" "[ $RC = 1 ] && [ \$(count c $TARGET) = 1 ] && [ \$(count c $DECOY) = 1 ]"
sut --slug="Bad_Slug" --confirm="Bad_Slug";   check "invalid slug -> exit 1" "[ $RC = 1 ]"
sut --slug="$TARGET" --confirm="$TARGET" --bogus=1; check "unknown argument -> exit 1, nothing removed" "[ $RC = 1 ] && [ \$(count c $TARGET) = 1 ]"
for s in sa qubix qubix-sa n8n automation www fleet jjbags; do
  sut --slug="$s" --confirm="$s"
  check "reserved slug '$s' -> exit 2 (refused before any docker call)" "[ $RC = 2 ] && grep -q Refused '$T/out'"
done

# A project that carries our label but belongs to a live compose file must be refused.
docker run -d --name "tdt-live-$R" --label "com.docker.compose.project=qubix-$LIVEISH" \
  --label "com.docker.compose.project.config_files=/opt/qubix/docker-compose.prod.yml" alpine:3 sleep 600 >/dev/null
sut --slug="$LIVEISH" --confirm="$LIVEISH"
check "label collides with a live compose file -> exit 2, container survives" "[ $RC = 2 ] && [ \$(docker ps -q -f name=tdt-live-$R | wc -l) = 1 ]"
docker rm -f "tdt-live-$R" >/dev/null

# --- dry run changes nothing -----------------------------------------------------------------
sut --slug="$TARGET" --confirm="$TARGET" --dry-run
check "--dry-run -> exit 0, prints [dry-run], removes nothing" "[ $RC = 0 ] && grep -q '\[dry-run\]' '$T/out' \
  && [ \$(count c $TARGET) = 1 ] && [ \$(count v $TARGET) = 1 ] && [ \$(count n $TARGET) = 1 ] \
  && docker image inspect qubix/app-$TARGET >/dev/null 2>&1 && [ -d '$BASE/qubix-$TARGET' ] && [ -f '$CADDY/docker/caddy/clients/$TARGET.caddy' ]"

# --- docker unreachable is transient (10), not success ----------------------------------------
DOCKER_HOST=unix:///nonexistent.sock sut --slug="$TARGET" --confirm="$TARGET"
check "docker daemon unreachable -> exit 10 (transient), nothing removed" "[ $RC = 10 ] && [ -d '$BASE/qubix-$TARGET' ]"

# --- the real thing ---------------------------------------------------------------------------
: > "$STUBLOG"
sut --slug="$TARGET" --confirm="$TARGET"
check "teardown -> exit 0" "[ $RC = 0 ]"
check "containers, volume and private network are gone" "[ \$(count c $TARGET) = 0 ] && [ \$(count v $TARGET) = 0 ] && [ \$(count n $TARGET) = 0 ]"
check "per-client image is gone" "! docker image inspect qubix/app-$TARGET >/dev/null 2>&1"
check "checkout dir (compose file, .env) is gone" "[ ! -e '$BASE/qubix-$TARGET' ]"
check "Caddy block file is gone, and Caddy was validated then reloaded (in that order)" \
  "[ ! -e '$CADDY/docker/caddy/clients/$TARGET.caddy' ] && [ \"\$(tr '\n' ' ' < '$STUBLOG')\" = 'validate reload ' ]"
check "DECOY untouched: container running, volume, network, image, checkout, caddy block" \
  "[ \$(docker ps -q -f label=com.docker.compose.project=qubix-$DECOY | wc -l) = 1 ] && [ \$(count v $DECOY) = 1 ] && [ \$(count n $DECOY) = 1 ] \
   && docker image inspect qubix/app-$DECOY >/dev/null 2>&1 && [ -d '$BASE/qubix-$DECOY' ] && [ -f '$CADDY/docker/caddy/clients/$DECOY.caddy' ]"

# --- idempotent -------------------------------------------------------------------------------
: > "$STUBLOG"
sut --slug="$TARGET" --confirm="$TARGET"
check "re-run on an already-removed client -> exit 0, no Caddy call" "[ $RC = 0 ] && [ ! -s '$STUBLOG' ]"

# --- checkout guard: a directory that does not look like this repo is never deleted ----------
mkdir -p "$BASE/qubix-$NOART"; : > "$BASE/qubix-$NOART/precious"
sut --slug="$NOART" --confirm="$NOART"
check "dir without .git/artisan -> exit 2, contents left in place" "[ $RC = 2 ] && [ -f '$BASE/qubix-$NOART/precious' ]"
ln -s "$BASE/qubix-$DECOY" "$BASE/qubix-tdtsl$R"
sut --slug="tdtsl$R" --confirm="tdtsl$R"
check "symlinked checkout path -> exit 2, symlink target (the decoy) untouched" "[ $RC = 2 ] && [ -d '$BASE/qubix-$DECOY' ] && [ \$(count c $DECOY) = 1 ]"

echo "----"; [ "$FAILS" = 0 ] && echo "all passed" || echo "$FAILS failed"
exit "$([ "$FAILS" = 0 ] && echo 0 || echo 1)"
