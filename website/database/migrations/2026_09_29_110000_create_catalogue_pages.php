<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_label');
            $table->string('image_path')->nullable();
            $table->string('default_image')->nullable();
            $table->string('default_thumbnail')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        $categories = DB::table('categories')->get();
        foreach (json_decode(file_get_contents(database_path('data/catalogue-pages.json')), true, 512, JSON_THROW_ON_ERROR) as $i => $page) {
            $category = $categories->first(fn ($c) => Str::slug($c->name) === Str::slug($page['label']));
            DB::table('catalogue_pages')->insert(['title' => $page['title'], 'custom_label' => $page['label'], 'category_id' => $category?->id, 'default_image' => $page['image'], 'default_thumbnail' => $page['thumbnail'], 'sort_order' => ($i + 1) * 10, 'is_active' => true, 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_pages');
    }
};
