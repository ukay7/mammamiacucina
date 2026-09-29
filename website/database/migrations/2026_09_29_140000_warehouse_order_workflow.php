<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->text('warehouse_note')->nullable();
            $t->timestamp('warehouse_sent_at')->nullable();
            $t->unsignedInteger('warehouse_round')->default(0);
            $t->unsignedBigInteger('manual_collected_cents')->nullable();
            $t->unsignedInteger('tax_basis_points')->nullable();
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->boolean('packed')->default(false);
            $t->text('warehouse_note')->nullable();
            $t->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('packed_at')->nullable();
        });
        Schema::create('order_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('amount_cents');
            $t->string('method');
            $t->string('reference')->nullable();
            $t->text('note');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        if (! DB::table('roles')->where('name', 'Warehouse User')->exists()) {
            DB::table('roles')->insert(['name' => 'Warehouse User', 'permissions' => json_encode(['dashboard.view', 'warehouse.pack']), 'is_super' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_settlements');
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropForeign(['packed_by']);
            $t->dropColumn(['packed', 'warehouse_note', 'packed_by', 'packed_at']);
        });
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['warehouse_note', 'warehouse_sent_at', 'warehouse_round', 'manual_collected_cents', 'tax_basis_points']));
        // Keep the role and user assignments; a rollback must not delete team accounts.
    }
};
