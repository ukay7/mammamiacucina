<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'product_id']);
        });
        DB::table('products')->orderBy('id')->chunkById(500, function ($products) {
            foreach ($products as $p) {
                DB::table('category_product')->insert(['category_id' => $p->category_id, 'product_id' => $p->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
