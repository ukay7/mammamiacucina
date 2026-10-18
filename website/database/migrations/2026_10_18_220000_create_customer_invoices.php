<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('invoice_sequences',function(Blueprint $t){$t->unsignedInteger('id')->primary();$t->unsignedBigInteger('last_number');});
  DB::table('invoice_sequences')->insert(['id'=>1,'last_number'=>3999]);
  Schema::create('customer_invoices',function(Blueprint $t){$t->id();$t->foreignId('order_id')->unique()->constrained('orders');$t->unsignedBigInteger('number')->unique();$t->unsignedBigInteger('total_cents');$t->json('snapshot');$t->timestamp('issued_at');$t->boolean('historical')->default(false);});
 }
 public function down():void {Schema::dropIfExists('customer_invoices');Schema::dropIfExists('invoice_sequences');}
};
