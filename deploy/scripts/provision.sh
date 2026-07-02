#!/usr/bin/env bash
#
# One-time provisioning for a fresh Ubuntu 22.04/24.04 VPS (DigitalOcean
# droplet or equivalent). Installs Nginx, PHP-FPM 8.3 + required
# extensions, MySQL, Node.js, Composer, Supervisor, and Certbot — nothing
# app-specific. Run once per server, as root or via sudo:
#
#   sudo bash deploy/scripts/provision.sh
#
# After this, follow docs/DEPLOYMENT.md from "First deploy" onward: clone
# the repo, configure .env, install the Nginx/PHP-FPM/Supervisor/cron
# configs from this deploy/ directory, and run deploy/scripts/deploy.sh.
#
# Safe to re-run — every step here is an idempotent `apt-get install` or
# an installer that no-ops if already present.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Run as root (sudo bash deploy/scripts/provision.sh)." >&2
    exit 1
fi

echo "==> Updating package index"
apt-get update -y

echo "==> Installing Nginx"
apt-get install -y nginx

echo "==> Installing PHP 8.3-FPM and required extensions"
# Ubuntu 24.04 ships PHP 8.3 in the default repos; on 22.04 add
# ppa:ondrej/php first (uncomment the next two lines) if php8.3-fpm isn't
# found.
#   add-apt-repository -y ppa:ondrej/php
#   apt-get update -y
apt-get install -y \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-bcmath \
    php8.3-xml php8.3-curl php8.3-zip php8.3-opcache

echo "==> Installing MySQL"
apt-get install -y mysql-server
# Interactive — walks through setting a root password and tightening
# defaults. Run manually once, it can't be safely scripted unattended:
#   sudo mysql_secure_installation

echo "==> Installing Composer"
if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Installing Node.js (LTS)"
if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
    apt-get install -y nodejs
fi

echo "==> Installing Supervisor (queue worker process manager)"
apt-get install -y supervisor

echo "==> Installing Certbot (Let's Encrypt SSL)"
apt-get install -y certbot python3-certbot-nginx

echo "==> Creating the application directory"
mkdir -p /var/www/invoicing-app
chown www-data:www-data /var/www/invoicing-app

cat <<'EOF'

Provisioning complete. Next steps (see docs/DEPLOYMENT.md):
  1. sudo mysql_secure_installation
  2. Run deploy/mysql/setup.sql to create the database and app user
  3. git clone the repo into /var/www/invoicing-app
  4. Install deploy/nginx/invoicing-app.conf, deploy/php-fpm/invoicing-app-pool.conf,
     deploy/supervisor/invoicing-app-worker.conf, and deploy/cron/invoicing-app.cron
  5. Obtain an SSL certificate: sudo certbot --nginx -d your-domain.tld
  6. Configure .env, then run deploy/scripts/deploy.sh
EOF
