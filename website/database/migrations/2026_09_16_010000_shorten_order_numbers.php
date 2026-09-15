<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  DB::transaction(function(){
   DB::table('orders')->orderBy('id')->chunkById(200,function($orders){
    foreach($orders as $order){
     $number='mmc-'.$order->id;
     if($order->number===$number)continue;
     DB::table('orders')->where('id',$order->id)->update(['number'=>$number]);
     DB::table('inventory_movements')->where('reason','Order '.$order->number)->update(['reason'=>'Order '.$number]);
    }
   });
  });
 }
 public function down(): void {
  // Keep assigned customer-facing references when rolling back code.
 }
};
