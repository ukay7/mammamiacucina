<?php

namespace App\Console\Commands;

use App\Services\SqliteToMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class MoveToMysql extends Command
{
    protected $signature = 'database:move-to-mysql
        {--host=127.0.0.1 : MySQL host (localhost TCP on the confirmed web01 server)}
        {--port=3306}
        {--database=mammamiacucina}
        {--user=mmc_app}
        {--reuse-empty-database : With --create, allow an existing database only if it has no tables, views, routines or events}
        {--create : Create a new database and dedicated user using an administrator connection}
        {--socket=/var/run/mysqld/mysqld.sock : Administrator socket, with --create only}';

    protected $description = 'Back up SQLite, copy and verify every table in empty MySQL, then switch .env while remaining in maintenance mode';

    public function handle(SqliteToMysql $transfer): int
    {
        $backup = null;
        $environmentChanged = false;
        $sourceLock = null;
        $stage = 'preflight';
        try {
            if (!class_exists(\SQLite3::class)) { throw new RuntimeException('Enable the SQLite3 extension to create a consistent backup.'); }
            if (!extension_loaded('pdo_mysql')) { throw new RuntimeException('Install/enable pdo_mysql before running this command.'); }
            if (config('database.default') !== 'sqlite') { throw new RuntimeException('Current application connection is not SQLite. No changes made.'); }
            if (config('app.maintenance.driver', 'file') !== 'file') { throw new RuntimeException('Use file-based maintenance mode for this migration.'); }
            if (getenv('DB_URL')) { throw new RuntimeException('Remove the exported DB_URL override before proceeding.'); }
            $database = (string) $this->option('database'); $user = (string) $this->option('user');
            foreach ([$database, $user] as $identifier) {
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,47}$/', $identifier)) { throw new RuntimeException('Use letters, digits and underscores for the database/user.'); }
            }
            $host = (string) $this->option('host'); $port = (string) $this->option('port');
            if (!preg_match('/^[a-zA-Z0-9.:-]+$/', $host) || !ctype_digit($port)) { throw new RuntimeException('Invalid MySQL host/port.'); }
            $envPath = base_path('.env');
            if (!is_writable($envPath) || !is_writable(dirname($envPath))) { throw new RuntimeException('The .env file and its directory must be writable.'); }
            $env = file_get_contents($envPath);
            $this->info("Destination: MySQL $host:$port / $database. Existing databases will not be overwritten.");
            if (!$this->confirm('Have you paused the MMC queue/import workers, and are you ready to stop the website and switch to MySQL after verification?', false)) { return self::FAILURE; }

            $backup = storage_path('app/private/mysql-migration-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)));
            if (!mkdir($backup, 0700, true)) { throw new RuntimeException('Could not create private backup directory.'); }
            file_put_contents($backup.'/env.before', $env); chmod($backup.'/env.before', 0600);
            $this->info("Private backup: $backup");

            if ($this->option('create')) {
                if ($host !== '127.0.0.1' && $host !== 'localhost') { throw new RuntimeException('Automatic provisioning is restricted to MySQL on this same server.'); }
                $adminUser = $this->ask('MySQL administrator username', 'root');
                $adminPassword = $this->secret('MySQL administrator password (Enter if root uses Unix socket authentication)') ?? '';
                $socket = (string) $this->option('socket');
                $dsn = $socket ? "mysql:unix_socket=$socket;charset=utf8mb4" : "mysql:host=$host;port=$port;charset=utf8mb4";
                $stage = 'administrator connection';
                $admin = new PDO($dsn, $adminUser, $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $check = $admin->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
                $check->execute([$database]);
                $databaseExists = (bool) $check->fetchColumn();
                if ($databaseExists) {
                    if (!$this->option('reuse-empty-database')) { throw new RuntimeException('Database exists. The --reuse-empty-database option is required to reuse a verified empty database.'); }
                    self::assertEmptyDatabase($admin, $database);
                }
                $check = $admin->prepare("SELECT User FROM mysql.user WHERE User = ? AND Host = 'localhost'");
                $check->execute([$user]);
                if ($check->fetchColumn()) { throw new RuntimeException('The dedicated MySQL user already exists; choose another username.'); }
                $password = self::generateDatabasePassword();
                file_put_contents($backup.'/generated-user.json', json_encode(['database' => $database, 'username' => $user, 'password' => $password], JSON_THROW_ON_ERROR));
                chmod($backup.'/generated-user.json', 0600);
                $account = $admin->quote($user)."@'localhost'";
                $stage = 'database creation';
                if (!$databaseExists) { $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); }
                $stage = 'application user creation';
                $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));
                $stage = 'application user grants';
                $admin->exec("GRANT ALL PRIVILEGES ON `$database`.* TO $account");
                $admin = null;
            } else {
                $password = $this->secret('Dedicated MySQL application user password') ?? '';
            }
            $mysql = array_replace(config('database.connections.mysql'), [
                'url' => null, 'host' => $host, 'port' => $port, 'database' => $database,
                'username' => $user, 'password' => $password, 'unix_socket' => '', 'engine' => 'InnoDB',
            ]);
            config(['database.connections.mmc_target' => $mysql]);
            DB::purge('mmc_target');
            $stage = 'application user connection';
            DB::connection('mmc_target')->getPdo();
            if (DB::connection('mmc_target')->getSchemaBuilder()->getTables($database)) { throw new RuntimeException('Destination is not empty. No data was overwritten.'); }

            // Retain generated credentials privately if copying fails, without writing them to console/logs.
            file_put_contents($backup.'/mysql-connection.json', json_encode($mysql, JSON_THROW_ON_ERROR)); chmod($backup.'/mysql-connection.json', 0600);
            if ($this->call('down', ['--retry' => 60]) !== 0) { throw new RuntimeException('Could not enter maintenance mode.'); }
            $sourcePath = DB::connection()->getConfig('database');
            $sourceLock = DB::connection()->getPdo();
            // Wait for the current writer, then prevent changes throughout backup, copy and cutover.
            $sourceLock->exec('PRAGMA busy_timeout=30000');
            $sourceLock->exec('BEGIN IMMEDIATE');
            $reader = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $snapshot = new \SQLite3($backup.'/database.sqlite');
            if (!$reader->backup($snapshot)) { throw new RuntimeException('SQLite backup failed.'); }
            $snapshot->close(); $reader->close();
            chmod($backup.'/database.sqlite', 0600);
            config(['database.connections.mmc_source' => array_replace(config('database.connections.sqlite'), ['url' => null, 'database' => $backup.'/database.sqlite'])]);
            DB::purge('mmc_source');
            $stage = 'data transfer and verification';
            $summary = $transfer->transfer('mmc_source', 'mmc_target', fn ($line) => $this->line($line));
            file_put_contents($backup.'/verified-tables.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            chmod($backup.'/verified-tables.json', 0600);
            $stage = 'environment cutover';
            $next = self::mysqlEnvironment($env, $host, $port, $database, $user, $password);
            $temp = $envPath.'.mysql-'.bin2hex(random_bytes(4));
            if (file_put_contents($temp, $next) !== strlen($next)) { throw new RuntimeException('Could not write new environment file.'); }
            chmod($temp, fileperms($envPath) & 0777);
            if (PHP_OS_FAMILY !== 'Windows') {
                chown($temp, fileowner($envPath)); chgrp($temp, filegroup($envPath));
            }
            if (!rename($temp, $envPath)) { throw new RuntimeException('Could not replace environment file.'); }
            $environmentChanged = true;
            if ($this->call('config:clear') !== 0) { throw new RuntimeException('Could not clear the old configuration cache.'); }
            $sourceLock->exec('COMMIT'); $sourceLock = null;
            $this->info('All tables and foreign keys verified. .env now selects MySQL. APP_KEY and existing SQLite are preserved.');
            $this->warn('Website remains in maintenance mode. Rebuild config as the deployment user, confirm the MySQL connection, then run artisan up.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($sourceLock) { try { $sourceLock->exec('ROLLBACK'); } catch (\Throwable) {} }
            if ($environmentChanged) {
                file_put_contents($envPath, $env);
                $this->call('config:clear');
            }
            // QueryException text can contain customer data or credentials; never print it here.
            $this->error(get_class($e) === RuntimeException::class ? $e->getMessage() : 'Migration failed ('.class_basename($e).'). No successful cutover was reported.');
            $this->line('Failed stage: '.$stage);
            if ($e instanceof \PDOException) {
                $this->line('SQLSTATE: '.($e->errorInfo[0] ?? $e->getCode()).'; driver error: '.($e->errorInfo[1] ?? 'unknown'));
                if (($e->errorInfo[1] ?? null) === 1819) { $this->error('MySQL rejected the generated password under its password policy.'); }
            }
            if ($backup) { $this->warn("Backup/credentials: $backup. Keep maintenance mode enabled and review before retrying. Do not erase the source."); }
            return self::FAILURE;
        }
    }

    public static function generateDatabasePassword(): string
    {
        // Meet common MySQL policies without weakening the server's password validation.
        return 'Aa9!'.bin2hex(random_bytes(32));
    }

    public static function assertEmptyDatabase(PDO $admin, string $database): void
    {
        foreach (['TABLES' => 'TABLE_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $table => $column) {
            $check = $admin->prepare("SELECT COUNT(*) FROM information_schema.$table WHERE $column = ?");
            $check->execute([$database]);
            if ((int) $check->fetchColumn() !== 0) {
                throw new RuntimeException('Existing database contains objects. It will not be reused or erased.');
            }
        }
    }

    public static function mysqlEnvironment(string $env, string $host, string $port, string $database, string $user, string $password): string
    {
        $values = ['DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database,
            'DB_USERNAME' => $user, 'DB_PASSWORD' => $password, 'DB_URL' => '', 'DB_SOCKET' => ''];
        foreach ($values as $key => $value) {
            if (str_contains($value, "\n") || str_contains($value, "\r")) { throw new RuntimeException('Multiline database credentials are not supported.'); }
            $quoted = '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
            $line = $key.'='.$quoted;
            $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
            $env = preg_match($pattern, $env) ? preg_replace_callback($pattern, fn () => $line, $env) : rtrim($env)."\n".$line."\n";
        }
        return $env;
    }
}
