<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('smtp_settings',function(Blueprint $table){
  $table->id();$table->boolean('enabled')->default(false);$table->string('host')->default('smtp.gmail.com');
  $table->unsignedSmallInteger('port')->default(587);$table->string('encryption',10)->default('tls');
  $table->string('username')->nullable();$table->text('password')->nullable();
  $table->string('from_address')->default('info@mammamiacucina.ca');$table->string('from_name')->default('Mamma Mia Cucina');
  $table->unsignedInteger('revision')->default(0);$table->timestamps();
 });}
 public function down():void {Schema::dropIfExists('smtp_settings');}
};
