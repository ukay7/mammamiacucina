<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('quotations',fn(Blueprint $t)=>$t->unsignedInteger('discount_basis_points')->nullable());}
 public function down():void {Schema::table('quotations',fn(Blueprint $t)=>$t->dropColumn('discount_basis_points'));}
};
