@php($remaining=\App\Services\OutgoingEmail::remaining($verificationUser))
@php($resendLabel=$remaining>0 ? 'Resend available in '.gmdate('i:s',$remaining) : 'Send Verification Email')
<button type="submit" class="{{ $buttonClass ?? 'btn btn-outline-primary' }}" data-verification-icon="{{ !empty($iconOnly)?'1':'0' }}" data-verification-cooldown="{{ $remaining }}" title="{{ $resendLabel }}" aria-label="{{ $resendLabel }}" @disabled($remaining>0)>@if(!empty($iconOnly))<i class="fas fa-envelope" aria-hidden="true"></i>@else{{ $remaining>0 ? $resendLabel : 'Resend verification email' }}@endif</button>
@once
<script>
document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('[data-verification-cooldown]').forEach(function(button){
  const deadline=Date.now()+Number(button.dataset.verificationCooldown)*1000;
  function label(text){button.title=text;button.setAttribute('aria-label',text);if(button.dataset.verificationIcon!=='1')button.textContent=text;}
  function tick(){const seconds=Math.max(0,Math.ceil((deadline-Date.now())/1000));button.disabled=seconds>0;label(seconds>0?'Resend available in '+String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0'):(button.dataset.verificationIcon==='1'?'Send Verification Email':'Resend verification email'));if(seconds>0)setTimeout(tick,1000);}
  tick();button.form.addEventListener('submit',function(event){if(Date.now()<deadline){event.preventDefault();return;}button.disabled=true;label('Sending…');});
 });
});
</script>
@endonce
