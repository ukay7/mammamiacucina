<?php
namespace App\Console\Commands;

use App\Models\{Role,User};
use App\Services\AdminNavigation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConfigureStaffAccess extends Command
{
    protected $signature='staff:configure-access {--super-email=} {--administrator-email=} {--apply : Apply assignments; otherwise preview only}';
    protected $description='Upgrade sidebar permissions and assign the sole Super Admin and Administrator accounts';

    public function handle(): int
    {
        $superEmail=strtolower(trim((string)$this->option('super-email')));
        $adminEmail=strtolower(trim((string)$this->option('administrator-email')));
        if(!filter_var($superEmail,FILTER_VALIDATE_EMAIL) || !filter_var($adminEmail,FILTER_VALIDATE_EMAIL) || $superEmail===$adminEmail) {
            $this->error('Supply two different, exact email addresses. No changes made.');return self::FAILURE;
        }
        $super=User::whereRaw('LOWER(email) = ?',[$superEmail])->first();
        $administrator=User::whereRaw('LOWER(email) = ?',[$adminEmail])->first();
        if(!$super || !$administrator || $super->isCustomer() || $administrator->isCustomer() || !$super->is_active || !$administrator->is_active) {
            $this->error('Both addresses must match existing active staff accounts. No accounts are created and no passwords changed.');return self::FAILURE;
        }
        $this->line('Sole Super Admin: '.$super->email);
        $this->line('Administrator: '.$administrator->email);
        $this->line('Other current Super Admin users become Administrators. Other staff keep their current user types.');
        if(!$this->option('apply')) {$this->info('Preview only. Add --apply to perform this one-time setup.');return self::SUCCESS;}
        DB::transaction(function()use($super,$administrator){
            $roles=Role::lockForUpdate()->get();
            $oldSuperIds=$roles->where('is_super',true)->pluck('id');
            $superRole=Role::firstOrNew(['name'=>'Super Admin']);
            $superRole->forceFill(['is_super'=>true,'permissions'=>[]])->save();
            $oldSuperIds=$oldSuperIds->push($superRole->id)->unique();
            $adminRole=Role::firstOrNew(['name'=>'Administrator']);
            if(!$adminRole->exists || $adminRole->is_super) {
                $adminRole->permissions=AdminNavigation::normalize([...array_keys(config('admin.permissions')),...array_keys(AdminNavigation::nodes()),'orders.scope.all']);
            } else {
                $adminRole->permissions=AdminNavigation::upgrade($adminRole->permissions??[]);
            }
            $adminRole->forceFill(['is_super'=>false])->save();
            User::whereIn('role_id',$oldSuperIds)->where('id','!=',$super->id)->update(['role_id'=>$adminRole->id]);
            Role::where('id','!=',$superRole->id)->update(['is_super'=>false]);
            foreach(Role::where('is_super',false)->get() as $role) $role->update(['permissions'=>AdminNavigation::upgrade($role->permissions??[])]);
            $super->update(['role_id'=>$superRole->id]);
            $administrator->update(['role_id'=>$adminRole->id]);
        });
        $this->info('Staff access configured. Existing passwords, orders and warehouse role assignments were preserved.');
        return self::SUCCESS;
    }
}
