#!/usr/bin/env bash
# Run as root after pulling the latest main branch on web01.
set -euo pipefail
app=/var/www/mammamiacucina-app/website
cd "$app"
test "$(id -u)" = 0 || { echo 'Run this script as root.'; exit 1; }
test -f .env
test -f public/admin-assets/smartadmin/css/app.bundle.css
command -v php8.4 >/dev/null
backup="/var/backups/mmc-update-$(date +%Y%m%d-%H%M%S)"
install -d -m 700 "$backup"
cp .env "$backup/env"
cp /etc/nginx/sites-available/mammamiacucina.ca "$backup/nginx"
git -c safe.directory=/var/www/mammamiacucina-app rev-parse HEAD > "$backup/release-commit"
echo "Backup directory: $backup"
sudo -H -u mmcdeploy php8.4 artisan down --retry=60
trap 'echo "Deployment stopped. Backup: $backup. Site remains in maintenance mode; resolve the error before running php8.4 artisan up as mmcdeploy."' ERR
# Back up the actual configured database (SQLite or MySQL) before migrations.
MMC_BACKUP_DIR="$backup" php8.4 scripts/backup-database.php
tar -czf "$backup/storage.tar.gz" storage
sudo -H -u mmcdeploy composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
sudo -H -u mmcdeploy php8.4 artisan config:clear
sudo -H -u mmcdeploy php8.4 artisan route:clear
sudo -H -u mmcdeploy php8.4 artisan migrate --force
sudo -H -u mmcdeploy php8.4 artisan app:initialize-numbering
sudo -H -u mmcdeploy php8.4 artisan db:seed --class=DeliveryRateSeeder --force
sudo -H -u mmcdeploy php8.4 artisan config:cache
sudo -H -u mmcdeploy php8.4 artisan view:cache
systemctl reload php8.4-fpm
sudo -H -u mmcdeploy php8.4 artisan up
trap - ERR
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/product-grid
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/admin/login
printf 'Deployment complete. Backup: %s\n' "$backup"
