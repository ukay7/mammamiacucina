<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A checkout deadline must never become an implicit ON UPDATE timestamp on MySQL/MariaDB.
        Schema::table('payments', fn (Blueprint $table) => $table->dateTime('expires_at')->change());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->timestamp('expires_at')->change());
    }
};
