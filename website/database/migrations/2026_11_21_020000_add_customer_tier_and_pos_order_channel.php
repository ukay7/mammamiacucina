<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('tier', 16)->default('standard');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_channel', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('order_channel'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('tier'));
    }
};
