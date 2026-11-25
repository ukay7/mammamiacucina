<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_receipt_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('provider_invoice', 40)->unique();
            $table->string('mode', 10);
            $table->string('status', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->string('transaction_id', 20)->nullable();
            $table->unsignedBigInteger('processor_amount_cents')->nullable();
            $table->char('processor_currency', 3)->nullable();
            $table->text('checkout_token')->nullable();
            $table->string('attention')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'last_checked_at']);
            $table->unique(['mode', 'transaction_id']);
        });
        Schema::create('invoice_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('invoice');
            $table->unsignedBigInteger('amount_cents');
            $table->unique(['invoice_payment_id', 'customer_invoice_id'], 'invoice_payment_invoice_unique');
        });
        Schema::create('invoice_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_payment_id')->constrained()->restrictOnDelete();
            $table->string('action', 30);
            $table->string('detail');
            $table->timestamps();
        });
        Schema::table('customer_receipt_lines', fn (Blueprint $table) => $table->string('gateway', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('customer_receipt_lines', fn (Blueprint $table) => $table->dropColumn('gateway'));
        Schema::dropIfExists('invoice_payment_events');
        Schema::dropIfExists('invoice_payment_allocations');
        Schema::dropIfExists('invoice_payments');
    }
};
