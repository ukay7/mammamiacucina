#!/usr/bin/env bash
# Run as root after pulling main. Additive notification tables; no database backup or data reset.
set -euo pipefail
app=/var/www/mammamiacucina-app/website
cd "$app"
test "$(id -u)" = 0 || { echo 'Run as root.'; exit 1; }
test -f .env
command -v php8.4 >/dev/null
command -v crontab >/dev/null
php8.4 -r 'if (!extension_loaded("gd")) { fwrite(STDERR,"PHP GD is required by the existing PDF email outbox. Install php8.4-gd first.\n"); exit(1); }'

cron_file=$(mktemp)
trap 'rm -f "$cron_file"' EXIT
if ! crontab -u mmcdeploy -l > "$cron_file" 2>/dev/null; then
    # No user crontab is normal on a first deployment.
    test ! -f /var/spool/cron/crontabs/mmcdeploy || { echo 'Could not read the existing mmcdeploy crontab.'; exit 1; }
fi
existing=$(awk '!/^[[:space:]]*#/ && /mammamiacucina-app\/website/ && /artisan schedule:run/ {n++} END {print n+0}' "$cron_file")
test "$existing" -le 1 || { echo 'Multiple MMC scheduler entries exist in the mmcdeploy crontab. Keep one before deploying.'; exit 1; }

sudo -H -u mmcdeploy php8.4 artisan down --retry=60
trap 'echo "Deployment stopped; site remains in maintenance mode. Resolve the error and rerun this script."' ERR
sudo -H -u mmcdeploy composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
sudo -H -u mmcdeploy php8.4 artisan config:clear
sudo -H -u mmcdeploy php8.4 artisan route:clear
sudo -H -u mmcdeploy php8.4 artisan migrate --path=database/migrations/2026_11_12_210000_create_notification_module.php --force
sudo -H -u mmcdeploy php8.4 artisan config:cache
sudo -H -u mmcdeploy php8.4 artisan view:cache
sudo -H -u mmcdeploy php8.4 artisan notifications:send --dry-run
sudo -H -u mmcdeploy php8.4 artisan schedule:list

if test "$existing" -eq 0; then
    printf '\n* * * * * cd /var/www/mammamiacucina-app/website && /usr/bin/php8.4 artisan schedule:run >> /var/www/mammamiacucina-app/website/storage/logs/scheduler.log 2>&1\n' >> "$cron_file"
    crontab -u mmcdeploy "$cron_file"
    echo 'Installed the Laravel scheduler in the mmcdeploy crontab.'
else
    echo 'Reusing the existing mmcdeploy Laravel scheduler.'
fi
systemctl enable --now cron
systemctl reload php8.4-fpm
sudo -H -u mmcdeploy php8.4 artisan up
trap - ERR
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/admin/login
echo 'Notifications deployed. Open Manage Email > Notifications. No database backup or reset was performed.'
