<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('delivery_postal_zones',function(Blueprint $t){$t->string('prefix',3)->primary();$t->unsignedTinyInteger('zone');});
  Schema::create('delivery_services',function(Blueprint $t){$t->string('code',20)->primary();$t->string('name');$t->text('description')->nullable();});
  Schema::create('delivery_rates',function(Blueprint $t){$t->id();$t->string('service_code',20);$t->foreign('service_code')->references('code')->on('delivery_services');$t->unsignedTinyInteger('from_zone');$t->unsignedTinyInteger('to_zone');$t->unsignedInteger('amount_cents')->nullable();$t->unique(['service_code','from_zone','to_zone']);});
  Schema::table('general_settings',function(Blueprint $t){$t->string('warehouse_postal_code',7)->nullable();$t->boolean('matrix_delivery_enabled')->default(false);});
  Schema::table('orders',function(Blueprint $t){$t->string('delivery_service',20)->nullable();$t->string('delivery_from_postal',7)->nullable();$t->unsignedTinyInteger('delivery_from_zone')->nullable();$t->unsignedTinyInteger('delivery_to_zone')->nullable();});
 }
 public function down():void {
  Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn(['delivery_service','delivery_from_postal','delivery_from_zone','delivery_to_zone']));
  Schema::table('general_settings',fn(Blueprint $t)=>$t->dropColumn(['warehouse_postal_code','matrix_delivery_enabled']));
  Schema::dropIfExists('delivery_rates');Schema::dropIfExists('delivery_services');Schema::dropIfExists('delivery_postal_zones');
 }
};
