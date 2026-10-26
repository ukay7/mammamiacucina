<section class="inv-table-card"><div class="table-responsive inv-table-scroll"><table class="table table-hover inv-table"><thead><tr>
@foreach($columns->labels() as $key=>$label)<th data-inventory-column="{{ $key }}" @if(!in_array($key,$selectedColumns,true))hidden @endif>{{ $label }}</th>@endforeach
<th>Current stock</th><th class="inv-editing-column">New quantity</th><th class="inv-editing-column">Warning below</th><th class="inv-actions-column">Actions</th></tr>
<tr class="inv-column-filters">
@foreach($columns->labels() as $key=>$label)
<th data-inventory-column="{{ $key }}" @if(!in_array($key,$selectedColumns,true))hidden @endif>
@if($key==='premium_marketing_name')<input class="form-control form-control-sm" form="inventory-filters" name="product" value="{{ request('product') }}" placeholder="Filter product name" aria-label="Filter product name">
@elseif($key==='category')<select class="form-control form-control-sm" data-category-mirror aria-label="Filter category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>@endforeach</select>
@elseif($key==='uom')<input class="form-control form-control-sm" form="inventory-filters" name="uom" value="{{ request('uom') }}" placeholder="UoM" aria-label="Filter unit of measure">
@elseif($key==='product_code')<input class="form-control form-control-sm" form="inventory-filters" name="code" value="{{ request('code') }}" placeholder="Product code" aria-label="Filter product code">
@elseif($key==='record_id')<input class="form-control form-control-sm" type="number" min="1" form="inventory-filters" name="record_id" value="{{ request('record_id') }}" placeholder="ID" aria-label="Filter record ID">
@endif</th>
@endforeach
<th><div class="inv-range"><input class="form-control form-control-sm" type="number" step="any" form="inventory-filters" name="quantity_min" value="{{ request('quantity_min') }}" placeholder="Min" aria-label="Minimum current stock"><input class="form-control form-control-sm" type="number" step="any" form="inventory-filters" name="quantity_max" value="{{ request('quantity_max') }}" placeholder="Max" aria-label="Maximum current stock"></div></th>
<th class="inv-editing-column"><small class="text-muted">Enter stock below</small></th>
<th class="inv-editing-column"><div class="inv-range"><input class="form-control form-control-sm" type="number" step="any" min="0" form="inventory-filters" name="threshold_min" value="{{ request('threshold_min') }}" placeholder="Min" aria-label="Minimum warning threshold"><input class="form-control form-control-sm" type="number" step="any" min="0" form="inventory-filters" name="threshold_max" value="{{ request('threshold_max') }}" placeholder="Max" aria-label="Maximum warning threshold"></div></th>
<th class="inv-actions-column"><button type="submit" form="inventory-filters" class="btn btn-outline-primary btn-sm"><i class="fas fa-filter" aria-hidden="true"></i> Apply filters</button></th>
</tr></thead><tbody>
@forelse($products as $product)
@php($qty=$product->stock_quantity)
@php($threshold=(float)($product->inventory?->low_stock_threshold??0))
@php($state=$qty===null?'unset':((float)$qty<0?'shortage':((float)$qty==0?'out':((float)$qty<=$threshold?'low':'healthy'))))
@php($values=$columns->values($product))
<tr data-inventory-row data-state="{{ $state }}">
@foreach($columns->labels() as $key=>$label)
<td data-inventory-column="{{ $key }}" @if(!in_array($key,$selectedColumns,true))hidden @endif>
@if($key==='image')@if($values[$key])<img class="mmc-lazy-image inv-product-image" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="{{ $values[$key] }}" alt="{{ $product->premium_marketing_name }}" width="48" height="48" decoding="async">@else<span class="inv-image-missing">No image</span>@endif
@elseif($key==='premium_marketing_name')<strong class="inv-product-name">{{ $values[$key] }}</strong>
@elseif($key==='status')<span class="inv-product-state {{ $product->is_active?'active':'inactive' }}">{{ $values[$key] }}</span>
@else{{ $columns->format($values[$key],$key) }}@endif
</td>
@endforeach
<td><div class="inv-current"><strong data-current>{{ \App\Support\NumberDisplay::quantity($qty,'Not set') }}</strong><small>{{ $selectedWarehouse?->name }}</small><span class="inv-status {{ $state }}" data-stock-label>{{ ['healthy'=>'Healthy','low'=>'Low stock','out'=>'Out of stock','shortage'=>'Shortage','unset'=>'Not set'][$state] }}</span><small>Total: {{ \App\Support\NumberDisplay::quantity($product->inventory?->quantity_on_hand,'Not set') }}</small></div></td>
<td colspan="2" class="inv-editing-column">@if(auth()->user()->hasAdminPermission('inventory.manage'))<form class="inv-adjust-form" method="post" action="{{ route('admin.inventory.adjust',$product) }}" data-product="{{ $product->premium_marketing_name }} — {{ $selectedWarehouse?->name }}">@csrf<input type="hidden" name="warehouse_id" value="{{ $selectedWarehouse?->id }}"><input type="hidden" name="action" value="set"><input type="hidden" name="expected_quantity" value="{{ $qty }}"><input type="hidden" name="reason" value=""><label><span class="sr-only">New quantity</span><input class="form-control" type="number" name="quantity" value="{{ $qty }}" step="0.001" min="0" max="999999999" placeholder="Set quantity" required></label><label><span class="sr-only">Low-stock threshold</span><input class="form-control" type="number" name="low_stock_threshold" value="{{ $threshold }}" step="0.001" min="0" max="999999999" required></label><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Update</button></form>@else<span class="text-muted">View only</span>@endif</td>
<td class="inv-actions-column"><div class="inv-actions"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.inventory.show',$product) }}"><i class="fas fa-history"></i> History</a><button class="btn btn-outline-primary btn-sm" type="button" data-orders-url="{{ route('admin.inventory.orders',$product) }}"><i class="fas fa-shopping-cart"></i> Orders</button></div></td>
</tr>@empty<tr><td data-inventory-empty colspan="{{ count($selectedColumns)+4 }}" class="text-center py-5">No products match these filters.</td></tr>@endforelse
</tbody></table></div><footer class="inv-table-footer"><span>Showing {{ $products->firstItem()??0 }}–{{ $products->lastItem()??0 }} of {{ $products->total() }} products</span>{{ $products->links('pagination::bootstrap-4') }}</footer></section>
