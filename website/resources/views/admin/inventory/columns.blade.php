<div class="inv-toolbar">
    <button type="button" class="btn btn-outline-primary" data-inventory-toggle aria-expanded="false" aria-controls="inventory-column-picker"><i class="fas fa-list" aria-hidden="true"></i> Choose columns</button>
    @foreach(['xlsx'=>'Excel','csv'=>'CSV','pdf'=>'PDF','print'=>'Print'] as $format=>$label)
    <a class="btn btn-outline-primary" data-inventory-export href="{{ route('admin.inventory.export',array_merge(request()->except(['page','format','columns']),['columns'=>implode(',',$selectedColumns),'warehouse'=>$selectedWarehouse?->id,'format'=>$format])) }}" @if($format==='print')target="_blank" rel="noopener"@endif>{{ $label }}</a>
    @endforeach
    <small>Exports include all matching products, selected columns and Current stock. Editing controls are excluded.</small>
</div>
<section class="inv-column-picker" id="inventory-column-picker" hidden data-storage="mmc-inventory-columns-{{ auth()->id() }}" data-presets="{{ json_encode($columns->presets()) }}" aria-label="Inventory columns">
    <div class="inv-column-presets">
        @foreach($columns->presets() as $name=>$keys)<button type="button" class="btn btn-sm btn-outline-primary" data-inventory-preset="{{ $name }}">{{ $name }}</button>@endforeach
        <button type="button" class="btn btn-sm btn-outline-primary" data-inventory-preset="all">All columns</button>
        <button type="button" class="btn btn-sm btn-outline-primary" data-inventory-preset="clear">Clear selection</button>
    </div>
    <div class="inv-column-options">
        @foreach($columns->labels() as $key=>$label)<label><input type="checkbox" data-inventory-check value="{{ $key }}" @checked(in_array($key,$selectedColumns,true))>{{ $label }}</label>@endforeach
        <label><input type="checkbox" checked disabled>Current stock (always included)</label>
    </div>
    <p class="small mt-3 mb-0">Choose existing product fields. Select at least one product field to export. Excel and CSV include image links; Print and PDF include photos.</p>
</section>
<p class="text-muted small"><span data-inventory-column-count>{{ count($selectedColumns)+1 }}</span> visible data columns · Current stock is always included. Update, History and Orders remain on this screen.</p>
