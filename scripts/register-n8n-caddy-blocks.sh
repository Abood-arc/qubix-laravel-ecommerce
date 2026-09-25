#!/usr/bin/env bash
# Registers (or removes) the two FLEET-INFRASTRUCTURE Caddy blocks — automation.
# (the n8n editor) and fleet. (the read-only dashboard) — on the shared production
# Caddy that also serves jjbags.in and jj-bags.com. Task 4.4 piece 2.
#
# Run this from a `fleet` checkout on YOUR machine. It cannot run from /opt/qubix:
# that checkout tracks `abood` and has neither scripts/apply-caddy-block.sh nor the
# docker/n8n/caddy/ templates. So, exactly like register-caddy-client.sh, it renders
# the blocks locally and stages both them and apply-caddy-block.sh onto the target
# over SSH, then runs the apply script there (validate -> reload, with rollback).
#
# The basic-auth password hash comes in on STDIN, one line, as printed by
#   docker exec -it qubix-caddy-1 caddy hash-password
# It is never an argument (visible in `ps`) or an environment variable, is rendered
# with plain bash string replacement (docker/n8n/caddy/render-block.sh — a bcrypt
# hash contains $2a$14$... that envsubst or an unquoted expansion would mangle), and
# reaches the VPS through SSH's stdin into a `mktemp` (0600) file that is deleted
# afterwards. The rendered file lands gitignored in docker/caddy/clients/, like every
# other generated client block. The plaintext password is never seen by this script.
#
# Safety, in order:
#   1. Refuses to start unless every live site already answers 200 — so a later
#      failure cannot be blamed on, or hidden by, a pre-existing problem.
#   2. Snapshots each block's CURRENT content before touching it.
#   3. Applies each block through apply-caddy-block.sh (validates the FULL config,
#      reloads only if valid, rolls back its own file on a validation failure).
#   4. Re-checks the live sites, then the new hostnames over real TLS:
#      automation.digital-labs.ai must answer 401 (password required) and
#      fleet.digital-labs.ai must answer 404 (only the dashboard path is proxied).
#      Neither needs n8n to be running.
#   5. If ANY step fails it restores the snapshot — the previous content of a block
#      that already existed, or removes a block that did not — and re-checks the live
#      sites. It never blindly deletes a block that was working before this run.
#
# Usage:
#   docker exec -it qubix-caddy-1 caddy hash-password | scripts/register-n8n-caddy-blocks.sh
#   scripts/register-n8n-caddy-blocks.sh            # prompts for the hash (hidden)
#   scripts/register-n8n-caddy-blocks.sh --remove   # removes both blocks, no hash needed
#
# Options: --host <ssh-alias> (default hostinger-vps)  --target-dir <path> (default
#          /opt/qubix)  --user <basic-auth username> (default owner)
#
# Exit codes:
#   0   applied (or removed) and verified
#   1   invalid input, nothing changed
#   2   Caddy config validation failed — rolled back
#   10  SSH/transfer failure, or the reload itself failed — rolled back if reachable
#   11  a live site was unhealthy: before we started (nothing changed), or after the
#       change (rolled back)
#   12  the new hostnames did not verify in time — rolled back
#   14  the ROLLBACK itself failed: check the running Caddy config by hand NOW
#
# Test hooks (used only by docker/n8n/caddy/test-wrapper.sh): QUBIX_SSH_BIN,
# QUBIX_CURL_OPTS, QUBIX_LIVE_URLS, QUBIX_CHECK_PORT, QUBIX_CHECK_TIMEOUT,
# QUBIX_EXPECT_AUTOMATION_STATUS.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APPLY_SCRIPT="$SCRIPT_DIR/apply-caddy-block.sh"
RENDER="$SCRIPT_DIR/../docker/n8n/caddy/render-block.sh"
TPL_DIR="$SCRIPT_DIR/../docker/n8n/caddy"

SSH_BIN="${QUBIX_SSH_BIN:-ssh}"
read -ra CURL_OPTS <<< "${QUBIX_CURL_OPTS:-}"
read -ra LIVE_URLS <<< "${QUBIX_LIVE_URLS:-https://jjbags.in/ https://jj-bags.com/}"
CHECK_PORT="${QUBIX_CHECK_PORT:-443}"
CHECK_TIMEOUT="${QUBIX_CHECK_TIMEOUT:-150}"
EXPECT_AUTOMATION="${QUBIX_EXPECT_AUTOMATION_STATUS:-401}"
EXPECT_FLEET=404

AUTOMATION_HOST="automation.digital-labs.ai"
FLEET_HOST="fleet.digital-labs.ai"
SLUGS=(automation fleet)          # apply order; rollback runs in reverse

HOST="hostinger-vps"
TARGET_DIR="/opt/qubix"
USER_NAME="owner"
MODE="register"

err() { printf '%s\n' "$*" >&2; }
log() { printf '%s\n' "$*"; }
usage() { err "Usage: <hash on stdin> | $0 [--host <alias>] [--target-dir <path>] [--user <name>]   |   $0 --remove"; }

while [[ $# -gt 0 ]]; do
  case "$1" in
    --host)         [[ $# -ge 2 ]] || { err "Error: --host requires a value"; usage; exit 1; }; HOST="$2"; shift 2 ;;
    --host=*)       HOST="${1#--host=}"; shift ;;
    --target-dir)   [[ $# -ge 2 ]] || { err "Error: --target-dir requires a value"; usage; exit 1; }; TARGET_DIR="$2"; shift 2 ;;
    --target-dir=*) TARGET_DIR="${1#--target-dir=}"; shift ;;
    --user)         [[ $# -ge 2 ]] || { err "Error: --user requires a value"; usage; exit 1; }; USER_NAME="$2"; shift 2 ;;
    --user=*)       USER_NAME="${1#--user=}"; shift ;;
    --remove)       MODE="remove"; shift ;;
    *) err "Error: unknown argument: $1"; usage; exit 1 ;;
  esac
done

if [[ -z "$TARGET_DIR" || "$TARGET_DIR" =~ [[:space:]\'\"\$\`\\] ]]; then
  err "Error: --target-dir must be a non-empty path with no whitespace or shell metacharacters"
  exit 1
fi
for f in "$APPLY_SCRIPT" "$RENDER" "$TPL_DIR/automation.caddy.tpl" "$TPL_DIR/fleet.caddy.tpl"; do
  [[ -f "$f" ]] || { err "Error: $f not found — run this from a fleet checkout (the abood checkout on the VPS does not have it)."; exit 1; }
done

# --- render locally (register mode only) ----------------------------------------------
declare -A NEW_BLOCK
if [[ "$MODE" == "register" ]]; then
  if [[ -t 0 ]]; then
    read -rsp "bcrypt hash (from 'caddy hash-password'): " HASH_LINE; err ""
    HASH_INPUT="$HASH_LINE"
  else
    HASH_INPUT="$(cat)"
  fi
  # Command substitution strips trailing newlines and a failed render must stop us with
  # exit 1, so capture without a sentinel (the sentinel's own exit status would mask
  # the render's) and put the single trailing newline back.
  if ! rendered="$(printf '%s\n' "$HASH_INPUT" | "$RENDER" --template "$TPL_DIR/automation.caddy.tpl" --user "$USER_NAME")"; then
    err "Error: could not render automation.caddy (see above). Nothing changed."
    exit 1
  fi
  NEW_BLOCK[automation]="$rendered"$'\n'
  if ! rendered="$("$RENDER" --template "$TPL_DIR/fleet.caddy.tpl" < /dev/null)"; then
    err "Error: could not render fleet.caddy (see above). Nothing changed."
    exit 1
  fi
  NEW_BLOCK[fleet]="$rendered"$'\n'
  unset HASH_INPUT HASH_LINE
fi

# --- transport helpers -----------------------------------------------------------------
rssh() { "$SSH_BIN" -o ConnectTimeout=15 -o BatchMode=yes "$HOST" "$1"; }

REMOTE_APPLY=""
cleanup() {
  [[ -n "$REMOTE_APPLY" ]] && rssh "rm -f '$REMOTE_APPLY'" >/dev/null 2>&1 || true
}
trap cleanup EXIT

CLIENTS_DIR="$TARGET_DIR/docker/caddy/clients"

# stage_block <content> -> prints the remote temp path. mktemp creates it 0600, and the
# content travels on ssh's stdin, never on a command line.
stage_block() {
  local path
  path="$(printf '%s' "$1" | rssh 'f=$(mktemp /tmp/qubix-n8n-block.XXXXXX) && cat > "$f" && printf %s "$f"')" || return 1
  printf '%s' "$path"
}

# apply_remote <slug> register <content> | apply_remote <slug> remove
# Returns apply-caddy-block.sh's exit code; ssh's own 255 is reported as 10.
apply_remote() {
  local slug="$1" mode="$2" args block_path="" rc
  args="--slug '$slug' --target-dir '$TARGET_DIR'"
  if [[ "$mode" == "register" ]]; then
    block_path="$(stage_block "$3")" || return 10
    args="$args --block-file '$block_path'"
  else
    args="$args --remove"
  fi
  set +e
  rssh "chmod +x '$REMOTE_APPLY' && '$REMOTE_APPLY' $args; rc=\$?; rm -f '$block_path'; exit \$rc"
  rc=$?
  set -e
  if [[ "$rc" -eq 255 ]]; then
    rc=10
    # the connection died: best effort not to leave a 0600 file holding a hash in /tmp
    [[ -n "$block_path" ]] && { rssh "rm -f '$block_path'" >/dev/null 2>&1 || true; }
  fi
  return "$rc"
}

# --- health checks -----------------------------------------------------------------------
http_code() { curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${CURL_OPTS[@]}" "$1" 2>/dev/null || true; }

live_ok() {   # every live site must answer 200; up to 3 tries each, 2s apart
  local url code i
  for url in "${LIVE_URLS[@]}"; do
    for i in 1 2 3; do
      code="$(http_code "$url")"
      [[ "$code" == "200" ]] && break
      sleep 2
    done
    if [[ "$code" != "200" ]]; then err "  live site $url answered '$code', expected 200"; return 1; fi
  done
  return 0
}

site_url() { if [[ "$CHECK_PORT" == "443" ]]; then printf 'https://%s/' "$1"; else printf 'https://%s:%s/' "$1" "$CHECK_PORT"; fi; }

new_hosts_ok() {   # poll: the first HTTP-01 certificate can take a few seconds to issue
  local deadline=$((SECONDS + CHECK_TIMEOUT)) a="" f=""
  while (( SECONDS < deadline )); do
    a="$(http_code "$(site_url "$AUTOMATION_HOST")")"
    f="$(http_code "$(site_url "$FLEET_HOST")")"
    [[ "$a" == "$EXPECT_AUTOMATION" && "$f" == "$EXPECT_FLEET" ]] && return 0
    sleep 4
  done
  err "  $AUTOMATION_HOST answered '$a' (want $EXPECT_AUTOMATION), $FLEET_HOST answered '$f' (want $EXPECT_FLEET) — gave up after ${CHECK_TIMEOUT}s"
  return 1
}

# --- 1. never start against an already-unhealthy box ---------------------------------------
log "Checking the live sites before changing anything ..."
if ! live_ok; then
  err "Refusing to touch the shared Caddy while a live site is unhealthy. Nothing changed."
  exit 11
fi

# --- 2. stage the apply script, snapshot the current blocks ----------------------------------
REMOTE_APPLY="$(rssh 'f=$(mktemp /tmp/qubix-apply-caddy-block.XXXXXX) && cat > "$f" && printf %s "$f"' < "$APPLY_SCRIPT")" || {
  err "Error: failed to stage apply-caddy-block.sh on $HOST (SSH/transfer failure). Nothing changed."
  REMOTE_APPLY=""
  exit 10
}

declare -A PREV_EXISTS PREV_CONTENT
for slug in "${SLUGS[@]}"; do
  set +e
  rssh "[ -f '$CLIENTS_DIR/$slug.caddy' ]"
  rc=$?
  set -e
  if [[ "$rc" -eq 0 ]]; then
    PREV_EXISTS[$slug]=1
    # sentinel keeps trailing newlines byte-exact through $(...)
    # the sentinel is appended on the remote side, after cat, so cat's own failure is not masked
    if ! prev="$(rssh "cat '$CLIENTS_DIR/$slug.caddy' && printf x")"; then
      err "Error: could not read the existing $slug.caddy on $HOST. Nothing changed."
      exit 10
    fi
    PREV_CONTENT[$slug]="${prev%x}"
  elif [[ "$rc" -eq 1 ]]; then
    PREV_EXISTS[$slug]=0
  else
    err "Error: could not inspect $CLIENTS_DIR/$slug.caddy on $HOST (ssh exit $rc). Nothing changed."
    exit 10
  fi
done

# --- rollback: restore what was there, never blindly remove --------------------------------------
CHANGED=()
rollback() {
  local i slug failed=0
  err "Rolling back ..."
  for (( i=${#CHANGED[@]}-1; i>=0; i-- )); do
    slug="${CHANGED[$i]}"
    if [[ "${PREV_EXISTS[$slug]}" == "1" ]]; then
      err "  restoring the previous $slug.caddy"
      apply_remote "$slug" register "${PREV_CONTENT[$slug]}" || failed=1
    else
      err "  removing $slug.caddy (it did not exist before this run)"
      apply_remote "$slug" remove || failed=1
    fi
  done
  if [[ "$failed" -ne 0 ]]; then
    err "ROLLBACK FAILED for at least one block. Verify the RUNNING Caddy config and both live sites by hand NOW."
    exit 14
  fi
  if ! live_ok; then
    err "Rolled back, but a live site is still unhealthy. Investigate immediately."
    exit 14
  fi
  err "Rolled back; live sites healthy."
}

# --- 3. apply -----------------------------------------------------------------------------------------
for slug in "${SLUGS[@]}"; do
  if [[ "$MODE" == "register" ]]; then
    log "Applying $slug.caddy ..."
    set +e; apply_remote "$slug" register "${NEW_BLOCK[$slug]}"; rc=$?; set -e
  else
    log "Removing $slug.caddy ..."
    set +e; apply_remote "$slug" remove; rc=$?; set -e
  fi
  if [[ "$rc" -ne 0 ]]; then
    err "Error: $slug.caddy failed (exit $rc)."
    # exit 2 = apply-caddy-block.sh already rolled THIS file back; exit 10 = the file on
    # disk is the new one but the reload failed, so it must be reverted too.
    [[ "$rc" -eq 10 ]] && CHANGED+=("$slug")
    rollback
    exit "$rc"
  fi
  CHANGED+=("$slug")
done

# --- 4. verify ---------------------------------------------------------------------------------------------
log "Re-checking the live sites ..."
if ! live_ok; then
  err "A live site is unhealthy after the change."
  rollback
  exit 11
fi

if [[ "$MODE" == "register" ]]; then
  log "Verifying $AUTOMATION_HOST (expect $EXPECT_AUTOMATION) and $FLEET_HOST (expect $EXPECT_FLEET) over TLS ..."
  if ! new_hosts_ok; then
    rollback
    exit 12
  fi
fi

log "OK: $MODE complete for ${SLUGS[*]} on $HOST — live sites healthy."
