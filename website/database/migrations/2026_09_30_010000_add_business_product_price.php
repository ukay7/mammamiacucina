<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('products',fn(Blueprint $t)=>$t->decimal('business_selling_price_cad',20,8)->nullable());}
 public function down():void {Schema::table('products',fn(Blueprint $t)=>$t->dropColumn('business_selling_price_cad'));}
};
