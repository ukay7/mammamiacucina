window.MmcPosDrafts = function (app, adapter) {
    'use strict';
    const $=id=>app.querySelector('#'+id), dialog=$('pos-drafts'), buttons=[...app.querySelectorAll('[data-save-pos-draft]')];
    let active=null, clientKey=crypto.randomUUID(), busy=false, page=1, last=1, total=0, listSerial=0;
    const node=(tag,text)=>{const el=document.createElement(tag);el.textContent=text;return el;};
    const error=message=>{const el=$('pos-draft-error');el.textContent=message||'';el.hidden=!message;};
    async function request(url,body,method) {
        const response=await fetch(url,{method:method||(body?'POST':'GET'),credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':app.dataset.csrf,...(body instanceof FormData?{}:{'Content-Type':'application/json'})},body:body?(body instanceof FormData?body:JSON.stringify(body)):undefined});
        const result=await response.json().catch(()=>({}));
        if(!response.ok)throw Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'Unable to load drafts. Please try again.');
        return result;
    }
    function refreshButtons(){buttons.forEach(b=>b.disabled=busy||!adapter.canSave());$('open-pos-drafts').disabled=busy||adapter.blocked();}
    function lock(value){busy=value;adapter.setBusy(value);refreshButtons();dialog.querySelectorAll('button,input').forEach(el=>el.disabled=value);if(!value){$('pos-draft-prev').disabled=page<=1;$('pos-draft-next').disabled=page>=last;}}
    function reference(){const label=$('pos-active-draft');label.hidden=!active;label.textContent=active?'Editing '+active.number+' · Save again to keep further changes, or place the order at checkout.':'';}
    async function list(next=1){
        const url=new URL(app.dataset.drafts,location.href);url.searchParams.set('page',next);url.searchParams.set('q',$('pos-draft-search').value);
        const serial=++listSerial,data=await request(url);if(serial!==listSerial)return;page=data.page;last=data.last_page;total=data.total;
        $('pos-draft-count').textContent=String(total);
        const rows=$('pos-draft-rows');rows.replaceChildren();
        data.rows.forEach(row=>{
            const article=node('article','');article.className='pos-draft-row';
            const identity=node('div',''), name=node('p',row.customer);window.mmcBusinessCustomerIcon.append(name,row.account_type);
            identity.append(node('strong',row.number),name,node('small',row.item_count+' units'));
            const saved=node('div','');saved.append(node('small',row.updated_at),node('small','Saved by '+row.saved_by));
            const actions=node('div','');actions.className='pos-draft-actions';
            const resume=node('button','Resume'),remove=node('button','Delete');resume.type=remove.type='button';resume.className='btn btn-primary';remove.className='btn btn-outline-danger';
            resume.setAttribute('aria-label','Resume '+row.number);remove.setAttribute('aria-label','Delete '+row.number);
            resume.onclick=()=>resumeDraft(row);remove.onclick=()=>discard(row);actions.append(resume,remove);article.append(identity,saved,actions);rows.append(article);
        });
        if(!data.rows.length)rows.append(node('p','No draft orders found.'));
        $('pos-draft-page').textContent=total+' drafts · Page '+page+' of '+last;
        $('pos-draft-prev').disabled=page<=1;$('pos-draft-next').disabled=page>=last;
    }
    async function saveCurrent(){
        const data=new FormData();data.append('client_key',active?.client_key||clientKey);data.append('revision',String(active?.revision||0));
        if(active)data.append('id',active.id);
        data.append('payload',JSON.stringify(adapter.capture()));
        const receipt=adapter.receipt();if(receipt)data.append('receipt',receipt);else data.append('remove_receipt','1');
        const saved=await request(app.dataset.drafts,data);
        active=null;clientKey=crypto.randomUUID();adapter.reset();reference();
        adapter.feedback(saved.number+' saved. You can start another order now.');
        return saved;
    }
    async function save(){
        if(busy||!adapter.canSave())return;
        lock(true);error();
        try{await saveCurrent();await list().catch(()=>{$('pos-draft-count').textContent='';});}catch(e){adapter.feedback(e.message,true);if(dialog.open)error(e.message);}finally{lock(false);}
    }
    async function resumeDraft(row){
        if(busy||adapter.blocked())return;
        if(active?.id===row.id){dialog.close();adapter.feedback(row.number+' is already open.');return;}
        if(adapter.hasCart()&&!window.confirm('Save the current sale as a draft before opening '+row.number+'?'))return;
        if(adapter.hasCart()&&!adapter.canSave()){error('Confirm the current customer before saving and switching drafts.');return;}
        lock(true);error();let loaded=null;
        try{
            if(adapter.hasCart())await saveCurrent();
            loaded=await request(row.url);
            let receipt=null;
            if(loaded.receipt){const r=await fetch(loaded.receipt.url,{credentials:'same-origin'});if(!r.ok)throw Error('The saved receipt could not be loaded. Please try again.');receipt=new File([await r.blob()],loaded.receipt.name,{type:loaded.receipt.mime});}
            await adapter.restore(loaded,receipt);active=loaded;clientKey=loaded.client_key;reference();dialog.close();
            adapter.feedback(row.number+' restored. Prices and availability are checked again at checkout.');
        }catch(e){loaded=null;error(e.message);}finally{lock(false);}
        if(loaded)adapter.afterRestore(loaded.payload.stage);
    }
    async function discard(row){
        if(busy||!window.confirm('Delete '+row.number+'? This draft has not placed an order.'))return;
        lock(true);error();
        try{await request(row.url,{revision:row.revision},'DELETE');if(active?.id===row.id){active=null;clientKey=crypto.randomUUID();reference();adapter.feedback('Draft deleted. The open cart is now an unsaved sale.');}await list(page);}catch(e){error(e.message);}finally{lock(false);}
    }
    async function showPage(next){if(busy)return;lock(true);error();$('pos-draft-rows').replaceChildren(node('p','Loading drafts…'));try{await list(next);}catch(e){error(e.message);$('pos-draft-rows').replaceChildren();}finally{lock(false);}}
    buttons.forEach(b=>b.onclick=save);
    $('open-pos-drafts').onclick=()=>{if(adapter.blocked())return;dialog.showModal();showPage(1);};
    $('close-pos-drafts').onclick=()=>{if(!busy)dialog.close();};dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});
    $('pos-draft-search-form').onsubmit=e=>{e.preventDefault();showPage(1);};
    $('pos-draft-prev').onclick=()=>showPage(page-1);$('pos-draft-next').onclick=()=>showPage(page+1);
    list().catch(()=>{$('pos-draft-count').textContent='';});
    return {refreshButtons,checkoutFields:()=>active?{pos_draft_id:active.id,pos_draft_revision:active.revision}:{},
        placed(){active=null;clientKey=crypto.randomUUID();reference();list().catch(()=>{});refreshButtons();}};
};
