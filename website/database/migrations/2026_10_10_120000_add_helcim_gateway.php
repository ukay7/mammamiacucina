<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_settings', function (Blueprint $table) {
            $table->boolean('helcim_enabled')->default(false);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->text('checkout_token')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['checkout_token', 'cancel_requested_at']));
        Schema::table('gateway_settings', fn (Blueprint $table) => $table->dropColumn('helcim_enabled'));
    }
};
