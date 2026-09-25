#!/usr/bin/env bash
# Every script with a shebang under scripts/ and docker/n8n/ must be mode 100755 IN GIT.
#
# Why: this repo has core.fileMode=false, so `chmod +x` never reaches git on its own, and a fresh
# clone (how every provisioned client's checkout, and the VPS's, is made) gets 0644 files. The
# onboarding workflow runs scripts/{provision-client,generate-caddy-block,apply-caddy-block}.sh
# by path; without the bit they fail with "Permission denied" (exit 126). It already bit once
# (56f19b0272, generate-client-compose.sh). Fix a failure with:
#   git update-index --chmod=+x <file>
#
# Usage: scripts/test-exec-bits.sh      (exit 0 = all executable in git)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
FAILS=0; N=0
while IFS= read -r f; do
  [[ -f "$f" ]] || continue
  head -c2 "$f" | grep -q '^#!' || continue
  N=$((N+1)); mode="$(git ls-tree HEAD -- "$f" | cut -c1-6)"
  if [[ "$mode" == 100755 ]]; then printf 'PASS %s\n' "$f"
  else printf 'FAIL %s is %s in git, must be 100755\n' "$f" "${mode:-untracked}"; FAILS=$((FAILS+1)); fi
done < <(git ls-files -- 'scripts/*' 'docker/n8n/*' | sort)
echo "----"; [[ $N -gt 0 ]] || { echo "no shebang scripts found — broken?"; exit 1; }
[[ $FAILS == 0 ]] && echo "all $N scripts executable in git" || echo "$FAILS not executable in git"
[[ $FAILS == 0 ]]
