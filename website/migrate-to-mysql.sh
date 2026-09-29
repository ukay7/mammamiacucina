#!/usr/bin/env bash
# Run as root, after updating main. Only changes this application's database.
set -euo pipefail
app=/var/www/mammamiacucina-app/website
cd "$app"
test "$(id -u)" = 0 || { echo 'Run as root.'; exit 1; }
php8.4 -r 'exit(extension_loaded("pdo_mysql") && class_exists("SQLite3") ? 0 : 1);' || {
  echo 'Install PHP database drivers first: apt-get install php8.4-mysql php8.4-sqlite3'; exit 1;
}
command -v mysqldump >/dev/null || { echo 'Install the MySQL client (mysqldump) before switching.'; exit 1; }
trap 'echo "Stopped. Read the error above. Do not delete databases or retry blindly. If maintenance mode is active, keep it active until the problem is resolved."' ERR
# This creates an SQLite backup and applies any pending schema fixes before transfer.
bash deploy-update.sh
# Prompts privately for the MySQL administrator password; the app password is generated.
php8.4 artisan database:move-to-mysql --create "$@"
sudo -H -u mmcdeploy php8.4 artisan config:cache
sudo -H -u mmcdeploy php8.4 artisan view:cache
sudo -H -u mmcdeploy php8.4 <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDriverName() !== 'mysql') { throw new RuntimeException('Cutover check did not resolve to MySQL.'); }
echo 'Confirmed database: '.$db->getDatabaseName().' (MySQL)'.PHP_EOL;
foreach (['users', 'customers', 'products', 'orders', 'delivery_rates'] as $table) {
    echo $table.': '.$db->table($table)->count().' rows'.PHP_EOL;
}
PHP
systemctl reload php8.4-fpm
sudo -H -u mmcdeploy php8.4 artisan up
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/product-grid
curl --fail --silent --show-error --output /dev/null https://mammamiacucina.ca/admin/login
trap - ERR
echo 'MySQL migration complete. Resume this app’s paused workers using fresh processes/configuration.'
