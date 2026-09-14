#!/usr/bin/env bash
# Nightly logical backup of the Saudi storefront's database.
# Run from /opt/qubix-sa on the VPS (installed via cron, see below).
#
# Per-database mysqldump only — NEVER a volume snapshot. A volume restore
# rolls back whichever database shares the volume; a logical dump restores
# independently. This was the deciding constraint in the deployment design
# (docs/superpowers/specs/2026-08-31-saudi-deployment-design.md).
#
# Install with:
#   crontab -e
#   0 3 * * * /opt/qubix-sa/scripts/backup-saudi-db.sh >> /var/log/qubix-sa-backup.log 2>&1
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENVF="$ROOT/.env"
getenv() { grep -E "^${1}=" "$ENVF" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^"//;s/"$//'; }
DB_PASSWORD="$(getenv DB_PASSWORD)"

OUT_DIR=/backups
DATE="$(date +%F)"
OUT="$OUT_DIR/qubix_sa-$DATE.sql"

mkdir -p "$OUT_DIR"
docker compose -p qubix-sa -f docker-compose.sa.yml exec -T mysql \
  mysqldump -uroot -p"$DB_PASSWORD" --single-transaction qubix_sa > "$OUT"
gzip -f "$OUT"

# Keep 14 days of backups.
find "$OUT_DIR" -maxdepth 1 -name 'qubix_sa-*.sql.gz' -mtime +14 -delete

echo "$(date -u +%FT%TZ) backed up qubix_sa to $OUT.gz"
