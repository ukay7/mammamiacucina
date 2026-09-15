<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('general_settings',function(Blueprint $t){foreach(['facebook_url','instagram_url','twitter_url'] as $field)$t->string($field,500)->nullable();});}
 public function down(): void {Schema::table('general_settings',function(Blueprint $t){$t->dropColumn(['facebook_url','instagram_url','twitter_url']);});}
};
