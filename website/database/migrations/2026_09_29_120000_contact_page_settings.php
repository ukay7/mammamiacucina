<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $t) {
            $t->string('whatsapp_number', 30)->nullable();
            $t->string('website_url', 500)->nullable();
            $t->string('contact_eyebrow')->default('LET’S TALK');
            $t->string('contact_heading')->default('We’d Love to Hear from You');
            $t->text('contact_description')->nullable();
            $t->string('contact_image_path')->nullable();
            $t->string('contact_image_alt')->default('Italian cannoli and cornetti');
        });
        DB::table('general_settings')->where('id', 1)->update(['contact_description' => 'Planning a celebration or looking for your next Italian favourite? Tell us what you have in mind.']);
    }

    public function down(): void
    {
        Schema::table('general_settings', fn (Blueprint $t) => $t->dropColumn(['whatsapp_number', 'website_url', 'contact_eyebrow', 'contact_heading', 'contact_description', 'contact_image_path', 'contact_image_alt']));
    }
};
