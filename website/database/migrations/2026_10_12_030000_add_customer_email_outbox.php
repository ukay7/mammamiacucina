<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_history', function (Blueprint $t) {
            $t->string('event_key', 191)->nullable()->unique();
            $t->longText('payload')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable()->index();
            $t->timestamp('started_at')->nullable();
            $t->string('delivery_note')->nullable();
        });
        foreach (config('email_templates') as $key => $template) {
            DB::table('email_templates')->insertOrIgnore(['key' => $key, 'subject' => $template['subject'], 'body' => $template['body'], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('email_history', function (Blueprint $t) {
            $t->dropUnique(['event_key']);
            $t->dropColumn(['event_key', 'payload', 'attempts', 'available_at', 'started_at', 'delivery_note']);
        });
    }
};
