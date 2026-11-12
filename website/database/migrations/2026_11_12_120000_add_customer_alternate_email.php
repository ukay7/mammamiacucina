<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('alternate_email')->nullable();
        });
        Schema::table('email_history', function (Blueprint $table) {
            $table->string('alternate_recipient')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_history', fn (Blueprint $table) => $table->dropColumn('alternate_recipient'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('alternate_email'));
    }
};
