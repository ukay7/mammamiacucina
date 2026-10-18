<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('customers',function(Blueprint $t){$t->unsignedBigInteger('credit_limit_cents')->default(0);$t->unsignedBigInteger('overage_cents')->default(0);$t->string('credit_policy',10)->default('block');$t->unsignedInteger('order_discount_bps')->default(0);$t->unsignedSmallInteger('cheque_days')->default(7);$t->json('payment_permissions')->nullable();$t->unsignedInteger('commercial_revision')->default(0);});
  Schema::create('customer_product_discounts',function(Blueprint $t){$t->id();$t->foreignId('customer_id')->constrained()->cascadeOnDelete();$t->foreignId('product_id')->constrained()->cascadeOnDelete();$t->unsignedInteger('discount_bps')->default(0);$t->boolean('enabled')->default(false);$t->unique(['customer_id','product_id']);$t->timestamps();});
  Schema::create('customer_setting_events',function(Blueprint $t){$t->id();$t->foreignId('customer_id')->constrained()->cascadeOnDelete();$t->unsignedBigInteger('user_id')->nullable();$t->text('changes');$t->timestamp('created_at');});
  Schema::table('orders',function(Blueprint $t){$t->unsignedBigInteger('order_discount_cents')->default(0);$t->unsignedInteger('order_discount_bps')->default(0);$t->unsignedSmallInteger('cheque_days')->nullable();$t->date('cheque_due_date')->nullable()->index();$t->text('credit_warning')->nullable();});
  Schema::table('order_items',function(Blueprint $t){$t->unsignedBigInteger('original_unit_cents')->nullable();$t->unsignedInteger('discount_bps')->default(0);});
  DB::table('orders')->whereIn('status',['transfer_pending','placed','confirmed'])->update(['status'=>'parked_admin']);
  DB::table('orders')->where('status','preparing')->update(['status'=>'packing']);
 }
 public function down(): void {
  Schema::dropIfExists('customer_setting_events');Schema::dropIfExists('customer_product_discounts');
  Schema::table('order_items',fn(Blueprint $t)=>$t->dropColumn(['original_unit_cents','discount_bps']));
  Schema::table('orders',function(Blueprint $t){$t->dropIndex(['cheque_due_date']);$t->dropColumn(['order_discount_cents','order_discount_bps','cheque_days','cheque_due_date','credit_warning']);});
  Schema::table('customers',fn(Blueprint $t)=>$t->dropColumn(['credit_limit_cents','overage_cents','credit_policy','order_discount_bps','cheque_days','payment_permissions','commercial_revision']));
 }
};
