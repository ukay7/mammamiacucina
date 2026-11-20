<section class="nt-attachment-summary" aria-label="Email attachments">
    <h3>Attachments <span class="nt-muted">({{ count($files) }})</span></h3>
    @if($files)
        <ul class="nt-files">
            @foreach($files as $file)
                <li><span class="nt-file-symbol" aria-hidden="true">↧</span><div class="nt-file-info"><a href="{{ isset($attachmentDelivery) ? route('admin.notifications.delivery-attachment',[$campaign,$attachmentDelivery,$file['id']]) : route('admin.notifications.attachment',[$campaign,$file['id']]) }}">{{ $file['name'] }}</a><small>{{ number_format($file['size']/1024,1) }} KB</small></div></li>
            @endforeach
        </ul>
        <p class="nt-muted">{{ number_format(array_sum(array_column($files,'size'))/1024/1024,2) }} MB combined · Included with this email.</p>
    @else
        <p class="nt-muted">No attachments.</p>
    @endif
</section>
