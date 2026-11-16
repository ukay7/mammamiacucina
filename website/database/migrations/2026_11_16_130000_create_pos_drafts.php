<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_key')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_label');
            $table->unsignedInteger('item_count');
            $table->json('snapshot');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->string('receipt_mime', 100)->nullable();
            $table->char('request_hash', 64);
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['deleted_at','order_id','updated_at','id'], 'pos_drafts_listing');
        });
    }

    public function down(): void { Schema::dropIfExists('pos_drafts'); }
};
