@if($entry->customer_receipt_line_id && auth()->user()->isSuper() && $entry->receiptLine?->receipt)
<br><a href="{{ route('admin.customers.receipts.show',[$o->customer_id,$entry->receiptLine->receipt]) }}">Customer receipt {{ $entry->receiptLine->receipt->number }} · Line #{{ $entry->customer_receipt_line_id }}</a>
@endif
