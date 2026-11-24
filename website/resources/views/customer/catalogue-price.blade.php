@if($price['unit_cents'] === null)
    <span class="customer-catalogue-muted">Price on request</span>
@else
    <strong>${{ number_format($price['unit_cents'] / 100, 2) }}</strong>
    @if($price['discount_bps'])
        <small class="customer-catalogue-discount"><del>${{ number_format($price['original_unit_cents'] / 100, 2) }}</del> · {{ $price['discount_bps'] / 100 }}% off</small>
    @endif
@endif
