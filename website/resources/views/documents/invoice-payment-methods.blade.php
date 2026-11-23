@php
    // Embedded images also render in PDFs with remote assets disabled.
    $icon = fn ($name) => 'data:image/png;base64,'.base64_encode(
        file_get_contents(resource_path('images/invoice/'.$name.'.png'))
    );
    $bankIcon = $icon('bank');
    $envelopeIcon = $icon('envelope');
@endphp
<section class="doc-invoice-remittance" aria-label="Payment Methods">
    <h2>Payment Methods</h2>
    <table>
        <tbody><tr>
            <td>
                <h3><img class="doc-remittance-icon" src="{{ $bankIcon }}" width="16" height="16" alt="">EFT (Direct Deposit)</h3>
                @if(count($methods['eft']) === 3)
                    @foreach($methods['eft'] as $label => $number)
                        <div>{{ $label }}: {{ $number }}</div>
                    @endforeach
                @else
                    <div>Contact us for direct deposit details.</div>
                @endif
            </td>
            <td>
                <h3><img class="doc-remittance-icon" src="{{ $envelopeIcon }}" width="16" height="16" alt="">E-transfer</h3>
                <div class="doc-remittance-email"><!--email_off-->{{ $methods['etransfer'] }}<!--/email_off--></div>
            </td>
            <td>
                <h3><img class="doc-remittance-icon" src="{{ $envelopeIcon }}" width="16" height="16" alt="">Cheque by mail</h3>
                @if($methods['cheque'])
                    <div>{!! nl2br(e($methods['cheque'])) !!}</div>
                @else
                    <div>Contact us for the mailing address.</div>
                @endif
            </td>
        </tr></tbody>
    </table>
</section>
