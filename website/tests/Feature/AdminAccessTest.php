<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $type = 'Super Admin', array $extra = []): User
    {
        return User::factory()->create(array_merge(['role_id' => Role::where('name', $type)->value('id'), 'is_active' => true], $extra));
    }

    public function test_guests_are_redirected_and_login_is_available(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Welcome back');
        $this->get('/admin/users')->assertRedirect('/admin/login');
    }

    public function test_active_user_can_login_and_logout(): void
    {
        $user = $this->account('Staff', ['password' => 'TestingPassword123']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'TestingPassword123'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = $this->account('Staff', ['is_active' => false, 'password' => 'TestingPassword123']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'TestingPassword123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_cannot_bypass_hidden_sidebar_links(): void
    {
        $this->actingAs($this->account('Staff'));
        $this->get('/admin')->assertOk()->assertDontSee('User Management')->assertDontSee('User Types &amp; Permissions', false);
        $this->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
        $this->get('/admin/user-types')->assertForbidden();
    }

    public function test_super_admin_creates_hashed_user_and_can_deactivate_it(): void
    {
        $this->actingAs($this->account());
        $data = ['name' => 'Team Member', 'email' => 'TEAM@example.com', 'password' => 'TestingPassword123', 'password_confirmation' => 'TestingPassword123', 'role_id' => Role::where('name', 'Staff')->value('id'), 'is_active' => 1];
        $this->post('/admin/users', $data)->assertRedirect('/admin/users');
        $user = User::where('email', 'team@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('TestingPassword123', $user->password));
        $this->put('/admin/users/'.$user->id, [...$data, 'email' => 'team@example.com', 'password' => '', 'password_confirmation' => '', 'is_active' => 0])->assertRedirect('/admin/users');
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_super_accounts_and_super_role_cannot_be_changed_through_ui(): void
    {
        $actor = $this->account();
        $other = $this->account();
        $this->actingAs($actor)->get('/admin/users/'.$other->id.'/edit')->assertForbidden();
        $this->put('/admin/user-types/'.$actor->role_id, ['name' => 'Broken', 'permissions' => []])->assertForbidden();
    }

    public function test_role_permissions_control_sidebar_and_server_access(): void
    {
        $actor = $this->account();
        $this->actingAs($actor)->post('/admin/user-types', ['name' => 'Read only team', 'permissions' => ['users.view']])->assertRedirect('/admin/user-types');
        $role = Role::where('name', 'Read only team')->firstOrFail();
        $user = $this->account('Staff', ['role_id' => $role->id]);
        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('User Management');
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/users/create')->assertForbidden();
    }

    public function test_cannot_grant_permissions_beyond_your_own(): void
    {
        $role = Role::create(['name' => 'Limited manager', 'permissions' => ['dashboard.view', 'roles.view', 'roles.manage', 'users.manage']]);
        $this->actingAs($this->account('Staff', ['role_id' => $role->id]));
        $this->post('/admin/user-types', ['name' => 'Escalated', 'permissions' => ['users.view']])->assertSessionHasErrors('permissions.0');
        $this->post('/admin/users', ['name' => 'Escalated', 'email' => 'escalated@example.com', 'password' => 'TestingPassword123', 'password_confirmation' => 'TestingPassword123', 'role_id' => Role::where('is_super', true)->value('id'), 'is_active' => 1])->assertSessionHasErrors('role_id');
    }

    public function test_cannot_delete_assigned_type(): void
    {
        $staff = $this->account('Staff');
        $this->actingAs($this->account())->delete('/admin/user-types/'.$staff->role_id)->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $staff->role_id]);
    }

    public function test_inactive_session_is_rejected(): void
    {
        $user = $this->account('Staff', ['is_active' => false]);
        $this->actingAs($user)->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_cannot_deactivate_self(): void
    {
        $user = $this->account('Administrator');
        $this->actingAs($user)->put('/admin/users/'.$user->id, ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => 0])->assertStatus(422);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_login_is_throttled(): void
    {
        $user = $this->account('Staff');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }$response = $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong']);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts',session('errors')->first('email'));
    }
}
