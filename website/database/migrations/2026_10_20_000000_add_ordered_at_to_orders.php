<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ordered_at')->nullable()->after('number')->index();
        });

        // Existing records were previously dated exclusively by created_at.
        DB::table('orders')->whereNull('ordered_at')->update(['ordered_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ordered_at']);
            $table->dropColumn('ordered_at');
        });
    }
};
