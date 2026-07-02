#!/usr/bin/env bash
#
# Nightly database + storage backup. Installed via cron by
# deploy/cron/invoicing-app.cron, or run manually:
#
#   deploy/scripts/backup.sh
#
# Writes two timestamped files per run into BACKUP_DIR: a compressed
# mysqldump and a tarball of storage/app (uploaded logos, generated
# receipt PDFs — everything that isn't reproducible from the database
# alone). Prunes backups older than RETENTION_DAYS.
#
# This writes backups to local disk only. That protects against database
# corruption or a bad migration, but not against losing the whole server —
# for real disaster recovery, sync BACKUP_DIR off-box (e.g. `rclone sync`
# or `aws s3 sync` in a line added below, or point BACKUP_DIR itself at a
# mounted network volume). Left out of this script on purpose: the right
# destination depends entirely on what storage the deploy already has
# available, and getting credentials for it wired up safely isn't
# something to guess at generically.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/invoicing-app}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/invoicing-app}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"

# Reads DB_* from the app's own .env — no separate credentials file to
# keep in sync.
# shellcheck disable=SC1091
set -a
source "$APP_DIR/.env"
set +a

mkdir -p "$BACKUP_DIR"

echo "==> Dumping database ($DB_DATABASE)"
# Password via MYSQL_PWD rather than --password= on the command line —
# the latter is visible to any other user on the box via `ps aux` for as
# long as the dump runs.
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --user="$DB_USERNAME" \
    --host="${DB_HOST:-127.0.0.1}" \
    --single-transaction \
    --quick \
    "$DB_DATABASE" | gzip > "$BACKUP_DIR/db-$TIMESTAMP.sql.gz"

echo "==> Archiving storage/app"
tar -czf "$BACKUP_DIR/storage-$TIMESTAMP.tar.gz" -C "$APP_DIR" storage/app

echo "==> Pruning backups older than $RETENTION_DAYS days"
find "$BACKUP_DIR" -type f -name '*.gz' -mtime "+$RETENTION_DAYS" -delete

echo "==> Backup complete: $BACKUP_DIR/{db,storage}-$TIMESTAMP.*.gz"
