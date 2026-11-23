@php
    // Embedded images also render in PDFs with remote assets disabled.
    $icon = fn ($name) => 'data:image/png;base64,'.base64_encode(
        file_get_contents(resource_path('images/invoice/'.$name.'.png'))
    );
    $bankIcon = $icon('bank');
    $envelopeIcon = $icon('envelope');
@endphp
<section class="doc-invoice-remittance" aria-label="Payment Methods">
    <table role="presentation">
        <tbody><tr>
            <td class="doc-remittance-label">
                <h2 class="doc-remittance-badge"><span>Remit payment to:</span></h2>
            </td>
            <td class="doc-remittance-eft">
                <h3><img class="doc-remittance-icon" src="{{ $bankIcon }}" width="16" height="16" alt="">EFT (Direct Deposit)</h3>
                @if(count($methods['eft']) === 3)
                    <div class="doc-remittance-routing">
                        <span><strong>Transit:</strong> {{ $methods['eft']['transit'] }}</span>
                        <span><strong>Institution:</strong> {{ $methods['eft']['institution'] }}</span>
                    </div>
                    <div><strong>Account No.:</strong> {{ $methods['eft']['account'] }}</div>
                @else
                    <div>Contact us for direct deposit details.</div>
                @endif
            </td>
            <td class="doc-remittance-transfer">
                <h3><img class="doc-remittance-icon" src="{{ $envelopeIcon }}" width="16" height="16" alt="">E-transfer</h3>
                <div class="doc-remittance-email"><!--email_off-->{{ $methods['etransfer'] }}<!--/email_off--></div>
            </td>
            <td class="doc-remittance-cheque">
                <h3><img class="doc-remittance-icon" src="{{ $envelopeIcon }}" width="16" height="16" alt="">Mailing Address (Cheque)</h3>
                @if($methods['cheque'])
                    <div>{!! nl2br(e($methods['cheque'])) !!}</div>
                @else
                    <div>Contact us for the mailing address.</div>
                @endif
            </td>
        </tr></tbody>
    </table>
</section>
