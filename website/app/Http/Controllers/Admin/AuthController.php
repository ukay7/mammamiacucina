<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return auth()->check() ? redirect()->route($this->landingRoute()) : view('admin.login');
    }

    public function store(Request $r)
    {
        $data = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $data['email'] = Str::lower($data['email']);
        $key = 'admin-login:'.hash('sha256', $data['email'].'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt([...$data, 'is_active' => true, fn ($q) => $q->whereNotNull('role_id')])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active admin account.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        // The intended page may belong to a different account or require permissions this user lacks.
        $r->session()->forget('url.intended');

        return redirect()->route($this->landingRoute());
    }

    private function landingRoute(): string
    {
        $user = auth()->user();
        if ($user->isCustomer()) return 'customer.orders';

        return $user->hasAdminPermission('warehouse.pack') && ! $user->hasAdminPermission('orders.manage')
            ? 'admin.orders.index' : 'admin.dashboard';
    }

    public function destroy(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
