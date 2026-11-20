<fieldset class="nt-fieldset nt-attachments">
    <legend>Attachments <small class="nt-muted">optional</small></legend>
    <p class="nt-muted" id="notification-file-help">Up to 5 files, 10 MB combined. PDF, JPG, PNG, WebP, Word or Excel. Files are included in the test email and every bulk email.</p>
    @if($campaign->attachments)
        <ul class="nt-files" id="notification-saved-files">
            @foreach($campaign->attachments as $file)
                <li data-saved-file data-name="{{ $file['name'] }}" data-size="{{ $file['size'] }}">
                    <span class="nt-file-symbol" aria-hidden="true">↧</span>
                    <div class="nt-file-info"><a href="{{ route('admin.notifications.attachment',[$campaign,$file['id']]) }}">{{ $file['name'] }}</a><small>{{ number_format($file['size']/1024,1) }} KB · Saved</small></div>
                    <label class="nt-check nt-file-remove"><input type="checkbox" name="remove_attachments[]" value="{{ $file['id'] }}" @checked(in_array($file['id'],(array)old('remove_attachments',[]),true))> Remove</label>
                </li>
            @endforeach
        </ul>
    @endif
    <label for="notification-files">Add files</label>
    <input id="notification-files" class="form-control" type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" aria-describedby="notification-file-help notification-file-summary">
    <ul class="nt-files" id="notification-new-files" aria-label="Files to upload"></ul>
    <p id="notification-file-summary" class="nt-muted" aria-live="polite"></p>
    <p id="notification-file-error" class="alert alert-danger" role="alert" hidden></p>
    <p class="nt-muted mb-0">Save the draft to apply file changes, then send a new test. If a save fails, choose new files again.</p>
</fieldset>
