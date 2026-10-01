<div class="panel panel-content">
<h2>Business approval requests <small>({{ $businessRequests->total() }})</small></h2>
<div class="table-responsive"><table class="table"><thead><tr><th>Customer / requested</th><th>Business name / BIN</th><th>Business contact</th><th>Email verification</th><th>Action</th></tr></thead><tbody>
@forelse($businessRequests as $business)
<tr><td>#{{ $business->id }} · {{ $business->name }}<br>{{ $business->email }}<br>{{ $business->created_at }}</td><td>{{ $business->business_name }}<br>BIN: {{ $business->business_bin }}</td><td>{{ $business->business_phone }}<br>{{ $business->business_email }}</td><td>{{ $business->user->email_verified_at?'Verified':'Pending' }}<br>{{ $business->user->is_active?'Active':'Inactive' }}</td><td><a class="btn btn-outline-primary" href="{{ route('admin.customers.show',$business) }}">Review details</a>
@if(auth()->user()->hasAdminPermission('users.manage'))
<form method="post" action="{{ route('admin.customers.approve-business',$business) }}" class="mt-2">@csrf <button class="btn btn-primary" type="submit" @disabled(!$business->user->is_active)>Approve business</button></form>
@endif
</td></tr>
@empty<tr><td colspan="5">No business accounts awaiting approval.</td></tr>@endforelse
</tbody></table></div>{{ $businessRequests->withQueryString()->links() }}</div>
