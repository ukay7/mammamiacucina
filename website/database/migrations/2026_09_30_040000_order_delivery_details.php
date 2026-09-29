<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::table('orders',function(Blueprint $t){$t->string('delivery_to_postal',7)->nullable();$t->string('delivery_service_name')->nullable();$t->unsignedInteger('delivery_rate_cents')->nullable();});
  DB::table('orders')->whereNotNull('delivery_service')->orderBy('id')->chunkById(100,function($orders){foreach($orders as $o){
   $postal=strtoupper(preg_replace('/\s+/','',$o->postal_code));
   DB::table('orders')->where('id',$o->id)->update(['delivery_to_postal'=>strlen($postal)===6?substr($postal,0,3).' '.substr($postal,3):(strlen($postal)===3?$postal:null),'delivery_service_name'=>ucwords(str_replace('_',' ',$o->delivery_service)),'delivery_rate_cents'=>$o->delivery_cents]);
  }});
 }
 public function down():void {Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn(['delivery_to_postal','delivery_service_name','delivery_rate_cents']));}
};
