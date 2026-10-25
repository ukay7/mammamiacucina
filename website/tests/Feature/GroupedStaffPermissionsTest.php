<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\{AdminNavigation, StaffPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupedStaffPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        $role=Role::create(['name'=>'Grouped '.uniqid(), 'permissions'=>StaffPermissions::normalize($permissions)]);
        return User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
    }

    public function test_every_sidebar_item_is_rendered_once_with_no_duplicate_actions(): void
    {
        $super=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
        $html=$this->actingAs($super)->get('/admin/user-types/create')->assertOk()->getContent();
        $html=preg_replace('/<script\b[^>]*>.*?<\/script>/s','',$html);
        foreach(AdminNavigation::nodes() as $key=>$node) $this->assertSame(($node['super_only']??false)?0:1,substr_count($html,'value="'.$key.'"'),$key);
        foreach(StaffPermissions::actions() as $actions) foreach($actions as $key=>$label) $this->assertSame(1,substr_count($html,'value="'.$key.'"'),$key);
        $this->assertSame(0,substr_count($html,'value="pos.manage"'));
        $this->assertStringNotContainsString('Actions &amp; order visibility',$html);
    }

    public function test_action_dependencies_and_removal_are_consistent_for_every_module(): void
    {
        foreach(StaffPermissions::actions() as $route=>$actions) {
            foreach($actions as $action=>$label) {
                $saved=StaffPermissions::normalize([$action]);
                $this->assertContains('access.'.$route,$saved);
                $this->assertContains($action,$saved);
                $this->assertNotContains($action,StaffPermissions::normalize([]));
            }
        }
        $this->assertContains('payments.manage',StaffPermissions::normalize(['payments.refund']));
        $this->assertNotContains('orders.manage',StaffPermissions::normalize(['access.admin.reports.overdue-cheques']));
    }

    public function test_customer_management_does_not_grant_staff_or_business_approval(): void
    {
        $user=$this->staff(['customers.manage']);
        $this->actingAs($user)->get('/admin/customers/create')->assertOk();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/customers/business-requests')->assertForbidden();
        $this->assertFalse($user->hasAdminPermission('users.manage'));
        $this->assertFalse($user->hasAdminPermission('customers.approve'));
        $staffManager=$this->staff(['users.manage','access.admin.customers.index']);
        $this->actingAs($staffManager)->get('/admin/users/create')->assertOk();
        $this->get('/admin/customers/create')->assertForbidden();
    }

    public function test_view_only_and_quick_sale_permissions(): void
    {
        $user=$this->staff(['access.admin.products.index','access.admin.users.index','access.admin.pos.index']);
        $this->actingAs($user)->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/pos')->assertOk();
        $this->assertTrue($user->hasAdminPermission('pos.manage'));
    }

    public function test_upgrade_preserves_visible_modules_and_is_repeatable(): void
    {
        foreach([
            ['access.admin.customers.index','users.manage','orders.manage'],
            ['access.admin.orders.index','warehouse.pack','access.admin.pos.index'],
            ['access.admin.reports.index','payments.refund'],
            ['access.admin.users.index','users.manage'],
            ['access.admin.reports.overdue-cheques','orders.manage'],
        ] as $selected) {
            $old=AdminNavigation::normalize($selected);
            $new=StaffPermissions::upgrade($old);
            $keys=array_keys(AdminNavigation::nodes());
            $this->assertEqualsCanonicalizing(array_intersect($old,$keys),array_intersect($new,$keys));
            $this->assertSame($new,StaffPermissions::upgrade($new));
        }
        $old=AdminNavigation::normalize(['access.admin.customers.index','users.manage','orders.manage']);
        $new=StaffPermissions::upgrade($old);
        $this->assertContains('customers.manage',$new);
        $this->assertContains('customers.invoice-email',$new);
        $this->assertNotContains('users.manage',$new);
        $this->assertNotContains('payments.refund',StaffPermissions::upgrade(AdminNavigation::normalize(['access.admin.reports.index','payments.refund'])));
    }

    public function test_upgrade_command_keeps_user_assignments_and_super_admin(): void
    {
        $role=Role::create(['name'=>'Existing staff','permissions'=>AdminNavigation::normalize(['access.admin.customers.index','users.manage'])]);
        $user=User::factory()->create(['role_id'=>$role->id]);
        $super=Role::where('is_super',true)->firstOrFail();
        $this->artisan('staff:upgrade-permissions')->assertSuccessful();
        $this->assertNotContains(StaffPermissions::VERSION,$role->fresh()->permissions);
        $this->artisan('staff:upgrade-permissions',['--apply'=>true])->assertSuccessful();
        $this->assertContains('customers.manage',$role->fresh()->permissions);
        $this->assertSame($role->id,$user->fresh()->role_id);
        $this->assertTrue($super->fresh()->is_super);
        $saved=$role->fresh()->permissions;
        $this->artisan('staff:upgrade-permissions',['--apply'=>true])->assertSuccessful();
        $this->assertSame($saved,$role->fresh()->permissions);
    }
}
