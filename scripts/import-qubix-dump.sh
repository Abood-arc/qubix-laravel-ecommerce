#!/usr/bin/env bash
# Import a mysqldump from Windows Sail into this machine's Sail MySQL.
# Usage (from repo root):  ./scripts/import-qubix-dump.sh [path/to/qubix-dump.sql]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DUMP="${1:-$ROOT/qubix-dump.sql}"
if [[ ! -f "$DUMP" ]]; then
  echo "Dump not found: $DUMP"
  echo "Copy your Windows dump to the repo root as qubix-dump.sql, or pass the path:"
  echo "  $0 /path/to/qubix-dump.sql"
  exit 1
fi

# Read DB_* from .env (avoid sourcing .env — values can break bash)
ENVF="$ROOT/.env"
getenv() { grep -E "^${1}=" "$ENVF" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//;s/"$//'; }
DB_USERNAME="$(getenv DB_USERNAME)"
DB_PASSWORD="$(getenv DB_PASSWORD)"
DB_DATABASE="$(getenv DB_DATABASE)"

MYSQL=(docker exec -i ecommerce-mysql-1 mysql "-u${DB_USERNAME}" "-p${DB_PASSWORD}")

echo "Importing into database ${DB_DATABASE} (this replaces existing data)..."
"${MYSQL[@]}" "${DB_DATABASE}" < "$DUMP"

echo "Clearing Laravel caches..."
cd "$ROOT"
./vendor/bin/sail artisan optimize:clear 2>/dev/null || true

echo "Done. Open your storefront again (hard refresh: Ctrl+Shift+R)."
