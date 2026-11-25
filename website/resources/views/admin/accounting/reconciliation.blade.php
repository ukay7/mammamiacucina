@extends('admin.layout')
@section('title', 'Payment Reconciliation')
@include('customer.account-style')
@section('content')
@php
    $statuses = \App\Services\PaymentReconciliation::STATUSES;
@endphp
<section class="panel panel-content">
    <div class="account-section-heading"><h2>{{ $week }} to {{ $end }}</h2>
        <strong>{{ $period?->completed_at ? 'Review completed' : $outstanding.' awaiting review' }}</strong>
    </div>
    <form class="account-filters" method="get" action="{{ route('admin.payment-reconciliation.index') }}">
        <label>Choose a date in the week <input type="date" name="week" value="{{ $week }}" class="form-control" required></label>
        <button type="submit" class="btn btn-primary">Load week</button>
    </form>
    @if($period?->completed_at)
        <p>Completed by <strong>{{ $period->completed_by_name }}</strong> on {{ \App\Support\BusinessTime::format($period->completed_at) }}.</p>
        @if($period->notes)<p class="account-message">{{ $period->notes }}</p>@endif
        @if($outstanding)<p class="alert alert-warning">{{ $outstanding }} source transactions were added or changed after completion. Review these separately as adjustments.</p>@endif
    @else
        <p>Compare each recorded amount with the bank or processor statement. Enter the matching amount and reference, or record a discrepancy for follow-up.</p>
    @endif
    <div class="table-responsive"><table class="table account-table">
        <thead><tr><th scope="col">Recorded</th><th scope="col">Customer / Orders</th><th scope="col">Method / Reference</th><th scope="col">System CAD</th><th scope="col">Review</th></tr></thead>
        <tbody>
            @forelse($source->keys()->merge($reviews->keys())->unique() as $key)
                @php
                    $movement = $source->get($key);
                    $saved = $reviews->get($key, collect());
                    $last = $saved->first();
                    $record = $period?->completed_at && $last ? $last->snapshot : ($movement['snapshot'] ?? $last->snapshot);
                    $changed = $movement && $last && $movement['fingerprint'] !== $last->fingerprint;
                @endphp
                <tr>
                    <td>{{ $record['date'] }}<small class="d-block">{{ $record['type'] }}</small></td>
                    <td>{{ $record['customer'] }}<small class="d-block">{{ $record['orders'] }}</small></td>
                    <td>{{ $record['method'] }} · {{ $record['provider'] }}<small class="d-block">{{ $record['reference'] ?: 'No source reference' }}</small></td>
                    <td><strong>{{ \App\Services\AccountingReports::money($record['amount']) }}</strong></td>
                    <td>
                        @if($last)
                            <strong>{{ $statuses[$last->status] }}</strong> · {{ \App\Services\AccountingReports::money($last->matched_cents) ?: '—' }}
                            <small class="d-block">{{ $last->reviewed_by_name }} · {{ \App\Support\BusinessTime::format($last->created_at) }}</small>
                            @if($last->bank_reference)<small class="d-block">Statement reference {{ $last->bank_reference }}</small>@endif
                            @if($last->notes)<p class="account-message mb-1">{{ $last->notes }}</p>@endif
                        @endif
                        @if(! $movement || $changed || ($period?->completed_at && ! $last))
                            <small class="text-danger d-block">{{ ! $movement ? 'Source is no longer in this period.' : ($changed ? 'Source amount or details changed.' : 'Recorded after review closed.') }}</small>
                        @endif
                        @if($movement && ! $period?->completed_at)
                            <details class="mt-2" @if(! $last && $source->count() === 1) open @endif>
                                <summary>{{ $last ? 'Record another review' : 'Review transaction' }}</summary>
                                <form method="post" action="{{ route('admin.payment-reconciliation.record') }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="week" value="{{ $week }}">
                                    <input type="hidden" name="movement" value="{{ $key }}">
                                    <input type="hidden" name="fingerprint" value="{{ $movement['fingerprint'] }}">
                                    <label class="d-block">Result
                                        <select name="status" class="form-control" required>
                                            <option value="">Choose a result</option>
                                            @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="d-block">Bank / processor amount (CAD)<input class="form-control" name="matched_cad" inputmode="decimal" placeholder="{{ \App\Services\AccountingReports::money($movement['snapshot']['amount']) }}"></label>
                                    <label class="d-block">Statement reference<input class="form-control" name="bank_reference" maxlength="150"></label>
                                    <label class="d-block">Review notes<textarea class="form-control" name="notes" rows="2" maxlength="2000"></textarea></label>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Save review</button>
                                </form>
                            </details>
                        @endif
                        @if($saved->count() > 1)
                            <details class="mt-2"><summary>Earlier reviews</summary><ul class="mb-0">
                                @foreach($saved->skip(1) as $review)
                                    <li>{{ $statuses[$review->status] }} · {{ $review->reviewed_by_name }} · {{ \App\Support\BusinessTime::format($review->created_at) }}
                                        <div>{{ \App\Services\AccountingReports::money($review->matched_cents) }} {{ $review->bank_reference }} {{ $review->notes }}</div></li>
                                @endforeach
                            </ul></details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No dated payments or refunds were recorded this week.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @unless($period?->completed_at)
        <form method="post" action="{{ route('admin.payment-reconciliation.complete') }}" data-confirm="Confirm the weekly review is complete?">
            @csrf<input type="hidden" name="week" value="{{ $week }}">
            <label class="d-block">Weekly notes<textarea class="form-control" name="notes" maxlength="4000" rows="2"></textarea></label>
            <button type="submit" class="btn btn-primary" @disabled($outstanding)>Confirm weekly review</button>
        </form>
    @endunless
</section>
@if($history->isNotEmpty())
    <section class="panel panel-content">
        <h2>Recently completed weeks</h2>
        <ul>@foreach($history as $entry)<li><a href="{{ route('admin.payment-reconciliation.index', ['week' => $entry->week_start]) }}">{{ $entry->week_start }}</a> · {{ $entry->completed_by_name }}</li>@endforeach</ul>
    </section>
@endif
@endsection
