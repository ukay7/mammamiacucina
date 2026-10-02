@extends('admin.layout')
@section('title',$quotation->number)
@section('content')
<div class="mb-3"><a class="btn btn-primary" target="_blank" href="{{ route('admin.quotations.print',$quotation) }}">Print / Save PDF</a> @if(auth()->user()->hasAdminPermission('quotations.manage'))<a class="btn btn-outline-primary" href="{{ route('admin.quotations.edit',$quotation) }}">Edit quotation</a>@endif <a class="btn btn-outline-primary" href="{{ route('admin.quotations.index') }}">All quotations</a></div>
@include('admin.quotations.document')
@endsection
