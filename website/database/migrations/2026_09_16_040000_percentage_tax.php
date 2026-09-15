<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('general_settings',fn(Blueprint $t)=>$t->unsignedInteger('tax_basis_points')->default(0));}
 public function down():void {Schema::table('general_settings',fn(Blueprint $t)=>$t->dropColumn('tax_basis_points'));}
};
