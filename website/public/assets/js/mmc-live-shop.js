(() => {
 const floatingCart = document.querySelector('[data-floating-cart]');
 const headerCart = document.querySelector('.mmc-header-cart');
 if (floatingCart && headerCart) {
  document.body.append(floatingCart);
  const observer = new IntersectionObserver(entries => {
   const entry = entries[0];
   floatingCart.hidden = entry.isIntersecting || entry.boundingClientRect.bottom > 0;
  });
  observer.observe(headerCart);
 }
 let pending=false;
 document.addEventListener('submit',async e=>{
  const form=e.target;if(!form.matches('[data-live-cart-form]'))return;e.preventDefault();if(pending)return;pending=true;
  const button=e.submitter||form.querySelector('button');const label=button?.innerHTML;const formData=new FormData(form);if(button?.name)formData.set(button.name,button.value);if(button){button.disabled=true;button.setAttribute('aria-busy','true');if(!form.closest('.mmc-mini-cart-actions'))button.textContent=form.querySelector('[name="_method"]')?'Updating…':'Adding…';}
  let feedback=form.closest('.mmc-header-cart__panel')?.querySelector('[data-mini-cart-feedback]')||document.querySelector('[data-cart-feedback]');if(!feedback){feedback=document.createElement('div');feedback.dataset.cartFeedback='';feedback.className='mmc-cart-toast';feedback.setAttribute('role','status');document.body.append(feedback);}
  try{const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:formData});let data;try{data=await response.json();}catch{throw Error('Unable to update cart. Refresh the page and try again.');}if(!response.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Unable to update cart.');
   document.querySelectorAll('[data-live-mini-cart]').forEach(el=>el.innerHTML=data.mini);document.querySelectorAll('.mmc-header-cart__count').forEach(el=>el.textContent=data.count);document.querySelector('[data-live-cart-content]')?.replaceChildren();const cart=document.querySelector('[data-live-cart-content]');if(cart)cart.innerHTML=data.content;feedback.textContent=data.message;
  }catch(err){feedback.textContent=err.message;}finally{pending=false;if(button){button.disabled=false;button.removeAttribute('aria-busy');button.innerHTML=label;}}
 });
 document.querySelectorAll('[data-live-gallery]').forEach(gallery => {
  const stage = gallery.querySelector('.mmc-live-stage');
  const slides = [...gallery.querySelectorAll('[data-live-slide]')];
  const thumbs = [...gallery.querySelectorAll('[data-live-thumb]')];
  const activate = index => {
   slides.forEach((slide, i) => {
    slide.hidden = i !== index;
    if (i !== index) slide.querySelector('video')?.pause();
   });
   thumbs.forEach((thumb, i) => thumb.setAttribute('aria-pressed', String(i === index)));
   gallery.querySelector('[data-gallery-caption]').textContent = (index + 1) + ' / ' + slides.length;
  };
  thumbs.forEach((thumb, index) => {
   thumb.addEventListener('click', () => activate(index));
   thumb.addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? thumbs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + thumbs.length) % thumbs.length;
    activate(next);
    thumbs[next].focus();
   });
  });
 });
 document.querySelector('[data-place-order]')?.addEventListener('submit', e => {
  const button=e.target.querySelector('button[type="submit"]');button.disabled=true;button.textContent='Placing order…';
 });
 window.addEventListener('pageshow', () => {const button=document.querySelector('[data-place-order] button[type="submit"]');if(button){button.disabled=false;button.textContent='Place Order';}});
 document.querySelector('[data-contact-preview]')?.addEventListener('submit',e=>{e.preventDefault();document.querySelector('[data-contact-status]').textContent='Please contact us directly while online messaging is being connected.';});
 document.querySelectorAll('.mmc-categories').forEach(section => {
  const track=section.querySelector('.mmc-categories__grid');
  const controls=section.querySelector('.mmc-categories__controls');
  if(!track || !controls)return;
  const prev=controls.querySelector('[data-category-prev]');
  const next=controls.querySelector('[data-category-next]');
  const sync=()=>{
   const max=track.scrollWidth-track.clientWidth;
   controls.hidden=max<=2;
   prev.disabled=track.scrollLeft<=2;
   next.disabled=track.scrollLeft>=max-2;
  };
  const move=direction=>track.scrollBy({left:direction*(track.querySelector('.mmc-category')?.getBoundingClientRect().width||track.clientWidth),behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
  prev.addEventListener('click',()=>move(-1));next.addEventListener('click',()=>move(1));
  track.addEventListener('scroll',sync,{passive:true});
  track.addEventListener('keydown',event=>{
   if(event.target!==track || !['ArrowLeft','ArrowRight'].includes(event.key))return;
   event.preventDefault();move(event.key==='ArrowLeft'?-1:1);
  });
  window.addEventListener('resize',sync);
  sync();
 });
})();
