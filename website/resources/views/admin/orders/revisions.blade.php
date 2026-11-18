@if($order->revisions()->exists())
<section class="panel panel-content">
<h3>Revisions after delivery</h3><p>Original invoices remain unchanged. These records show the agreed changes and the balance at the time of each revision.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Revision</th><th>Date / Super Admin</th><th>Change to order total</th><th>Reason</th></tr></thead><tbody>
@foreach($order->revisions()->select(['id','number','created_by','created_at','difference_cents','reason'])->with('author:id,name')->latest('id')->get() as $revision)
<tr><td>@if($order->customer_id)<a target="_blank" href="{{ route('admin.customers.documents.file',[$order->customer_id,'revision',$revision->id]) }}">{{ $revision->number }}</a>@else{{ $revision->number }}@endif</td><td>{{ \App\Support\BusinessTime::format($revision->created_at) }}<br>{{ $revision->author?->name }}</td><td>CAD {{ number_format($revision->difference_cents/100,2) }}</td><td>{{ $revision->reason }}</td></tr>
@endforeach</tbody></table></div></section>
@endif
