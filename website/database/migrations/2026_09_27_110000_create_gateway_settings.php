<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mode')->default('sandbox');
            $table->boolean('stripe_enabled')->default(false);
            $table->boolean('paypal_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_settings');
    }
};
