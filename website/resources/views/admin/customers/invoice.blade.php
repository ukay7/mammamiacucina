@php($doc=app(\App\Services\DocumentTheme::class)->order($invoice->order,$invoice))
@include('documents.financial')
