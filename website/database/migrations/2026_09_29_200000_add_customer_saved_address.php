<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('customers',function(Blueprint $t){
   $t->string('address')->nullable();$t->string('city',100)->nullable();$t->string('province',100)->nullable();$t->string('postal_code',30)->nullable();$t->string('country',100)->nullable();
  });
  DB::table('customers')->orderBy('id')->each(function($customer){
   $order=DB::table('orders')->where('customer_id',$customer->id)->whereNotNull('address')->where('address','!=','')->orderByDesc('id')->first();
   if($order) DB::table('customers')->where('id',$customer->id)->update(array_intersect_key((array)$order,array_flip(['address','city','province','postal_code','country'])));
  });
 }
 public function down():void {
  Schema::table('customers',fn(Blueprint $t)=>$t->dropColumn(['address','city','province','postal_code','country']));
 }
};
