<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('users',function(Blueprint $t){$t->timestamp('business_approved_at')->nullable();$t->unsignedBigInteger('business_approved_by')->nullable();});}
 public function down():void {Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['business_approved_at','business_approved_by']));}
};
