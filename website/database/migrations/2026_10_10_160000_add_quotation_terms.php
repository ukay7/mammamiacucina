<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('quotations', function (Blueprint $table) { $table->string('payment_terms')->nullable(); $table->string('delivery_terms')->nullable(); }); }
    public function down(): void { Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn(['payment_terms','delivery_terms'])); }
};
