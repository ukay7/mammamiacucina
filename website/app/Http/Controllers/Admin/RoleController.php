<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles.index', ['roles' => Role::withCount('users')->orderBy('id')->get()]);
    }

    private function guard(Role $role): void
    {
        abort_if($role->is_super || array_diff($role->permissions, auth()->user()->permissions()), 403);
        abort_if($role->id === auth()->user()->role_id, 403, 'You cannot change your own user type permissions.');
    }

    public function create()
    {
        return view('admin.roles.form', ['role' => new Role(['permissions' => ['dashboard.view']])]);
    }

    public function edit(Role $role)
    {
        $this->guard($role);

        return view('admin.roles.form', compact('role'));
    }

    public function store(Request $r)
    {
        Role::create($this->validated($r));

        return redirect()->route('admin.roles.index')->with('status', 'User type created.');
    }

    public function update(Request $r, Role $role)
    {
        $this->guard($role);
        $role->update($this->validated($r, $role));

        return redirect()->route('admin.roles.index')->with('status', 'User type and sidebar access updated.');
    }

    public function destroy(Role $role)
    {
        $this->guard($role);
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Reassign users before deleting this type.']);
        }$role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'User type deleted.');
    }

    private function validated(Request $r, ?Role $role = null): array
    {
        $data = $r->validate(['name' => ['required', 'string', 'max:80', Rule::unique('roles')->ignore($role?->id)], 'permissions' => 'required|array', 'permissions.*' => ['string', Rule::in(auth()->user()->permissions())]]);
        $permissions = array_unique(['dashboard.view', ...$data['permissions']]);
        foreach (['users', 'roles', 'categories', 'products', 'imports', 'inventory'] as $module) {
            if (in_array($module.'.manage', $permissions)) {
                $permissions[] = $module.'.view';
            }
        }abort_if(array_diff($permissions, auth()->user()->permissions()), 403);
        $data['permissions'] = array_values(array_unique($permissions));

        return $data;
    }
}
