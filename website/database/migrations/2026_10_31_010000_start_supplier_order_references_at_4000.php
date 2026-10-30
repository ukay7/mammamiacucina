<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $orders = DB::table('supplier_orders')->orderBy('id')->lockForUpdate()->get(['id', 'number', 'status']);
            $changes = $orders->filter(fn ($order) => $order->number === 'PO-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT));
            $reserved = $orders->whereNotIn('id', $changes->pluck('id'))->pluck('number')->filter()->all();
            foreach ($changes as $order) {
                if (in_array('PO-'.(3999 + $order->id), $reserved, true)) {
                    throw new RuntimeException('Supplier order renumbering conflicts with an existing custom reference. No references were changed.');
                }
            }
            // Free the old unique references together before assigning the new range.
            DB::table('supplier_orders')->whereIn('id', $changes->pluck('id'))->update(['number' => null]);
            foreach ($changes as $order) {
                $number = 'PO-'.(3999 + $order->id);
                DB::table('supplier_orders')->where('id', $order->id)->update(['number' => $number]);
                DB::table('supplier_order_events')->insert(['supplier_order_id' => $order->id,
                    'user_id' => null, 'actor_name' => 'System update', 'from_status' => $order->status, 'to_status' => $order->status,
                    'reason' => 'Order reference changed from '.$order->number.' to '.$number.'. Quantities, prices, status and inventory are unchanged.',
                    'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Keep issued references and their audit history when rolling back code.
    }
};
