<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Services\StaffPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpgradeStaffPermissions extends Command
{
    protected $signature = 'staff:upgrade-permissions {--apply : Save the conversion; otherwise preview only}';
    protected $description = 'Convert existing roles to grouped permissions without changing account assignments';

    public function handle(): int
    {
        DB::transaction(function () {
            foreach (Role::where('is_super', false)->lockForUpdate()->get() as $role) {
                $permissions = StaffPermissions::upgrade($role->permissions ?? []);
                if ($permissions === $role->permissions) continue;
                $this->line('Convert user type: '.$role->name);
                if ($this->option('apply')) $role->update(['permissions' => $permissions]);
            }
        });
        $this->info($this->option('apply') ? 'Grouped permissions saved. User assignments and Super Admin are unchanged.' : 'Preview only. Use --apply to save.');
        return self::SUCCESS;
    }
}
