@if(auth()->user()->hasAdminPermission('orders.manage'))
<div class="panel"><div class="panel-content"><h2 class="text-danger">{{ \App\Models\Order::where('status','warehouse_issue')->count() }} warehouse issues</h2><p>Review item notes, resolve shortages and send orders back for packing.</p><a class="btn btn-danger" href="{{ route('admin.orders.index',['status'=>'warehouse_issue','from'=>'1970-01-01']) }}">Review warehouse issues</a><p class="mt-3">{{ \App\Models\Order::where('source','website')->where('status','placed')->count() }} new website orders</p><a href="{{ route('admin.orders.index',['status'=>'placed','from'=>'1970-01-01']) }}">View new orders / Send to warehouse</a></div></div>
@endif
@if(auth()->user()->hasAdminPermission('warehouse.pack'))
<div class="panel"><div class="panel-content"><h2>{{ \App\Models\Order::where('source','website')->whereIn('status',['warehouse_pending','packing'])->count() }} orders to pack</h2><p>Check each item, record shortages or mark the order ready for dispatch.</p><a class="btn btn-primary" href="{{ route('admin.orders.index') }}">View Orders / Packing</a></div></div>
@endif
