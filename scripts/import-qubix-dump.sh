#!/usr/bin/env bash
# Import a mysqldump into this machine's local Sail MySQL container.
# Usage (from repo root):  ./scripts/import-qubix-dump.sh [path/to/qubix-dump.sql]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DUMP="${1:-$ROOT/qubix-dump.sql}"
if [[ ! -f "$DUMP" ]]; then
  echo "Dump not found: $DUMP"
  echo "Copy your dump to the repo root as qubix-dump.sql, or pass the path:"
  echo "  $0 /path/to/qubix-dump.sql"
  exit 1
fi

# Read DB_* from .env (avoid sourcing .env — values can break bash)
ENVF="$ROOT/.env"
getenv() { grep -E "^${1}=" "$ENVF" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//;s/"$//'; }
DB_USERNAME="$(getenv DB_USERNAME)"
DB_PASSWORD="$(getenv DB_PASSWORD)"
DB_DATABASE="$(getenv DB_DATABASE)"

cd "$ROOT"

echo "Importing into database ${DB_DATABASE} (this replaces existing data)..."
docker compose exec -T mysql mysql "-u${DB_USERNAME}" "-p${DB_PASSWORD}" "${DB_DATABASE}" < "$DUMP"

echo "Clearing Laravel caches..."
docker compose exec -T laravel.test php artisan optimize:clear 2>/dev/null || true

echo "Done. Open your storefront again (hard refresh: Ctrl+Shift+R)."
