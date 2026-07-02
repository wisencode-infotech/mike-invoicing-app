# Deployment Artifacts

Ready-to-use config files and scripts for running the Invoicing App on a single VPS (DigitalOcean droplet or equivalent Ubuntu 22.04/24.04 box) behind Nginx + PHP-FPM + MySQL. The narrative walkthrough — what order to do things in, what each choice is for, troubleshooting — is [docs/DEPLOYMENT.md](../docs/DEPLOYMENT.md). This directory is what that guide installs.

| Path | What it is |
|---|---|
| `scripts/provision.sh` | One-time: installs Nginx, PHP-FPM 8.3 + extensions, MySQL, Node, Composer, Supervisor, Certbot on a fresh server |
| `nginx/invoicing-app.conf` | Nginx server block — HTTP→HTTPS redirect, PHP-FPM handoff, security headers, cached asset serving |
| `php-fpm/invoicing-app-pool.conf` | A dedicated PHP-FPM pool (not the default `www` pool) with this app's required extensions/limits documented |
| `mysql/setup.sql` | Creates the database and a scoped application user |
| `supervisor/invoicing-app-worker.conf` | Keeps the queue worker running — nothing sends email/SMS or generates recurring invoices without this |
| `cron/invoicing-app.cron` | The scheduler tick + nightly backup, as an `/etc/cron.d` entry |
| `scripts/deploy.sh` | Repeatable deploy (and rollback — pass a ref) |
| `scripts/backup.sh` | Nightly MySQL + `storage/app` backup with retention pruning |
| `scripts/fix-permissions.sh` | Fixes `storage/`/`bootstrap/cache` ownership after a fresh clone |

Every file has its own install instructions in its header comment — replace `your-domain.tld` and the `CHANGE_ME` database password before use, nothing here is meant to be copied in blindly without reading it once.
