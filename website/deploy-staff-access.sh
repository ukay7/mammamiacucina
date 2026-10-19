#!/usr/bin/env bash
set -Eeuo pipefail
test "$(id -u)" = 0 || { echo 'Run as root.'; exit 1; }
test -n "${1:-}" || { echo 'Usage: bash website/deploy-staff-access.sh EXACT-INFO-ACCOUNT-EMAIL'; exit 1; }
cd /var/www/mammamiacucina-app/website
sudo -H -u mmcdeploy php8.4 artisan config:clear
# Validate the two existing accounts before making assignments.
sudo -H -u mmcdeploy php8.4 artisan staff:configure-access --super-email=admin@mammamiacucina.ca --administrator-email="$1"
sudo -H -u mmcdeploy php8.4 artisan down --retry=60
trap 'echo "Deployment stopped. Fix the error before running artisan up."' ERR
sudo -H -u mmcdeploy php8.4 artisan route:clear
sudo -H -u mmcdeploy php8.4 artisan staff:configure-access --super-email=admin@mammamiacucina.ca --administrator-email="$1" --apply
sudo -H -u mmcdeploy php8.4 artisan config:cache
sudo -H -u mmcdeploy php8.4 artisan view:cache
sudo -H -u mmcdeploy php8.4 artisan queue:restart
systemctl reload php8.4-fpm
sudo -H -u mmcdeploy php8.4 artisan up
trap - ERR
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/admin/login
echo 'Dynamic staff access is live. Sign out and sign in again.'
