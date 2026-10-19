#!/usr/bin/env bash
set -Eeuo pipefail
test "$(id -u)" = 0 || { echo 'Run as root.'; exit 1; }
cd /var/www/mammamiacucina-app/website
sudo -H -u mmcdeploy php8.4 artisan down --retry=60
trap 'echo "Deployment stopped. Fix the error before running artisan up."' ERR
sudo -H -u mmcdeploy php8.4 artisan config:clear
sudo -H -u mmcdeploy php8.4 artisan route:clear
sudo -H -u mmcdeploy php8.4 artisan staff:upgrade-permissions --apply
sudo -H -u mmcdeploy php8.4 artisan config:cache
sudo -H -u mmcdeploy php8.4 artisan view:cache
sudo -H -u mmcdeploy php8.4 artisan queue:restart
systemctl reload php8.4-fpm
sudo -H -u mmcdeploy php8.4 artisan up
trap - ERR
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/admin/login
echo 'Grouped staff permissions are live.'
