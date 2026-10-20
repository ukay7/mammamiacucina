<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventories')->where('low_stock_threshold', 0)->update(['low_stock_threshold' => 10]);
        Schema::table('inventories', function (Blueprint $table) {
            $table->decimal('low_stock_threshold', 15, 3)->default(10)->change();
        });
        Schema::table('order_items', function (Blueprint $table) {
            // NULL means inventory was not configured when the item was reserved.
            $table->decimal('inventory_shortage_quantity', 15, 3)->nullable()->after('stock_deducted');
            $table->index(['product_id', 'inventory_shortage_quantity'], 'order_items_inventory_shortage_index');
        });

        DB::table('order_items')->where('stock_deducted', true)->update(['inventory_shortage_quantity' => 0]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_inventory_shortage_index');
            $table->dropColumn('inventory_shortage_quantity');
        });
        Schema::table('inventories', function (Blueprint $table) {
            $table->decimal('low_stock_threshold', 15, 3)->default(0)->change();
        });
    }
};
