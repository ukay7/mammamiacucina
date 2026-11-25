<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->date('posted_on')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('accounting_journals')->restrictOnDelete();
            $table->string('classification', 20)->index();
            $table->string('account', 120);
            $table->bigInteger('amount_cents');
            $table->string('cash_flow', 15)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document', 120)->nullable();
            $table->date('due_on')->nullable();
            $table->string('memo', 255)->nullable();
        });
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->unique();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('completed_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_reconciliation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('payment_reconciliations')->restrictOnDelete();
            $table->string('movement', 60);
            $table->char('fingerprint', 64);
            $table->json('snapshot');
            $table->string('status', 20);
            $table->bigInteger('matched_cents')->nullable();
            $table->string('bank_reference', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewed_by_name');
            $table->timestamp('created_at');
            $table->index(['reconciliation_id', 'movement', 'id'], 'payment_reconciliation_movement');
        });

        if (! DB::table('roles')->where('name', 'Accountant / Bookkeeper')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Accountant / Bookkeeper', 'is_super' => false,
                'permissions' => json_encode(\App\Services\StaffPermissions::normalize([
                    'access.admin.accounting.index', 'access.admin.payment-reconciliation.index', 'accounting.reconcile',
                ])),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['payment_reconciliation_reviews', 'payment_reconciliations', 'accounting_entries', 'accounting_journals'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Accounting and reconciliation history must be preserved.');
            }
        }
        Schema::dropIfExists('payment_reconciliation_reviews');
        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('accounting_entries');
        Schema::dropIfExists('accounting_journals');
    }
};
