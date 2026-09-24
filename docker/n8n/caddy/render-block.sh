#!/usr/bin/env bash
# Renders one Caddy block template to stdout.
#
# The bcrypt hash is read from STDIN (one line) — never from an argument (visible
# in `ps`) or an environment variable — and substituted with plain bash string
# replacement. A bcrypt hash looks like $2a$14$..., which envsubst or an unquoted
# shell expansion would mangle. It is validated against the exact bcrypt shape
# first, so a typo or a plaintext password is rejected instead of written into a
# live Caddy config.
#
# Usage:
#   printf '%s\n' "$HASH" | render-block.sh --template <file> [--user <name>]
#   render-block.sh --template <file> < /dev/null        # template without a secret
#
# Exit codes: 0 rendered, 1 invalid input (nothing written to stdout).

set -euo pipefail

# bash 5.2 treats `&` in a replacement string as "the matched text"; turn that off.
shopt -u patsub_replacement 2>/dev/null || true

err() { printf '%s\n' "$*" >&2; }

TEMPLATE=""
USER_NAME="owner"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --template)   [[ $# -ge 2 ]] || { err "Error: --template requires a value"; exit 1; }; TEMPLATE="$2"; shift 2 ;;
    --template=*) TEMPLATE="${1#--template=}"; shift ;;
    --user)       [[ $# -ge 2 ]] || { err "Error: --user requires a value"; exit 1; }; USER_NAME="$2"; shift 2 ;;
    --user=*)     USER_NAME="${1#--user=}"; shift ;;
    *) err "Error: unknown argument: $1"; exit 1 ;;
  esac
done

if [[ -z "$TEMPLATE" || ! -f "$TEMPLATE" ]]; then
  err "Error: --template must name an existing file"
  exit 1
fi

# $(...) drops trailing newlines; a sentinel keeps the file byte-for-byte.
tpl="$(cat "$TEMPLATE"; printf x)"
tpl="${tpl%x}"

if [[ "$tpl" == *'{{BASIC_AUTH_HASH}}'* || "$tpl" == *'{{BASIC_AUTH_USER}}'* ]]; then
  if ! [[ "$USER_NAME" =~ ^[a-z][a-z0-9_-]{1,31}$ ]]; then
    err "Error: --user must match ^[a-z][a-z0-9_-]{1,31}\$"
    exit 1
  fi

  hash=""
  IFS= read -r hash || true
  hash="${hash%$'\r'}"
  extra=""
  if IFS= read -r extra; then
    err "Error: expected exactly one line (the bcrypt hash) on stdin"
    exit 1
  fi
  if ! [[ "$hash" =~ ^\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}$ ]]; then
    err "Error: stdin is not a bcrypt hash (expected \$2a\$14\$ + 53 characters, as printed by 'caddy hash-password')."
    exit 1
  fi

  tpl="${tpl//'{{BASIC_AUTH_USER}}'/$USER_NAME}"
  tpl="${tpl//'{{BASIC_AUTH_HASH}}'/$hash}"
fi

if [[ "$tpl" == *'{{'* ]]; then
  err "Error: template still contains an unresolved {{placeholder}} after rendering"
  exit 1
fi

printf '%s' "$tpl"
