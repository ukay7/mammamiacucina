@extends('admin.layout')
@section('title','Delivery Rates')
@section('content')
<link rel="stylesheet" href="{{ asset('admin-assets/delivery-rates.css') }}?v={{ filemtime(public_path('admin-assets/delivery-rates.css')) }}">
<div class="delivery-admin">
<header class="dr-intro"><div><span class="dr-eyebrow">DELIVERY CONTROL CENTRE</span><h2>Every route. Every service.</h2><p>Compare delivery options and manage the prices customers see at checkout.</p></div><a class="btn btn-outline-primary" href="{{ route('admin.settings.general') }}">Warehouse settings</a></header>
<div class="dr-stats"><div><span>Dispatch from</span><strong>{{ $settings->warehouse_postal_code ?: 'Not configured' }}</strong></div><div><span>Courier services</span><strong>{{ $services->count() }}</strong></div><div><span>Zones per service</span><strong>30 × 30</strong></div><div><span>Website pricing</span><strong class="{{ $settings->matrix_delivery_enabled?'dr-live':'' }}">{{ $settings->matrix_delivery_enabled?'Matrix enabled':'Flat rate enabled' }}</strong></div></div>
<section class="dr-card" aria-labelledby="compare-title"><div class="dr-heading"><div><span class="dr-eyebrow">ROUTE CALCULATOR</span><h3 id="compare-title">Find the right delivery option</h3></div><span class="dr-chip">CAD · Base rates</span></div>
<form method="get" class="dr-route-form" action="{{ route('admin.delivery.index') }}"><input type="hidden" name="service" value="{{ $selected }}"><div><label for="dr-from">From postal code</label><input class="form-control" id="dr-from" name="from" value="{{ $from }}" maxlength="7" placeholder="M2N 1A1" required></div><div><label for="dr-to">To postal code</label><input class="form-control" id="dr-to" name="to" value="{{ $to }}" maxlength="7" placeholder="M1P 1A1" required></div><button class="btn btn-primary" type="submit">Compare all services</button></form>
@if($quoteError)<div class="alert alert-danger mt-3" role="alert">{{ $quoteError }}</div>@endif
@if(count($comparison))
<p class="dr-route-label">{{ $from }} <span>Zone {{ $fromZone }}</span> <b aria-hidden="true">→</b> {{ $to }} <span>Zone {{ $toZone }}</span></p>
@php($lowest=collect($comparison)->whereNotNull('amount_cents')->min('amount_cents'))
<div class="dr-comparison">@foreach($services as $service)
@php($quote=$comparison[$service->code]??null)
<div class="dr-option {{ $quote && $quote->amount_cents!==null && $quote->amount_cents===$lowest?'dr-best':'' }}"><h4>{{ $service->name }}</h4><strong>{{ !$quote || $quote->amount_cents===null?'Unavailable':'$'.number_format($quote->amount_cents/100,2) }}</strong>@if($quote && $quote->amount_cents!==null && $quote->amount_cents===$lowest)<span class="dr-chip">Lowest base rate</span>@endif<p>{{ $service->description }}</p>@if($quote)<a href="{{ route('admin.delivery.index',['service'=>$service->code,'edit'=>$quote->id,'from'=>$from,'to'=>$to]) }}#rate-editor">Edit this route →</a>@endif</div>
@endforeach</div>
@else<p class="dr-muted mt-3 mb-0">Enter two postal codes or three-character prefixes to compare all five services.</p>@endif
</section>
@if($editing)
<section class="dr-card dr-editor" id="rate-editor" aria-labelledby="edit-title"><div class="dr-heading"><div><span class="dr-eyebrow">EDIT ROUTE PRICE</span><h3 id="edit-title">{{ $services->firstWhere('code',$selected)?->name }} · Zone {{ $editing->from_zone }} → {{ $editing->to_zone }}</h3></div><a href="{{ route('admin.delivery.index',['service'=>$selected,'from'=>$from,'to'=>$to]) }}#rate-matrix">Close editor</a></div>
<p>This changes this direction only. The reverse route has its own price.</p>
<form method="post" action="{{ route('admin.delivery.update',$editing->id) }}" class="dr-route-form">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ old('revision',$editing->revision) }}"><div><label for="dr-available">Route availability</label><select class="form-control" id="dr-available" name="available"><option value="1" @selected((string)old('available',$editing->amount_cents!==null?'1':'0')==='1')>Available</option><option value="0" @selected((string)old('available',$editing->amount_cents!==null?'1':'0')==='0')>Unavailable</option></select></div><div><label for="dr-amount">Base price (CAD)</label><input class="form-control" id="dr-amount" name="amount" type="number" step="0.01" min="0" max="999999.99" value="{{ old('amount',$editing->amount_cents===null?'':number_format($editing->amount_cents/100,2,'.','')) }}"></div><button type="submit" class="btn btn-primary">Save delivery rate</button></form><small class="dr-muted">New quotes update immediately. Existing orders retain their saved charges. Unavailable routes cannot be selected at checkout.</small>
</section>
@endif
<section class="dr-card" id="rate-matrix" aria-labelledby="matrix-title"><div class="dr-heading"><div><span class="dr-eyebrow">VISUAL RATE MATRIX</span><h3 id="matrix-title">{{ $services->firstWhere('code',$selected)?->name }} delivery prices</h3></div><span class="dr-chip">{{ $rates->whereNotNull('amount_cents')->count() }} available · {{ $rates->whereNull('amount_cents')->count() }} unavailable</span></div>
<nav class="dr-tabs" aria-label="Delivery service">@foreach($services as $service)<a class="{{ $selected===$service->code?'is-active':'' }}" @if($selected===$service->code) aria-current="page" @endif href="{{ route('admin.delivery.index',['service'=>$service->code,'from'=>$from,'to'=>$to,'search'=>$search]) }}#rate-matrix">{{ $service->name }}</a>@endforeach</nav>
@php($currentService=$services->firstWhere('code',$selected))
<form method="post" action="{{ route('admin.delivery.description',$selected) }}" class="my-3">
@csrf @method('PUT')
<label for="service-description">{{ $currentService->name }} — customer-facing description</label>
<textarea class="form-control" id="service-description" name="description" rows="3" maxlength="1000" placeholder="For example: Delivery will happen in 60 mins">{{ old('description',$currentService->description) }}</textarea>
<p class="dr-muted mt-2">Shown in brackets beside the service name at checkout and in sale/order delivery dropdowns. Leave blank to show only the name.</p>
<button class="btn btn-primary" type="submit">Save description</button>
</form>
<div class="dr-legend"><span>Rows = From · Columns = To. Click a price to edit.</span><span><i class="dr-swatch dr-heat-0"></i>Lower <i class="dr-swatch dr-heat-4"></i>Higher <i class="dr-swatch dr-unavailable"></i>Unavailable</span></div>
@php($min=$rates->whereNotNull('amount_cents')->min('amount_cents')??0)
@php($max=$rates->whereNotNull('amount_cents')->max('amount_cents')??0)
<div class="dr-matrix-wrap" tabindex="0" role="region" aria-label="Scrollable delivery rate matrix"><table class="dr-matrix"><caption class="sr-only">{{ $selected }} rates in CAD; rows are origin zones, columns are destination zones</caption><thead><tr><th scope="col">From / To</th>@for($toNumber=1;$toNumber<=30;$toNumber++)<th scope="col" class="{{ (int)$toZone===$toNumber?'dr-axis-selected':'' }}">{{ $toNumber }}</th>@endfor</tr></thead><tbody>
@for($fromNumber=1;$fromNumber<=30;$fromNumber++)<tr><th scope="row" class="{{ (int)$fromZone===$fromNumber?'dr-axis-selected':'' }}">{{ $fromNumber }}</th>@for($toNumber=1;$toNumber<=30;$toNumber++)
@php($cell=$matrix[$fromNumber.':'.$toNumber]??null)
@php($heat=$cell && $cell->amount_cents!==null?(int)floor(4*($cell->amount_cents-$min)/max(1,$max-$min)):0)
<td class="{{ !$cell || $cell->amount_cents===null?'dr-unavailable':'dr-heat-'.$heat }} {{ (int)$fromZone===$fromNumber && (int)$toZone===$toNumber?'dr-cell-selected':'' }}">@if($cell)<a href="{{ route('admin.delivery.index',['service'=>$selected,'edit'=>$cell->id,'from'=>$from,'to'=>$to]) }}#rate-editor" aria-label="Edit {{ $selected }}, from zone {{ $fromNumber }} to zone {{ $toNumber }}, {{ $cell->amount_cents===null?'unavailable':number_format($cell->amount_cents/100,2).' CAD' }}">{{ $cell->amount_cents===null?'N/A':number_format($cell->amount_cents/100,2) }}</a>@else<span>—</span>@endif</td>
@endfor</tr>@endfor
</tbody></table></div><p class="dr-muted mt-3 mb-0">Base prices exclude courier fuel, HST, downtown and package/weight surcharges. Website tax continues to use General Settings.</p>
</section>
<section class="dr-card" aria-labelledby="postal-title" id="postal-reference"><div class="dr-heading"><div><span class="dr-eyebrow">COVERAGE DIRECTORY</span><h3 id="postal-title">Postal codes & zones</h3></div><span class="dr-chip">{{ $zones->count() }} prefixes shown</span></div>
<form method="get" action="{{ route('admin.delivery.index') }}#postal-reference" class="dr-search"><input type="hidden" name="service" value="{{ $selected }}"><label for="dr-search" class="sr-only">Search postal prefix</label><input class="form-control" id="dr-search" name="search" value="{{ $search }}" placeholder="Search a prefix, e.g. M2N" maxlength="30"><button class="btn btn-outline-primary" type="submit">Search</button><a href="{{ route('admin.delivery.index',['service'=>$selected]) }}#postal-reference">Reset</a></form>
<div class="dr-postals">@forelse($zones as $zone)<div><strong>{{ $zone->prefix }}</strong><span>Zone {{ $zone->zone }}</span></div>@empty<p>No postal prefixes match your search.</p>@endforelse</div></section>
</div>
<script src="{{ asset('admin-assets/delivery-rates.js') }}?v={{ filemtime(public_path('admin-assets/delivery-rates.js')) }}"></script>
@endsection
