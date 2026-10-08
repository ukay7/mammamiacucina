<?php
namespace App\Console\Commands;
use App\Services\ClientDemoReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ResetClientDemo extends Command
{
    protected $signature = 'app:reset-client-demo {--execute : Perform the reset after a full database backup} {--force : Confirm permanent deletion without prompting}';
    protected $description = 'Preview or clear all customers, orders and quotations; preserve staff, products and ID sequences';

    public function handle(ClientDemoReset $reset): int
    {
        $this->table(['Data', 'Rows'], [
            ['Customer accounts', DB::table('users')->whereIn('account_type', ['individual','business'])->count()],
            ['Customer profiles', DB::table('customers')->count()],
            ['Orders', DB::table('orders')->count()],
            ['Quotations', DB::table('quotations')->count()],
            ['Payments', DB::table('payments')->count()],
            ['Refund records', DB::table('payment_refunds')->count()],
        ]);
        $this->warn('Clears ALL orders, quotations, customers, linked payment records and customer email history. No refunds or inventory adjustments are performed. Staff, products, images, stock and ID sequences are preserved.');
        if (!$this->option('execute')) {
            $this->info('Preview only. Use --execute to back up and reset. Pause workers and scheduled jobs, and enable maintenance mode first.');
            return self::SUCCESS;
        }
        if (!app()->isDownForMaintenance()) {
            $this->error('Enable maintenance mode first: php artisan down');
            return self::FAILURE;
        }
        if (!$this->option('force') && !$this->confirm('Permanently delete the displayed customer/order/quotation data?', false)) return self::FAILURE;
        $directory = storage_path('app/private/client-demo-backups/'.date('Ymd-His').'-'.bin2hex(random_bytes(4)));
        if (!mkdir($directory, 0700, true)) {
            $this->error('Cannot create backup directory. Nothing deleted.');
            return self::FAILURE;
        }
        $this->info('Backup: '.$directory);
        $process = new Process([PHP_BINARY, base_path('scripts/backup-database.php')], base_path(), ['MMC_BACKUP_DIR'=>$directory]);
        $process->setTimeout(660);
        try {
            $process->mustRun();
            $reset->run();
        } catch (\Throwable $e) {
            $this->error('Reset stopped. Backup failure or database constraint prevented completion. Keep maintenance mode enabled and review the backup/database before retrying.');
            return self::FAILURE;
        }
        $this->info('Reset complete. Database backup retained at '.$directory);
        $this->info('Maintenance mode remains enabled. Run php artisan up when ready.');
        return self::SUCCESS;
    }
}
