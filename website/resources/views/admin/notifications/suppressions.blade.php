@extends('admin.layout')
@section('title','Unsubscribed & Suppressed Recipients')
@section('content')
@include('admin.notifications.style')
<p><a href="{{ route('admin.notifications.index') }}">← Notifications</a></p><div class="panel nt-panel"><p>These addresses are excluded from future bulk notification emails. Order, receipt and account-security emails continue through the existing email system.</p><form method="get" class="nt-filters"><label>Find an email<input class="form-control" name="q" value="{{ request('q') }}"></label><button class="btn btn-primary">Search</button></form><div class="table-responsive"><table class="table nt-table"><thead><tr><th>Email</th><th>Reason</th><th>Since</th></tr></thead><tbody>@forelse($suppressions as $s)<tr><td>{{ $s->address }}</td><td>{{ ucfirst($s->reason) }}</td><td>{{ \App\Support\BusinessTime::format($s->created_at) }}</td></tr>@empty<tr><td colspan="3">No suppressed recipients found.</td></tr>@endforelse</tbody></table></div>{{ $suppressions->links() }}</div>
@endsection
