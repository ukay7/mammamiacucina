<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStartTest extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        return User::factory()->create(['role_id'=>Role::where('is_super', true)->value('id'), 'is_active'=>true, 'password'=>'StartPageTest123']);
    }

    public function test_both_login_forms_send_super_admin_to_home_even_with_an_intended_url(): void
    {
        $user = $this->super();
        foreach (['admin.login.store', 'customer.login.store'] as $login) {
            $this->withSession(['url.intended'=>route('admin.notes.index')])
                ->post(route($login), ['email'=>$user->email, 'password'=>'StartPageTest123'])
                ->assertRedirect(route('admin.start'))->assertSessionMissing('url.intended');
            $this->get(route('admin.home'))->assertRedirect(route('admin.start'));
            $this->get(route('admin.login'))->assertRedirect(route('admin.start'));
            $this->get('/app')->assertRedirect(route('admin.start'));
            $this->post(route('admin.logout'));
        }
    }

    public function test_home_has_module_links_and_the_existing_sidebar_and_dashboard(): void
    {
        $response = $this->actingAs($this->super())->get(route('admin.start'))->assertOk();
        $response->assertSee('class="page-sidebar"', false)->assertSee('Where would you like to start?')
            ->assertSee('href="'.route('admin.start').'"  aria-current="page"', false);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $dom = new \DOMDocument; @$dom->loadHTML($response->getContent()); $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//a[@class="admin-module-card"]');
        $modules = collect(config('admin_start'))->flatten(1)->values();
        $this->assertSame($modules->count(), $cards->length);
        foreach ($modules as $i=>$module) {
            $this->assertSame(route($module['route']), $cards->item($i)->getAttribute('href'));
            $this->assertStringContainsString($module['label'], $cards->item($i)->textContent);
        }
        foreach (['Quick Sale', 'Reports', 'Customer Management'] as $i=>$label) {
            $this->assertStringContainsString($label, $cards->item($i)->textContent);
        }
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.notes.index'))->assertOk();
    }

    public function test_home_is_restricted_to_super_admin_even_if_a_role_claims_all_permissions(): void
    {
        $this->get(route('admin.start'))->assertRedirect(route('admin.login'));
        $role = Role::create(['name'=>'Full access without super status', 'permissions'=>[
            ...array_keys(config('admin.permissions')), ...array_keys(AdminNavigation::nodes()), AdminNavigation::MARKER,
        ]]);
        foreach ([$role->id, Role::where('name', 'Staff')->value('id'), Role::where('name', 'Warehouse User')->value('id')] as $roleId) {
            $user = User::factory()->create(['role_id'=>$roleId, 'is_active'=>true]);
            $this->actingAs($user)->get(route('admin.start'))->assertForbidden();
            $this->assertNotSame('admin.start', $user->accountRoute());
            $this->get(route('admin.notes.index'))->assertOk()->assertDontSee('href="'.route('admin.start').'"', false);
        }
        $customer = User::factory()->create(['role_id'=>Role::where('name','Customer')->value('id'), 'account_type'=>'individual', 'is_active'=>true]);
        $this->actingAs($customer)->get(route('admin.start'))->assertRedirect(route('customer.orders'));
        $super = $this->super(); $super->update(['is_active'=>false]);
        $this->actingAs($super)->get(route('admin.start'))->assertRedirect(route('admin.login'));
    }
}
