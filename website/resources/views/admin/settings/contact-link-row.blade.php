<tr data-index="{{ $index }}">
<td><input type="hidden" name="links[{{ $index }}][id]" value="{{ $entry['id'] ?? '' }}"><input class="form-control link-label" aria-label="Link name" name="links[{{ $index }}][label]" value="{{ $entry['label'] }}" required maxlength="80" placeholder="e.g. LinkedIn"></td>
<td><input class="form-control link-url" type="url" aria-label="Link URL" name="links[{{ $index }}][url]" value="{{ $entry['url'] }}" required maxlength="2000" placeholder="https://..."></td>
<td>
@php($savedLink = collect($settings->contactLinks())->firstWhere('id', $entry['id'] ?? ''))
<div class="contact-link-icon-preview"><img src="{{ !empty($savedLink['icon']) ? route('contact.link-icon',['id'=>$entry['id'],'v'=>$settings->revision]) : \App\Models\GeneralSetting::defaultContactIcon($entry) }}" alt="{{ $entry['label'] }} icon" width="28" height="28" style="object-fit:contain"><small>{{ !empty($savedLink['icon']) ? 'Uploaded icon' : 'Default icon' }}</small></div>
@if(!empty($savedLink['icon']))<label><input type="checkbox" name="links[{{ $index }}][remove_icon]" value="1" @checked($entry['remove_icon'] ?? false)> Use default icon</label>@endif
<input type="file" aria-label="Upload link icon" name="links[{{ $index }}][icon]" accept="image/png,image/jpeg,image/webp"></td>
<td><select class="form-control" aria-label="Link status" name="links[{{ $index }}][active]"><option value="1" @selected($entry['active'])>Active</option><option value="0" @selected(!$entry['active'])>Inactive</option></select></td>
<td><input class="form-control link-sort" type="number" aria-label="Display order" name="links[{{ $index }}][sort]" value="{{ $entry['sort'] }}" min="0" max="9999" required></td>
<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-link>Remove</button></td>
</tr>
