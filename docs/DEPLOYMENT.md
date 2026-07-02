# Deployment

Referenced from `README.md` and the in-app Help page (`/help#deployment`) — this is the full version; those are pointers/summaries back to here.

This is a single-tenant Laravel app (one Square account, one business) meant to run on a single VPS. The steps below target a plain Ubuntu 22.04/24.04 box (a DigitalOcean droplet or equivalent) with Nginx + PHP-FPM + MySQL + Supervisor + cron — the most common, cheapest shape for an app at this scale. Every config file referenced here is a ready-to-copy artifact in [`deploy/`](../deploy/README.md); this document is the order to use them in and why.

---

## 1. Server requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `bcmath`, `fileinfo`, `openssl`, `ctype`, `xml` extensions (`gd`/`imagick` not required — PDF rendering is pure-PHP via `barryvdh/laravel-dompdf`)
- MySQL 8.x (or a compatible MariaDB)
- Composer 2.x
- Node.js + npm (build step only — not needed at runtime once assets are built)
- Nginx (or any web server that can proxy to PHP-FPM — Nginx is what the provided config targets)
- Supervisor (or systemd — the provided config targets Supervisor) to keep the queue worker alive
- HTTPS — required in practice, not just recommended: Square webhooks, the customer portal, and Square's hosted checkout all expect a real `https://` `APP_URL`

## 2. Provision a fresh server

```bash
git clone <repo> /var/www/invoicing-app
cd /var/www/invoicing-app
sudo bash deploy/scripts/provision.sh
```

Installs Nginx, PHP-FPM 8.3 with every required extension, MySQL, Node.js, Composer, Supervisor, and Certbot on a bare Ubuntu box — see [`deploy/scripts/provision.sh`](../deploy/scripts/provision.sh) for exactly what it does (it's a short, readable script, not a black box). It's idempotent — safe to re-run. Skip this step entirely on a host that already has these installed (a managed PHP host, an existing shared box, etc.).

## 3. MySQL setup

```bash
sudo mysql_secure_installation   # interactive — set a root password, remove test defaults
```

Then create the app's database and a scoped user (never reuse the MySQL root account for the app):

```bash
# Edit deploy/mysql/setup.sql first — replace CHANGE_ME with a real generated password.
sudo mysql -u root -p < deploy/mysql/setup.sql
```

Put that same password in `.env` as `DB_PASSWORD` in the next step. The generated user is scoped to `localhost` and only the `invoicing_app` database — see [`deploy/mysql/setup.sql`](../deploy/mysql/setup.sql).

For a small single-app VPS, MySQL's stock `my.cnf` defaults are usually fine; the one setting worth checking on a 1–2GB RAM box is `innodb_buffer_pool_size` (default is often too high for a small droplet) — `sudo mysql -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"` and lower it in `/etc/mysql/mysql.conf.d/mysqld.cnf` if MySQL is fighting PHP-FPM for memory.

## 4. First deploy (application code)

```bash
cd /var/www/invoicing-app   # if not already there from step 2

composer install --no-dev --optimize-autoloader
npm ci
npm run build

cp .env.example .env
php artisan key:generate
```

Edit `.env` for production — see **README.md → Environment Variables Reference** for the full table and `.env.example` for inline docs on every value. At minimum for a working deploy:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.tld`
- `DB_DATABASE=invoicing_app`, `DB_USERNAME=invoicing_app`, `DB_PASSWORD=` (from step 3)
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` (all already the defaults; no infra beyond MySQL is required to run this app)
- `MAIL_*` — a real transactional provider (SMTP/Mailgun/SES/SendGrid); leaving `MAIL_MAILER=log` in production silently writes customer emails to a log file instead of sending them
- `SQUARE_*` — production access token/location ID, `SQUARE_ENV=production`, and `SQUARE_WEBHOOK_SIGNATURE_KEY` (see step 11 — the webhook must be registered first to get this value)
- `TWILIO_*` — if SMS delivery is wanted; the app runs fine without it, SMS sends just fail gracefully per customer

```bash
php artisan migrate --force
```

`--force` is required because `artisan migrate` refuses to run in `APP_ENV=production` without it — a deliberate guardrail, not a bug.

## 5. Nginx

```bash
sudo cp deploy/nginx/invoicing-app.conf /etc/nginx/sites-available/invoicing-app.conf
sudo sed -i 's/your-domain.tld/YOUR_REAL_DOMAIN/g' /etc/nginx/sites-available/invoicing-app.conf
sudo ln -s /etc/nginx/sites-available/invoicing-app.conf /etc/nginx/sites-enabled/
```

[`deploy/nginx/invoicing-app.conf`](../deploy/nginx/invoicing-app.conf) — HTTP→HTTPS redirect (except `.well-known/acme-challenge` for Certbot), hands PHP requests to the dedicated PHP-FPM pool from step 6, serves Vite-built assets (`public/build/`) with long-lived caching, denies dotfile access, and sets baseline security headers. Get the SSL certificate (step 7) before the first `nginx -t && systemctl reload nginx` — the file references certificate paths that don't exist yet.

## 6. PHP-FPM

```bash
sudo cp deploy/php-fpm/invoicing-app-pool.conf /etc/php/8.3/fpm/pool.d/invoicing-app.conf
sudo systemctl restart php8.3-fpm
```

[`deploy/php-fpm/invoicing-app-pool.conf`](../deploy/php-fpm/invoicing-app-pool.conf) is a *dedicated* pool (not the default `www` pool) so this app's memory/timeout settings never affect anything else on the box. It sets `memory_limit`/`max_execution_time` high enough for PDF generation (invoice/receipt rendering runs synchronously in-request), matches `upload_max_filesize`/`post_max_size` to the app's own 2MB upload validation limits, disables `display_errors` as defense-in-depth alongside `APP_DEBUG=false`, and enables opcache. `pm.max_children` is tuned for a small 1–2GB droplet — raise it on a bigger box.

## 7. SSL / HTTPS (Certbot)

```bash
sudo certbot --nginx -d your-domain.tld
```

Certbot obtains the certificate, writes it to `/etc/letsencrypt/live/your-domain.tld/`, and can rewrite the Nginx config's SSL directives itself if you'd rather bootstrap HTTP-only and let it add HTTPS — either way, the end state matches what [`deploy/nginx/invoicing-app.conf`](../deploy/nginx/invoicing-app.conf) already expects. Certbot installs its own renewal timer (`systemctl status certbot.timer`) — nothing further to configure; confirm it works once with:

```bash
sudo certbot renew --dry-run
```

## 8. Storage permissions

```bash
sudo deploy/scripts/fix-permissions.sh
```

[`deploy/scripts/fix-permissions.sh`](../deploy/scripts/fix-permissions.sh) sets `storage/` and `bootstrap/cache/` to be owned and writable by `www-data` — `git clone` doesn't preserve the permissions Laravel needs for logs, cached views, and (via `storage/app/public`) uploaded company logos and generated receipt PDFs. Run it once after the first clone, and again any time deploys start failing with a "permission denied" writing to `storage/logs/laravel.log`.

```bash
php artisan storage:link
```

Also required once — symlinks `public/storage` to `storage/app/public` so uploaded logos are actually reachable over HTTP.

## 9. Queue worker (Supervisor)

Every invoice/receipt email, every SMS, recurring-invoice generation, and the overdue-invoice sweep are dispatched onto the queue — **nothing in any of those happens without a worker running.**

```bash
sudo cp deploy/supervisor/invoicing-app-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start invoicing-app-worker:*
```

[`deploy/supervisor/invoicing-app-worker.conf`](../deploy/supervisor/invoicing-app-worker.conf) runs `php artisan queue:work` under Supervisor so it restarts on crash and survives reboots — never run `queue:work` directly in a terminal for production, it dies the moment the terminal closes. One worker process is plenty at this app's scale; the config file's own comments explain when to raise `numprocs`.

After every deploy that changes job/service code, restart workers so they pick up the new code (a running worker holds old code in memory otherwise) — `deploy/scripts/deploy.sh` (step 13) already does this for you via `php artisan queue:restart`.

## 10. Scheduler (cron)

Two scheduled commands depend on this running: `invoices:process-recurring` (every 5 minutes) and `invoices:mark-overdue` (daily) — see `routes/console.php`. Neither does anything without the scheduler ticking.

```bash
sudo cp deploy/cron/invoicing-app.cron /etc/cron.d/invoicing-app
sudo chmod 644 /etc/cron.d/invoicing-app
```

[`deploy/cron/invoicing-app.cron`](../deploy/cron/invoicing-app.cron) installs one line running `php artisan schedule:run` every minute — Laravel's scheduler itself decides what's actually due, so this line never needs to change even if more scheduled commands are added later — plus the nightly backup cron entry from step 12.

## 11. Square webhook registration

**Required for payments to ever mark an invoice paid.**

1. Confirm the app is reachable over HTTPS at its real `APP_URL` (steps 5–7 above) — Square needs to reach it.
2. In the Square Developer Dashboard, add a webhook subscription against your **production** application: notification URL `https://your-domain.tld/webhooks/square`, subscribed to at least the `payment.updated` event type.
3. Copy the resulting **Signature Key** into `SQUARE_WEBHOOK_SIGNATURE_KEY` in `.env`, then `php artisan config:cache` again.
4. Send a test event from the Dashboard's webhook subscription page and confirm it returns `204`. A `401` almost always means the notification URL registered in Square doesn't byte-for-byte match `APP_URL` (`http` vs `https`, a trailing slash, `www.` vs not) — the signature is computed over the exact URL string, so any mismatch fails verification.

**Until this is done, Square payments will never mark an invoice paid** — the portal return page is deliberately display-only and never mutates payment state itself (see `docs/ARCHITECTURE.md` section 11).

## 12. Backups

```bash
# Already installed by deploy/cron/invoicing-app.cron (step 10), or run manually:
deploy/scripts/backup.sh
```

[`deploy/scripts/backup.sh`](../deploy/scripts/backup.sh) writes a compressed `mysqldump` and a `storage/app` tarball (uploaded logos, generated receipt PDFs — everything not reproducible from the database alone) to `/var/backups/invoicing-app`, timestamped, pruning anything older than 14 days. This protects against database corruption or a bad migration — **it does not protect against losing the whole server**, since it writes to local disk only. For real disaster recovery, sync the backup directory off-box (the script's header comments have a pointer for where to add `rclone`/`aws s3 sync`); the right destination depends entirely on what off-box storage the deploy already has, so it's deliberately not baked in generically.

Test a restore at least once before trusting this:

```bash
gunzip -c /var/backups/invoicing-app/db-<timestamp>.sql.gz | mysql -u invoicing_app -p invoicing_app
```

## 13. Ongoing deploys

```bash
deploy/scripts/deploy.sh
```

[`deploy/scripts/deploy.sh`](../deploy/scripts/deploy.sh) is the same "git pull, composer install, build assets, migrate, rebuild caches, restart the queue worker" sequence every time — see the deployment command checklist below for exactly what it runs. Pass a tag/branch/commit to deploy something other than the current branch's latest commit:

```bash
deploy/scripts/deploy.sh v1.4.0
```

### Deployment command checklist

What `deploy.sh` runs, in order — useful as a reference if deploying by hand instead:

- [ ] `git fetch --all --tags` then `git pull` (or `git checkout <ref>`)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm ci && npm run build`
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `php artisan queue:restart` — the single easiest step to forget by hand; workers keep running fine, just on stale code, until the next server reboot makes it "fix itself" and hides the real bug

## 14. Full deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` is the real HTTPS domain, no trailing slash
- [ ] Database created, migrations run (`--force`)
- [ ] `MAIL_*` set to a real provider, not `log`
- [ ] `SQUARE_ACCESS_TOKEN` / `SQUARE_LOCATION_ID` are the **production** values, `SQUARE_ENV=production`
- [ ] Square webhook registered against the live `APP_URL`, `SQUARE_WEBHOOK_SIGNATURE_KEY` set and config cached
- [ ] `TWILIO_*` set, if SMS delivery is wanted
- [ ] Nginx config installed, SSL certificate obtained and auto-renewal confirmed (`certbot renew --dry-run`)
- [ ] PHP-FPM pool installed and running (`systemctl status php8.3-fpm`)
- [ ] Queue worker running under Supervisor, `autostart`/`autorestart` on (`supervisorctl status`)
- [ ] Cron entry installed for `schedule:run` (`cat /etc/cron.d/invoicing-app`)
- [ ] `storage/` and `bootstrap/cache/` writable by the web server user, `php artisan storage:link` run
- [ ] Nightly backup cron entry installed and confirmed to actually produce a file after the first run
- [ ] A real end-to-end smoke test performed: create an invoice, send it, open the portal link, pay with a Square sandbox/live card, confirm the webhook fires and the invoice shows paid with a receipt emailed
- [ ] Full manual QA pass: [`docs/QA_CHECKLIST.md`](QA_CHECKLIST.md)

## 15. Rollback

There's no separate rollback tool — [`deploy/scripts/deploy.sh`](../deploy/scripts/deploy.sh) *is* the rollback mechanism, pointed at the previous release's tag or commit:

```bash
deploy/scripts/deploy.sh <previous-tag-or-sha>
```

This checks out that ref and re-runs the exact same install/migrate/cache/restart sequence as a forward deploy. Because migrations in this codebase are additive/forward-only by convention (no destructive column drops without a prior deprecation phase), rolling back application code without also rolling back the database is generally safe — the older code simply won't know about newer columns, which is harmless. If a specific rollback *does* need to undo a migration (rare — e.g. a migration that dropped a column the old code still reads), stop and handle that migration manually with `php artisan migrate:rollback --step=1` *before* running `deploy.sh`, rather than assuming the script's `migrate --force` will sort it out — it never rolls back, only forward.
