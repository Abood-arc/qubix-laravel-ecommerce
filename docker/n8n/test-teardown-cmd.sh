#!/usr/bin/env bash
# Runs the ACTUAL `Prep Teardown` command from client-onboarding.workflow.json against a stub
# `docker` in a temp dir. No n8n, no real Docker, no network, nothing outside a mktemp dir.
#
# Why: the fleet-target shim answers `docker compose -f F -p P down` without checking that F
# exists, so it could not see the case where provision-client.sh failed BEFORE
# qubix:provision generated docker-compose.<slug>.yml (e.g. a failed `docker build`, exit 10).
# Real `docker compose -f <missing file> down` exits 1, which used to end the run as a terminal
# failed_teardown instead of a retry.
#
# Usage: docker/n8n/test-teardown-cmd.sh      (exit 0 = all pass)
set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WF="${WF_UNDER_TEST:-$HERE/client-onboarding.workflow.json}"
command -v node >/dev/null || { echo "node is required"; exit 2; }

T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
FAILS=0
ok()   { printf 'PASS %s\n' "$1"; }
fail() { printf 'FAIL %s\n' "$1"; FAILS=$((FAILS+1)); }

# Stub docker: models only what Prep Teardown calls.
mkdir -p "$T/bin"
cat > "$T/bin/docker" <<'STUB'
#!/usr/bin/env bash
echo "docker $*" >> "$STUB_LOG"
if [ "${1:-}" = compose ] && [ "${2:-}" = ls ]; then cat "$STUB_PROJECTS"; exit 0; fi
if [ "${1:-}" = compose ] && [ "${2:-}" = -f ]; then
  f="$3"
  if [ ! -f "$f" ]; then echo "open $f: no such file or directory" >&2; exit 1; fi   # real compose behaviour
  proj="$5"; grep -vxF -- "$proj" "$STUB_PROJECTS" > "$STUB_PROJECTS.tmp"; cat "$STUB_PROJECTS.tmp" > "$STUB_PROJECTS"; rm -f "$STUB_PROJECTS.tmp"
  exit 0
fi
echo "stub docker: unsupported: $*" >&2; exit 1
STUB
chmod +x "$T/bin/docker"

# Build the teardown command exactly as n8n would, from the committed JSON.
build_cmd() { # slug run_id base
  node -e '
    const wf = JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"));
    const code = wf.nodes.find(n => n.name === "Prep Teardown").parameters.jsCode;
    const st = { owned_by_run: true, next: "teardown", run_id: process.argv[3],
                 input: { slug: process.argv[2] }, cfg: { base_dir: process.argv[4] } };
    const fn = new Function("$input", code);
    process.stdout.write(fn({ first: () => ({ json: st }) })[0].json.teardown_cmd);
  ' "$WF" "$1" "$2" "$3"
}

# scenario name  slug  marker(run id or "-" for none)  compose-file(yes|no)  project-listed(yes|no)
run() {
  local name=$1 slug=$2 marker=$3 compose=$4 listed=$5
  local base="$T/$name"; mkdir -p "$base/qubix-$slug/.git"
  [ "$marker" != "-" ] && printf '%s' "$marker" > "$base/qubix-$slug/.git/fleet-run-id"
  [ "$compose" = yes ] && : > "$base/qubix-$slug/docker-compose.$slug.yml"
  : > "$base/projects"; [ "$listed" = yes ] && echo "qubix-$slug" > "$base/projects"
  : > "$base/log"
  local cmd; cmd="$(build_cmd "$slug" run-A "$base")" || { echo "could not build command"; return 99; }
  OUT="$(STUB_LOG="$base/log" STUB_PROJECTS="$base/projects" PATH="$T/bin:$PATH" bash -c "$cmd" 2>&1)"; RC=$?
  LOG="$(cat "$base/log")"; MARKER_LEFT=no; [ -e "$base/qubix-$slug/.git/fleet-run-id" ] && MARKER_LEFT=yes
}

# 1. THE BUG: provision failed before a compose file was generated. Nothing was ever created,
#    so teardown must report GONE (retryable), release the marker, and never call `down`.
run nocompose acme run-A no no
if echo "$OUT" | grep -qx GONE && [ "$MARKER_LEFT" = no ] && ! echo "$LOG" | grep -q ' down '; then
  ok "compose file absent (provision died before generating it) -> GONE, marker released, no 'down' attempted"
else fail "compose file absent -> expected GONE + marker released; got rc=$RC marker_left=$MARKER_LEFT out=[$(echo "$OUT" | tr '\n' '|')]"; fi

# 2. Compose file absent BUT the project somehow exists: must NOT claim success.
run nocompose_listed acme run-A no yes
if echo "$OUT" | grep -qx STILL_PRESENT && ! echo "$OUT" | grep -qx GONE && [ "$MARKER_LEFT" = yes ]; then
  ok "compose file absent but project listed -> STILL_PRESENT, marker kept (never claims a teardown it did not do)"
else fail "absent file + listed project must be STILL_PRESENT with marker kept; got out=[$(echo "$OUT" | tr '\n' '|')] marker_left=$MARKER_LEFT"; fi

# 3. Normal path: compose file present -> `down` runs, project gone, marker released.
run normal acme run-A yes yes
if echo "$OUT" | grep -qx GONE && [ "$MARKER_LEFT" = no ] && echo "$LOG" | grep -q 'compose -f docker-compose.acme.yml -p qubix-acme down -v --remove-orphans'; then
  ok "compose file present -> 'down -v --remove-orphans' on exactly project qubix-acme, GONE, marker released"
else fail "normal path: got out=[$(echo "$OUT" | tr '\n' '|')] marker_left=$MARKER_LEFT log=[$(echo "$LOG" | tr '\n' '|')]"; fi

# 4. Ownership guard unchanged: foreign marker -> NOT_OWNER and no docker call at all.
run foreign acme run-B yes yes
if echo "$OUT" | grep -qx NOT_OWNER && ! echo "$LOG" | grep -q ' down ' && [ "$MARKER_LEFT" = yes ]; then
  ok "foreign run id -> NOT_OWNER, no 'down', marker untouched"
else fail "foreign marker: got out=[$(echo "$OUT" | tr '\n' '|')] log=[$(echo "$LOG" | tr '\n' '|')]"; fi

# 5. No marker at all -> NO_MARKER (a failed clone), no docker call.
run nomarker acme - yes yes
if echo "$OUT" | grep -qx NO_MARKER && ! echo "$LOG" | grep -q ' down '; then
  ok "no marker -> NO_MARKER, no 'down'"
else fail "no marker: got out=[$(echo "$OUT" | tr '\n' '|')]"; fi

echo "----"; [ "$FAILS" = 0 ] && echo "all passed" || echo "$FAILS failed"
exit "$([ "$FAILS" = 0 ] && echo 0 || echo 1)"
