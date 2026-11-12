<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = config('email_templates.new_order_notification');
        DB::table('email_templates')->insertOrIgnore(['key' => 'new_order_notification',
            'subject' => $definition['subject'], 'body' => $definition['body'],
            'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Preserve the template and any administrator customization on rollback.
    }
};
