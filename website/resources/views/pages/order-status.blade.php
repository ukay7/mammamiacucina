@extends('layouts.mmc-page',['pageTitle'=>'Track Your Order'])
@section('page-content')
<section class="mmc-panel"><p class="mmc-eyebrow">ORDER {{ $order->number }}</p><h2>{{ $order->status_label }}</h2><p>Last updated {{ $order->updated_at->format('d M Y, H:i') }}</p>
@if($order->status!=='cancelled')<ol class="mmc-order-progress">@foreach(array_diff_key(\App\Models\Order::STATUSES,['cancelled'=>true]) as $key=>$label)<li @if($key===$order->status) aria-current="step" @endif>{{ $label }}</li>@endforeach</ol>@else<p>This order has been cancelled.</p>@endif
<form class="mmc-form" method="post" action="{{ route('order.verify',$order->number) }}">@csrf<h3>View Full Order Details</h3><p>Enter the email used at checkout to view delivery details, products, totals and the printable order.</p>@error('email')<p role="alert">{{ $message }}</p>@enderror<label for="order-email">Order email address</label><input id="order-email" class="form-control" type="email" name="email" autocomplete="email" required maxlength="255"><p><button class="mmc-button" type="submit">Show Order Details</button></p></form>
<a class="mmc-text-link" href="{{ route('order.track-form') }}">Track another order</a></section>
@endsection
