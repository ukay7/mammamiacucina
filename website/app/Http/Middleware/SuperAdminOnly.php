<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isSuper(), 403);

        return $next($request);
    }
}
