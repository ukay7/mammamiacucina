<?php
namespace App\Services;
use App\Models\{Order,Inventory,User};
use Illuminate\Support\Facades\DB;
class AdminDashboard {
 /** Mirror Order's signed ledger balance in SQL, without loading every order into memory. */
 public function balances() {
  $settlements=DB::table('order_settlements')->select('order_id')->selectRaw('SUM(amount_cents) AS settled')->groupBy('order_id');
  $total='CAST((o.subtotal_cents + o.delivery_cents + o.tax_cents) AS SIGNED)';
  $collected="(CASE WHEN p.id IS NOT NULL THEN CASE WHEN p.paid_at IS NOT NULL THEN p.amount_cents ELSE 0 END ELSE COALESCE(o.manual_collected_cents, CASE WHEN o.payment_status IN ('paid','refunded') THEN COALESCE($total,0) ELSE 0 END) END)";
  $refund="(CASE WHEN p.id IS NOT NULL THEN p.refunded_cents ELSE COALESCE(o.manual_refunded_cents, CASE WHEN o.payment_status = 'refunded' THEN $collected ELSE 0 END) END)";
  $net="(CAST($collected AS SIGNED) - CAST(COALESCE($refund,0) AS SIGNED) + COALESCE(s.settled,0))";
  return DB::table('orders as o')->leftJoin('payments as p','p.order_id','=','o.id')->leftJoinSub($settlements,'s','s.order_id','=','o.id')
   ->where('o.currency','CAD')->where(fn($q)=>$q->whereNull('p.id')->orWhere('p.mode','live'))
   ->select('o.id','o.number','o.first_name','o.last_name','o.status','o.payment_method','o.created_at')
   ->selectRaw("$total AS total_cents, $net AS net_cents, CASE WHEN o.status = 'cancelled' THEN -$net WHEN o.payment_status = 'refunded' AND $net = 0 THEN 0 WHEN $total IS NULL THEN NULL ELSE $total - $net END AS balance_cents");
 }
 public function build(User $user,int $days): array {
  $data=['days'=>$days,'start'=>today()->subDays($days-1),'end'=>now(),'sales'=>null,'stock'=>null];
  // Warehouse-only accounts keep the same restricted order visibility as the order screens.
  $canSeeOrders=$user->hasAdminPermission('orders.view') && !($user->hasAdminPermission('warehouse.pack') && !$user->hasAdminPermission('orders.manage'));
  if($canSeeOrders){
   $base=Order::where('currency','CAD')->where(fn($q)=>$q->whereDoesntHave('payment')->orWhereHas('payment',fn($p)=>$p->where('mode','live')));
   $period=(clone $base)->whereBetween('created_at',[$data['start'],$data['end']]);
   $valid=(clone $period)->whereNotIn('status',['cancelled','awaiting_payment','payment_review','transfer_pending']);
   $daily=(clone $valid)->selectRaw('DATE(created_at) AS day, COUNT(*) AS orders, SUM(subtotal_cents + delivery_cents + tax_cents) AS amount')->groupByRaw('DATE(created_at)')->get()->keyBy('day');
   $trend=collect(range(0,$days-1))->map(function($i)use($daily,$data){$date=$data['start']->copy()->addDays($i);$row=$daily->get($date->toDateString());return ['date'=>$date->format('d M'),'amount'=>(int)($row?->amount??0),'orders'=>(int)($row?->orders??0)];});
   $balance=fn()=>DB::query()->fromSub($this->balances(),'b');
   $money=$balance()->selectRaw('COALESCE(SUM(CASE WHEN balance_cents > 0 THEN balance_cents ELSE 0 END),0) AS due, COALESCE(SUM(CASE WHEN balance_cents < 0 THEN -balance_cents ELSE 0 END),0) AS refunds, COALESCE(SUM(net_cents),0) AS net, SUM(CASE WHEN balance_cents IS NULL THEN 1 ELSE 0 END) AS unpriced')->first();
   $issues=(clone $base)->whereIn('status',['warehouse_issue','payment_review','transfer_pending'])->oldest()->limit(8)->get();
   $stages=(clone $base)->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total','status');
   $mix=(clone $period)->where('status','!=','cancelled')->select('payment_method')->selectRaw('COUNT(*) AS total')->groupBy('payment_method')->pluck('total','payment_method');
   $top=DB::table('order_items')->whereIn('order_id',(clone $valid)->select('id'))->select('product_id','name')->selectRaw('SUM(quantity) AS units, SUM(line_cents) AS amount')->groupBy('product_id','name')->orderByDesc('amount')->limit(5)->get();
   $data['sales']=compact('trend','money','issues','stages','mix','top')+[
    'orders'=>(clone $period)->count(),'value'=>(int)(clone $valid)->selectRaw('SUM(subtotal_cents + delivery_cents + tax_cents) AS value')->value('value'),
    'completed'=>(clone $period)->whereIn('status',['completed','delivered'])->count(),
    'cancelled'=>(clone $period)->where('status','cancelled')->count(),
    'dues'=>$balance()->where('balance_cents','>',0)->orderByDesc('balance_cents')->limit(8)->get(),
    'refunds'=>$balance()->where('balance_cents','<',0)->orderBy('balance_cents')->limit(8)->get(),
    'due_count'=>$balance()->where('balance_cents','>',0)->count(),'refund_count'=>$balance()->where('balance_cents','<',0)->count(),
    'recent'=>(clone $base)->latest()->limit(6)->get(),
   ];
  }
  if($user->hasAdminPermission('inventory.view')){
   $low=Inventory::whereNotNull('quantity_on_hand')->whereColumn('quantity_on_hand','<=','low_stock_threshold');
   $data['stock']=['low'=>(clone $low)->count(),'empty'=>Inventory::whereNotNull('quantity_on_hand')->where('quantity_on_hand','<=',0)->count(),'unset'=>Inventory::whereNull('quantity_on_hand')->count(),'healthy'=>Inventory::whereColumn('quantity_on_hand','>','low_stock_threshold')->count(),'items'=>(clone $low)->with('product')->orderBy('quantity_on_hand')->limit(8)->get()];
  }
  return $data;
 }
}
