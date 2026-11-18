<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_refunds', function (Blueprint $table) {
            $table->string('kind', 20)->default('return');
        });
        Schema::create('order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('number')->nullable()->unique();
            $table->string('invoice_number')->nullable();
            $table->text('reason');
            $table->bigInteger('difference_cents');
            $table->bigInteger('tax_difference_cents');
            $table->json('before');
            $table->json('after');
            $table->json('stock_returns');
            $table->json('brand');
            $table->timestamps();
            $table->unique(['order_id', 'revision']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('order_revisions');
        Schema::table('customer_refunds', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
