<p class="credit-document-meta"><strong>{{ $signature ? 'Electronically signed' : 'Unsigned review copy' }}</strong> · Template {{ $template['version'] }} · Customer #{{ $customer_number }}</p>
<table class="doc-info credit-document-details">
    <tr>
        <td>
            <h2>Applicant</h2>
            <p><strong>{{ $details['applicant_name'] }}</strong></p>
            <p>{{ $details['contact_name'] }} · {{ $details['contact_phone'] }}</p>
            <p class="credit-document-text">{{ $details['billing_address'] }}</p>
            <p>Requested credit limit <strong>CAD {{ number_format($details['requested_limit'], 2) }}</strong></p>
        </td>
        <td>
            <h2>Guarantor</h2>
            <p><strong>{{ $details['guarantor_name'] }}</strong></p>
            @if($details['signing_title'] ?? null)<p>{{ $details['signing_title'] }}</p>@endif
            <p class="credit-document-text">{{ $details['guarantor_address'] }}</p>
        </td>
    </tr>
</table>
<section class="credit-document-terms">
    <h2>Credit application terms</h2>
    <p class="credit-document-text">{{ $template['application_terms'] }}</p>
    <h2>Personal guarantee</h2>
    <p class="credit-document-text">{{ $template['guarantee_terms'] }}</p>
</section>
@if($signature)
    <section class="doc-box credit-document-signature">
        <h2>Electronic signature and consent</h2>
        <p class="credit-document-text">{{ $template['consent_statement'] }}</p>
        <p class="credit-document-signed-name">{{ $signature['name'] }}</p>
        <p>Submitted {{ $signature['signed_at'] }} · {{ $signature['method'] }}</p>
        <p>Verified account email <!--email_off-->{{ $signature['verified_email'] }}<!--/email_off--></p>
    </section>
@endif
