@extends('admin.layout')
@section('title',\App\Services\BusinessReports::TYPES[$kind][0])
@section('content')
@php
$cash = in_array($kind,['payments','methods']);
$money = fn($value) => $value === null ? 'Unconfirmed' : ($value<0?'−':'').'$'.number_format(abs($value)/100,2);
$labels = ['cash'=>'Cash','etransfer'=>'E-transfer','card'=>'Card','paypal'=>'PayPal'];
$linkFilters = array_filter($filters,fn($v)=>$v!==null && $v!=='');
@endphp
<link rel="stylesheet" href="{{ asset('admin-assets/reports.css') }}">
<div class="report-intro"><div><a href="{{ route('admin.reports.index') }}">← Reporting Centre</a><p>{{ \App\Services\BusinessReports::TYPES[$kind][1] }}</p></div><div class="report-actions"><a class="btn btn-outline-primary" href="{{ route('admin.reports.business.export',['kind'=>$kind]+$linkFilters) }}">Export CSV</a> <button class="btn btn-outline-primary" type="button" onclick="window.print()">Print</button></div></div>
<nav class="report-tabs" aria-label="Reports">@foreach(\App\Services\BusinessReports::TYPES as $key=>$definition)<a class="{{ $key===$kind?'current':'' }}" href="{{ route('admin.reports.business',$key) }}">{{ $definition[0] }}</a>@endforeach</nav>
<form class="report-filters" method="get">
@if($kind!=='balances')
<label>From<input class="form-control" type="date" name="from" value="{{ $filters['from'] }}" required></label><label>To<input class="form-control" type="date" name="to" value="{{ $filters['to'] }}" required></label>
@endif
<label>{{ $cash?'Transaction method':'Order payment method' }}<select class="form-control" name="method"><option value="">All methods</option>@foreach($labels as $key=>$label)<option value="{{ $key }}" @selected(($filters['method']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
@if($cash)<label>Provider<select class="form-control" name="provider"><option value="">All providers</option>@foreach(['manual'=>'Manual / terminal','helcim'=>'Helcim','stripe'=>'Stripe','paypal'=>'PayPal'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['provider']??'')===$key)>{{ $label }}</option>@endforeach</select></label>@endif
<label>Source<select class="form-control" name="source"><option value="">Website & POS</option>@foreach(['website'=>'Website','pos'=>'POS'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['source']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Order / customer search<input class="form-control" name="q" value="{{ $filters['q']??'' }}" maxlength="120" placeholder="Order, name or email"></label>
@if(!empty($filters['customer']))<input type="hidden" name="customer" value="{{ $filters['customer'] }}">@endif
@if($kind==='balances')<label>Balance type<select class="form-control" name="balance"><option value="due" @selected(($filters['balance']??'due')==='due')>Amount due</option><option value="refund" @selected(($filters['balance']??'')==='refund')>Refund due</option></select></label>@endif
@if($kind==='fulfilment')
<label>Schedule<select class="form-control" name="schedule">@foreach(['scheduled'=>'Scheduled in date range','overdue'=>'Overdue · before today','unscheduled'=>'Unscheduled · all dates'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['schedule']??'scheduled')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Fulfillment<select class="form-control" name="fulfillment"><option value="">Delivery & pickup</option>@foreach(['delivery'=>'Delivery','pickup'=>'Pickup'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['fulfillment']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
@endif
<div class="report-actions"><button class="btn btn-primary">Apply filters</button><a class="btn btn-outline-primary" href="{{ route('admin.reports.business',$kind) }}">Reset</a></div>
</form>
@if($kind!=='balances')<div class="report-tabs" aria-label="Date shortcuts">@foreach(['Today'=>0,'Tomorrow'=>1,'Day after tomorrow'=>2] as $label=>$offset)@if($kind==='fulfilment'||$offset===0)<a href="{{ route('admin.reports.business',['kind'=>$kind]+array_replace($linkFilters,['from'=>today()->addDays($offset)->toDateString(),'to'=>today()->addDays($offset)->toDateString(),'schedule'=>'scheduled'])) }}">{{ $label }}</a>@endif @endforeach @if($kind==='fulfilment')<a href="{{ route('admin.reports.business',['kind'=>$kind]+array_replace($linkFilters,['schedule'=>'unscheduled'])) }}">Unscheduled</a>@endif</div>@endif
<p class="report-timing">Live CAD only · {{ config('app.timezone') }} · Generated {{ now()->format('d M Y H:i') }}.
@if($cash) Payment dates use when receipts were recorded; gateway receipts use paid-at and confirmed refunds use their entry creation date. Excludes fees and bank payout timing. Order creation dates do not limit this report.
@elseif($kind==='balances') Current balances across all order dates; this is not a historical balance snapshot. Cancelled orders with money retained are shown as refund due.
@elseif($kind==='fulfilment') Planned date filters apply to open orders. Overdue / Unscheduled ignore the date range. Payment-pending orders remain visible with their status and are not automatically approved for dispatch.
@else Orders created {{ $filters['from'] }} to {{ $filters['to'] }}; received amounts and balances reflect their current position, including later payments/refunds. Cancelled orders are excluded from order value.
@endif</p>
@if($undated)<div class="alert alert-warning">{{ $undated }} historic payment/refund entries have no reliable date and are excluded from dated collections. Their amounts remain included in current order balances.</div>@endif
@if(!$cash && ($summary['unpriced']??0))<div class="alert alert-warning">{{ $summary['unpriced'] }} orders have unconfirmed totals. They are not treated as zero-value sales or known outstanding balances.</div>@endif
<div class="report-kpis">
@foreach($cash?['transactions'=>'Transactions','received'=>'Received','refunded'=>'Refunded','net'=>'Net collected']:['orders'=>'Orders','value'=>'Order value','received'=>'Net received','due'=>'Amount due','refund_due'=>'Refund due'] as $key=>$label)
<div class="report-kpi"><span>{{ $label }}</span><strong>{{ in_array($key,['orders','transactions'])?$summary[$key]:$money($summary[$key]) }}</strong><small>{{ in_array($key,['orders','transactions'])?'Matching filters':'CAD' }}</small></div>
@endforeach
</div>
@if($trend->isNotEmpty())
<div class="report-chart"><h2>{{ $cash?'Daily receipts':'Daily order value' }}</h2><div class="report-bars" role="img" aria-label="Daily amounts; exact values are available in the table and CSV">
@foreach($trend as $point)<div class="report-bar" title="{{ $point->day }} · {{ $money($point->amount) }}"><span style="height:{{ max(1,round(($point->amount??0)/max(1,$trend->max('amount'))*100,2)) }}%"></span></div>@endforeach
</div><small><span>{{ $trend->first()->day }}</span><span>{{ $trend->last()->day }}</span></small></div>
@endif
<div class="report-table-panel"><div style="overflow-x:auto"><table class="report-table"><thead><tr>
@if($kind==='customers')<th>Customer</th><th>Orders</th><th>Order value</th><th>Net received</th><th>Amount due</th><th>Refund due</th>
@elseif($kind==='methods')<th>Method / provider</th><th>Transactions</th><th>Orders</th><th>Received</th><th>Refunded</th><th>Net collected</th>
@elseif($kind==='payments')<th>Recorded date</th><th>Order / customer</th><th>Method / provider</th><th>Entry</th><th>Amount CAD</th><th>Reference</th>
@else<th>Order / customer</th><th>{{ $kind==='fulfilment'?'Planned date':'Order date' }}</th><th>Status</th><th>Method / source</th><th>Order total</th><th>Net received</th><th>Amount due</th><th>Refund due</th>@endif
</tr></thead><tbody>
@forelse($rows as $row)<tr>
@if($kind==='customers')
<td><a href="{{ route('admin.reports.business',['kind'=>'sales']+array_replace($linkFilters,['customer'=>$row->customer_id?'id:'.$row->customer_id:'email:'.$row->guest_email])) }}">{{ $row->first_name }} {{ $row->last_name }}</a><small>{{ $row->email }}</small>@if($row->unpriced)<small>{{ $row->unpriced }} unconfirmed totals</small>@endif</td><td>{{ $row->orders }}</td><td>{{ $money($row->value) }}</td><td>{{ $money($row->received) }}</td><td>{{ $money($row->due) }}</td><td>{{ $money($row->refund_due) }}</td>
@elseif($kind==='methods')
<td><a href="{{ route('admin.reports.business',['kind'=>'payments']+array_replace($linkFilters,['method'=>$row->method,'provider'=>$row->provider])) }}">{{ $labels[$row->method]??$row->method }}</a><small>{{ ucfirst($row->provider) }}</small></td><td>{{ $row->transactions }}</td><td>{{ $row->orders }}</td><td>{{ $money($row->received) }}</td><td>{{ $money($row->refunded) }}</td><td>{{ $money($row->net) }}</td>
@elseif($kind==='payments')
<td>{{ $row->occurred_at }}</td><td><button type="button" class="report-link" data-report-order="{{ route('admin.reports.order',$row->order_id) }}">{{ $row->number }}</button><small>{{ $row->first_name }} {{ $row->last_name }}</small></td><td>{{ $labels[$row->method]??$row->method }}<small>{{ ucfirst($row->provider) }}</small></td><td>{{ $row->entry_type }}</td><td>{{ $row->amount_cents>0?'+':'' }}{{ $money($row->amount_cents) }}</td><td>{{ $row->reference?:'—' }}</td>
@else
<td><button type="button" class="report-link" data-report-order="{{ route('admin.reports.order',$row->id) }}">{{ $row->number }}</button><small>{{ $row->first_name }} {{ $row->last_name }}</small></td><td>{{ $kind==='fulfilment'?($row->fulfillment_date?:'Unscheduled'):substr($row->ordered_at,0,10) }}@if($kind==='fulfilment')<small>{{ ucfirst($row->fulfillment) }}</small>@endif</td><td><span class="report-pill">{{ \App\Models\Order::STATUSES[$row->status]??$row->status }}</span><small>{{ ucfirst(str_replace('_',' ',$row->payment_status)) }}</small></td><td>{{ $labels[$row->payment_method]??$row->payment_method }}<small>{{ strtoupper($row->source) }}</small></td><td>{{ $money($row->total_cents) }}</td><td>{{ $money($row->net_cents) }}</td><td><button type="button" class="report-link" data-report-order="{{ route('admin.reports.order',$row->id) }}">{{ $money($row->balance_cents===null?null:max(0,$row->balance_cents)) }}</button></td><td><button type="button" class="report-link" data-report-order="{{ route('admin.reports.order',$row->id) }}">{{ $money($row->balance_cents===null?null:max(0,-$row->balance_cents)) }}</button></td>
@endif
</tr>@empty<tr><td colspan="8" class="report-empty">No matching records. Try another date range or reset the filters.@if($kind==='fulfilment') Start with Unscheduled to assign planned dates.@endif</td></tr>@endforelse
</tbody></table></div><div class="report-pagination">{{ $rows->links() }}</div></div>
<dialog class="report-dialog" id="report-order-dialog" aria-labelledby="report-dialog-title"><div class="report-dialog-header"><h2 id="report-dialog-title">Order breakdown</h2><button class="btn btn-outline-primary" type="button" data-close-report>Close</button></div><div class="report-dialog-body" id="report-order-body"></div></dialog>
<script src="{{ asset('admin-assets/reports.js') }}" defer></script>
@endsection
