<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $r)
    {
        $search = (string) $r->query('search', '');
        $users = User::with('role')->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    private function roles()
    {
        return Role::where('is_super', false)->get()->filter(fn ($role) => ! array_diff($role->permissions, auth()->user()->permissions()));
    }

    private function target(User $user): void
    {
        abort_if($user->role?->is_super, 403, 'Super Admin accounts are protected.');
        abort_if(array_diff($user->permissions(), auth()->user()->permissions()), 403);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->roles()]);
    }

    public function edit(User $user)
    {
        $this->target($user);

        return view('admin.users.form', ['user' => $user, 'roles' => $this->roles()]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        User::create($data);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function update(Request $r, User $user)
    {
        $this->target($user);
        $data = $this->validated($r, $user);
        if ($user->id === $r->user()->id) {
            abort_if(! $data['is_active'] || (int) $data['role_id'] !== $user->role_id, 422, 'You cannot deactivate yourself or change your own user type.');
        }if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['remember_token'] = Str::random(60);
        }$user->fill($data);
        if (isset($data['remember_token'])) {
            $user->remember_token = $data['remember_token'];
        }$user->save();

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    private function validated(Request $r, ?User $user = null): array
    {
        $r->merge(['email' => Str::lower((string) $r->input('email'))]);
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)], 'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers(), 'max:255'], 'role_id' => ['required', Rule::in($this->roles()->pluck('id')->all())], 'is_active' => 'required|boolean']);

        return $data;
    }
}
