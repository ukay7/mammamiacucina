(()=>{
 const modal=document.querySelector('[data-count-logs-modal]');
 if(!modal)return;
 const rows=modal.querySelector('[data-log-rows]'),empty=modal.querySelector('[data-log-empty]');
 const format=value=>new Intl.NumberFormat(undefined,{maximumFractionDigits:3}).format(Number(value)||0);
 const close=()=>{modal.hidden=true;document.body.classList.remove('sc-log-open')};
 const cell=(row,value,className='')=>{const td=document.createElement('td');td.textContent=value;if(className)td.className=className;row.append(td)};
 const open=async button=>{
  modal.hidden=false;document.body.classList.add('sc-log-open');rows.replaceChildren();empty.hidden=true;
  modal.querySelector('[data-log-product]').textContent='Loading history…';modal.querySelector('[data-log-total]').textContent='—';
  try{
   const response=await fetch(button.dataset.countLogsUrl,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}),data=await response.json();
   if(!response.ok)throw new Error(data.message||'Unable to load count history.');
   modal.querySelector('[data-log-product]').textContent=data.product;modal.querySelector('[data-log-total]').textContent=format(data.total);
   data.logs.forEach(log=>{const row=document.createElement('tr'),change=Number(log.change);cell(row,log.date);cell(row,(change>0?'+':'')+format(change),change<0?'text-danger':'text-success');cell(row,format(log.before));cell(row,format(log.after));cell(row,log.note||'—');cell(row,log.user);rows.append(row)});
   empty.hidden=data.logs.length>0;
  }catch(error){modal.querySelector('[data-log-product]').textContent='Count history';empty.textContent=error.message;empty.hidden=false;}
 };
 document.addEventListener('click',event=>{const button=event.target.closest('[data-count-logs-url]');if(button)open(button);if(event.target.closest('[data-log-close]'))close();});
 modal.addEventListener('click',event=>{if(event.target===modal)close()});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)close()});

 document.querySelectorAll('.sc-count-form').forEach(form=>form.addEventListener('submit',async event=>{
  event.preventDefault();const row=form.closest('[data-count-row]'),button=form.querySelector('button'),label=button.querySelector('span'),old=label.textContent;
  button.disabled=true;label.textContent='Saving…';
  try{
   const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}),data=await response.json();
   if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'Unable to save adjustment.');
   row.querySelector('[data-count-total]').textContent=format(data.quantity);row.querySelector('.sc-quantity-cell small').textContent='Accumulated count';
   row.querySelector('[name="quantity_change"]').value='';row.querySelector('[name="note"]').value='';
   document.querySelectorAll('[data-progress]').forEach(element=>element.textContent=data.counted+' / '+data.total);
   document.querySelectorAll('[data-progress-bar]').forEach(element=>element.style.width=(data.total?data.counted/data.total*100:0)+'%');
   button.classList.remove('btn-outline-primary');button.classList.add('btn-light');label.textContent='Saved ✓';setTimeout(()=>label.textContent='Update',1200);
  }catch(error){alert(error.message);label.textContent=old}finally{button.disabled=false}
 }));
})();
