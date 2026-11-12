<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pricing_lists', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('pricing_tier', 20);
            $table->date('effective_date');
            $table->date('valid_until')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->json('brand_snapshot');
            $table->json('items');
            $table->json('filters');
            $table->boolean('show_images')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pricing_tier', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_lists');
    }
};
