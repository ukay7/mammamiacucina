<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('customers',function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
   $t->string('name'); $t->string('email')->unique(); $t->string('phone',40)->nullable(); $t->string('account_type',20); $t->timestamps();
  });
  Schema::table('orders',fn(Blueprint $t)=>$t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete());
  DB::table('users')->whereIn('account_type',['individual','business'])->orderBy('id')->each(function($u){
   $id=DB::table('customers')->insertGetId(['user_id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'phone'=>$u->phone,'account_type'=>$u->account_type,'created_at'=>$u->created_at,'updated_at'=>now()]);
   DB::table('orders')->where('created_by',$u->id)->where('source','website')->update(['customer_id'=>$id]);
  });
 }
 public function down(): void {
  Schema::table('orders',fn(Blueprint $t)=>$t->dropConstrainedForeignId('customer_id'));
  Schema::dropIfExists('customers');
 }
};
