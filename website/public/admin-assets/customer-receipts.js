(() => {
    const money = cents => new Intl.NumberFormat('en-CA',{style:'currency',currency:'CAD'}).format(cents/100);
    const methods = {cash:'Cash',cheque:'Cheque',etransfer:'E-transfer',card:'External card',paypal:'Verified PayPal'};
    const showError = (el,message='') => {el.textContent=message;el.hidden=!message;};
    async function send(url,body,token) {
        const response=await fetch(url,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':token},body});
        let data;try{data=await response.json();}catch{throw new Error('Unable to process the request. Refresh and try again; review history before resubmitting.');}
        if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):(data.message||'Unable to save this payment.'));
        return data;
    }
    function cell(row,value){const td=document.createElement('td');td.textContent=value??'—';row.append(td);return td;}
    function renderPreview(target,plan) {
        const summary=target.querySelector('[data-receipt-preview-summary]');summary.replaceChildren();
        const figures=plan.total_cents!==undefined?{'Total entered':plan.total_cents,'Confirmed funds':plan.confirmed_cents,'Cheque awaiting clearance':plan.pending_cents,'Outstanding after confirmed funds':plan.outstanding_after_confirmed_cents}:{'Amount to reverse / cancel':plan.amount_cents};
        Object.entries(figures).forEach(([label,amount])=>{const box=document.createElement('div'),span=document.createElement('span'),strong=document.createElement('strong');span.textContent=label;strong.textContent=money(amount);box.append(span,strong);summary.append(box);});
        const body=target.querySelector('[data-receipt-preview-rows]');body.replaceChildren();
        for(const order of plan.orders){const row=document.createElement('tr');cell(row,`${order.number}${order.invoice?' / '+order.invoice:''}`);cell(row,money(order.balance_cents));
            const allocations=cell(row,'');
            if(order.allocations){for(const entry of order.allocations){const p=document.createElement('div');p.textContent=`${methods[entry.method]}${entry.reference?' · '+entry.reference:''}: ${money(entry.amount_cents)}${entry.pending?' — awaiting clearance':''}`;if(entry.pending)p.className='receipt-pending';allocations.append(p);}}
            else allocations.textContent=`Reverse ${money(order.amount_cents)}`;
            cell(row,money(order.after_confirmed_cents??order.after_cents));cell(row,order.after_clearance_cents===undefined?'—':money(order.after_clearance_cents));body.append(row);
        }
        if(!plan.orders.length){const row=document.createElement('tr');const td=cell(row,'No confirmed order allocations will change. The pending cheque reservation will be released.');td.colSpan=5;body.append(row);}
    }
    const form=document.querySelector('[data-customer-receipt]');
    if(form){
        const container=form.querySelector('[data-receipt-lines]'),template=document.querySelector('#customer-receipt-line-template'),preview=form.querySelector('[data-receipt-preview]'),save=form.querySelector('[data-receipt-save]'),result=form.querySelector('[data-receipt-result]'),confirmed=form.querySelector('[data-receipt-confirmed]'),error=form.querySelector('[data-receipt-error]');
        let hash=null,busy=false;
        const rows=()=>[...container.querySelectorAll('.receipt-line')];
        function renumber(){rows().forEach((row,i)=>{row.querySelector('[data-receipt-line-title]').textContent=`Payment ${i+1}`;row.querySelectorAll('[data-receipt-field]').forEach(input=>{const field=input.dataset.receiptField;input.name=`lines[${i}][${field}]`;input.id=`receipt-line-${i}-${field}`;const label=row.querySelector(`[data-receipt-label="${field}"]`);if(label)label.htmlFor=input.id;});row.querySelector('[data-receipt-remove]').disabled=rows().length===1;});form.querySelector('[data-receipt-add]').disabled=rows().length>=10||busy;}
        function total(){let sum=0;for(const row of rows()){const raw=row.querySelector('[data-receipt-field="amount"]').value.trim();if(/^\d{1,7}(\.\d{1,2})?$/.test(raw)){const [whole,fraction='']=raw.split('.');sum+=Number(whole)*100+Number((fraction+'00').slice(0,2));}}form.querySelector('[data-receipt-total]').textContent=money(sum);return sum;}
        function invalidate(){hash=null;result.hidden=true;confirmed.checked=false;save.disabled=true;showError(error);total();}
        function configure(row){const cheque=row.querySelector('[data-receipt-field="method"]').value==='cheque',transfer=row.querySelector('[data-receipt-field="method"]').value==='etransfer';row.querySelectorAll('[data-cheque-only]').forEach(el=>el.hidden=!cheque);const chequeDate=row.querySelector('[data-receipt-field="cheque_date"]');chequeDate.required=cheque;chequeDate.disabled=!cheque;row.querySelector('[data-receipt-field="bank"]').disabled=!cheque;const verified=row.querySelector('[data-receipt-field="verified"]');verified.disabled=cheque;verified.required=!cheque;row.querySelector('[data-verified-only]').hidden=cheque;row.querySelector('[data-receipt-field="reference"]').required=row.querySelector('[data-receipt-field="method"]').value!=='cash';row.querySelector('[data-receipt-field="proof"]').required=transfer;}
        function add(){if(rows().length>=10||busy)return;const row=template.content.firstElementChild.cloneNode(true);row.querySelector('[data-receipt-field="key"]').value=crypto.randomUUID();container.append(row);configure(row);row.querySelector('[data-receipt-remove]').addEventListener('click',()=>{if(busy)return;row.remove();renumber();invalidate();});row.querySelector('[data-receipt-field="method"]').addEventListener('change',()=>configure(row));renumber();invalidate();}
        form.querySelector('[data-receipt-add]').addEventListener('click',add);
        form.addEventListener('input',event=>{if(event.target===confirmed){save.disabled=!hash||!confirmed.checked||busy;return;}invalidate();});
        form.addEventListener('change',event=>{if(event.target!==confirmed)invalidate();else save.disabled=!hash||!confirmed.checked||busy;});
        const lock=state=>{busy=state;preview.disabled=state;save.disabled=state||!hash||!confirmed.checked;form.querySelectorAll('input,select,textarea,[data-receipt-remove],[data-receipt-add]').forEach(el=>{if(state)el.dataset.wasDisabled=String(el.disabled);el.disabled=state||el.dataset.wasDisabled==='true';if(!state)delete el.dataset.wasDisabled;});if(!state){renumber();rows().forEach(configure);}};
        function valid(){for(const row of rows()){const input=row.querySelector('[data-receipt-field="amount"]'),raw=input.value.trim();input.setCustomValidity(/^\d{1,7}(\.\d{1,2})?$/.test(raw)&&Number(raw)>0?'':'Enter a positive amount with up to two decimal places.');const file=row.querySelector('[data-receipt-field="proof"]');file.setCustomValidity(file.files[0]?.size>5*1024*1024?'Each attachment must be at most 5 MB.':'');}if(!form.reportValidity())return false;if(total()>Number(form.dataset.maximum)){showError(error,`Payment total ${money(total())} exceeds the maximum ${money(Number(form.dataset.maximum))}. Enter this maximum or less. Customer advances are not supported.`);return false;}return true;}
        preview.addEventListener('click',async()=>{if(busy)return;showError(error);if(!valid())return;renumber();const data=new FormData(form);lock(true);preview.textContent='Loading Preview…';try{const plan=await send(form.dataset.previewUrl,data,form.querySelector('[name="_token"]').value);hash=plan.preview_hash;renderPreview(result,plan);result.hidden=false;confirmed.checked=false;result.scrollIntoView({behavior:'smooth',block:'start'});}catch(e){showError(error,e.message);}finally{preview.textContent='Preview Allocation';lock(false);}});
        form.addEventListener('submit',async event=>{event.preventDefault();if(busy||!hash||!confirmed.checked)return;const data=new FormData(form);data.set('preview_hash',hash);lock(true);save.textContent='Recording Payment…';try{const response=await send(form.dataset.storeUrl,data,form.querySelector('[name="_token"]').value);location.assign(response.redirect);}catch(e){showError(error,e.message);}finally{save.textContent='Confirm & Record Payment';lock(false);}});
        add();
    }
    document.querySelectorAll('[data-receipt-action]').forEach(actionForm=>{
        const result=actionForm.querySelector('[data-receipt-result]'),preview=actionForm.querySelector('[data-action-preview]'),confirm=actionForm.querySelector('[data-action-confirm]'),check=actionForm.querySelector('[name="confirmed"]'),error=actionForm.querySelector('[data-action-error]');let hash=null,busy=false;
        actionForm.addEventListener('input',event=>{if(event.target===check){confirm.disabled=!hash||!check.checked||busy;return;}hash=null;result.hidden=true;check.checked=false;confirm.disabled=true;});
        actionForm.addEventListener('change',event=>{if(event.target!==check){hash=null;result.hidden=true;check.checked=false;confirm.disabled=true;}});
        preview.addEventListener('click',async()=>{if(busy||!actionForm.reportValidity())return;busy=true;preview.disabled=true;showError(error);try{const plan=await send(actionForm.dataset.previewUrl,new FormData(actionForm),actionForm.querySelector('[name="_token"]').value);hash=plan.preview_hash;renderPreview(result,plan);result.hidden=false;check.checked=false;confirm.disabled=true;}catch(e){showError(error,e.message);}finally{busy=false;preview.disabled=false;}});
        actionForm.addEventListener('submit',async event=>{event.preventDefault();if(busy||!hash||!check.checked)return;busy=true;confirm.disabled=true;preview.disabled=true;const data=new FormData(actionForm);data.set('preview_hash',hash);try{const response=await send(actionForm.getAttribute('action'),data,actionForm.querySelector('[name="_token"]').value);location.assign(response.redirect);}catch(e){showError(error,e.message);}finally{busy=false;preview.disabled=false;confirm.disabled=!hash||!check.checked;}});
    });
})();
