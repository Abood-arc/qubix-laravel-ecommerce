#!/usr/bin/env bash
# Nightly logical backup of the India storefront's (jjbags.in) database.
# Run from /opt/qubix on the VPS (installed via cron, see below).
# Mirrors scripts/backup-saudi-db.sh.
#
# Per-database mysqldump only — NEVER a volume snapshot. A volume restore
# rolls back whichever database shares the volume; a logical dump restores
# independently.
#
# Install with:
#   crontab -e
#   30 3 * * * /opt/qubix/scripts/backup-india-db.sh >> /var/log/qubix-backup.log 2>&1
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENVF="$ROOT/.env"
getenv() { grep -E "^${1}=" "$ENVF" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//;s/"$//'; }
DB_PASSWORD="$(getenv DB_PASSWORD)"
DB_DATABASE="$(getenv DB_DATABASE)"

OUT_DIR=/backups
DATE="$(date +%F)"
OUT="$OUT_DIR/${DB_DATABASE}-$DATE.sql"

mkdir -p "$OUT_DIR"
docker compose -p qubix -f docker-compose.prod.yml exec -T mysql \
  mysqldump -uroot -p"$DB_PASSWORD" --single-transaction "$DB_DATABASE" > "$OUT"
gzip -f "$OUT"

# Keep 14 days of backups. The trailing "-2" keeps this from matching qubix_sa-* files.
find "$OUT_DIR" -maxdepth 1 -name "${DB_DATABASE}-2*.sql.gz" -mtime +14 -delete

echo "$(date -u +%FT%TZ) backed up $DB_DATABASE to $OUT.gz"
