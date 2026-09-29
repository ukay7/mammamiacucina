<?php
// Invoked by deploy-update.sh as root; credentials are never passed on the command line.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = getenv('MMC_BACKUP_DIR');
if (!$directory || !is_dir($directory)) { throw new RuntimeException('Missing private backup directory.'); }
$connection = Illuminate\Support\Facades\DB::connection();
$driver = $connection->getDriverName();
if ($driver === 'sqlite') {
    $pdo = $connection->getPdo();
    $pdo->exec('VACUUM INTO '.$pdo->quote($directory.'/database.sqlite'));
    chmod($directory.'/database.sqlite', 0600);
} elseif ($driver === 'mysql' || $driver === 'mariadb') {
    $config = $connection->getConfig();
    $quote = static fn ($v) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $v).'"';
    $options = "[client]\nuser=".$quote($config['username'])."\npassword=".$quote($config['password'])."\n";
    $options .= 'host='.$quote($config['host'])."\nport=".(int) $config['port']."\n";
    if (!empty($config['unix_socket'])) { $options .= 'socket='.$quote($config['unix_socket'])."\n"; }
    $file = $directory.'/mysql-client.cnf';
    file_put_contents($file, $options); chmod($file, 0600);
    $dump = $directory.'/database.sql';
    touch($dump); chmod($dump, 0600);
    try {
        $process = new Symfony\Component\Process\Process(['mysqldump', '--defaults-extra-file='.$file,
            '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--hex-blob',
            '--result-file='.$dump, $config['database']]);
        $process->setTimeout(600);
        $process->run();
        if (!$process->isSuccessful() || filesize($dump) === 0) {
            throw new RuntimeException('MySQL backup failed. Check mysqldump installation and database grants before continuing.');
        }
    } finally { unlink($file); }
} else { throw new RuntimeException('Unsupported database driver; arrange a backup before deployment.'); }
echo "Database backup complete ($driver).\n";
