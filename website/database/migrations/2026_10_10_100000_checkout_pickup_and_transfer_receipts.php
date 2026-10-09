<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('general_settings', function(Blueprint $t){$t->text('pickup_address')->nullable();});
  Schema::table('orders', function(Blueprint $t){
   $t->text('pickup_address')->nullable();
   $t->string('transfer_receipt_path')->nullable();
   $t->timestamp('transfer_receipt_uploaded_at')->nullable();
  });
 }
 public function down(): void {
  Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn(['pickup_address','transfer_receipt_path','transfer_receipt_uploaded_at']));
  Schema::table('general_settings',fn(Blueprint $t)=>$t->dropColumn('pickup_address'));
 }
};
