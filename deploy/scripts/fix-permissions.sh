#!/usr/bin/env bash
#
# Fixes ownership/permissions on the directories Laravel needs to write to
# at runtime (logs, cached views, sessions/cache if file-backed, uploaded
# logos, generated receipt PDFs). Safe to re-run any time; typically needed
# once after a fresh clone (git doesn't preserve the permissions this app
# needs) and occasionally after deploys run under a different user.
#
#   sudo deploy/scripts/fix-permissions.sh

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/invoicing-app}"
WEB_USER="${WEB_USER:-www-data}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run as root (sudo deploy/scripts/fix-permissions.sh)." >&2
    exit 1
fi

echo "==> Setting ownership to $WEB_USER"
chown -R "$WEB_USER:$WEB_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "==> Setting directory/file permissions"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \;
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;

echo "==> Done"
