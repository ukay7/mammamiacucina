<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){$t->string('phone',40)->nullable();$t->string('account_type',20)->nullable()->index();});
  if(!DB::table('roles')->where('name','Customer')->exists())DB::table('roles')->insert(['name'=>'Customer','permissions'=>'[]','is_super'=>false,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void {Schema::table('users',function(Blueprint $t){$t->dropColumn(['phone','account_type']);});}
};
