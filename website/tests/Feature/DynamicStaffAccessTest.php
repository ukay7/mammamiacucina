<?php
namespace Tests\Feature;

use App\Models\{Role,User};
use App\Services\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        $role=Role::create(['name'=>'Custom '.uniqid(),'permissions'=>AdminNavigation::normalize($permissions)]);
        return User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
    }

    public function test_custom_packing_role_can_use_other_selected_modules(): void
    {
        $user=$this->staff(['warehouse.pack','access.admin.orders.index','access.admin.notes.index','access.admin.pos.index']);
        $this->actingAs($user)->get('/admin/notes')->assertOk()->assertSee('Quick Sale / POS')->assertSee('Pending Orders')->assertDontSee('Completed Orders')->assertDontSee('Reporting Centre');
        $this->get('/admin/pos')->assertOk();
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/orders/completed')->assertForbidden();
        $this->get('/admin/dashboard')->assertForbidden();
        $this->get('/admin/customers')->assertForbidden();
        $this->assertSame('admin.notes.index',$user->accountRoute());
    }

    public function test_shared_legacy_permissions_do_not_expose_unchecked_pages(): void
    {
        $user=$this->staff(['access.admin.customers.index','access.admin.theme.edit']);
        $this->actingAs($user)->get('/admin/customers')->assertOk();
        $this->get('/admin/customers/business-requests')->assertForbidden();
        $this->get('/admin/orders')->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/settings/theme')->assertOk();
        $this->get('/admin/settings/general')->assertForbidden();
        $this->put('/admin/settings/email',[])->assertForbidden();
        $this->get('/admin/notes')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_pending_completed_filters_cannot_bypass_the_selected_page(): void
    {
        $user=$this->staff(['access.admin.orders.completed']);
        $this->actingAs($user)->get('/admin/orders/completed')->assertOk();
        $this->get('/admin/orders?view=completed')->assertForbidden();
        $this->get('/admin/orders/export?view=pending&format=print')->assertForbidden();
    }

    public function test_no_permissions_has_safe_landing_and_no_notes_access(): void
    {
        $user=$this->staff([]);
        $this->assertSame('admin.no-access',$user->accountRoute());
        $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.no-access'));
        $this->get('/admin/no-access')->assertOk();
        $this->get('/admin/notes')->assertForbidden();
    }

    public function test_content_only_role_has_an_accessible_landing(): void
    {
        $user=$this->staff(['access.admin.content.index.policies']);
        $this->assertSame('admin.module-home',$user->accountRoute());
        $this->actingAs($user)->get('/admin/module-home')->assertRedirect('/admin/content/policies');
        $this->get('/admin/content/policies')->assertOk();
        $this->get('/admin/content/jobs')->assertForbidden();
    }

    public function test_all_staff_routes_have_a_module_mapping(): void
    {
        $user=$this->staff([...array_keys(AdminNavigation::nodes()),...array_keys(config('admin.permissions'))]);
        foreach(app('router')->getRoutes() as $route) {
            if(!in_array(\App\Http\Middleware\AdminAccess::class,$route->gatherMiddleware(),true)) continue;
            if(in_array(\App\Http\Middleware\SuperAdminOnly::class,$route->gatherMiddleware(),true)) continue;
            $uri=preg_replace('/\{[^}]+\}/','1',$route->uri());
            $uri=str_replace('admin/content/1','admin/content/policies',$uri);
            $request=\Illuminate\Http\Request::create('/'.$uri,$route->methods()[0]);
            $bound=clone $route;
            $bound->bind($request);
            $request->setRouteResolver(fn()=>$bound);
            $request->setUserResolver(fn()=>$user);
            try { AdminNavigation::authorize($request); }
            catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->fail('Missing module mapping: '.$route->getName()); }
            $this->addToAssertionCount(1);
        }
    }

    public function test_role_form_saves_sidebar_nodes_without_automatic_dashboard(): void
    {
        $super=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
        $this->actingAs($super)->get('/admin/user-types/create')->assertOk()->assertSee('My Notes')->assertSee('Pending Orders')->assertSee('Completed Orders')->assertSee('Business Approval Requests');
        $this->post('/admin/user-types',['name'=>'Dispatch team','permissions'=>['access.admin.notes.index','access.admin.orders.completed']])->assertSessionHasNoErrors();
        $role=Role::where('name','Dispatch team')->firstOrFail();
        $this->assertContains('access.admin.orders.completed',$role->permissions);
        $this->assertNotContains('dashboard.view',$role->permissions);
        $this->post('/admin/user-types',['name'=>'super admin','permissions'=>[]])->assertSessionHasErrors('name');
    }

    public function test_setup_is_exact_safe_and_repeatable(): void
    {
        $superRole=Role::where('is_super',true)->firstOrFail();
        $owner=User::factory()->create(['email'=>'admin@mammamiacucina.ca','role_id'=>$superRole->id,'is_active'=>true]);
        $info=User::factory()->create(['email'=>'info@example.test','role_id'=>$superRole->id,'is_active'=>true]);
        $other=User::factory()->create(['role_id'=>$superRole->id,'is_active'=>true]);
        $worker=$this->staff(['warehouse.pack','access.admin.orders.index']);
        $workerRole=$worker->role_id;
        $args=['--super-email'=>$owner->email,'--administrator-email'=>$info->email];
        $this->artisan('staff:configure-access',$args)->assertSuccessful();
        $this->assertTrue($info->fresh()->isSuper());
        $this->artisan('staff:configure-access',$args+['--apply'=>true])->assertSuccessful();
        $this->assertTrue($owner->fresh()->isSuper());
        $this->assertFalse($info->fresh()->isSuper());
        $this->assertSame('Administrator',$info->fresh()->role->name);
        $this->assertFalse($other->fresh()->isSuper());
        $this->assertSame($workerRole,$worker->fresh()->role_id);
        $this->artisan('staff:configure-access',$args+['--apply'=>true])->assertSuccessful();
        $this->assertSame(1,User::whereHas('role',fn($q)=>$q->where('is_super',true))->count());
        $this->artisan('staff:configure-access',['--super-email'=>$owner->email,'--administrator-email'=>'missing@example.test','--apply'=>true])->assertFailed();
        $this->assertTrue($owner->fresh()->isSuper());
    }
}
