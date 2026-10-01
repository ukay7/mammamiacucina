<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('customers',function(Blueprint $t){
  $t->string('business_bin',100)->nullable(); $t->string('business_name')->nullable();
  $t->string('business_phone',40)->nullable(); $t->string('business_email')->nullable();
 }); }
 public function down(): void { Schema::table('customers',fn(Blueprint $t)=>$t->dropColumn(['business_bin','business_name','business_phone','business_email'])); }
};
