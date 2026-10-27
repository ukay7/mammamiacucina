<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('number')->nullable()->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('customer_snapshot');
            $t->unsignedBigInteger('total_cents');
            $t->uuid('request_key')->unique();
            $t->char('request_hash', 64);
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
        });
        Schema::create('customer_receipt_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_receipt_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->uuid('client_key');
            $t->string('method', 20);
            $t->unsignedBigInteger('amount_cents');
            $t->date('payment_date');
            $t->string('reference')->nullable();
            $t->string('bank')->nullable();
            $t->date('cheque_date')->nullable();
            $t->text('note')->nullable();
            $t->string('proof_path')->nullable();
            $t->string('proof_name')->nullable();
            $t->char('proof_hash', 64)->nullable();
            $t->string('status', 30)->index();
            $t->json('planned_allocations');
            $t->date('cleared_on')->nullable();
            $t->string('clearance_reference')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->unique(['customer_receipt_id', 'client_key']);
            $t->index(['customer_id', 'status']);
        });
        Schema::create('customer_receipt_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_receipt_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_receipt_line_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('actor_name');
            $t->string('action', 30);
            $t->text('reason');
            $t->string('reference')->nullable();
            $t->json('snapshot');
            $t->timestamps();
        });
        Schema::table('order_settlements', function (Blueprint $t) {
            $t->foreignId('customer_receipt_line_id')->nullable()->constrained()->restrictOnDelete();
            $t->date('received_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_settlements', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_receipt_line_id');
            $t->dropColumn('received_on');
        });
        Schema::dropIfExists('customer_receipt_events');
        Schema::dropIfExists('customer_receipt_lines');
        Schema::dropIfExists('customer_receipts');
    }
};
