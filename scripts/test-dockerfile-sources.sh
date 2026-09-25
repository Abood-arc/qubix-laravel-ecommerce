#!/usr/bin/env bash
# Every COPY/ADD source in the client-image Dockerfiles must be tracked by git.
#
# Why: provisioned clients are built from a fresh `git clone`, which has no gitignored paths.
# docker/8.3/Dockerfile once COPYed two files out of vendor/ (gitignored), so it built fine
# on every developer checkout and on the two legacy VPS checkouts (vendor/ installed by hand)
# but failed on the first real provision with `"/vendor/...": not found`. Fast static check;
# no Docker needed. Run from anywhere inside the repo.
#
# Usage: scripts/test-dockerfile-sources.sh      (exit 0 = all sources tracked)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
FAILS=0; N=0
for df in docker/8.3/Dockerfile docker/provisioner/Dockerfile; do
  [[ -f "$df" ]] || continue
  # Join line continuations, keep COPY/ADD lines, drop flags (--from=..., --chown=...) and the destination.
  while IFS= read -r line; do
    read -ra words <<<"$line"; args=()
    for w in "${words[@]:1}"; do [[ "$w" == --* ]] && continue; args+=("$w"); done
    [[ ${#args[@]} -ge 2 ]] || continue
    [[ "$line" == *"--from="* ]] && continue                       # copies from another build stage
    for src in "${args[@]:0:${#args[@]}-1}"; do
      [[ "$src" == http* || "$src" == *'$'* ]] && continue         # URLs / build-arg paths: not checkable here
      N=$((N+1))
      if [[ -n "$(git ls-files -- "$src" | head -1)" ]]; then printf 'PASS %s: %s\n' "$df" "$src"
      else printf 'FAIL %s: COPY source "%s" is not tracked by git (a fresh clone will not have it)\n' "$df" "$src"; FAILS=$((FAILS+1)); fi
    done
  done < <(sed -e ':a' -e '/\\$/N; s/\\\n//; ta' "$df" | grep -E '^\s*(COPY|ADD)\s')
done
echo "----"; [[ $N -gt 0 ]] || { echo "no COPY sources found — parser broken?"; exit 1; }
[[ $FAILS == 0 ]] && echo "all $N sources tracked" || echo "$FAILS untracked"
[[ $FAILS == 0 ]]
