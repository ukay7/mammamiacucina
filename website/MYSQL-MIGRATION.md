# SQLite to MySQL production cutover

The production requirement is MySQL on the existing 165.245.238.183 server. When run in that server's PuTTY session, the application connects to its local MySQL instance at 127.0.0.1 rather than sending traffic over the public IP. Other applications/databases are not migrated or modified.

The new default database is `mammamiacucina`; the dedicated application user is `mmc_app`@`localhost`. A random password is generated and saved directly into the application environment after verification. APP_KEY is retained, so existing passwords, sessions and encrypted gateway settings remain usable. Root credentials are prompted privately and never saved.

Run as root on web01, after pausing any MMC queue workers, payment reconciliation or import jobs (do not stop other sites' workers):

```bash
set +e
(
  set -e
  cd /var/www/mammamiacucina-app
  sudo -H -u mmcdeploy git fetch origin
  sudo -H -u mmcdeploy git merge --ff-only origin/main
  if ! php8.4 -m | grep -qx pdo_mysql; then
    apt-get update
    apt-get install -y php8.4-mysql
  fi
  bash website/migrate-to-mysql.sh
)
result=$?
echo "MySQL migration exit code: $result"
```

Answer yes only when this app's background writers are paused. Accept `root` at the MySQL administrator prompt. Enter the MySQL root password privately; press Enter if this server uses Unix socket authentication for root. If the socket path differs, pass `--socket=/actual/mysql.sock` to the script. A pre-existing database or user is refused rather than overwritten. Do not rerun blindly after a partial failure; inspect the output and backup first.

The script first runs the normal backup/deployment procedure against SQLite (including the payment deadline schema fix), then enters maintenance mode for the transfer. A SQLite write lock prevents new writes during backup/copy. It takes a consistent backup and copies into a newly migrated, empty MySQL database. It copies all source tables, including migration history, accounts, password/reset hashes, sessions, jobs, orders, inventory, CMS settings, gateway ciphertext and rates. It preserves deleted-ID sequence positions. It verifies every row using canonical content fingerprints, not just counts, and validates all foreign keys before updating `.env`.

Backups and the private verification report are under `storage/app/private/mysql-migration-*` with restricted permissions; the original SQLite file also remains intact. Fresh configuration and view caches are built as mmcdeploy, the MySQL connection is checked, PHP is reloaded, and the site is brought online. Resume workers as fresh processes only after success. A failure leaves the site in maintenance mode if it has already entered it. Copy failures do not change `.env`; target data is not a usable replacement until verification succeeds. MySQL DDL is not transactional: a failed destination may contain generated schema/defaults. Do not delete or reuse it without reviewing the failure.

Before reopening the site, rollback can use the exact printed migration backup's `env.before`, restored to `.env` while retaining its original owner/mode, followed by config cache rebuild and PHP reload. After accepting new MySQL orders, never switch back to the old SQLite snapshot without reconciling those newer records.

SQLyog: refresh the database list on 165.245.238.183. If your `sqlyog_manager` account lacks grants on the new database, it will not be visible to that account until its administrator grants appropriate access. The website user is intentionally restricted to localhost; do not expose it remotely just for SQLyog.

Future releases use `scripts/backup-database.php` from `deploy-update.sh`, which supports both SQLite and MySQL. It uses a private temporary option file for mysqldump, never a command-line password.

Validation: full application suite on SQLite and local MariaDB through Laravel's MySQL driver, plus opt-in MySQL transfer integration tests. Run transfer tests with `MMC_MYSQL_TRANSFER_TESTS=1 php -d extension=pdo_mysql vendor/phpunit/phpunit/phpunit tests/Integration/SqliteToMysqlTest.php`; the default local test administrator is root with no password, override with MMC_MYSQL_TEST_HOST / MMC_MYSQL_TEST_USER / MMC_MYSQL_TEST_PASSWORD. Tests only create/drop randomly named mmc_transfer_test_* databases.
