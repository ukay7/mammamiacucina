@extends('layouts.mmc-page',['pageTitle'=>$pageSettings->title])
@include('partials.content-styles')
@section('page-content')
<header class="content-intro">@if($pageSettings->eyebrow)<p class="mmc-eyebrow">{{ $pageSettings->eyebrow }}</p>@endif<h2>{{ $pageSettings->heading }}</h2><p class="content-prose">{{ $pageSettings->introduction }}</p></header>
@if($policies->isNotEmpty())<div class="policy-layout"><nav class="policy-nav" aria-label="Policy navigation"><strong>On this page</strong>@foreach($policies as $policy)<a href="#policy-{{ $policy->id }}">{{ $policy->title }}</a>@endforeach</nav><div>@foreach($policies as $policy)<article class="policy-card" id="policy-{{ $policy->id }}"><h2>{{ $policy->title }}</h2>@if($policy->effective_date)<p class="policy-date">Effective {{ $policy->effective_date->format('F j, Y') }}</p>@endif @if($policy->summary)<p><strong>{{ $policy->summary }}</strong></p>@endif<div class="content-prose">{{ $policy->body }}</div></article>@endforeach</div></div>
@else<div class="content-empty"><h2>Need some information?</h2><p>Our policies will be published here. In the meantime, our team can help with your questions.</p><a class="mmc-button" href="{{ route('theme.contact') }}">Contact us</a></div>@endif
@endsection
