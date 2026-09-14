<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('checkout_token')->unique();
            $t->string('number')->unique();
            foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'province', 'postal_code', 'country'] as $field) {
                $t->string($field);
            }
            $t->text('notes')->nullable();
            $t->string('status')->default('placed');
            $t->string('payment_method')->default('cash');
            $t->string('payment_status')->default('unpaid');
            $t->unsignedBigInteger('subtotal_cents');
            $t->unsignedBigInteger('delivery_cents')->nullable();
            $t->unsignedBigInteger('tax_cents')->nullable();
            $t->string('currency', 3)->default('CAD');
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('product_code')->nullable();
            $t->string('qr_code')->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_cents');
            $t->unsignedBigInteger('line_cents');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
