#!/usr/bin/env bash
# Customer accounts and A4 documents release. Intentionally no database backup.
set -euo pipefail
cd /var/www/mammamiacucina-app/website
test "$(id -u)" = 0 || { echo 'Run as root.'; exit 1; }
test -f .env
php8.4 -r 'if (!extension_loaded("gd")) { fwrite(STDERR, "PHP 8.4 GD is required. Install php8.4-gd before deploying.\n"); exit(1); }'
sudo -H -u mmcdeploy php8.4 artisan down --retry=60
trap 'echo "Deployment stopped; site remains in maintenance mode. Fix the reported error and rerun this script."' ERR
sudo -H -u mmcdeploy composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
sudo -H -u mmcdeploy php8.4 artisan config:clear
sudo -H -u mmcdeploy php8.4 artisan route:clear
sudo -H -u mmcdeploy php8.4 artisan migrate --force
sudo -H -u mmcdeploy php8.4 artisan invoices:backfill
sudo -H -u mmcdeploy php8.4 artisan config:cache
sudo -H -u mmcdeploy php8.4 artisan view:cache
sudo -H -u mmcdeploy php8.4 artisan queue:restart
systemctl reload php8.4-fpm
sudo -H -u mmcdeploy php8.4 artisan up
trap - ERR
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/product-grid
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/admin/login
echo 'Customer accounts and A4 document release deployed. No database backup was performed.'
