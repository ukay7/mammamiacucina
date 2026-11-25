@extends('admin.layout')
@section('title', 'Receipts')
@include('customer.account-style')
@section('content')
<section class="panel panel-content" id="customer-documents">
    <form class="account-filters" method="get" action="{{ route('customer.receipts') }}">
        <label>Find a receipt<input class="form-control" type="search" name="document_search" value="{{ request('document_search') }}" maxlength="150" placeholder="Receipt or order number"></label>
        <button class="btn btn-primary" type="submit">Search</button>
        @if(request()->filled('document_search'))<a class="btn btn-outline-primary" href="{{ route('customer.receipts') }}">Clear</a>@endif
    </form>
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Receipt or adjustment</th><th scope="col">Date</th><th scope="col">Actions</th></tr></thead>
        <tbody>@forelse($documents as $document)
            @php($url = route('customer.documents.file', [$document['kind'], $document['id']]))
            <tr><td><strong>{{ $document['name'] }}</strong><small class="d-block text-muted">{{ $document['note'] }}</small></td>
                <td>{{ \App\Support\BusinessTime::format($document['date'], 'd M Y') }}</td>
                <td><div class="account-actions"><a class="btn btn-outline-primary btn-sm" href="{{ $url }}">View</a><a class="btn btn-outline-primary btn-sm" href="{{ $url }}?download=1">Download PDF</a></div></td></tr>
        @empty<tr><td colspan="3">No receipts match your search.</td></tr>@endforelse</tbody>
    </table></div>{{ $documents->links() }}
</section>
@endsection
