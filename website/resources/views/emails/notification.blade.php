<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $message['subject'] }}</title></head>
<body style="margin:0;background:#f5f0e7;color:#342a22;font:16px/1.65 Arial,sans-serif"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px"><table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:100%;background:#fffaf2;border-top:4px solid #b49358"><tr><td style="padding:30px">
<p style="margin:0;color:#8a682c;font-weight:bold;letter-spacing:1px">{{ $message['company'] }}</p>
@if($test)<p style="background:#e8f0fc;padding:10px;font-size:13px">Test preview · no bulk recipients have been contacted.</p>@endif
<h1 style="font:26px Georgia,serif;color:#35291f;margin:22px 0">{{ $message['subject'] }}</h1>
<div style="overflow-wrap:anywhere">{!! $bodyHtml !!}</div>
<div style="border-top:1px solid #cfb783;margin-top:32px;padding-top:18px;font-size:12px;color:#776b5b"><strong>{{ $message['company'] }}</strong><br>{{ $message['address'] }}<br>{{ $message['phone'] }} · {{ $message['email'] }}
@if($unsubscribe)<p><a href="{{ $unsubscribe }}" style="color:#685337">Unsubscribe from bulk notifications</a><br>This does not stop order, receipt or account-security emails.</p>@endif
</div></td></tr></table></td></tr></table>@if($pixel)<img src="{{ $pixel }}" width="1" height="1" alt="" style="border:0">@endif</body></html>
