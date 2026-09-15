<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('tracking_token', 64)->nullable()->unique();
            $t->unsignedInteger('revision')->default(0);
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->boolean('stock_deducted')->default(false));
        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('description');
            $t->timestamp('created_at');
        });
        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $o) {
                DB::table('orders')->where('id', $o->id)->update(['tracking_token' => Str::random(48)]);
                foreach (DB::table('order_items')->where('order_id', $o->id)->get() as $item) {
                    $deducted = DB::table('inventory_movements')->where('product_id', $item->product_id)->where('reason', 'Order '.$o->number)->where('quantity_change', '<', 0)->exists();
                    DB::table('order_items')->where('id', $item->id)->update(['stock_deducted' => $deducted]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('stock_deducted'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropUnique(['tracking_token']);
            $t->dropColumn(['tracking_token', 'revision', 'paid_at', 'cancelled_at']);
        });
    }
};
