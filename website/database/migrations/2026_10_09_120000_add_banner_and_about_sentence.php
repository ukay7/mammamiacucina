<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('general_settings',fn(Blueprint $t)=>$t->string('page_banner_path')->nullable());
  Schema::table('about_pages',fn(Blueprint $t)=>$t->text('closing_sentence')->nullable());
 }
 public function down():void {
  Schema::table('general_settings',fn(Blueprint $t)=>$t->dropColumn('page_banner_path'));
  Schema::table('about_pages',fn(Blueprint $t)=>$t->dropColumn('closing_sentence'));
 }
};
