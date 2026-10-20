<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryDashboardController extends Controller
{
    private function dates(Request $request): array
    {
        $data=$request->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d']);
        $from=$data['from']??now()->subDays(29)->toDateString();$to=$data['to']??today()->toDateString();
        abort_if($to<$from,422,'The To date must be on or after the From date.');
        return [$from,$to];
    }

    public function __invoke(Request $request)
    {
        [$from,$to]=$this->dates($request);$end=Carbon::parse($to)->addDay();
        $base=Inventory::query();
        $metrics=[
            'products'=>Product::count(),'active'=>Product::where('is_active',true)->count(),
            'tracked'=>(clone $base)->whereNotNull('quantity_on_hand')->count(),'unset'=>(clone $base)->whereNull('quantity_on_hand')->count(),
            'healthy'=>(clone $base)->whereNotNull('quantity_on_hand')->whereColumn('quantity_on_hand','>','low_stock_threshold')->count(),
            'low'=>(clone $base)->whereNotNull('quantity_on_hand')->where('quantity_on_hand','>',0)->whereColumn('quantity_on_hand','<=','low_stock_threshold')->count(),
            'out'=>(clone $base)->where('quantity_on_hand',0)->count(),'shortage'=>(clone $base)->where('quantity_on_hand','<',0)->count(),
        ];
        $activeStatuses=array_values(array_diff(array_keys(Order::STATUSES),['cancelled','completed','delivered']));
        $metrics['affected_orders']=DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')->whereIn('o.status',$activeStatuses)->where('oi.inventory_shortage_quantity','>',0)->distinct()->count('o.id');
        $metrics['untracked_orders']=DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')->whereIn('o.status',$activeStatuses)->whereNull('oi.inventory_shortage_quantity')->distinct()->count('o.id');
        $metrics['movements_today']=InventoryMovement::where('created_at','>=',today())->count();
        $metrics['movements_period']=InventoryMovement::where('created_at','>=',$from.' 00:00:00')->where('created_at','<',$end)->count();

        $stockHealth=[
            ['label'=>'Healthy','value'=>$metrics['healthy'],'class'=>'healthy'],['label'=>'Low','value'=>$metrics['low'],'class'=>'low'],
            ['label'=>'Out','value'=>$metrics['out'],'class'=>'out'],['label'=>'Shortage','value'=>$metrics['shortage'],'class'=>'shortage'],['label'=>'Not set','value'=>$metrics['unset'],'class'=>'unset'],
        ];
        $rawTrend=InventoryMovement::where('created_at','>=',$from.' 00:00:00')->where('created_at','<',$end)
            ->selectRaw('DATE(created_at) as day, SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END) as added, SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END) as used')
            ->groupByRaw('DATE(created_at)')->orderBy('day')->get()->keyBy('day');
        $trend=collect();for($day=Carbon::parse($from);$day->lt($end);$day->addDay()){$key=$day->toDateString();$trend->push((object)['day'=>$key,'added'=>(float)($rawTrend[$key]->added??0),'used'=>(float)($rawTrend[$key]->used??0)]);}
        $maxTrend=max(1,(float)$trend->max(fn($r)=>max($r->added,$r->used)));

        // Sort after loading because the quantity belongs to the related inventory row.
        $attention=Product::with('inventory')->whereHas('inventory',fn($q)=>$q->whereNotNull('quantity_on_hand')->whereColumn('quantity_on_hand','<=','low_stock_threshold'))->get()->sortBy(fn($p)=>(float)$p->inventory->quantity_on_hand)->take(12);
        $unset=Product::with('inventory')->whereHas('inventory',fn($q)=>$q->whereNull('quantity_on_hand'))->orderBy('premium_marketing_name')->limit(10)->get();
        $affected=Order::with(['items'=>fn($q)=>$q->where('inventory_shortage_quantity','>',0),'items.product'])->whereIn('status',$activeStatuses)->whereHas('items',fn($q)=>$q->where('inventory_shortage_quantity','>',0))->latest('ordered_at')->limit(10)->get();
        $usage=DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')->leftJoin('products as p','p.id','=','oi.product_id')->where('o.status','!=','cancelled')->where('o.ordered_at','>=',$from.' 00:00:00')->where('o.ordered_at','<',$end)->groupBy('oi.product_id','p.premium_marketing_name','p.uom')->orderByDesc('units')->limit(10)->get(['oi.product_id','p.premium_marketing_name','p.uom',DB::raw('SUM(oi.quantity) as units'),DB::raw('COUNT(DISTINCT o.id) as orders')]);
        $recent=InventoryMovement::with(['product','author'])->latest('id')->limit(12)->get();
        $uomTotals=Inventory::join('products','products.id','=','inventories.product_id')->whereNotNull('quantity_on_hand')->selectRaw("COALESCE(NULLIF(products.uom,''),'Unspecified') as uom, SUM(inventories.quantity_on_hand) as quantity, COUNT(*) as products")->groupByRaw("COALESCE(NULLIF(products.uom,''),'Unspecified')")->orderBy('uom')->get();

        return view('admin.inventory.dashboard',compact('from','to','metrics','stockHealth','trend','maxTrend','attention','unset','affected','usage','recent','uomTotals'));
    }

    public function export(Request $request,string $report): StreamedResponse
    {
        abort_unless(in_array($report,['snapshot','movements','usage'],true),404);[$from,$to]=$this->dates($request);$end=Carbon::parse($to)->addDay();
        return response()->streamDownload(function()use($report,$from,$end){$out=fopen('php://output','w');fputs($out,"\xEF\xBB\xBF");
            if($report==='snapshot'){fputcsv($out,['Product','Code','Active','UoM','On hand','Threshold','Status']);Product::with('inventory')->orderBy('premium_marketing_name')->chunk(200,function($rows)use($out){foreach($rows as $p){$q=$p->inventory?->quantity_on_hand;$t=$p->inventory?->low_stock_threshold??0;$status=$q===null?'Not set':($q<0?'Shortage':($q==0?'Out of stock':($q<=$t?'Low stock':'Healthy')));fputcsv($out,[$p->premium_marketing_name,$p->product_code,$p->is_active?'Yes':'No',$p->uom,$q,$t,$status]);}});
            }elseif($report==='movements'){fputcsv($out,['Date','Product','Change','Before','After','Reason','Changed by']);InventoryMovement::with(['product','author'])->where('created_at','>=',$from.' 00:00:00')->where('created_at','<',$end)->orderBy('id')->chunk(200,function($rows)use($out){foreach($rows as $m)fputcsv($out,[$m->created_at,$m->product?->premium_marketing_name,$m->quantity_change,$m->quantity_before,$m->quantity_after,$m->reason,$m->author?->name]);});
            }else{fputcsv($out,['Product','UoM','Units ordered','Orders']);$rows=DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')->leftJoin('products as p','p.id','=','oi.product_id')->where('o.status','!=','cancelled')->where('o.ordered_at','>=',$from.' 00:00:00')->where('o.ordered_at','<',$end)->groupBy('oi.product_id','p.premium_marketing_name','p.uom')->orderByDesc('units')->get(['p.premium_marketing_name','p.uom',DB::raw('SUM(oi.quantity) as units'),DB::raw('COUNT(DISTINCT o.id) as orders')]);foreach($rows as $row)fputcsv($out,(array)$row);}
            fclose($out);},'inventory-'.$report.'-'.$from.'-to-'.$to.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
