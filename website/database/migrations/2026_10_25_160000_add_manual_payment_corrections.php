<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('original_payment_method')->nullable();
        });
        // Freeze the method of the original collection before any future method change.
        DB::table('orders')->where(fn ($q) => $q->where('manual_collected_cents', '>', 0)
            ->orWhereIn('payment_status', ['paid', 'refunded']))
            ->update(['original_payment_method' => DB::raw('payment_method')]);
        Schema::table('order_settlements', function (Blueprint $table) {
            $table->string('entry_kind')->nullable();
            $table->foreignId('related_settlement_id')->nullable()->constrained('order_settlements')->restrictOnDelete();
            $table->boolean('related_original')->default(false);
        });
        Schema::table('payments', fn (Blueprint $table) => $table->timestamp('superseded_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('superseded_at'));
        Schema::table('order_settlements', function (Blueprint $table) {
            $table->dropForeign(['related_settlement_id']);
            $table->dropColumn(['entry_kind', 'related_settlement_id', 'related_original']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('original_payment_method'));
    }
};
