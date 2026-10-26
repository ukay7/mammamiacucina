<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The approved emblem is the bundled public/logo.png on every deployment.
        // Retain uploaded files; clearing this override also updates website/admin logos.
        DB::table('general_settings')->where('id', 1)->whereNotNull('logo_path')->update([
            'logo_path' => null,
            'revision' => DB::raw('revision + 1'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // A later logo chosen in General Settings must survive a code rollback.
    }
};
