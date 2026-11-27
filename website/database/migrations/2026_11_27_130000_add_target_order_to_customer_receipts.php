<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->foreignId('target_order_id')->nullable()->constrained('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_order_id');
        });
    }
};
