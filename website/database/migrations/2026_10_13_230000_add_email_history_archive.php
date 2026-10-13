<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('email_history', function (Blueprint $table) {
            $table->longText('archive')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('email_history', fn (Blueprint $table) => $table->dropColumn(['archive', 'archived_at']));
    }
};
