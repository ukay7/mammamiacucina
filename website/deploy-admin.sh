#!/usr/bin/env bash
# Run as root on the existing web01 installation, after git pull.
set -euo pipefail
app=/var/www/mammamiacucina-app/website
cd "$app"
test -f .env
if ! grep -Eq 'root[[:space:]]+/var/www/mammamiacucina-app/website/public/?[[:space:]]*;' /etc/nginx/sites-available/mammamiacucina.ca; then
    echo 'Nginx must point this domain to /var/www/mammamiacucina-app/website/public. Stop and check the site configuration before deploying.'
    exit 1
fi
test -f public/admin-assets/smartadmin/css/app.bundle.css || { echo 'Copy the private SmartAdmin assets first.'; exit 1; }
# Install the database driver missing from the original static-site deployment.
apt-get update
apt-get install -y php8.4-sqlite3 php8.4-zip
backup="/var/backups/mmc-admin-$(date +%Y%m%d-%H%M%S)"
install -d -m 700 "$backup"
cp .env "$backup/env"
cp /etc/nginx/sites-available/mammamiacucina.ca "$backup/nginx"
sudo -H -u mmcdeploy php artisan down --retry=60
# Preserve application data and uploads before migrating.
tar -czf "$backup/data.tar.gz" database storage
sudo -H -u mmcdeploy composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
sudo -H -u mmcdeploy php artisan config:clear
# Bootstrap SQLite only when this deployment is configured to use SQLite.
# Keep every existing key, password and database setting.
sudo -H -u mmcdeploy php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config("database.default")!=="sqlite"){fwrite(STDERR,"This first-deploy script expects the original SQLite configuration. Stop and check the configured database.\n");exit(1);}
$path=config("database.connections.sqlite.database");
if(!$path || $path===":memory:" || !str_starts_with($path,"/")){fwrite(STDERR,"Set DB_DATABASE to an absolute persistent SQLite path first.\n");exit(1);}
if(!is_file($path) && !touch($path)){exit(1);}
echo "Database ready at ".$path.PHP_EOL;
'
# Set the shared writable directories, not the whole source tree.
chown -R mmcdeploy:www-data storage bootstrap/cache database
find storage bootstrap/cache database -type d -exec chmod 2770 {} +
find storage bootstrap/cache database -type f -exec chmod 660 {} +
sudo -H -u mmcdeploy php artisan migrate --force
sudo -H -u mmcdeploy php artisan config:cache
sudo -H -u mmcdeploy php artisan view:cache
# Configure uploads for this application only.
cat > public/.user.ini <<'INI'
upload_max_filesize=50M
post_max_size=512M
max_file_uploads=20
INI
chown mmcdeploy:www-data public/.user.ini
chmod 644 public/.user.ini
python3 - <<'PY'
from pathlib import Path
import re
p=Path('/etc/nginx/sites-available/mammamiacucina.ca')
s=p.read_text()
if re.search(r'^\s*client_max_body_size\s+',s,re.M):
    s=re.sub(r'(^\s*client_max_body_size\s+)[^;]+;',r'\g<1>512m;',s,flags=re.M)
else:
    s=re.sub(r'(^\s*server_name\s+[^;]*mammamiacucina\.ca[^;]*;)',r'\1\n    client_max_body_size 512m;',s,flags=re.M)
p.write_text(s)
PY
nginx -t
systemctl reload nginx
systemctl reload php8.4-fpm
sudo -H -u mmcdeploy php artisan up
curl --fail --silent --output /dev/null https://mammamiacucina.ca/admin/login
printf 'Admin deployed. Backup: %s\nNext: sudo -H -u mmcdeploy php artisan admin:create\n' "$backup"
