<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->char('name_key', 64)->unique();
            $t->string('contact_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 60)->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
        });
        Schema::table('products', fn (Blueprint $t) => $t->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete());
        Schema::create('supplier_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('supplier_snapshot');
            $t->string('warehouse_name');
            $t->enum('status', ['placed', 'completed', 'deleted'])->default('placed')->index();
            $t->text('notes')->nullable();
            $t->json('totals');
            $t->uuid('request_key')->unique();
            $t->char('request_hash', 64);
            $t->unsignedInteger('revision')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('deleted_at')->nullable();
            $t->text('deletion_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('supplier_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('product_code')->nullable();
            $t->string('barcode')->nullable();
            $t->decimal('quantity', 15, 3);
            $t->string('currency', 3);
            $t->decimal('unit_cost', 24, 8);
            $t->decimal('line_total', 24, 2);
            $t->json('pricing_snapshot');
            $t->timestamps();
            $t->unique(['supplier_order_id', 'product_id']);
        });
        Schema::create('supplier_order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_events');
        Schema::dropIfExists('supplier_order_items');
        Schema::dropIfExists('supplier_orders');
        Schema::table('products', fn (Blueprint $t) => $t->dropConstrainedForeignId('supplier_id'));
        Schema::dropIfExists('suppliers');
    }
};
