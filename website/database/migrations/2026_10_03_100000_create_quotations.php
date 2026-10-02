<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('customers',fn(Blueprint $t)=>$t->string('website')->nullable());
  Schema::create('quotations',function(Blueprint $t){
   $t->id();$t->foreignId('customer_id')->constrained()->restrictOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();
   $t->date('due_date');$t->string('pricing_tier');$t->json('customer_snapshot');$t->json('items');
   foreach(['subtotal_cents','line_discount_cents','discount_cents','charge_cents','tax_cents','total_cents'] as $f)$t->unsignedBigInteger($f);
   $t->unsignedInteger('charge_basis_points');$t->unsignedInteger('tax_basis_points');$t->text('notes')->nullable();$t->unsignedInteger('revision')->default(1);$t->timestamps();
  });
 }
 public function down():void {Schema::dropIfExists('quotations');Schema::table('customers',fn(Blueprint $t)=>$t->dropColumn('website'));}
};
