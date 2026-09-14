@extends('layouts.mmc-page', ['pageTitle' => 'Our Products'])
@section('page-content')
<div data-live-catalogue data-grid-path="{{ parse_url(route('theme.product-grid'),PHP_URL_PATH) }}">
<div class="mmc-catalogue-loading" data-catalogue-status role="status" hidden><span class="mmc-catalogue-spinner" aria-hidden="true"></span>Loading products…</div>
<div class="mmc-catalogue-error" data-catalogue-error role="alert" hidden><span></span> <button type="button" class="mmc-toolbar-apply">Retry</button></div>
<div data-catalogue-content aria-busy="false">@include('partials.catalogue-grid')</div>
</div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/mmc-catalogue.js') }}?v={{ filemtime(public_path('assets/js/mmc-catalogue.js')) }}"></script>@endpush
