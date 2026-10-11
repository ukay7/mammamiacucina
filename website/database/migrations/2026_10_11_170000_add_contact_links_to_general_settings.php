<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->json('contact_links')->nullable();
            $table->boolean('show_email')->default(true);
            $table->boolean('show_phone')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn(['contact_links', 'show_email', 'show_phone']));
    }
};
