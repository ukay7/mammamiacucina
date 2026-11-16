{{-- A real table keeps instructions beside totals in both browser print and Dompdf. --}}
<table class="doc-payment-summary" role="presentation"><tbody><tr>
    <td class="doc-payment-instructions">{{ $instructions }}</td>
    <td class="doc-payment-amounts">{{ $slot }}</td>
</tr></tbody></table>
