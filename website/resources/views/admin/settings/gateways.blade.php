@extends('admin.layout')
@section('title','Gateway Settings')
@section('content')
<style>
.gateway-page{max-width:1200px}.gateway-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}.gateway-toolbar p{margin:0;color:#746653}.gateway-box{border:1px solid #dfc89f;background:#fffaf1;border-radius:12px;padding:24px;margin-bottom:22px}.gateway-box h2{font-size:25px;margin:0}.gateway-box h3{font-size:18px;margin:0 0 6px}.gateway-note{font-size:13px;line-height:1.6;color:#746653;margin:10px 0}.gateway-envs{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}.gateway-env{display:flex;gap:12px;align-items:flex-start;border:1px solid #dfc89f;border-radius:9px;padding:16px;cursor:pointer;margin:0}.gateway-env input{margin-top:4px;accent-color:#98091e}.gateway-env strong,.gateway-env small{display:block}.gateway-env small{margin-top:5px;color:#746653}.gateway-env:has(input:checked){background:#f2e7d3;border-color:#a47b37}.gateway-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;align-items:start}.gateway-grid .gateway-box{margin:0;min-width:0}.gateway-heading{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:16px}.gateway-switch{display:flex;align-items:center;gap:8px;white-space:nowrap;font-size:14px;margin:0;cursor:pointer}.gateway-switch input[type=checkbox]{appearance:none!important;width:40px!important;min-width:40px!important;max-width:40px!important;height:23px!important;margin:0!important;border:0!important;border-radius:20px;background:#d1cabd;position:relative;cursor:pointer;flex:none}.gateway-switch input:before{content:"";position:absolute;width:17px;height:17px;left:3px;top:3px;border-radius:50%;background:white;transition:transform .15s}.gateway-switch input:checked{background:#98091e}.gateway-switch input:checked:before{transform:translateX(17px)}.gateway-switch input:focus-visible{outline:2px solid #a47b37;outline-offset:3px}.gateway-profile{border-top:1px solid #e6d8bf;padding-top:16px;margin-top:16px}.gateway-profile summary{font-weight:600;cursor:pointer;margin-bottom:14px}.gateway-field{display:block;margin:12px 0 0;font-size:14px}.gateway-field input{margin-top:6px}.gateway-field small{font-size:12px;color:#746653}.gateway-url{display:block;overflow-wrap:anywhere;padding:10px;border-radius:6px;background:#f2e7d3;color:#54462f;font-size:12px;user-select:all}.gateway-test{margin-top:12px}.gateway-foot{margin-top:24px}.gateway-pill{font:12px Arial,sans-serif;border-radius:20px;background:#f2e7d3;color:#765323;padding:5px 10px}
@media(max-width:850px){.gateway-grid{grid-template-columns:1fr}}@media(max-width:550px){.gateway-envs{grid-template-columns:1fr}.gateway-box{padding:18px}.gateway-toolbar{align-items:flex-start;flex-direction:column}}
</style>
<div class="gateway-page">
<form method="post" action="{{ route('admin.gateways.update') }}" autocomplete="off">
@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $revision }}">
<div class="gateway-toolbar"><p>Manage online payments, credentials and checkout availability.</p><button class="btn btn-primary" type="submit">Save gateway settings</button></div>
<section class="gateway-box">
<div class="gateway-heading"><h2>Payment environment</h2><span class="gateway-pill">Currently {{ $mode==='live'?'Production':'UAT' }}</span></div>
<p class="gateway-note">The selected environment applies to new online checkouts. Each environment keeps its own credentials.</p>
<div class="gateway-envs">
<label class="gateway-env"><input type="radio" name="mode" value="sandbox" @checked(old('mode',$mode)==='sandbox')><span><strong>UAT / Sandbox</strong><small>Use test credentials only. Helcim requires a separate developer test account; selecting UAT does not convert a live token into a test token.</small></span></label>
<label class="gateway-env"><input type="radio" name="mode" value="live" @checked(old('mode',$mode)==='live')><span><strong>Production</strong><small>Accept real payments from customers.</small></span></label>
</div>
</section>
<div class="gateway-grid">
@foreach(\App\Services\GatewayConfiguration::FIELDS as $provider=>$fields)
<section class="gateway-box">
<div class="gateway-heading"><h2>{{ ['helcim'=>'Helcim','stripe'=>'Stripe','paypal'=>'PayPal'][$provider] }}</h2><label class="gateway-switch"><input type="hidden" name="{{ $provider }}_enabled" value="0"><input type="checkbox" name="{{ $provider }}_enabled" value="1" role="switch" @checked(old($provider.'_enabled',$enabled[$provider])) aria-label="Enable {{ ucfirst($provider) }}"><span>Enabled</span></label></div>
<p class="gateway-note">{{ $provider==='helcim'?'Website card payments through Helcim’s secure payment window. Enable this provider to offer Pay by Card.':($provider==='stripe'?'Legacy Stripe payments remain available for reconciliation and refunds.':'Legacy PayPal payments remain available for reconciliation and refunds.') }}</p>
@foreach(['sandbox'=>'UAT / Sandbox','live'=>'Production'] as $environment=>$title)
<details class="gateway-profile" @if($environment===old('mode',$mode)) open @endif>
<summary>{{ $title }} credentials</summary>
@foreach($fields as $field=>$label)
<label class="gateway-field" for="{{ $provider }}-{{ $environment }}-{{ $field }}">{{ $label }}
<input class="form-control" id="{{ $provider }}-{{ $environment }}-{{ $field }}" type="password" name="credentials[{{ $environment }}][{{ $provider }}][{{ $field }}]" value="" maxlength="512" autocomplete="new-password" spellcheck="false" placeholder="{{ $saved[$environment][$provider][$field]?'Saved •••••••• — leave blank to keep':'Not configured — enter '.$label }}">
<small>{{ $saved[$environment][$provider][$field]?'Saved securely. Enter a value only to replace it.':'Required to enable this provider in this environment.' }}</small></label>
@endforeach
<p class="gateway-note">Webhook endpoint for {{ $title }}:</p>
<code class="gateway-url">{{ url($provider==='helcim'?'/payments/card-events':'/payments/webhooks/'.$provider) }}?mode={{ $environment }}</code>
<button class="btn btn-outline-primary gateway-test" type="submit" form="test-{{ $provider }}-{{ $environment }}">Test saved credentials</button>
@if($provider==='helcim')
<p class="gateway-note">Helcim setup: enable checkout integration on your API token and allow your website domain. Transaction Processing must support purchases and refunds; grant transaction read access for reconciliation. The connection test checks authentication and transaction access without charging.</p>
<p class="gateway-note">Enable the Card Transaction webhook in Helcim, use the HTTPS endpoint above, and paste its verifier token in this profile. Local testing needs a public HTTPS forwarding URL for webhooks; browser confirmation and scheduled reconciliation also verify payments.</p>
@endif
<p class="gateway-note">Save changes before testing. This checks API access without charging a customer.</p>
</details>
@endforeach
</section>
@endforeach
</div>
<div class="gateway-box gateway-foot"><h3>Existing orders stay connected</h3><p class="gateway-note">Disabling a gateway stops new checkouts. Existing payments, refunds and notifications continue using their original environment. Cash remains available.</p><p class="gateway-note">Keep credentials for both environments. When replacing credentials, use keys for the same merchant account so previous orders can still be reconciled. Webhooks and the server scheduler must also be configured.</p><button class="btn btn-primary" type="submit">Save gateway settings</button>@if($updatedAt)<small class="ml-3">Last saved {{ $updatedAt->format('d M Y H:i') }}</small>@endif</div>
</form>
@foreach(array_keys(\App\Services\GatewayConfiguration::FIELDS) as $provider)@foreach(['sandbox','live'] as $environment)
<form id="test-{{ $provider }}-{{ $environment }}" action="{{ route('admin.gateways.test',[$provider,$environment]) }}" method="post">@csrf</form>
@endforeach @endforeach
</div>
@endsection
