<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema,Storage};
return new class extends Migration {
 public function up(): void {
  Schema::table('categories',function(Blueprint $t){$t->string('homepage_key')->nullable()->unique();});
 }
 public function down(): void { Schema::table('categories',function(Blueprint $t){$t->dropColumn('homepage_key');}); }
};
