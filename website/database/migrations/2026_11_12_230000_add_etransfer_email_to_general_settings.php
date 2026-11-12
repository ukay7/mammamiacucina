<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->string('etransfer_email')->default('info@mammamiacucina.ca');
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn('etransfer_email'));
    }
};
