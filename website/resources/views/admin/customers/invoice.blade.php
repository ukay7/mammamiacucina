@php($doc=app(\App\Services\DocumentTheme::class)->invoice($invoice))
@include('documents.financial')
