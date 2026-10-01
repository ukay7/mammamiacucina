<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('delivery_services',function(Blueprint $t){$t->boolean('is_active')->default(true);$t->text('notes')->nullable();});}
 public function down():void {Schema::table('delivery_services',fn(Blueprint $t)=>$t->dropColumn(['is_active','notes']));}
};
