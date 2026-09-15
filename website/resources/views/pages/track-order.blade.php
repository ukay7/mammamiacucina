@extends('layouts.mmc-page',['pageTitle'=>'Track Your Order'])
@section('page-content')
<form class="mmc-panel mmc-form" method="post" action="{{ route('order.lookup') }}">@csrf<h2>Check your order</h2><p>Enter your order number to check its progress.</p>@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif<label for="track-number">Order number</label><input class="form-control" id="track-number" name="number" value="{{ old('number') }}" placeholder="mmc-1" required maxlength="100"><p><button class="mmc-button" type="submit">Track Order</button></p></form>
@endsection
