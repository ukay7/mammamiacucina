<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $t->uuid('reference')->unique();
            $t->string('provider');
            $t->string('mode');
            $t->string('status')->default('creating')->index();
            $t->string('provider_order_id')->nullable();
            $t->string('transaction_id')->nullable();
            $t->text('checkout_url')->nullable();
            $t->unsignedBigInteger('amount_cents');
            $t->unsignedBigInteger('refunded_cents')->default(0);
            $t->string('currency', 3)->default('CAD');
            $t->timestamp('expires_at');
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->timestamp('last_checked_at')->nullable();
            $t->string('attention')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'mode', 'provider_order_id']);
            $t->unique(['provider', 'mode', 'transaction_id']);
        });
        Schema::create('payment_refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->restrictOnDelete();
            $t->string('provider_refund_id');
            $t->unsignedBigInteger('amount_cents');
            $t->string('status');
            $t->timestamps();
            $t->unique(['payment_id', 'provider_refund_id']);
        });
        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider');
            $t->string('mode');
            $t->string('event_id');
            $t->timestamps();
            $t->unique(['provider', 'mode', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payments');
    }
};
