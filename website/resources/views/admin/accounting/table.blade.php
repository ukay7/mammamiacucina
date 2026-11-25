<div class="table-responsive">
    <table class="table account-table">
        <thead><tr>@foreach($headers as $header)<th scope="col">{{ $header }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>@foreach($row as $value)
                    <td>@if($headers[$loop->index] === 'PDF' && $value)<a href="{{ $value }}">Download PDF</a>@else{{ $value === '' || $value === null ? '—' : $value }}@endif</td>
                @endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headers) }}">No records match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
