<link rel="stylesheet" href="{{ asset('admin-assets/warehouse-orders.css') }}?v={{ filemtime(public_path('admin-assets/warehouse-orders.css')) }}">
<section class="warehouse-orders" aria-label="Warehouse orders">
    <header class="warehouse-list-heading">
        <h1><i class="fas fa-box" aria-hidden="true"></i> {{ $completed?'Completed Orders':'Pending Orders' }}</h1>
        <p>{{ $completed?'Completed orders created by you.':'Orders currently parked at the warehouse for packing.' }}</p>
    </header>
    <details class="warehouse-filter-drawer" open>
    <summary><span><i class="fas fa-filter" aria-hidden="true"></i> Search &amp; filters @if($search || request('status'))<small> · Filters applied</small>@endif</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
    <form action="{{ route($listRoute) }}" method="get" class="warehouse-filters">
        <input type="hidden" name="sort" value="{{ request('sort','date') }}">
        <input type="hidden" name="direction" value="{{ request('direction','desc') }}">
        <label class="warehouse-search"><span class="sr-only">Search orders</span><i class="fas fa-search" aria-hidden="true"></i><input class="form-control" type="search" name="q" value="{{ $search }}" placeholder="Order number, customer or email"></label>
        <label>From<input class="form-control" type="date" name="from" value="{{ $from }}" required></label>
        <label>To<input class="form-control" type="date" name="to" value="{{ $to }}" required></label>
        <label>Status<select class="form-control" name="status"><option value="">All statuses</option>@foreach(\App\Models\Order::STATUSES as $key=>$label)@if(($key==='completed')===$completed)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endif @endforeach</select></label>
        <label>Rows<select class="form-control" name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',20)===$size)>{{ $size }}</option>@endforeach</select></label>
        <button class="btn btn-primary" type="submit"><i class="fas fa-search" aria-hidden="true"></i> Filter</button>
        <a class="btn btn-outline-primary" href="{{ route($listRoute) }}"><i class="fas fa-undo" aria-hidden="true"></i> Reset</a>
    </form>
    </details>
    <script>
    (function () {
        const drawer = document.querySelector('.warehouse-filter-drawer');
        const mobile = window.matchMedia('(max-width: 991px)');
        const resize = function () { drawer.open = !mobile.matches; };
        resize();
        mobile.addEventListener('change', resize);
    })();
    </script>
    <div class="warehouse-table-toolbar">
    <div class="warehouse-exports" aria-label="Export orders">
        @foreach(['xlsx'=>['file-excel','Excel'],'pdf'=>['file-pdf','PDF'],'print'=>['print','Print']] as $format=>$export)
        <a class="btn btn-outline-primary" href="{{ route('admin.orders.export',array_merge(request()->except('page'),['format'=>$format,'view'=>$completed?'completed':'pending'])) }}" @if($format!=='xlsx') target="_blank" rel="noopener" @endif><i class="fas fa-{{ $export[0] }}" aria-hidden="true"></i> {{ $export[1] }}</a>
        @endforeach
    </div>
    <p class="warehouse-export-help">Exports include all matching orders. For PDF, choose Save as PDF in the print dialog.</p>
    <div class="warehouse-results">
        <strong>{{ number_format($orders->total()) }} {{ $orders->total()===1?'order':'orders' }} found</strong>
        <form method="get" action="{{ route($listRoute) }}" class="warehouse-sort">
            @foreach(['q'=>$search,'from'=>$from,'to'=>$to,'status'=>request('status',''),'per_page'=>request('per_page',20),'direction'=>request('direction','desc')] as $name=>$value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
            <label for="warehouse-sort">Sort</label>
            <select id="warehouse-sort" name="sort" class="form-control">
                @foreach(['date'=>'Date','number'=>'Order number','customer'=>'Customer','creator'=>'Created by','status'=>'Status'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort','date')===$key)>{{ $label }}</option>@endforeach
            </select>
            <button class="btn btn-outline-primary" name="direction" value="{{ request('direction','desc')==='asc'?'desc':'asc' }}" title="{{ request('direction','desc')==='asc'?'Sort descending':'Sort ascending' }}" aria-label="{{ request('direction','desc')==='asc'?'Sort descending':'Sort ascending' }}"><i class="fas fa-sort-amount-{{ request('direction','desc')==='asc'?'up':'down' }}" aria-hidden="true"></i></button>
            <button class="btn btn-outline-primary warehouse-sort-apply" type="submit">Apply</button>
        </form>
    </div>
    </div>
    <div class="warehouse-mobile-orders">
        @forelse($orders as $order)
        <article class="warehouse-order-card {{ $order->status==='warehouse_issue'?'warehouse-issue-row':'' }}" aria-label="Order {{ $order->number }}">
            <div class="warehouse-card-top">
                <div><h2>{{ $order->number }}</h2><span>{{ $order->source==='pos'?'POS':'Website' }} · {{ $order->fulfillment==='pickup'?'Pick up':'Delivery' }}</span></div>
                <span class="mmc-status-pill" data-status="{{ $order->status }}">{{ $order->status_label }}</span>
            </div>
            <div class="warehouse-order-date"><span><i class="fas fa-calendar-alt" aria-hidden="true"></i> {{ $order->created_at->format('d M Y') }}</span><span><i class="fas fa-clock" aria-hidden="true"></i> {{ $order->created_at->format('H:i') }}</span></div>
            <div class="warehouse-card-customer"><i class="fas fa-user" aria-hidden="true"></i><div><strong>{{ $order->first_name }} {{ $order->last_name }}</strong><span>{{ $order->email }}</span></div></div>
            <dl class="warehouse-card-meta"><div><dt>Source</dt><dd><span class="warehouse-source">{{ $order->source==='pos'?'Quick Sale / POS':'Website' }}</span></dd></div><div><dt>Created by</dt><dd>{{ $order->creator?->name ?? ($order->created_by?'Former user':'Website customer') }}@if((int)$order->created_by===auth()->id())<small>Created by you</small>@endif</dd></div></dl>
            @if($order->status==='warehouse_issue')<p class="text-danger">Action needed — open order to review item notes.</p>@endif
            <div class="warehouse-card-actions"><a class="btn btn-primary" href="{{ route('admin.orders.show',$order) }}"><i class="fas fa-{{ in_array($order->status,['warehouse_pending','packing'])?'box':'eye' }}" aria-hidden="true"></i> {{ in_array($order->status,['warehouse_pending','packing'])?'Pack / View':'View' }}</a><a class="btn btn-outline-primary" href="{{ route('admin.orders.print',$order) }}" target="_blank" rel="noopener"><i class="fas fa-print" aria-hidden="true"></i> Print</a></div>
        </article>
        @empty
        <div class="warehouse-empty"><i class="fas fa-box-open" aria-hidden="true"></i><h2>No orders found</h2><p>Try another date range or clear your search filters.</p></div>
        @endforelse
        <div class="warehouse-pagination"><p>Showing {{ $orders->firstItem()??0 }}–{{ $orders->lastItem()??0 }} of {{ $orders->total() }} orders</p>{{ $orders->links() }}</div>
    </div>
    <div class="warehouse-desktop-orders">@include('admin.orders.list-table')</div>
</section>
<script>
document.getElementById('warehouse-sort').addEventListener('change', function () { this.form.requestSubmit(); });
</script>
