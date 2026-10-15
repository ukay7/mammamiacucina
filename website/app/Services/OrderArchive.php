<?php
namespace App\Services;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class OrderArchive {
 public function archive(Order $order, int $revision, int $actor): void {
  DB::transaction(function() use($order,$revision,$actor){
   $order=Order::lockForUpdate()->findOrFail($order->id);
   $order->assertEditable();
   if((int)$order->revision!==$revision) throw ValidationException::withMessages(['order'=>'Order changed. Refresh and review it before archiving.']);
   if($reason=$order->archiveBlockReason()) throw ValidationException::withMessages(['order'=>$reason]);
   $order->update(['archived_at'=>now(),'archived_by'=>$actor,'revision'=>$order->revision+1]);
   $order->events()->create(['user_id'=>$actor,'description'=>'Order archived. Read-only; previous status: '.$order->status_label.'.','created_at'=>now()]);
  });
 }
}
