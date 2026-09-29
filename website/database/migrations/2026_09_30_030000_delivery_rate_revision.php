<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::table('delivery_rates',fn(Blueprint $t)=>$t->unsignedInteger('revision')->default(0)); }
 public function down():void { Schema::table('delivery_rates',fn(Blueprint $t)=>$t->dropColumn('revision')); }
};
