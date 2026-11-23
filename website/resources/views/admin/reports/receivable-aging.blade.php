@extends('admin.layout')
@section('title', 'Accounts Receivable Aging')
@section('content')
<section class="panel panel-content">
    @include('admin.reports.receivable-aging-table')
</section>
@endsection
