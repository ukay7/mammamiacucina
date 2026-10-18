<form method="get" class="panel panel-content mb-4" action="{{ route($approvalScreen?'admin.customers.business-requests':'admin.customers.index') }}">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end">
<label style="grid-column:1/-1">Search customers<input class="form-control" name="search" value="{{ $search }}" maxlength="200" placeholder="Customer ID, name, email, phone, business name, BIN, city or postal code"></label>
@unless($approvalScreen)
<label>Account type<select class="form-control" name="account_type"><option value="">All account types</option>@foreach(['individual'=>'Individual','business'=>'Business'] as $value=>$label)<option value="{{ $value }}" @selected(request('account_type')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Business approval<select class="form-control" name="approval"><option value="">All approvals</option>@foreach(['pending'=>'Pending approval','approved'=>'Approved'] as $value=>$label)<option value="{{ $value }}" @selected(request('approval')===$value)>{{ $label }}</option>@endforeach</select></label>
@endunless
<label>Status<select class="form-control" name="active"><option value="">All statuses</option><option value="1" @selected(request('active')==='1')>Active</option><option value="0" @selected(request('active')==='0')>Inactive</option></select></label>
<label>Email verification<select class="form-control" name="verified"><option value="">All verification states</option><option value="1" @selected(request('verified')==='1')>Verified</option><option value="0" @selected(request('verified')==='0')>Pending</option></select></label>
<label>Rows per page<select class="form-control" name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',25)===$size)>{{ $size }}</option>@endforeach</select></label>
<label>Sort by<select class="form-control" name="sort">@foreach(['id'=>'Customer ID','name'=>'Name','email'=>'Email','created_at'=>'Created date'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','id')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Direction<select class="form-control" name="direction"><option value="desc" @selected(request('direction','desc')==='desc')>Descending</option><option value="asc" @selected(request('direction')==='asc')>Ascending</option></select></label>
<div class="d-flex mb-2" style="gap:8px"><button class="btn btn-primary">Search / Apply</button><a class="btn btn-outline-primary" href="{{ route($approvalScreen?'admin.customers.business-requests':'admin.customers.index') }}">Reset</a></div>
</div>
</form>
