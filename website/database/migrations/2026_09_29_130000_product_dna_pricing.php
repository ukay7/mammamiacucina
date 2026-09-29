<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->json('dna')->nullable();
            $t->unsignedInteger('editor_revision')->default(0);
        });
        Schema::create('product_pricing_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('inputs');
            $t->json('options');
            $t->unsignedInteger('revision')->default(0);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('product_price_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('draft_revision');
            $t->json('snapshot');
            $t->decimal('previous_price', 20, 8)->nullable();
            $t->unsignedBigInteger('proposed_cents');
            $t->string('sale_label');
            $t->string('status')->default('pending')->index();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('review_note')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('product_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime_type');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('product_price_reviews');
        Schema::dropIfExists('product_pricing_drafts');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['dna', 'editor_revision']));
    }
};
