<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\BusinessTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PosRecommendations
{
    private function completedItems(): Builder
    {
        return DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')
            ->whereNull('o.deleted_at')->whereIn('o.status',['delivered','completed'])
            ->whereNotNull('oi.product_id')->where('oi.quantity','>',0)
            ->whereRaw('COALESCE(o.ordered_at, o.created_at) <= ?', [now()])
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('payments as payment')
                ->whereColumn('payment.order_id','o.id')->whereNull('payment.superseded_at')->where('payment.mode','sandbox'));
    }

    private function sellable(Customer $customer): Builder
    {
        $price=$customer->user->account_type==='business'
            ? 'COALESCE(p.business_selling_price_cad, p.total_selling_price_cad)' : 'p.total_selling_price_cad';
        return DB::table('products as p')->leftJoin('inventories as i','i.product_id','=','p.id')
            ->where('p.is_active',true)->whereRaw($price.' >= 0')
            ->where(fn($q)=>$q->whereNull('i.quantity_on_hand')->orWhere('i.quantity_on_hand','>=',1));
    }

    public function forCustomer(Customer $customer): array
    {
        $frequencies=$this->completedItems()->where('o.customer_id',$customer->id)->groupBy('oi.product_id')
            ->selectRaw('oi.product_id, COUNT(DISTINCT o.id) as order_count, SUM(oi.quantity) as units, MAX(COALESCE(o.ordered_at,o.created_at)) as last_order_at');
        $frequent=$this->sellable($customer)->joinSub($frequencies,'history','history.product_id','=','p.id')
            ->orderByDesc('history.order_count')->orderByDesc('history.last_order_at')->orderByDesc('history.units')->orderBy('p.id')
            ->limit(3)->get(['p.id as product_id','history.order_count']);

        // Ninety Toronto calendar days including today; drafts and unfinished orders are not sales.
        $since=BusinessTime::today()->subDays(89)->utc();
        $sales=$this->completedItems()->whereRaw('COALESCE(o.ordered_at,o.created_at) >= ?',[$since])
            ->groupBy('oi.product_id')->selectRaw('oi.product_id, SUM(oi.quantity) as units');
        $slow=$this->sellable($customer)->leftJoinSub($sales,'demand','demand.product_id','=','p.id')
            ->whereNotNull('i.quantity_on_hand')->whereRaw('i.quantity_on_hand > COALESCE(i.low_stock_threshold, 0)')
            ->whereRaw('i.quantity_on_hand > COALESCE(demand.units, 0)')
            ->selectRaw('p.id as product_id, COALESCE(demand.units, 0) as sold_units, i.quantity_on_hand - COALESCE(demand.units, 0) as excess_units')
            ->orderByDesc('excess_units')->orderByDesc('i.quantity_on_hand')->orderBy('p.id')->limit(3)->get();
        return ['frequent'=>$frequent,'slow'=>$slow];
    }
}
