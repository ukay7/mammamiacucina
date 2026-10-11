@extends('admin.layout')
@section('title','Reporting Centre')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/reports.css') }}">
<div class="report-intro"><div><span class="report-eyebrow">BUSINESS AT A GLANCE</span><h2>Every order. Every dollar. A clearer picture.</h2><p>Live CAD orders only · {{ now()->format('d M Y') }} · Dates use {{ config('app.timezone') }}.</p></div><a class="btn btn-outline-primary" href="{{ route('admin.reports.payments') }}">Gateway reconciliation report</a></div>
<div class="report-kpis">
@foreach([
 ['Today’s orders',$sales['orders'],'sales',false],
 ['Today’s order value',$sales['value'],'sales',true],
 ['Received today',$cash['received'],'payments',true],
 ['Refunds recorded today',$cash['refunded'],'payments',true],
 ['Net collected today',$cash['net'],'payments',true],
 ['Outstanding · all orders',$balances['due'],'balances',true],
 ['Refund due · all orders',$balances['refund_due'],'balances',true],
] as [$label,$value,$type,$money])
<a class="report-kpi" href="{{ route('admin.reports.business',['kind'=>$type,'from'=>today()->toDateString(),'to'=>today()->toDateString(),'balance'=>str_starts_with($label,'Refund due')?'refund':'due']) }}"><span>{{ $label }}</span><strong>{{ $money?'$'.number_format($value/100,2):$value }}</strong><small>{{ $money?'CAD · View breakdown →':'View orders →' }}</small></a>
@endforeach
</div>
<p class="report-note">Order value excludes cancelled orders and unconfirmed totals. Collections use recorded payment dates, including payments for older orders. Net collected means receipts minus recorded refunds, before provider fees; it is not a bank payout or profit figure.</p>
<div class="report-cards">@foreach(\App\Services\BusinessReports::TYPES as $kind=>[$title,$description,$icon])
<a class="report-card" href="{{ route('admin.reports.business',$kind) }}"><i class="fas fa-{{ $icon }}" aria-hidden="true"></i><h2>{{ $title }}</h2><p>{{ $description }}</p><span>Open report →</span></a>
@endforeach</div>
@endsection
