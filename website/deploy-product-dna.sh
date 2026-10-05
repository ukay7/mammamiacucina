#!/usr/bin/env bash
# Run as root after deploying the latest code. Does not insert any products.
set -euo pipefail
cd /var/www/mammamiacucina-app/website
test "$(id -u)" = 0 || { echo 'Run as root.'; exit 1; }
backup="/var/backups/mmc-product-dna-$(date +%Y%m%d-%H%M%S)"
install -d -m 700 "$backup"
sudo -H -u mmcdeploy php8.4 artisan down --retry=60
trap 'echo "Product update stopped. Database backup: $backup. Keep maintenance mode enabled and review the error."' ERR
MMC_BACKUP_DIR="$backup" php8.4 scripts/backup-database.php
sudo -H -u mmcdeploy php8.4 artisan db:seed --class=ProductDnaOctober2026Seeder --force
sudo -H -u mmcdeploy php8.4 artisan up
trap - ERR
printf 'Product update finished. Full database backup: %s\n' "$backup"
