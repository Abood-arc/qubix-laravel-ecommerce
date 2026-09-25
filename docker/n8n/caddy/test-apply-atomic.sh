#!/usr/bin/env bash
# Integration test for the ATOMIC behaviour of scripts/apply-caddy-block.sh, against a REAL
# caddy:2-alpine in a throwaway Compose project shaped like the VPS one (./docker/caddy mounted
# as a DIRECTORY, `import clients/*.caddy`). Nothing here can reach the VPS or a real site.
#
# The property under test: on the shared Caddy, an unvalidated block is never visible to the
# `import clients/*.caddy` glob — not even while `caddy validate` runs — so a process killed at
# ANY point (SIGKILL included) cannot leave a file that breaks the next Caddy restart for the
# live sites. Also: concurrent applies are serialised, and validation sees the OTHER clients'
# blocks (duplicate site addresses are caught) while ignoring the slug's own old block
# (re-registering works).
#
# Usage: docker/n8n/caddy/test-apply-atomic.sh      Exit 0 = all scenarios passed.
set -uo pipefail
cd "$(dirname "$0")/../../.."
REPO="$PWD"
IMG="caddy:2-alpine"; PORT=18445
WORK="$(mktemp -d)"
TARGET="$WORK/qubixatomictest"           # compose project name = directory name
CLIENTS="$TARGET/docker/caddy/clients"
APPLY="${APPLY_UNDER_TEST:-$REPO/scripts/apply-caddy-block.sh}"
PASS=0; FAIL=0

cleanup() {
  touch "$WORK/release" 2>/dev/null
  [[ -f "$TARGET/docker-compose.prod.yml" ]] && docker compose -f "$TARGET/docker-compose.prod.yml" down -v >/dev/null 2>&1
  rm -rf "$WORK"
}
trap cleanup EXIT
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n        %s\n' "$1" "${2:-}"; }
check() { [[ "$2" == "$3" ]] && ok "$1" || bad "$1" "expected '$2', got '$3'"; }
section() { printf '\n== %s\n' "$1"; }

mkdir -p "$TARGET/docker/caddy/clients" "$WORK/bin"
cat > "$TARGET/docker-compose.prod.yml" <<EOF
services:
  caddy:
    image: $IMG
    ports: ["127.0.0.1:$PORT:443"]
    volumes:
      - ./docker/caddy:/etc/caddy:ro
      - caddy-data:/data
      - caddy-config:/config
volumes:
  caddy-data: {}
  caddy-config: {}
EOF
cat > "$TARGET/docker/caddy/Caddyfile" <<'EOF'
{
	local_certs
	skip_install_trust
	auto_https disable_redirects
}
import clients/*.caddy
live-a.test {
	respond "ok" 200
}
live-b.test {
	respond "ok" 200
}
EOF

# docker stub: only `caddy validate` can be made slow (touch $WORK/slow); everything else is the real docker.
REAL_DOCKER="$(command -v docker)"
cat > "$WORK/bin/docker" <<STUB
#!/usr/bin/env bash
if [[ "\$*" == *"caddy validate"* && -e "$WORK/slow" ]]; then
  : > "$WORK/validating"
  for _ in \$(seq 1 300); do [[ -e "$WORK/release" ]] && break; sleep 0.1; done
  rm -f "$WORK/validating"
fi
exec "$REAL_DOCKER" "\$@"
STUB
chmod +x "$WORK/bin/docker"

docker image inspect "$IMG" >/dev/null 2>&1 || docker pull -q "$IMG" >/dev/null
section "start the throwaway Caddy"
if ! docker compose -f "$TARGET/docker-compose.prod.yml" up -d >"$WORK/up.out" 2>&1; then bad "compose up" "$(tail -5 "$WORK/up.out")"; exit 1; fi

code() { curl -sk -o /dev/null -w '%{http_code}' --max-time 15 --resolve "$1:$PORT:127.0.0.1" "https://$1:$PORT/"; }
body() { curl -sk --max-time 15 --resolve "$1:$PORT:127.0.0.1" "https://$1:$PORT/"; }
wait_up() { for _ in $(seq 1 40); do [[ "$(code live-a.test)" == 200 ]] && return 0; sleep 1; done; return 1; }
wait_up && ok "throwaway Caddy serves the stand-in live sites" || { bad "caddy did not come up"; exit 1; }
live_ok() { check "$1: live sites still 200" "200 200" "$(code live-a.test) $(code live-b.test)"; }

blockfile() { printf '%s.test {\n\trespond "%s" 200\n}\n' "$1" "$2" > "$WORK/$1.block"; echo "$WORK/$1.block"; }
apply() { PATH="$WORK/bin:$PATH" "$APPLY" --slug "$1" --target-dir "$TARGET" "${@:2}" >"$WORK/out" 2>&1; RC=$?; }
glob_set() { ls "$CLIENTS"/*.caddy 2>/dev/null | xargs -r -n1 basename | sort | tr '\n' ' '; }
debris() { { ls "$CLIENTS"/*.caddy.new "$TARGET/docker/caddy"/Caddyfile.candidate.* 2>/dev/null; } | wc -l; }

section "T1  register a new client"
apply t1 --block-file "$(blockfile t1 t1-v1)"
check "exit 0" 0 "$RC"; check "served with its own content" "t1-v1" "$(body t1.test)"; check "no debris" 0 "$(debris)"; live_ok T1

section "T2  invalid block: rejected, live directory never touched"
printf 't2.test {\n\tthis_is_not_a_directive\n}\n' > "$WORK/t2.block"
before="$(glob_set)"; apply t2 --block-file "$WORK/t2.block"
check "exit 2" 2 "$RC"; check "no t2.caddy created" "$before" "$(glob_set)"; check "no debris" 0 "$(debris)"; live_ok T2

section "T3  duplicate site address across clients is caught (validation sees the OTHER blocks)"
apply t3 --block-file "$(blockfile t1 t3-steals-t1-address)"
check "exit 2" 2 "$RC"; check "t1 still serves its own content" "t1-v1" "$(body t1.test)"; check "no t3 block" "t1.caddy " "$(glob_set)"

section "T4  re-register over an existing block (validation IGNORES the slug's own old block)"
apply t1 --block-file "$(blockfile t1 t1-v2)"
check "exit 0" 0 "$RC"; check "new content served" "t1-v2" "$(body t1.test)"; check "no debris" 0 "$(debris)"

section "T5  failed re-register leaves the previous block byte-for-byte intact"
cp "$CLIENTS/t1.caddy" "$WORK/t1.before"
printf 't1.test {\n\tnope_not_a_directive\n}\n' > "$WORK/bad.block"; apply t1 --block-file "$WORK/bad.block"
check "exit 2" 2 "$RC"; cmp -s "$CLIENTS/t1.caddy" "$WORK/t1.before" && ok "t1.caddy unchanged" || bad "t1.caddy changed"
check "still served" "t1-v2" "$(body t1.test)"

section "T6  SIGKILL while validating: glob never saw the block; a Caddy restart still comes up"
rm -f "$WORK/release"; : > "$WORK/slow"
before="$(glob_set)"
( exec env PATH="$WORK/bin:$PATH" "$APPLY" --slug t6 --target-dir "$TARGET" --block-file "$(blockfile t6 t6-v1)" >"$WORK/out6" 2>&1 ) &
PID=$!
for _ in $(seq 1 100); do [[ -e "$WORK/validating" ]] && break; sleep 0.1; done
[[ -e "$WORK/validating" ]] && ok "apply is mid-validation" || bad "never reached validation"
check "live glob unchanged while validating (no t6.caddy)" "$before" "$(glob_set)"
[[ -e "$CLIENTS/t6.caddy.new" ]] && ok "block is staged only as t6.caddy.new (invisible to the glob)" || bad "staged file missing"
kill -9 "$PID"; wait "$PID" 2>/dev/null
check "no t6.caddy after SIGKILL" "$before" "$(glob_set)"
# The killed apply's `docker compose exec` child is still alive (asleep in the stub). It must NOT have
# inherited the flock, or one killed apply would block every later apply until that child exits.
[[ -e "$WORK/validating" ]] && ok "orphaned validate child is still running" || bad "orphan child already gone (test would prove nothing)"
flock -n "$CLIENTS" true && ok "clients/ lock is free while the orphaned child still runs" || bad "orphaned child inherited the apply lock"
rm -f "$WORK/slow"; touch "$WORK/release"; sleep 1
apply t6b --block-file "$(blockfile t6b t6b-v1)"
check "lock was free right after SIGKILL: next apply succeeds (exit 0)" 0 "$RC"
docker compose -f "$TARGET/docker-compose.prod.yml" restart caddy >/dev/null 2>&1
wait_up && ok "Caddy restarted with the debris present and came back up" || bad "Caddy failed to restart"
live_ok T6
check "existing client still served after restart" "t1-v2" "$(body t1.test)"
check "the killed run's debris was swept by the next apply" 0 "$(debris)"
rm -f "$WORK/release"

section "T7  concurrent applies are serialised, not interleaved"
: > "$WORK/slow"; rm -f "$WORK/release"
( exec env PATH="$WORK/bin:$PATH" "$APPLY" --slug t7a --target-dir "$TARGET" --block-file "$(blockfile t7a t7a-v1)" >"$WORK/out7a" 2>&1 ) & PA=$!
for _ in $(seq 1 100); do [[ -e "$WORK/validating" ]] && break; sleep 0.1; done
( exec env PATH="$WORK/bin:$PATH" "$APPLY" --slug t7b --target-dir "$TARGET" --block-file "$(blockfile t7b t7b-v1)" >"$WORK/out7b" 2>&1 ) & PB=$!
sleep 2
kill -0 "$PB" 2>/dev/null && ok "second apply is waiting on the lock" || bad "second apply did not wait"
[[ ! -e "$CLIENTS/t7b.caddy.new" ]] && ok "second apply has not staged anything yet" || bad "second apply staged while the first held the lock"
rm -f "$WORK/slow"; touch "$WORK/release"
wait "$PA"; RA=$?; wait "$PB"; RB=$?
check "both exit 0" "0 0" "$RA $RB"
check "both served" "t7a-v1 t7b-v1" "$(body t7a.test) $(body t7b.test)"; check "no debris" 0 "$(debris)"; live_ok T7
rm -f "$WORK/release"

section "T8  remove"
apply t1 --remove
check "exit 0" 0 "$RC"; [[ ! -e "$CLIENTS/t1.caddy" ]] && ok "block gone" || bad "block still there"
check "t1 no longer served by its own block" "000" "$(code t1.test)"
check "other clients unaffected" "t7a-v1" "$(body t7a.test)"
apply t1 --remove
check "removing an absent block: exit 0" 0 "$RC"; live_ok T8

section "T9  a Caddyfile not shaped as expected fails closed"
cp "$TARGET/docker/caddy/Caddyfile" "$WORK/Caddyfile.keep"
printf 'import clients/*.caddy\n' >> "$TARGET/docker/caddy/Caddyfile"
before="$(glob_set)"; apply t9 --block-file "$(blockfile t9 t9-v1)"
check "exit 1" 1 "$RC"; check "nothing applied" "$before" "$(glob_set)"
cp "$WORK/Caddyfile.keep" "$TARGET/docker/caddy/Caddyfile"; live_ok T9

section "T10 stale debris from an earlier killed run is never loaded and gets swept"
: > "$CLIENTS/zombie.caddy.new"; printf 'zombie.test { respond "z" 200 }\n' > "$CLIENTS/zombie.caddy.new"
: > "$TARGET/docker/caddy/Caddyfile.candidate.zzzzzz"
docker compose -f "$TARGET/docker-compose.prod.yml" exec -T caddy caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile --force >/dev/null 2>&1
check "debris is not served" "000" "$(code zombie.test)"
apply t10 --block-file "$(blockfile t10 t10-v1)"
check "exit 0" 0 "$RC"; check "debris swept" 0 "$(debris)"; live_ok T10

printf '\n== result\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ "$FAIL" == 0 ]]
