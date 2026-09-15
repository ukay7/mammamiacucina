const menu=document.querySelector('.mmc-admin-menu');
menu?.addEventListener('click',()=>{const open=document.body.classList.toggle('sidebar-open');menu.setAttribute('aria-expanded',String(open));});
document.addEventListener('click',e=>{if(document.body.classList.contains('sidebar-open')&&!e.target.closest('.page-sidebar')&&!e.target.closest('.mmc-admin-menu')){document.body.classList.remove('sidebar-open');menu?.setAttribute('aria-expanded','false');}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.body.classList.remove('sidebar-open');menu?.setAttribute('aria-expanded','false');}});
document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));

document.querySelector('[data-select-products]')?.addEventListener('change', function(){document.querySelectorAll('input[name="product_ids[]"]').forEach(input=>input.checked=this.checked);});

// Save a grid status without leaving the current search/page.
document.addEventListener('change', async event => {
 const select=event.target;
 if(!select.matches('[data-status-autosave] select[name="status"]'))return;
 const form=select.closest('form'), feedback=form.querySelector('.mmc-status-feedback');
 const previous=form.dataset.savedStatus;
 if(select.value===previous)return;
 const payload=new FormData(form);
 select.disabled=true;select.dispatchEvent(new Event('status:sync'));form.setAttribute('aria-busy','true');feedback.textContent='Saving…';feedback.dataset.state='saving';
 try{
  const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:payload});
  const data=await response.json();
  if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Unable to save. Reload and try again.');
  select.replaceChildren(...Object.entries(data.options).map(([value,label])=>new Option(label,value)));
  select.value=data.status;select.dataset.status=data.status;form.dataset.savedStatus=data.status;
  form.querySelector('[name="revision"]').value=data.revision;
  select.disabled=data.terminal;feedback.textContent='✓ Saved';feedback.dataset.state='saved';
 }catch(error){
  select.value=previous;select.disabled=false;feedback.dataset.state='error';feedback.textContent=error.message||'Unable to confirm save. Reload to check the order.';
 }finally{form.removeAttribute('aria-busy');select.dispatchEvent(new Event('status:sync'));}
});

// Custom status picker; retain the select for form values and no-JS fallback.
document.querySelectorAll('[data-status-autosave] select').forEach((select,index)=>{
 const trigger=document.createElement('button');trigger.type='button';trigger.className='mmc-status-pill mmc-status-trigger';
 trigger.setAttribute('aria-haspopup','menu');trigger.setAttribute('aria-expanded','false');
 const popup=document.createElement('div');popup.className='mmc-status-menu';popup.id='status-menu-'+index;popup.setAttribute('role','menu');popup.setAttribute('aria-label',select.getAttribute('aria-label'));popup.hidden=true;
 trigger.setAttribute('aria-controls',popup.id);document.body.append(popup);select.before(trigger);select.hidden=true;
 const sync=()=>{trigger.replaceChildren();const dot=document.createElement('span');dot.className='mmc-status-dot';dot.setAttribute('aria-hidden','true');const label=document.createElement('span');label.textContent=select.selectedOptions[0]?.textContent||'';const arrow=document.createElement('span');arrow.className='mmc-status-chevron';arrow.textContent='⌄';arrow.setAttribute('aria-hidden','true');trigger.append(dot,label,arrow);trigger.dataset.status=select.dataset.status;trigger.disabled=select.disabled;trigger.setAttribute('aria-label',select.getAttribute('aria-label')+': '+label.textContent);};
 const close=(focus=false)=>{popup.hidden=true;trigger.setAttribute('aria-expanded','false');if(focus)trigger.focus({preventScroll:true});};
 const open=()=>{
  if(trigger.disabled)return;document.dispatchEvent(new Event('status:close'));popup.replaceChildren();
  [...select.options].forEach(option=>{const item=document.createElement('button');item.type='button';item.className='mmc-status-option';item.dataset.status=option.value;item.setAttribute('role','menuitemradio');item.setAttribute('aria-checked',String(option.selected));const dot=document.createElement('span');dot.className='mmc-status-dot';dot.setAttribute('aria-hidden','true');const label=document.createElement('span');label.textContent=option.textContent;const check=document.createElement('span');check.className='mmc-status-check';check.textContent=option.selected?'✓':'';check.setAttribute('aria-hidden','true');item.append(dot,label,check);item.addEventListener('click',()=>{close(true);if(option.value!==select.value){select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));}});popup.append(item);});
  popup.hidden=false;const rect=trigger.getBoundingClientRect();const width=Math.min(230,innerWidth-24);popup.style.width=width+'px';popup.style.left=Math.max(12,Math.min(rect.left,innerWidth-width-12))+'px';const height=popup.offsetHeight;popup.style.top=Math.max(12,rect.bottom+8+height>innerHeight-12?rect.top-height-8:rect.bottom+8)+'px';trigger.setAttribute('aria-expanded','true');(popup.querySelector('[aria-checked="true"]')||popup.firstElementChild)?.focus();
 };
 trigger.addEventListener('click',()=>popup.hidden?open():close());trigger.addEventListener('keydown',e=>{if(['ArrowDown','ArrowUp'].includes(e.key)){e.preventDefault();open();}});
 popup.addEventListener('keydown',e=>{const items=[...popup.children],i=items.indexOf(document.activeElement);if(e.key==='Escape'){e.preventDefault();close(true);}else if(e.key==='Tab'){close();}else if(['ArrowDown','ArrowUp','Home','End'].includes(e.key)){e.preventDefault();items[e.key==='Home'?0:e.key==='End'?items.length-1:(i+(e.key==='ArrowDown'?1:-1)+items.length)%items.length]?.focus();}});
 document.addEventListener('click',e=>{if(!trigger.contains(e.target)&&!popup.contains(e.target))close();});document.addEventListener('status:close',()=>close());window.addEventListener('resize',()=>close());window.addEventListener('scroll',()=>close(),true);select.addEventListener('status:sync',sync);sync();
});
