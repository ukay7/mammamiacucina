<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_credit_cents')->default(0);
            $table->unsignedBigInteger('refund_credit_tax_cents')->default(0);
            $table->unsignedBigInteger('pending_refund_cents')->default(0);
            $table->unsignedBigInteger('pending_refund_tax_cents')->default(0);
        });
        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('order_id')->constrained();
            $table->string('number', 32)->nullable()->unique();
            $table->uuid('request_key')->unique();
            $table->char('request_hash', 64);
            $table->char('preview_hash', 64);
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('tax_cents');
            $table->text('reason');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['customer_id', 'id']);
            $table->index(['order_id', 'id']);
        });
        Schema::create('customer_refund_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_refund_id')->constrained();
            $table->uuid('client_key');
            $table->string('method', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('tax_cents');
            $table->date('refund_date');
            $table->string('reference', 150)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank', 150)->nullable();
            $table->text('note')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
            $table->char('proof_hash', 64)->nullable();
            $table->string('status', 30);
            $table->date('resolved_on')->nullable();
            $table->string('clearance_reference', 150)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['customer_refund_id', 'client_key']);
        });
        Schema::table('order_settlements', function (Blueprint $table) {
            $table->foreignId('customer_refund_line_id')->nullable()->unique()->constrained();
        });
        Schema::create('order_credits', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('customer_refund_line_id')->unique()->constrained();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('tax_cents');
            $table->json('snapshot');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('customer_refund_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_refund_id')->constrained();
            $table->foreignId('customer_refund_line_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->text('description');
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_refund_events');
        Schema::dropIfExists('order_credits');
        Schema::table('order_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_refund_line_id');
        });
        Schema::dropIfExists('customer_refund_lines');
        Schema::dropIfExists('customer_refunds');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['refund_credit_cents', 'refund_credit_tax_cents', 'pending_refund_cents', 'pending_refund_tax_cents']));
    }
};
