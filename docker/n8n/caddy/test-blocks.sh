#!/usr/bin/env bash
# Local proof for the automation. / fleet. Caddy blocks (Task 4.4 piece 2).
# Touches NOTHING outside local Docker: no SSH, no VPS, no real DNS.
#
# What it stands up:
#   - a stub "n8n" (stock caddy:2-alpine) that answers on :5678, emits n8n's own
#     `sandbox` CSP, and logs every request it actually receives — so "did this
#     request reach the upstream?" is measured, not assumed;
#   - a proxy Caddy (same image the VPS runs) loaded with the two rendered
#     blocks, using `local_certs` so no ACME call is made.
# NOTHING is bind-mounted: files go in with `docker cp` (a missing bind source
# would be created root-owned on the host — see docker/n8n/test-target/run.sh).
#
# Usage: docker/n8n/caddy/test-blocks.sh
# Exit code: 0 all assertions passed, 1 otherwise.

set -uo pipefail
cd "$(dirname "$0")/../../.."

IMG="caddy:2-alpine"
NET="qubix-blocks-test"
STUB="n8n"                       # the blocks proxy to n8n:5678 — the name must match
PROXY="qubix-blocks-test-proxy"
VALIDATE="qubix-blocks-test-validate"
PORT=18443
AUTO_HOST="automation.digital-labs.ai"
FLEET_HOST="fleet.digital-labs.ai"
PW='correct-horse-battery-staple'
USER_NAME="owner"
STRICT_CSP="default-src 'none'; style-src 'unsafe-inline'"

PASS=0
FAIL=0
WORK="$(mktemp -d)"

cleanup() {
  docker rm -f "$STUB" "$PROXY" "$VALIDATE" >/dev/null 2>&1 || true
  docker network rm "$NET" >/dev/null 2>&1 || true
  rm -rf "$WORK"
}
trap cleanup EXIT

ok()   { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()  { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n        %s\n' "$1" "${2:-}"; }
check() { # check <label> <expected> <actual>
  if [[ "$2" == "$3" ]]; then ok "$1"; else bad "$1" "expected '$2', got '$3'"; fi
}

section() { printf '\n== %s\n' "$1"; }

# --- upstream request counter -------------------------------------------------
hits() { sleep 0.3; docker logs "$STUB" 2>&1 | grep -c '"logger":"http.log.access' || true; }
last_upstream_uri() { docker logs "$STUB" 2>&1 | grep '"logger":"http.log.access' | tail -1 | sed -E 's/.*"uri":"([^"]*)".*/\1/'; }

# req <host> <curl args...> -> prints the HTTP status. Always --path-as-is so
# curl does not tidy the dot-segments and %-escapes this harness is trying to send.
req() {
  local host="$1"; shift
  curl -sk --path-as-is --max-time 20 --resolve "$host:$PORT:127.0.0.1" \
    -o /dev/null -w '%{http_code}' "$@"
}

# expect_blocked <label> <host> <expected-status> <curl args...>
# Passes only if the status matches AND the stub upstream saw no new request.
expect_blocked() {
  local label="$1" host="$2" want="$3"; shift 3
  local before after got
  before="$(hits)"
  got="$(req "$host" "$@")"
  after="$(hits)"
  if [[ "$got" == "$want" && "$before" == "$after" ]]; then
    ok "$label -> $got, upstream untouched"
  else
    bad "$label" "status '$got' (want $want); upstream hits $before -> $after (want unchanged); last upstream request: $(last_upstream_uri)"
  fi
}

# expect_proxied <label> <host> <expected-status> <curl args...>
expect_proxied() {
  local label="$1" host="$2" want="$3"; shift 3
  local before after got
  before="$(hits)"
  got="$(req "$host" "$@")"
  after="$(hits)"
  if [[ "$got" == "$want" && "$after" -gt "$before" ]]; then
    ok "$label -> $got, reached upstream"
  else
    bad "$label" "status '$got' (want $want); upstream hits $before -> $after (want a new hit)"
  fi
}

# --- 0. preflight --------------------------------------------------------------
section "preflight"
docker image inspect "$IMG" >/dev/null 2>&1 || docker pull -q "$IMG" >/dev/null
docker image inspect "$IMG" >/dev/null 2>&1 && ok "image $IMG present" || { bad "image $IMG" "cannot pull"; exit 1; }
[[ -x docker/n8n/caddy/render-block.sh ]] && ok "render-block.sh executable" || { bad "render-block.sh" "missing or not executable"; exit 1; }

# --- reserved slugs: three copies must agree ----------------------------------
section "reserved slugs (generate-caddy-block.sh, register-caddy-client.sh, workflow Validate node)"
for slug in automation www fleet; do
  scripts/generate-caddy-block.sh --slug "$slug" >/dev/null 2>&1
  check "generate-caddy-block.sh rejects '$slug'" 1 "$?"
  # rejected before any SSH; --host points nowhere so a regression cannot reach the VPS
  scripts/register-caddy-client.sh --slug "$slug" --host qubix-test-no-such-host >/dev/null 2>&1
  check "register-caddy-client.sh rejects '$slug'" 1 "$?"
  grep -q "'$slug'" <(grep -o "const LEGACY = \[[^]]*\]" docker/n8n/client-onboarding.workflow.json) && ok "workflow LEGACY list contains '$slug'" || bad "workflow LEGACY list" "'$slug' missing"
done
scripts/generate-caddy-block.sh --slug acme >/dev/null 2>&1
check "generate-caddy-block.sh still accepts an ordinary slug" 0 "$?"

# A REAL bcrypt hash from Caddy itself — the dollar signs in it are the whole point.
HASH="$(docker run --rm "$IMG" caddy hash-password --plaintext "$PW")"
[[ "$HASH" =~ ^\$2[aby]\$[0-9]{2}\$ ]] && ok "caddy hash-password produced a bcrypt hash" || { bad "hash-password" "got '$HASH'"; exit 1; }

# --- 1. render: the hash survives byte-for-byte --------------------------------
section "render-block.sh"
mkdir -p "$WORK/blocks"
printf '%s\n' "$HASH" | docker/n8n/caddy/render-block.sh --template docker/n8n/caddy/automation.caddy.tpl --user "$USER_NAME" > "$WORK/blocks/automation.caddy"
check "automation render exit code" 0 "$?"
docker/n8n/caddy/render-block.sh --template docker/n8n/caddy/fleet.caddy.tpl > "$WORK/blocks/fleet.caddy" < /dev/null
check "fleet render exit code (no secret needed)" 0 "$?"
grep -qF -- "$USER_NAME $HASH" "$WORK/blocks/automation.caddy" && ok "hash and user rendered verbatim (\$2a\$... not mangled)" || bad "verbatim hash" "line '$USER_NAME <hash>' not found"
grep -q '{{' "$WORK/blocks/"*.caddy && bad "placeholders" "a {{...}} placeholder survived rendering" || ok "no {{placeholder}} left in either block"
for badhash in 'plaintext-not-a-hash' '$2a$14$short' '$2a$14$'"$(printf 'x%.0s' {1..53})"'&' ''; do
  printf '%s\n' "$badhash" | docker/n8n/caddy/render-block.sh --template docker/n8n/caddy/automation.caddy.tpl --user "$USER_NAME" >/dev/null 2>&1
  check "rejects invalid hash '${badhash:0:24}'" 1 "$?"
done
printf '%s\n' "$HASH" | docker/n8n/caddy/render-block.sh --template docker/n8n/caddy/automation.caddy.tpl --user 'bad user' >/dev/null 2>&1
check "rejects a username with a space" 1 "$?"

# --- 2. the real Caddyfile accepts them ----------------------------------------
section "caddy validate against the real docker/caddy/Caddyfile"
docker create --name "$VALIDATE" "$IMG" caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null
docker cp docker/caddy/. "$VALIDATE:/etc/caddy/" >/dev/null
docker cp "$WORK/blocks/." "$VALIDATE:/etc/caddy/clients/" >/dev/null
if docker start -a "$VALIDATE" >"$WORK/validate.out" 2>&1; then ok "real Caddyfile + both blocks validates"; else bad "validate" "$(tail -5 "$WORK/validate.out")"; fi

# --- 3. stub upstream + proxy ----------------------------------------------------
section "start stub n8n and proxy"
docker network create "$NET" >/dev/null
# The stub replies with n8n's own sandbox CSP so "replaced, not duplicated" is a real test.
docker run -d --name "$STUB" --network "$NET" "$IMG" sh -c '
cat > /tmp/Caddyfile <<EOF
{
	admin off
	auto_https off
}
:5678 {
	log {
		output stdout
		format json
	}
	header Content-Security-Policy "sandbox allow-forms allow-scripts"
	respond "stub-n8n uri={uri}" 200
}
EOF
exec caddy run --config /tmp/Caddyfile --adapter caddyfile' >/dev/null

cat > "$WORK/Caddyfile" <<'EOF'
{
	admin off
	local_certs
	skip_install_trust
	auto_https disable_redirects
}
import /etc/caddy/blocks/*.caddy
EOF
docker create --name "$PROXY" --network "$NET" -p "127.0.0.1:$PORT:443" "$IMG" caddy run --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null
docker cp "$WORK/Caddyfile" "$PROXY:/etc/caddy/Caddyfile" >/dev/null
docker cp "$WORK/blocks" "$PROXY:/etc/caddy/blocks" >/dev/null
docker start "$PROXY" >/dev/null

# wait for the proxy (local CA cert issuance is quick, but not instant)
up=""
for _ in $(seq 1 30); do
  code="$(req "$FLEET_HOST" "https://$FLEET_HOST:$PORT/" 2>/dev/null || true)"
  [[ "$code" == "404" ]] && { up=1; break; }
  sleep 1
done
[[ -n "$up" ]] && ok "proxy answering on :$PORT" || { bad "proxy start" "$(docker logs "$PROXY" 2>&1 | tail -5)"; exit 1; }
[[ "$(hits)" =~ ^[0-9]+$ ]] && ok "stub upstream logging requests (hits=$(hits))" || bad "stub logging" "cannot count hits"

# --- 4. automation.digital-labs.ai ---------------------------------------------
section "$AUTO_HOST — editor behind basic auth, only POST fleet-onboard public"
A="https://$AUTO_HOST:$PORT"
expect_blocked "GET / with no credentials"                 "$AUTO_HOST" 401 "$A/"
expect_blocked "GET / with a wrong password"               "$AUTO_HOST" 401 -u "$USER_NAME:wrong" "$A/"
expect_blocked "GET /rest/login (no credentials)"          "$AUTO_HOST" 401 "$A/rest/login"
expect_blocked "GET /webhook-test/x (no credentials)"      "$AUTO_HOST" 401 "$A/webhook-test/x"
expect_blocked "GET /api/v1/workflows (no credentials)"    "$AUTO_HOST" 401 "$A/api/v1/workflows"
expect_blocked "POST /webhook/fleet-dashboard (no credentials)" "$AUTO_HOST" 401 -X POST "$A/webhook/fleet-dashboard"
expect_proxied "GET / with the right password (real caddy hash)" "$AUTO_HOST" 200 -u "$USER_NAME:$PW" "$A/"
expect_proxied "GET /rest/x with the right password"       "$AUTO_HOST" 200 -u "$USER_NAME:$PW" "$A/rest/x"
expect_proxied "POST /webhook/fleet-onboard, no basic auth (token is n8n's job)" "$AUTO_HOST" 200 -X POST -d '{"a":1}' "$A/webhook/fleet-onboard"

# fail-closed: everything that is not EXACTLY POST /webhook/fleet-onboard needs the password
expect_blocked "GET  /webhook/fleet-onboard"               "$AUTO_HOST" 401 "$A/webhook/fleet-onboard"
expect_blocked "HEAD /webhook/fleet-onboard"               "$AUTO_HOST" 401 -I "$A/webhook/fleet-onboard"
expect_blocked "OPTIONS /webhook/fleet-onboard"            "$AUTO_HOST" 401 -X OPTIONS "$A/webhook/fleet-onboard"
expect_blocked "PUT /webhook/fleet-onboard"                "$AUTO_HOST" 401 -X PUT "$A/webhook/fleet-onboard"
expect_blocked "POST /webhook/fleet-onboard/ (trailing slash)" "$AUTO_HOST" 401 -X POST "$A/webhook/fleet-onboard/"
expect_blocked "POST /webhook/fleet-onboard/x (sub-path)"  "$AUTO_HOST" 401 -X POST "$A/webhook/fleet-onboard/x"
expect_blocked "POST //webhook/fleet-onboard (double slash)" "$AUTO_HOST" 401 -X POST "$A//webhook/fleet-onboard"
expect_blocked "POST /webhook/fleet-onboard/../../rest/x (dot-segments)" "$AUTO_HOST" 401 -X POST "$A/webhook/fleet-onboard/../../rest/x"
expect_blocked "POST /webhook/fleet-onboard/%2e%2e/%2e%2e/rest/x (encoded dots)" "$AUTO_HOST" 401 -X POST "$A/webhook/fleet-onboard/%2e%2e/%2e%2e/rest/x"
expect_blocked "POST /webhook/%2e%2e/rest/x"               "$AUTO_HOST" 401 -X POST "$A/webhook/%2e%2e/rest/x"
expect_blocked "POST /webhook/fleet-onboard%2f..%2frest%2fx (encoded slash)" "$AUTO_HOST" 401 -X POST "$A/webhook/fleet-onboard%2f..%2frest%2fx"

# A percent-encoded spelling of the SAME path decodes to it, is proxied, and n8n decodes
# it to the same endpoint — the same public route, no extra reach. Recorded, not assumed.
expect_proxied "POST /webhook/%66leet-onboard (decodes to the exact public path)" "$AUTO_HOST" 200 -X POST -d '{}' "$A/webhook/%66leet-onboard"

# The block compares the raw request path exactly (an `expression` matcher, not `path`:
# the `path` matcher is case-insensitive and merges //), so these are NOT the public
# endpoint and must be challenged for the password like everything else.
expect_blocked "POST /WEBHOOK/Fleet-Onboard (case variant is not the exact path)" "$AUTO_HOST" 401 -X POST -d '{}' "$A/WEBHOOK/Fleet-Onboard"

# 64 KiB body cap on the public endpoint
head -c 1024 /dev/zero | tr '\0' 'a' > "$WORK/small.bin"
head -c 70000 /dev/zero | tr '\0' 'a' > "$WORK/big.bin"
expect_proxied "POST /webhook/fleet-onboard, 1 KB body"    "$AUTO_HOST" 200 -X POST --data-binary "@$WORK/small.bin" "$A/webhook/fleet-onboard"
# Caddy enforces the cap while streaming the body, so n8n may still see the (aborted)
# request; what the caller gets — and what protects n8n from a huge body — is the 413.
check "POST /webhook/fleet-onboard, 70000-byte body (cap is 64 KiB) -> 413" 413 "$(req "$AUTO_HOST" -X POST --data-binary "@$WORK/big.bin" "$A/webhook/fleet-onboard")"
robots="$(curl -sk --resolve "$AUTO_HOST:$PORT:127.0.0.1" -D - -o /dev/null -u "$USER_NAME:$PW" "$A/" | tr -d '\r' | grep -i '^x-robots-tag' | tr 'A-Z' 'a-z')"
check "X-Robots-Tag: noindex on the editor host" "x-robots-tag: noindex" "$robots"

# --- 5. fleet.digital-labs.ai ----------------------------------------------------
section "$FLEET_HOST — exactly GET /webhook/fleet-dashboard, everything else 404, no Caddy auth"
F="https://$FLEET_HOST:$PORT"
expect_proxied "GET /webhook/fleet-dashboard"              "$FLEET_HOST" 200 "$F/webhook/fleet-dashboard"
csp_lines="$(curl -sk --resolve "$FLEET_HOST:$PORT:127.0.0.1" -D - -o /dev/null "$F/webhook/fleet-dashboard" | tr -d '\r' | grep -i '^content-security-policy')"
check "exactly one CSP header (n8n's sandbox one was REPLACED, not added to)" 1 "$(printf '%s\n' "$csp_lines" | grep -ic .)"
check "the CSP value is the strict policy" "content-security-policy: $STRICT_CSP" "$(printf '%s' "$csp_lines" | tr 'A-Z' 'a-z' | sed "s/^content-security-policy: //; s/^/content-security-policy: /")"
printf '%s' "$csp_lines" | grep -qi sandbox && bad "sandbox CSP" "n8n's sandbox policy still present" || ok "n8n's sandbox CSP is gone"
robots="$(curl -sk --resolve "$FLEET_HOST:$PORT:127.0.0.1" -D - -o /dev/null "$F/webhook/fleet-dashboard" | tr -d '\r' | grep -i '^x-robots-tag' | tr 'A-Z' 'a-z')"
check "X-Robots-Tag: noindex on the dashboard host" "x-robots-tag: noindex" "$robots"

expect_blocked "GET /"                                     "$FLEET_HOST" 404 "$F/"
expect_blocked "GET /rest/login"                           "$FLEET_HOST" 404 "$F/rest/login"
expect_blocked "GET /webhook-test/x"                       "$FLEET_HOST" 404 "$F/webhook-test/x"
expect_blocked "GET /api/v1/workflows"                     "$FLEET_HOST" 404 "$F/api/v1/workflows"
expect_blocked "POST /webhook/fleet-onboard (must NOT be reachable here)" "$FLEET_HOST" 404 -X POST -d '{}' "$F/webhook/fleet-onboard"
expect_blocked "GET  /webhook/fleet-onboard"               "$FLEET_HOST" 404 "$F/webhook/fleet-onboard"
expect_blocked "POST /webhook/fleet-dashboard (dashboard is GET-only)" "$FLEET_HOST" 404 -X POST -d '{}' "$F/webhook/fleet-dashboard"
expect_blocked "HEAD /webhook/fleet-dashboard"             "$FLEET_HOST" 404 -I "$F/webhook/fleet-dashboard"
expect_blocked "OPTIONS /webhook/fleet-dashboard"          "$FLEET_HOST" 404 -X OPTIONS "$F/webhook/fleet-dashboard"
expect_blocked "GET /webhook/fleet-dashboard/ (trailing slash)" "$FLEET_HOST" 404 "$F/webhook/fleet-dashboard/"
expect_blocked "GET /webhook/fleet-dashboard/x (sub-path)" "$FLEET_HOST" 404 "$F/webhook/fleet-dashboard/x"
expect_blocked "GET //webhook/fleet-dashboard (double slash)" "$FLEET_HOST" 404 "$F//webhook/fleet-dashboard"
expect_blocked "GET /webhook/fleet-dashboard/../../rest/x (dot-segments)" "$FLEET_HOST" 404 "$F/webhook/fleet-dashboard/../../rest/x"
expect_blocked "GET /webhook/fleet-dashboard/%2e%2e/%2e%2e/rest/x (encoded dots)" "$FLEET_HOST" 404 "$F/webhook/fleet-dashboard/%2e%2e/%2e%2e/rest/x"
expect_blocked "GET /webhook/%2e%2e/rest/x"                "$FLEET_HOST" 404 "$F/webhook/%2e%2e/rest/x"
expect_blocked "GET /webhook/fleet-dashboard%2f..%2frest%2fx (encoded slash)" "$FLEET_HOST" 404 "$F/webhook/fleet-dashboard%2f..%2frest%2fx"
expect_proxied "GET /webhook/%66leet-dashboard (decodes to the exact dashboard path)" "$FLEET_HOST" 200 "$F/webhook/%66leet-dashboard"
expect_proxied "GET /webhook/fleet-dashboard?x=1 (a query string is not a different path)" "$FLEET_HOST" 200 "$F/webhook/fleet-dashboard?x=1"
expect_blocked "GET /WEBHOOK/fleet-dashboard (case variant is not the exact path)" "$FLEET_HOST" 404 "$F/WEBHOOK/fleet-dashboard"

section "result"
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
[[ "$FAIL" -eq 0 ]]
