#!/usr/bin/env bash
# Integration test for scripts/register-n8n-caddy-blocks.sh (Task 4.4 piece 2).
# Runs the REAL wrapper and the REAL scripts/apply-caddy-block.sh against a REAL
# caddy:2-alpine in a throwaway Compose project shaped like the VPS one
# (docker-compose.prod.yml, ./docker/caddy mounted as a DIRECTORY, `import
# clients/*.caddy`). "ssh" is a shim that runs the remote command locally, so nothing
# here can reach the VPS. Live sites are two stand-in hostnames.
#
# Covers what a unit test of the blocks cannot: staging, the 0600 temp file, validate
# -> reload, the live-site gates, the new-hostname verification, and — the important
# part — that a failed re-apply RESTORES the previous block instead of deleting it.
#
# Usage: docker/n8n/caddy/test-wrapper.sh      Exit 0 = all scenarios passed.

set -uo pipefail
cd "$(dirname "$0")/../../.."
REPO="$PWD"

IMG="caddy:2-alpine"
PORT=18444
WORK="$(mktemp -d)"
TARGET="$WORK/qubixwrappertest"          # compose project name = directory name
CLIENTS="$TARGET/docker/caddy/clients"
WRAP="$REPO/scripts/register-n8n-caddy-blocks.sh"
PASS=0
FAIL=0

cleanup() {
  [[ -f "$TARGET/docker-compose.prod.yml" ]] && docker compose -f "$TARGET/docker-compose.prod.yml" down -v >/dev/null 2>&1
  rm -rf "$WORK"
}
trap cleanup EXIT

ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n        %s\n' "$1" "${2:-}"; }
check() { [[ "$2" == "$3" ]] && ok "$1" || bad "$1" "expected '$2', got '$3'"; }
section() { printf '\n== %s\n' "$1"; }

# --- environment -------------------------------------------------------------------
mkdir -p "$TARGET/docker/caddy"
cat > "$TARGET/docker-compose.prod.yml" <<EOF
services:
  caddy:
    image: $IMG
    ports: ["127.0.0.1:$PORT:443"]
    volumes:
      - ./docker/caddy:/etc/caddy:ro
      - caddy-data:/data
      - caddy-config:/config
    networks: [t]
  n8n:
    image: $IMG
    command: ["sh", "-c", "printf ':5678 {\\n\\trespond \\"stub-n8n\\" 200\\n}\\n' > /tmp/C && exec caddy run --config /tmp/C --adapter caddyfile"]
    networks: [t]
volumes:
  caddy-data: {}
  caddy-config: {}
networks:
  t: {}
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
mkdir -p "$CLIENTS"

cat > "$WORK/fake-ssh" <<'EOF'
#!/usr/bin/env bash
# stands in for `ssh -o X -o Y <host> <command>`: run the command locally, stdin intact
while [[ "${1:-}" == "-o" ]]; do shift 2; done
shift
exec bash -c "$1"
EOF
chmod +x "$WORK/fake-ssh"

RESOLVE=""
for h in live-a.test live-b.test automation.digital-labs.ai fleet.digital-labs.ai; do RESOLVE+=" --resolve $h:$PORT:127.0.0.1"; done
export QUBIX_SSH_BIN="$WORK/fake-ssh"
export QUBIX_CURL_OPTS="-k$RESOLVE"
export QUBIX_LIVE_URLS="https://live-a.test:$PORT/ https://live-b.test:$PORT/"
export QUBIX_CHECK_PORT="$PORT"
export QUBIX_CHECK_TIMEOUT=20

section "start the throwaway Caddy + stub n8n"
docker image inspect "$IMG" >/dev/null 2>&1 || docker pull -q "$IMG" >/dev/null
if ! docker compose -f "$TARGET/docker-compose.prod.yml" up -d >"$WORK/up.out" 2>&1; then bad "compose up" "$(tail -5 "$WORK/up.out")"; exit 1; fi
for _ in $(seq 1 30); do
  [[ "$(curl -sk -o /dev/null -w '%{http_code}' --resolve "live-a.test:$PORT:127.0.0.1" "https://live-a.test:$PORT/")" == "200" ]] && break
  sleep 1
done
check "throwaway Caddy serves the stand-in live site" 200 "$(curl -sk -o /dev/null -w '%{http_code}' --resolve "live-a.test:$PORT:127.0.0.1" "https://live-a.test:$PORT/")"

PW1='first-password-for-the-test'
PW2='second-password-for-the-test'
H1="$(docker run --rm "$IMG" caddy hash-password --plaintext "$PW1")"
H2="$(docker run --rm "$IMG" caddy hash-password --plaintext "$PW2")"

code() { curl -sk -o /dev/null -w '%{http_code}' --max-time 15 --resolve "$1:$PORT:127.0.0.1" "${@:3}" "https://$1:$PORT$2"; }
run_wrapper() { # run_wrapper <hash|-> [wrapper args...]; sets RC, OUT
  local hash="$1"; shift
  if [[ "$hash" == "-" ]]; then
    OUT="$("$WRAP" --host test --target-dir "$TARGET" "$@" </dev/null 2>&1)"; RC=$?
  else
    OUT="$(printf '%s\n' "$hash" | "$WRAP" --host test --target-dir "$TARGET" "$@" 2>&1)"; RC=$?
  fi
}
stray_tmp() { ls /tmp/qubix-n8n-block.* /tmp/qubix-apply-caddy-block.* 2>/dev/null | wc -l; }
TMP_BEFORE="$(stray_tmp)"

live_ok() { check "$1: live sites still 200" "200 200" "$(code live-a.test / ) $(code live-b.test / )"; }

# --- S1 fresh register ----------------------------------------------------------------
section "S1  fresh apply"
run_wrapper "$H1"
check "exit code" 0 "$RC"
[[ "$RC" -ne 0 ]] && printf '%s\n' "$OUT" | tail -15
check "automation.caddy written with mode 600" 600 "$(stat -c %a "$CLIENTS/automation.caddy" 2>/dev/null)"
[[ -f "$CLIENTS/fleet.caddy" ]] && ok "fleet.caddy written" || bad "fleet.caddy" "missing"
grep -qF -- "owner $H1" "$CLIENTS/automation.caddy" && ok "the hash landed verbatim in the block" || bad "hash verbatim" "not found"
check "automation. without password -> 401" 401 "$(code automation.digital-labs.ai /)"
check "automation. with the password -> 200 (reached n8n)" 200 "$(code automation.digital-labs.ai / -u "owner:$PW1")"
check "fleet. / -> 404" 404 "$(code fleet.digital-labs.ai /)"
live_ok S1
check "no stray staged temp files left behind" "$TMP_BEFORE" "$(stray_tmp)"

# --- S2 idempotent ---------------------------------------------------------------------
section "S2  re-apply the same hash"
run_wrapper "$H1"
check "exit code" 0 "$RC"

# --- S3 rotate -------------------------------------------------------------------------
section "S3  rotate to a new password"
run_wrapper "$H2"
check "exit code" 0 "$RC"
check "new password works" 200 "$(code automation.digital-labs.ai / -u "owner:$PW2")"
check "old password no longer works" 401 "$(code automation.digital-labs.ai / -u "owner:$PW1")"
cp "$CLIENTS/automation.caddy" "$WORK/snap.automation"; cp "$CLIENTS/fleet.caddy" "$WORK/snap.fleet"

# --- S4 failed verification restores, does not delete -------------------------------------
section "S4  new-hostname verification fails on a RE-apply -> previous blocks restored (not removed)"
QUBIX_EXPECT_AUTOMATION_STATUS=418 QUBIX_CHECK_TIMEOUT=6 run_wrapper "$H1"
check "exit code 12" 12 "$RC"
[[ -f "$CLIENTS/automation.caddy" && -f "$CLIENTS/fleet.caddy" ]] && ok "both blocks still exist (a working block was NOT deleted)" || bad "blocks deleted" "rollback removed a block that existed before"
cmp -s "$CLIENTS/automation.caddy" "$WORK/snap.automation" && ok "automation.caddy is byte-identical to the pre-run snapshot" || bad "automation.caddy" "content differs from the snapshot"
cmp -s "$CLIENTS/fleet.caddy" "$WORK/snap.fleet" && ok "fleet.caddy is byte-identical to the pre-run snapshot" || bad "fleet.caddy" "content differs from the snapshot"
check "previous password (pw2) works again in the RUNNING config" 200 "$(code automation.digital-labs.ai / -u "owner:$PW2")"
check "the failed attempt's password (pw1) does not" 401 "$(code automation.digital-labs.ai / -u "owner:$PW1")"
live_ok S4

# --- S5 validation failure on the second block ---------------------------------------------
section "S5  fleet. block fails Caddy validation after automation. was applied -> automation. restored"
printf 'fleet.digital-labs.ai {\n\trespond "conflict" 200\n}\n' > "$CLIENTS/zz-conflict.caddy"
run_wrapper "$H1"
check "exit code 2" 2 "$RC"
cmp -s "$CLIENTS/automation.caddy" "$WORK/snap.automation" && ok "automation.caddy restored to the snapshot" || bad "automation.caddy" "not restored after fleet.caddy failed validation"
cmp -s "$CLIENTS/fleet.caddy" "$WORK/snap.fleet" && ok "fleet.caddy restored to the snapshot" || bad "fleet.caddy" "not restored"
check "pw2 still works in the running config" 200 "$(code automation.digital-labs.ai / -u "owner:$PW2")"
rm -f "$CLIENTS/zz-conflict.caddy"
live_ok S5

# --- S6 remove ---------------------------------------------------------------------------------
section "S6  --remove"
run_wrapper - --remove
check "exit code" 0 "$RC"
[[ ! -e "$CLIENTS/automation.caddy" && ! -e "$CLIENTS/fleet.caddy" ]] && ok "both blocks removed" || bad "remove" "block files still present"
live_ok S6

# --- S7 verification fails on a FRESH apply -> removed -------------------------------------------
section "S7  verification fails on a fresh apply -> blocks that did not exist are removed"
QUBIX_EXPECT_AUTOMATION_STATUS=418 QUBIX_CHECK_TIMEOUT=6 run_wrapper "$H1"
check "exit code 12" 12 "$RC"
[[ ! -e "$CLIENTS/automation.caddy" && ! -e "$CLIENTS/fleet.caddy" ]] && ok "no block left behind" || bad "leftover" "block file(s) still present"
live_ok S7

# --- S8 a live site is unhealthy up front -----------------------------------------------------------
section "S8  refuses to start while a live site is down"
QUBIX_LIVE_URLS="https://live-a.test:$PORT/ https://live-b.test:1/" run_wrapper "$H1"
check "exit code 11" 11 "$RC"
[[ ! -e "$CLIENTS/automation.caddy" && ! -e "$CLIENTS/fleet.caddy" ]] && ok "nothing was written" || bad "wrote despite unhealthy site" "block file(s) present"

# --- S9 bad input -------------------------------------------------------------------------------------
section "S9  bad input changes nothing"
run_wrapper "plaintext-password-not-a-hash"
check "exit code 1 for a non-bcrypt stdin" 1 "$RC"
run_wrapper "$H1" --user "bad user"
check "exit code 1 for a bad --user" 1 "$RC"
run_wrapper "$H1" --target-dir "/opt/x y"
check "exit code 1 for a bad --target-dir" 1 "$RC"
[[ ! -e "$CLIENTS/automation.caddy" && ! -e "$CLIENTS/fleet.caddy" ]] && ok "nothing was written" || bad "wrote on bad input" "block file(s) present"

check "no stray staged temp files left behind (end of run)" "$TMP_BEFORE" "$(stray_tmp)"

section "result"
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
[[ "$FAIL" -eq 0 ]]
