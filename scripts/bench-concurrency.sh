#!/usr/bin/env bash
# Benchmarks per-store request concurrency: one request vs. 6 parallel requests
# against the same URL. Used to measure the Phase 0 fix for the single
# `php -S` process serializing every request for a store.
#
# Usage: bash scripts/bench-concurrency.sh <url>

set -euo pipefail

URL="${1:?Usage: bash scripts/bench-concurrency.sh <url>}"

single=$(curl -s -o /dev/null -w '%{time_total}' "$URL")
echo "1 request:  ${single}s"

start=$(date +%s.%N)
for _ in 1 2 3 4 5 6; do
  curl -s -o /dev/null "$URL" &
done
wait
end=$(date +%s.%N)

parallel=$(awk -v a="$start" -v b="$end" 'BEGIN { printf "%.3f", b - a }')
echo "6 parallel: ${parallel}s"

awk -v single="$single" -v parallel="$parallel" 'BEGIN {
  ratio = parallel / single
  printf "ratio: %.2fx\n", ratio
}'
