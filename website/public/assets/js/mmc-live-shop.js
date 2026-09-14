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
  const hoverPointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  const preview = document.createElement('div');
  preview.className = 'mmc-zoom-preview';
  preview.hidden = true;
  preview.setAttribute('aria-hidden', 'true');
  const enlarged = document.createElement('img');
  enlarged.alt = '';
  preview.append(enlarged);
  const lens = document.createElement('div');
  lens.className = 'mmc-zoom-lens';
  lens.hidden = true;
  lens.setAttribute('aria-hidden', 'true');
  document.body.append(preview, lens);
  let lastPointer = null;
  const resetZoom = () => { preview.hidden = true; lens.hidden = true; lastPointer = null; };
  const zoomAtPointer = event => {
   if (!hoverPointer.matches || event.pointerType === 'touch') return resetZoom();
   const img = stage.querySelector('[data-live-slide]:not([hidden]) img');
   if (!img) return resetZoom();
   lastPointer = event;
   if (!img.complete || !img.naturalWidth) return;
   const rect = img.getBoundingClientRect();
   // Account for object-fit: contain, including portrait/landscape letterboxing.
   const fit = Math.min(rect.width / img.naturalWidth, rect.height / img.naturalHeight);
   const width = img.naturalWidth * fit, height = img.naturalHeight * fit;
   const left = rect.left + (rect.width - width) / 2, top = rect.top + (rect.height - height) / 2;
   const x = event.clientX - left, y = event.clientY - top;
   if (x < 0 || x > width || y < 0 || y > height) return resetZoom();
   const size = Math.min(300, innerWidth - 24, innerHeight - 24), zoom = 2.3;
   const lensWidth = Math.min(width, size / zoom), lensHeight = Math.min(height, size / zoom);
   const cx = Math.max(lensWidth / 2, Math.min(width - lensWidth / 2, x));
   const cy = Math.max(lensHeight / 2, Math.min(height - lensHeight / 2, y));
   let px = rect.right + 18;
   if (px + size > innerWidth - 12) px = rect.left - size - 18;
   if (px < 12) px = Math.max(12, Math.min(innerWidth - size - 12, event.clientX + 24));
   const py = Math.max(12, Math.min(innerHeight - size - 12, event.clientY - size / 2));
   Object.assign(preview.style, {left: `${px}px`, top: `${py}px`, width: `${size}px`, height: `${size}px`});
   if (enlarged.src !== img.currentSrc) enlarged.src = img.currentSrc;
   Object.assign(enlarged.style, {width: `${width * zoom}px`, height: `${height * zoom}px`, left: `${size / 2 - cx * zoom}px`, top: `${size / 2 - cy * zoom}px`});
   Object.assign(lens.style, {left: `${left + cx - lensWidth / 2}px`, top: `${top + cy - lensHeight / 2}px`, width: `${lensWidth}px`, height: `${lensHeight}px`});
   preview.hidden = false;
   lens.hidden = false;
  };
  stage.querySelectorAll('img').forEach(img => img.addEventListener('load', () => { if (lastPointer) zoomAtPointer(lastPointer); }));
  stage.addEventListener('pointerenter', zoomAtPointer);
  stage.addEventListener('pointermove', zoomAtPointer);
  stage.addEventListener('pointerleave', resetZoom);
  stage.addEventListener('pointercancel', resetZoom);
  window.addEventListener('blur', resetZoom);
  window.addEventListener('scroll', resetZoom, {passive:true});
  window.addEventListener('resize', resetZoom);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') resetZoom(); });
  hoverPointer.addEventListener('change', resetZoom);
  const activate = index => {
   resetZoom();
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
})();
