@php
    $buckets = \App\Services\ReceivableAging::BUCKETS;
    $columns = $buckets + ['total' => 'Total Amount Owing'];
    $money = fn ($cents) => '$'.number_format($cents / 100, 2);
@endphp
<p class="text-muted">As of {{ $aging['asOf']->format('M j, Y') }} (Toronto) · CAD. Issued invoices age from their due date, or their issue date when no due date is recorded. Current includes balances due today or later.</p>
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Customer Number</th>
                <th scope="col">Business / Customer Name</th>
                @foreach($columns as $label)
                    <th scope="col" class="text-right">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($aging['rows'] as $row)
                <tr>
                    <td>
                        @if($row['customer_id'] && empty($profile))
                            <a href="{{ route('admin.customers.show', $row['customer_id']) }}#customer-aging">#{{ $row['customer_id'] }}</a>
                        @else
                            {{ $row['customer_id'] ? '#'.$row['customer_id'] : '—' }}
                        @endif
                    </td>
                    <th scope="row">{{ $row['name'] ?: 'Guest' }} <x-business-customer-icon :business="$row['business']" /></th>
                    @foreach($columns as $key => $label)
                        <td class="text-right text-nowrap">{{ $money($row['amounts'][$key]) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="8" class="text-muted">No outstanding invoice balances.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="font-weight-bold">
                <th scope="row" colspan="2">Totals</th>
                @foreach($columns as $key => $label)
                    <td class="text-right text-nowrap">{{ $money($aging['totals'][$key]) }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</div>
