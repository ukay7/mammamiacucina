<link rel="stylesheet" href="{{ asset('admin-assets/customer-documents.css') }}?v={{ filemtime(public_path('admin-assets/customer-documents.css')) }}">
<section class="customer-documents" aria-labelledby="customer-documents-heading">
<div class="customer-documents-heading"><div><h3 id="customer-documents-heading">Documents</h3><p>Orders, invoices, receipts and files linked to this customer account. New documents appear here automatically.</p></div><span>{{ $documents->total() }} {{ \Illuminate\Support\Str::plural('document',$documents->total()) }}</span></div>
<form class="customer-documents-filters" method="get" action="{{ url()->current() }}#customer-documents">
<label for="document-search">Find a document<input class="form-control" id="document-search" name="document_search" type="search" maxlength="150" value="{{ request('document_search') }}" placeholder="File name, order, invoice or receipt number"></label>
<label for="document-type">Document type<select class="form-control" id="document-type" name="document_type"><option value="">All documents</option>@foreach(\App\Services\CustomerDocuments::GROUPS as $key=>$label)<option value="{{ $key }}" @selected(request('document_type')===$key)>{{ $label }}</option>@endforeach</select></label>
<button class="btn btn-primary" type="submit">Search</button><a class="btn btn-outline-primary" href="{{ url()->current() }}#customer-documents">Clear</a>
</form>
<div class="table-responsive"><table class="table customer-documents-table"><thead><tr><th scope="col">Document / file name</th><th scope="col">Type</th><th scope="col">Related record</th><th scope="col">Date</th><th scope="col">Actions</th></tr></thead><tbody>
@forelse($documents as $document)
@php
 $fileUrl=auth()->user()->isCustomer()
  ? route('customer.documents.file',['kind'=>$document['kind'],'document'=>$document['id']])
  : route('admin.customers.documents.file',['customer'=>$customer,'kind'=>$document['kind'],'document'=>$document['id']]);
@endphp
<tr><td><strong>{{ $document['name'] }}</strong>@if($document['note'])<small>{{ $document['note'] }}</small>@endif</td><td data-label="Type">{{ \App\Services\CustomerDocuments::GROUPS[$document['group']] }}<small>{{ $document['format'] }}</small></td><td data-label="Related record">{{ $document['reference'] ?: '—' }}</td><td data-label="Date">{{ $document['date'] ? \App\Support\BusinessTime::format($document['date'],'d M Y H:i T') : '—' }}</td><td><div class="customer-document-actions"><a class="btn btn-outline-primary btn-sm" href="{{ $fileUrl }}" target="_blank" rel="noopener" aria-label="View {{ $document['name'] }}">View</a><a class="btn btn-outline-primary btn-sm" href="{{ $fileUrl }}?download=1" aria-label="Download {{ $document['name'] }}">Download</a></div></td></tr>
@empty
<tr><td colspan="5"><div class="customer-documents-empty"><strong>{{ request()->filled('document_search') || request()->filled('document_type') ? 'No matching documents.' : 'No documents available yet.' }}</strong><p>Generated documents and available uploads will appear here when they are linked to this account.</p></div></td></tr>
@endforelse
</tbody></table></div>
<p class="text-muted">Showing {{ $documents->firstItem() ?? 0 }}–{{ $documents->lastItem() ?? 0 }} of {{ $documents->total() }} documents. Uploaded proof and pending cheques do not confirm payment.</p>
{{ $documents->links() }}
</section>
