<style>.contact-links-table{min-width:850px;width:100%}.contact-links-table th{font-size:12px;color:#806335}.contact-links-table td,.contact-links-table th{padding:12px 8px;vertical-align:top;border-bottom:1px solid #e8d8bf}.contact-links-table input[type=file]{max-width:195px}.contact-links-table .link-label{min-width:140px}.contact-links-table .link-url{min-width:240px}.contact-link-icon-preview{display:flex;align-items:center;gap:10px;margin-bottom:8px}.contact-links-table th:nth-child(1){width:20%}.contact-links-table th:nth-child(2){width:32%}.contact-links-table .link-sort{width:70px}.contact-links-toolbar{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:16px}.contact-links-table label{font-size:12px;margin:6px 0}</style>
<div class="panel"><div class="panel-content">
<div class="contact-links-toolbar"><div><h2>Social & website links</h2><p>Add LinkedIn, YouTube, Google Maps, WhatsApp or any website. Active links appear on the contact page, footer and admin sidebar.</p></div><button type="button" class="btn btn-primary" id="add-contact-link">+ Add link</button></div>
<p class="text-muted">Smaller order numbers appear first. Upload a PNG, JPG or WebP icon (up to 1 MB), or keep the default icon. WhatsApp links use https://wa.me/ followed by the country code and number.</p>
<input type="hidden" name="links_present" value="1">
<div style="overflow-x:auto"><table class="contact-links-table"><thead><tr><th scope="col">Name</th><th scope="col">URL</th><th scope="col">Icon</th><th scope="col">Status</th><th scope="col">Order</th><th scope="col">Action</th></tr></thead><tbody id="contact-link-rows">
@foreach(old('links', old('links_present') ? [] : $settings->contactLinks()) as $index => $entry)
@include('admin.settings.contact-link-row')
@endforeach
</tbody></table></div>
<p id="contact-links-empty" class="text-muted" hidden>No links added. Use Add link to get started.</p>
<small>Changes, removals and uploads take effect when you click Save changes. After a validation error, select any new icon files again.</small>
</div></div>
<template id="contact-link-template">@include('admin.settings.contact-link-row', ['index'=>'__INDEX__', 'entry'=>['label'=>'','url'=>'','active'=>true,'sort'=>0]])</template>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('contact-link-rows');
    let next = Math.max(-1, ...Array.from(rows.querySelectorAll('tr')).map(row => Number(row.dataset.index))) + 1;
    const empty = () => document.getElementById('contact-links-empty').hidden = rows.children.length > 0;
    document.getElementById('add-contact-link').addEventListener('click', () => {
        rows.insertAdjacentHTML('beforeend', document.getElementById('contact-link-template').innerHTML.replaceAll('__INDEX__', next++));
        rows.lastElementChild.querySelector('.link-sort').value = rows.children.length;
        rows.lastElementChild.querySelector('.link-label').focus(); empty();
    });
    rows.addEventListener('click', event => { if (event.target.closest('[data-remove-link]')) { event.target.closest('tr').remove(); empty(); } });
    empty();
});
</script>
