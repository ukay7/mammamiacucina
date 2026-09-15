<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('delivery_cents')->default(0);
            $t->unsignedBigInteger('tax_cents')->default(0);
            $t->unsignedInteger('revision')->default(0);
            $t->timestamps();
        });
        DB::table('general_settings')->insert(['id' => 1, 'delivery_cents' => 0, 'tax_cents' => 0, 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('general_settings');
    }
};
