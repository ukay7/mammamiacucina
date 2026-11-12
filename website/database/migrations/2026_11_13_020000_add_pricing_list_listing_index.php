<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pricing_lists', function (Blueprint $table) {
            $table->index(['deleted_at', 'updated_at', 'id'], 'pricing_lists_listing_index');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_lists', fn (Blueprint $table) => $table->dropIndex('pricing_lists_listing_index'));
    }
};
