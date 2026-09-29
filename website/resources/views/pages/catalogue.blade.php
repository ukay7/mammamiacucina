@extends('layouts.mmc-page',['pageTitle'=>'Catalogue'])
@section('page-content')
@if($hasPages)
<iframe title="Interactive Mamma Mia catalogue" src="{{ route('catalogue.reader') }}" allowfullscreen class="catalogue-frame"></iframe>
<p style="text-align:center;margin-top:12px">Browse by category, turn the pages, or select a page to zoom in.</p>
@else
<p style="text-align:center">Our catalogue is being prepared. Please check back soon.</p>
@endif
@endsection
@push('styles')
<style>.mmc-page-content:has(.catalogue-frame){max-width:1800px;padding:30px 20px}.catalogue-frame{width:100%;height:88vh;height:88dvh;min-height:530px;border:1px solid #bd9348;border-radius:12px;background:#191209}@media(max-width:600px){.mmc-page-content:has(.catalogue-frame){padding:16px 8px}.catalogue-frame{height:78dvh;min-height:480px}}</style>
@endpush
