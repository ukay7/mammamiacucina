<style>
.handover{margin-top:16px;page-break-inside:avoid}
.handover h2{margin:0 0 3px;font-size:13px;color:#594135;text-transform:uppercase;letter-spacing:.3px}
.handover-subtitle,.handover-note{font-size:9px;color:#786b5b;margin:0 0 7px}.handover-note{margin:6px 0 0}
.handover-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:9px;line-height:1.25}
.handover-table th{padding:6px 4px;background:#594135;color:#fffaf1;border:1px solid #594135;border-bottom:2px solid #b98c45;text-align:left}
.handover-table td{height:22px;padding:3px 4px;border:1px solid #d9c8ae;vertical-align:middle;word-wrap:break-word}
.handover-table tr{page-break-inside:avoid}.handover-table td.handover-write{background:#faf6ee}
.handover-table .handover-date,.handover-table .handover-time{color:#8b7b65;text-align:center;white-space:nowrap}
.handover-table .handover-step{white-space:nowrap;font-weight:bold}.handover-process{font-weight:bold}
.handover-checkbox{display:inline-block;width:8px;height:8px;border:1px solid #9c7b48;vertical-align:middle;margin-right:3px}
.handover-tick{text-align:center}.handover-table th.handover-tick{font-family:DejaVu Sans,sans-serif}.handover-tick .handover-checkbox{margin:0}
.handover-hint{color:#83735e}.handover-option{white-space:nowrap;margin-right:5px}
.handover-table th:nth-child(1){width:3%}.handover-table th:nth-child(2){width:8%}.handover-table th:nth-child(3){width:13%}.handover-table th:nth-child(4){width:9%}.handover-table th:nth-child(5){width:25%}.handover-table th:nth-child(6){width:21%}.handover-table th:nth-child(7){width:21%}
</style>
@php
    $pickup=$doc['is_pickup'];
    $steps=$pickup
        ? ['Order created by','Order packed by','Order loaded by','Received by']
        : ['Order created by','Order packed by','Order loaded by','Delivery assigned','Delivery started','Arrived at destination','Delivered to','Payment received','Delivery completed','Payment handed over'];
@endphp
<section class="handover" aria-label="{{ $pickup?'Pickup':'Delivery' }} order handover form">
<h2>{{ $pickup?'Pickup':'Delivery' }} Order Handover Form</h2>
<p class="handover-subtitle">{{ $doc['brand']['name'] }}, order handover record</p>
<table class="handover-table"><thead><tr><th class="handover-tick">✓</th><th>Step</th><th>Date</th><th>Time</th><th>Process</th><th>Details</th><th>Confirmation</th></tr></thead><tbody>
@foreach($steps as $process)
@php($step=$loop->iteration)
<tr><td class="handover-tick"><span class="handover-checkbox"></span></td><td class="handover-step">Step {{ $step }}</td><td class="handover-date">__/__/____</td><td class="handover-time">__:__</td><td class="handover-process">{{ $process }}</td>
@if(!$pickup && $step===5)
    <td colspan="2" rowspan="2"></td>
@elseif(!$pickup && $step===6)
    {{-- Continuation of the delivery journey confirmation area. --}}
@elseif(!$pickup && $step===9)
    <td colspan="2"></td>
@else
    <td class="handover-write">@if(!$pickup && $step===8)CAD$@endif</td>
    @if($step===1)<td rowspan="3"></td>
    @elseif($step<=3){{-- Shared confirmation area for the first three steps. --}}
    @elseif($pickup || in_array($step,[7,10]))<td class="handover-write handover-hint"><span class="handover-checkbox"></span>Signature</td>
    @elseif($step===4)<td class="handover-write handover-hint"><span class="handover-checkbox"></span>Vehicle</td>
    @elseif($step===8)<td class="handover-write"><span class="handover-option"><span class="handover-checkbox"></span>Cash</span><span class="handover-option"><span class="handover-checkbox"></span>Cheque</span></td>
    @endif
@endif
</tr>
@endforeach
</tbody></table>
<p class="handover-note">Complete by hand and attach this process record to the order document.</p>
</section>
