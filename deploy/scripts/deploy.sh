#!/usr/bin/env bash
#
# Repeatable deploy for the Invoicing App. Run on the server, from the app
# directory (or set APP_DIR below):
#
#   deploy/scripts/deploy.sh                # deploy the latest commit on the current branch
#   deploy/scripts/deploy.sh v1.4.0          # deploy a specific tag/branch/commit
#
# The same script doubles as the rollback mechanism — pass the previous
# release's tag or commit SHA and it deploys that instead. There's no
# separate rollback tooling; see docs/DEPLOYMENT.md "Rollback" for why
# that's deliberate, not an oversight.
#
# Idempotent-ish: safe to re-run after a failed step, though composer/npm
# steps will simply repeat work that already succeeded.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/invoicing-app}"
REF="${1:-}"

cd "$APP_DIR"

echo "==> Fetching"
git fetch --all --tags

if [ -n "$REF" ]; then
    echo "==> Checking out $REF"
    git checkout "$REF"
else
    echo "==> Pulling latest on $(git rev-parse --abbrev-ref HEAD)"
    git pull
fi

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader

echo "==> Installing and building frontend assets"
npm ci
npm run build

echo "==> Running migrations"
# --force is required because artisan refuses to run migrations in
# APP_ENV=production without it — a deliberate guardrail, not a bug. If
# this deploy is a rollback past a migration that isn't safely reversible,
# stop here and handle the database manually first — see
# docs/DEPLOYMENT.md "Rollback".
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Restarting the queue worker"
# Workers hold old code in memory until told to restart — this is the one
# step it's easy to forget when deploying by hand, which is exactly why
# it's baked into this script rather than left as a manual note.
php artisan queue:restart

echo "==> Deploy complete: $(git rev-parse --short HEAD)"
