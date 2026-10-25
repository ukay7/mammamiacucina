<?php

namespace App\Http\Middleware;

use App\Models\CustomerInvoice;
use App\Models\Order;
use App\Models\Payment;
use Closure;
use Illuminate\Http\Request;

class DeletedOrderReadOnly
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->route()?->parameters() ?? [] as $record) {
            if ($record instanceof Order && $record->trashed()) {
                abort_unless($request->isMethodSafe() && $request->routeIs('admin.orders.deleted.*') && $request->user()?->isSuper(), 404);
            }
            if ($record instanceof Payment || $record instanceof CustomerInvoice) {
                abort_unless($record->order()->exists(), 404);
            }
        }

        return $next($request);
    }
}
