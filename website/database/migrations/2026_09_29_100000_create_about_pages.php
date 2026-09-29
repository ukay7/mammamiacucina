<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_pages', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->default('OUR TRADITION');
            $table->string('heading');
            $table->text('description');
            $table->string('image_path')->nullable();
            $table->string('image_alt');
            $table->string('button_name');
            $table->string('button_page')->default('product-grid');
            $table->json('items');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        DB::table('about_pages')->insert(['id' => 1, 'heading' => "A Little Italy,\nA Lot of Love",
            'description' => "Some of the best moments happen around the table. At Mamma Mia Cucina, we celebrate those moments with Italian favourites, from delicate pastries and creamy cannoli to cakes made for sharing.\n\nWhether you’re gathering for a celebration or enjoying a quiet coffee, our collection brings a little sweetness to the occasion.",
            'image_alt' => 'Traditional Italian cassata cake', 'button_name' => 'Explore Our Collection',
            'items' => json_encode([
                ['title' => 'Italian Favourites', 'description' => 'Discover the cakes, pastries and cannoli that make every gathering feel special.'],
                ['title' => 'Made for Sharing', 'description' => 'From the first slice to the last bite, find something everyone can enjoy.'],
                ['title' => 'Moments to Savour', 'description' => 'A celebration, a coffee break, or a little treat just because.'],
            ]), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('about_pages');
    }
};
