<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $t) {
            $t->enum('status', ['placed', 'packing', 'ready', 'completed', 'received', 'deleted'])->default('placed')->change();
        });
        Schema::table('supplier_order_events', fn (Blueprint $t) => $t->string('actor_name')->nullable());
        Schema::create('supplier_order_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_order_id')->unique()->constrained()->restrictOnDelete();
            $t->boolean('add_to_inventory');
            $t->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('warehouse_name')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('received_by_name');
            $t->timestamp('received_at');
            $t->text('reason');
            $t->boolean('initialize_unset')->default(false);
            $t->uuid('request_key')->unique();
            $t->char('request_hash', 64);
            $t->timestamps();
        });
        Schema::create('supplier_order_receipt_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_order_receipt_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_order_item_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('product_code')->nullable();
            $t->string('barcode')->nullable();
            $t->decimal('quantity', 15, 3);
            $t->decimal('quantity_before', 15, 3)->nullable();
            $t->decimal('quantity_after', 15, 3)->nullable();
            $t->decimal('total_before', 15, 3)->nullable();
            $t->decimal('total_after', 15, 3)->nullable();
            $t->foreignId('inventory_movement_id')->nullable()->constrained()->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('supplier_order_receipts')->exists() || DB::table('supplier_orders')->whereIn('status', ['packing', 'ready', 'received'])->exists()) {
            throw new RuntimeException('Supplier receiving history exists. Resolve it before rolling back this migration; stock must not be silently reversed.');
        }
        Schema::dropIfExists('supplier_order_receipt_items');
        Schema::dropIfExists('supplier_order_receipts');
        Schema::table('supplier_order_events', fn (Blueprint $t) => $t->dropColumn('actor_name'));
        Schema::table('supplier_orders', fn (Blueprint $t) => $t->enum('status', ['placed', 'completed', 'deleted'])->default('placed')->change());
    }
};
