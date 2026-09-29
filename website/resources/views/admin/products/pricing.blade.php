@php
$settings=$product->exists?$product->pricingDraft:null;
$pricingFields=[
'purchase_price'=>['Supplier quoted price','number',null],
'purchase_basis'=>['Supplier price basis','select',['piece'=>'Piece','carton'=>'Carton','pack'=>'Pack','kg'=>'Kilogram']],
'currency'=>['Purchase currency','select',['EUR'=>'EUR','CAD'=>'CAD']],
'pieces_per_carton'=>['Pieces per carton','number',null],
'units_per_pack'=>['Pieces in supplier-priced pack','number',null],
'grams'=>['Weight per piece (g)','number',null],
'discount'=>['Supplier discount % (e.g. 35+3)','text',null],
'fx'=>['CAD per 1 EUR','number',null],
'freight_carton'=>['Freight (CAD per carton)','number',null],
'other_unit_cost'=>['Other cost (CAD per piece)','number',null],
'profit_mode'=>['Pricing method','select',['markup'=>'Markup on cost','margin'=>'Target margin']],
'profit_percent'=>['Profit setting % (Individual)','number',null],
'business_profit_percent'=>['Profit setting % (Business)','number',null],
'carton_status'=>['Carton verification','select',['working'=>'Working value','source'=>'Source record','verified'=>'Verified','conflict'=>'Conflicting sources']],
'source_discount'=>['Original source discount expression','text',null],
];
$defaults=['purchase_price'=>$product->supplier_unit_price_eur,'purchase_basis'=>'piece','currency'=>'EUR','pieces_per_carton'=>data_get($product->dna,'pieces_per_carton'),'grams'=>$product->unit_weight_g,'discount'=>(string)($product->supplier_discount??'0'),'fx'=>'','freight_carton'=>'','other_unit_cost'=>'0','profit_mode'=>'markup','profit_percent'=>'','business_profit_percent'=>data_get($settings,'inputs.profit_percent',''),'carton_status'=>'working'];
@endphp
<div id="dna-pricing" @if($product->exists) data-quote="{{ route('admin.products.pricing.quote',$product) }}" data-save="{{ route('admin.products.pricing.save',$product) }}" @endif data-revision="{{ $settings?->revision??0 }}">
<section data-dna-panel="pricing" class="dna-panel" hidden>
<h3>Supplier cost and pricing</h3><p class="alert alert-warning">Calculate Preview shows the amounts below. Save Price saves both Individual and Business prices. Verified Business customers use Business prices on the website.</p>
@if(!$product->exists)<p class="alert alert-info">Save this new product first to enable price calculation and saving.</p>@endif
<div class="dna-pricing-grid">
@foreach($pricingFields as $key=>[$label,$type,$options])
<label>{{ $label }}
@if($type==='select')<select class="form-control" data-price-input="{{ $key }}">@foreach($options as $value=>$text)<option value="{{ $value }}" @selected(($settings?->inputs[$key]??$defaults[$key]??'')===$value)>{{ $text }}</option>@endforeach</select>
@else<input class="form-control" data-price-input="{{ $key }}" type="{{ $type }}" step="any" min="0" value="{{ $settings?->inputs[$key]??$defaults[$key]??'' }}">@endif
</label>
@endforeach
</div>
<button class="btn btn-outline-primary mt-3" type="button" data-use-source-discount>Use Original Source Discount</button><p class="mt-3 text-muted">Sequential discount: 35+3 means 35% off, then 3% off the remainder (36.95% total). Freight is divided by pieces per carton. Selling price is rounded per piece before carton totals.</p>
<div class="dna-results" data-price-results></div>
<button type="button" class="btn btn-outline-primary" data-pricing-action="quote" @disabled(!$product->exists)>Calculate Preview</button>
</section>
<div data-pricing-footer hidden class="dna-panel mt-3">
<label for="publish-choice">Selling unit to save for both customer types (CAD)</label><select class="form-control" id="publish-choice"><option value="">Calculate a preview first</option></select>
<p class="text-muted mt-2">Choose the amount for one current store unit: one piece or one carton. Stock quantities are unchanged.</p>
<button type="button" class="btn btn-primary" data-pricing-action="save" @disabled(!$product->exists)>Save Price</button>
<p class="dna-message" data-pricing-message role="status" aria-live="polite"></p>
</div></div>
