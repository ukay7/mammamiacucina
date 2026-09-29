@foreach(['instagram_url'=>'Instagram','facebook_url'=>'Facebook','website_url'=>'Website'] as $field=>$label)
@if($siteSettings?->$field)<li><a href="{{ $siteSettings->$field }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt" aria-hidden="true"></i><span>{{ $label }}</span></a></li>@endif
@endforeach
@if(!$siteSettings?->website_url)<li><a href="{{ route('theme.index') }}" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt" aria-hidden="true"></i><span>View Website</span></a></li>@endif
