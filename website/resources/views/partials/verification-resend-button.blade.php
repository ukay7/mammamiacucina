@php($remaining=\App\Services\OutgoingEmail::remaining($verificationUser))
<button type="submit" class="{{ $buttonClass ?? 'btn btn-outline-primary' }}" data-verification-cooldown="{{ $remaining }}" @disabled($remaining>0)>{{ $remaining>0 ? 'Resend available in '.gmdate('i:s',$remaining) : 'Resend verification email' }}</button>
@once
<script>
document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('[data-verification-cooldown]').forEach(function(button){
  const deadline=Date.now()+Number(button.dataset.verificationCooldown)*1000;
  function tick(){const seconds=Math.max(0,Math.ceil((deadline-Date.now())/1000));button.disabled=seconds>0;button.textContent=seconds>0?'Resend available in '+String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0'):'Resend verification email';if(seconds>0)setTimeout(tick,1000);}
  tick();button.form.addEventListener('submit',function(event){if(Date.now()<deadline){event.preventDefault();return;}button.disabled=true;button.textContent='Sending…';});
 });
});
</script>
@endonce
