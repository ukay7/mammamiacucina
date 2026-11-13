document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const form = document.querySelector('[data-refund-form]'), dialog = document.getElementById('refund-review');
    if (!dialog) return;
    const money = cents => 'CAD ' + (Number(cents) / 100).toFixed(2);
    const escape = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const confirm = dialog.querySelector('[data-refund-confirm]'), save = dialog.querySelector('[data-refund-save]');
    let pending = null, busy = false;
    async function post(url, data) {
        const response = await fetch(url, {method:'POST', body:data, headers:{'Accept':'application/json'}, credentials:'same-origin'});
        let result;
        try { result = await response.json(); } catch (_) { throw new Error('The server could not complete the request. Reload or try again.'); }
        if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'Unable to record refund.'));
        return result;
    }
    function error(target, message) { target.textContent = message; target.hidden = !message; }
    function openReview(source, data, plan, html, label) {
        pending = {source, data, plan}; confirm.checked = false; save.disabled = true; save.textContent = label;
        dialog.querySelector('[data-refund-review-content]').innerHTML = html;
        error(dialog.querySelector('[data-dialog-error]'),''); dialog.showModal();
    }
    confirm.addEventListener('change',()=>save.disabled = busy || !confirm.checked);
    dialog.querySelector('[data-refund-back]').addEventListener('click',()=>{if (!busy) dialog.close();});
    dialog.addEventListener('cancel',e=>{if (busy) e.preventDefault();});
    save.addEventListener('click', async()=>{
        if (busy || !pending || !confirm.checked) return;
        busy = true; save.disabled = true;
        pending.data.set('preview_hash',pending.plan.preview_hash); pending.data.set('confirmed','1');
        if (pending.plan.expires_at) pending.data.set('expires_at',pending.plan.expires_at);
        try { const result = await post(pending.source.getAttribute('action'),pending.data); window.location.assign(result.redirect); }
        catch(e) { error(dialog.querySelector('[data-dialog-error]'),e.message); busy = false; save.disabled = !confirm.checked; }
    });
    if (form) {
        let account = JSON.parse(document.getElementById('refund-initial-account').textContent), sequence = 0;
        const lines = form.querySelector('[data-refund-lines]'), order = form.querySelector('[name=order_id]');
        const field = (row,name)=>row.querySelector('[data-field="'+name+'"]');
        function showAccount() {
            const labels = {total_cents:'Original order total',net_received_cents:'Net received',credited_cents:'Confirmed credits',pending_cents:'Pending refund cheques',maximum_cents:'Available to refund',balance_cents:'Current order balance'};
            form.querySelector('[data-refund-account]').innerHTML = account ? Object.entries(labels).map(([key,label])=>'<div><span>'+label+'</span><strong>'+money(account[key])+'</strong></div>').join('') : '<p>Choose an order to see its refundable amount.</p>';
        }
        function total() { form.querySelector('[data-refund-total]').textContent = money(Array.from(lines.children).reduce((sum,row)=>sum+Math.round((Number(field(row,'amount').value)||0)*100),0)); }
        function method(row) {
            const type = field(row,'method').value, cheque = type === 'cheque';
            row.querySelectorAll('[data-cheque]').forEach(el=>el.hidden=!cheque);
            field(row,'cheque_date').required = cheque; field(row,'reference').required = type !== 'cash';
            field(row,'proof').required = type !== 'cash'; row.querySelector('[data-proof-hint]').textContent = type === 'cash' ? '(optional)' : '(required)';
            row.querySelector('[data-verified-label]').hidden = cheque; field(row,'verified').required = !cheque;
        }
        function add() {
            if (lines.children.length >= 10) return;
            const row = document.getElementById('refund-line-template').content.firstElementChild.cloneNode(true), i = sequence++;
            row.querySelectorAll('[data-field]').forEach(el=>el.name='lines['+i+']['+el.dataset.field+']');
            field(row,'key').value = crypto.randomUUID(); field(row,'refund_date').value = form.dataset.today;
            field(row,'refund_date').max = form.dataset.today; field(row,'cheque_date').value = form.dataset.today;
            field(row,'method').addEventListener('change',()=>method(row));
            field(row,'tax').addEventListener('input',()=>row.dataset.customTax='1');
            field(row,'amount').addEventListener('input',()=>{
                if (!row.dataset.customTax && account) field(row,'tax').value = (Math.round(Number(field(row,'amount').value)*100*(account.tax_cents/Math.max(1,account.total_cents)))/100).toFixed(2);
                total();
            });
            row.querySelector('[data-refund-remove]').addEventListener('click',()=>{if(lines.children.length>1){row.remove();total();}});
            lines.append(row); method(row);
        }
        const $ = window.jQuery;
        if ($?.fn.select2) {
            $(order).select2({width:'100%',placeholder:'Search delivered / completed order number',ajax:{url:order.dataset.searchUrl,dataType:'json',delay:250,data:p=>({q:p.term||'',page:p.page||1})}})
                .on('select2:select',e=>{account=e.params.data.account;showAccount();});
        } else {
            const input = document.createElement('input'); input.className='form-control mb-2'; input.placeholder='Search order number'; input.setAttribute('aria-label','Search order number'); order.before(input);
            let timeout;
            const search = async()=>{try{const res=await fetch(order.dataset.searchUrl+'?q='+encodeURIComponent(input.value),{headers:{Accept:'application/json'}});const data=await res.json();order.replaceChildren(new Option('Choose an order',''));data.results.forEach(r=>{const o=new Option(r.text,r.id);o.account=r.account;order.add(o);});}catch(e){error(form.querySelector('[data-refund-error]'),'Unable to load orders. Try again.');}};
            input.addEventListener('input',()=>{clearTimeout(timeout);timeout=setTimeout(search,250);});
            if (!order.value) search(); order.addEventListener('change',()=>{account=order.selectedOptions[0]?.account;showAccount();});
        }
        form.querySelector('[data-refund-add]').addEventListener('click',add); showAccount(); add();
        form.addEventListener('submit',e=>e.preventDefault());
        form.querySelector('[data-refund-preview]').addEventListener('click',async e=>{
            if (!form.reportValidity()) return;
            const button=e.currentTarget; button.disabled=true; error(form.querySelector('[data-refund-error]'),'');
            try {
                const data=new FormData(form), plan=await post(form.dataset.previewUrl,data);
                const rows=plan.lines.map(l=>'<tr><td>'+escape(l.method)+'<br>'+escape(l.reference||'Cash')+'</td><td>'+money(l.amount_cents)+'<br><small>Tax included '+money(l.tax_cents)+'</small></td><td>'+escape(l.refund_date)+'<br>'+escape(l.method==='cheque'?'Pending · cheque '+l.cheque_date:'Confirmed payout')+'</td><td>'+escape(l.proof_name||'No attachment')+'<br>'+escape(l.note)+'</td></tr>').join('');
                openReview(form,data,plan,'<p><strong>Order '+escape(plan.order.number)+'</strong></p><p>'+escape(plan.reason)+'</p><div class="receipt-metrics"><div><span>Confirmed refund</span><strong>'+money(plan.confirmed_cents)+'</strong></div><div><span>Pending cheque</span><strong>'+money(plan.pending_cents)+'</strong></div><div><span>Order balance after refund</span><strong>'+money(plan.balance_after_cents)+'</strong></div></div><table><thead><tr><th>Method / reference</th><th>Amount</th><th>Date / status</th><th>Proof / notes</th></tr></thead><tbody>'+rows+'</tbody></table>','Confirm & Record Refund');
            } catch(err) {error(form.querySelector('[data-refund-error]'),err.message);} finally {button.disabled=false;}
        });
    }
    document.querySelectorAll('[data-refund-action]').forEach(action=>{
        action.addEventListener('submit',async e=>{
            e.preventDefault(); if (!action.reportValidity()) return;
            const button=action.querySelector('button[type=submit]'); button.disabled=true;
            error(action.querySelector('[data-refund-error]'),'');
            try {
                const data=new FormData(action), plan=await post(action.dataset.previewUrl,data), type=plan.data.action;
                openReview(action,data,plan,'<p><strong>'+escape(type==='clear'?'Confirm bank clearance':'Cancel pending cheque')+'</strong> · Order '+escape(plan.order.number)+'</p><p>'+escape(plan.data.reason)+'</p><p>Action date: '+escape(plan.data.date)+' · Reference: '+escape(plan.data.reference)+'</p><p>'+escape(action.dataset.amount)+' · '+escape(action.dataset.reference)+'</p><p>'+escape(type==='clear'?'A refund and matching credit note will now be posted.':'No money or credit will be posted. The reserved amount becomes available again.')+'</p>','Confirm '+(type==='clear'?'Clearance':'Cancellation'));
            } catch(err){error(action.querySelector('[data-refund-error]'),err.message);}finally{button.disabled=false;}
        });
    });
});
