<form method="post" action="{{ route('admin.notifications.test',$campaign) }}">@csrf
<input type="hidden" name="revision" value="{{ $campaign->revision }}"><input type="hidden" name="test_key" value="{{ Str::uuid() }}">
<label>Test recipient<input class="form-control" type="email" name="recipient" maxlength="255" required placeholder="Your inbox address" value="{{ old('recipient') }}"></label>
<button class="btn btn-outline-primary" @disabled(!$sender?->ready())>Send test email</button></form>
