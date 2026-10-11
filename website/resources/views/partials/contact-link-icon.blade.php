@if(!empty($link['icon']))
<img src="{{ route('contact.link-icon', ['id'=>$link['id'], 'v'=>$siteSettings->revision]) }}" alt="" width="20" height="20" style="object-fit:contain;vertical-align:middle;flex-shrink:0">
@else
<img src="{{ \App\Models\GeneralSetting::defaultContactIcon($link) }}" alt="" width="20" height="20" style="object-fit:contain;vertical-align:middle;flex-shrink:0">
@endif
