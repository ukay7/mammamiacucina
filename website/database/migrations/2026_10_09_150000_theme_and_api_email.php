<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('theme_settings',function(Blueprint $t){$t->id();$t->json('website')->nullable();$t->json('admin')->nullable();$t->unsignedInteger('revision')->default(0);$t->timestamps();});
  DB::table('theme_settings')->insert(['id'=>1]);
  Schema::table('smtp_settings',function(Blueprint $t){$t->string('delivery_method')->default('smtp');$t->text('api_key')->nullable();});
 }
 public function down():void {Schema::dropIfExists('theme_settings');Schema::table('smtp_settings',fn(Blueprint $t)=>$t->dropColumn(['delivery_method','api_key']));}
};
