<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAccess
{
    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $user = $request->user();
        if ($user?->isCustomer()) return redirect()->route('customer.orders');
        if (! $user || ! $user->is_active || ! $user->role) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }
        if ($permission) {
            abort_unless(collect(explode('|',$permission))->contains(fn($ability)=>$user->hasAdminPermission($ability)), 403);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
