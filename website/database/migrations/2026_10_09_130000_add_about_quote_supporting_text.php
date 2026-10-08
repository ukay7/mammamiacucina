<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::table('about_pages',fn(Blueprint $t)=>$t->text('quote_supporting_text')->nullable());
  DB::table('about_pages')->whereNull('closing_sentence')->update(['closing_sentence'=>"Made in Italy.\nFinished by You."]);
  DB::table('about_pages')->update(['quote_supporting_text'=>"Imported from Italy. Inspired by tradition.\nPremium frozen Italian desserts.\nAuthentic Italian taste for Canadian professionals."]);
 }
 public function down():void {Schema::table('about_pages',fn(Blueprint $t)=>$t->dropColumn('quote_supporting_text'));}
};
