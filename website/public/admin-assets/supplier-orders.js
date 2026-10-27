(() => {
    const form = document.querySelector('[data-purchase-builder]');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-purchase-row]')];
    const modal = document.querySelector('[data-purchase-modal]');
    const review = form.querySelector('[data-purchase-review]');
    const place = modal.querySelector('[data-purchase-place]');
    const all = form.querySelector('[data-purchase-all]');
    const token = form.querySelector('[name="_token"]').value;
    let pending = null, busy = false;
    const selected = () => rows.filter(row => row.querySelector('[data-purchase-select]').checked);
    const error = (element, message = '') => { element.textContent = message; element.hidden = !message; };
    const count = () => {
        const chosen = selected();
        form.querySelector('[data-purchase-count]').textContent = chosen.length;
        review.disabled = !chosen.length || busy;
        const available = rows.filter(row => !row.querySelector('[data-purchase-select]').disabled);
        all.checked = available.length > 0 && chosen.length === available.length;
        all.indeterminate = chosen.length > 0 && !all.checked;
    };
    rows.forEach(row => row.querySelector('[data-purchase-select]').addEventListener('change', count));
    all.addEventListener('change', () => { rows.forEach(row => { const check = row.querySelector('[data-purchase-select]'); if (!check.disabled) check.checked = all.checked; }); count(); });
    const close = () => {
        if (busy) return;
        modal.hidden = true; document.body.classList.remove('purchase-dialog-open'); review.focus();
    };
    modal.querySelectorAll('[data-purchase-close]').forEach(button => button.addEventListener('click', close));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key !== 'Tab') return;
        const controls = [...modal.querySelectorAll('button:not([disabled]),textarea,a[href]')];
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { last.focus(); event.preventDefault(); }
        else if (!event.shiftKey && document.activeElement === last) { first.focus(); event.preventDefault(); }
    });
    async function post(url, payload) {
        const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':token}, body:JSON.stringify(payload)});
        let data;
        try { data = await response.json(); } catch { throw new Error('Unable to load the order. Refresh the page and try again.'); }
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Unable to save the supplier order.'));
        return data;
    }
    function textCell(row, value) { const cell = document.createElement('td'); cell.textContent = value ?? '—'; row.append(cell); }
    review.addEventListener('click', async () => {
        if (busy) return;
        const chosen = selected();
        error(form.querySelector('[data-purchase-error]'));
        if (!chosen.length) return;
        for (const row of chosen) {
            const input = row.querySelector('[data-purchase-quantity]');
            if (!input.value || !input.checkValidity()) { input.focus(); input.reportValidity(); error(form.querySelector('[data-purchase-error]'), 'Enter a positive order quantity for every selected product, with up to three decimal places.'); return; }
        }
        const payload = {supplier_id:form.querySelector('[name="supplier_id"]').value,
            warehouse_id:form.querySelector('[name="warehouse_id"]').value || null,
            items:chosen.map(row => ({product_id:row.dataset.productId,quantity:row.querySelector('[data-purchase-quantity]').value}))};
        busy = true; count(); review.textContent = 'Loading Preview…';
        try {
            const data = await post(form.dataset.previewUrl, payload);
            pending = {...payload,preview_hash:data.preview_hash,request_key:form.querySelector('[name="request_key"]').value};
            const details = modal.querySelector('[data-purchase-supplier]'); details.replaceChildren();
            const heading = document.createElement('h3'); heading.textContent = `#${data.supplier.id} · ${data.supplier.name}`; details.append(heading);
            const contacts = document.createElement('div'); contacts.className = 'purchase-contact-grid';
            for (const [key,label] of Object.entries({contact_name:'Contact',email:'Email',phone:'Phone',address:'Address'})) { const p = document.createElement('p'); p.textContent = `${label}: ${data.supplier[key] || 'Not provided'}`; contacts.append(p); }
            details.append(contacts);
            modal.querySelector('[data-purchase-location]').textContent = `Stock location: ${data.warehouse_name}`;
            const body = modal.querySelector('[data-purchase-preview-lines]'); body.replaceChildren();
            data.items.forEach(item => { const row = document.createElement('tr'); [item.product_code || item.barcode,item.name,item.quantity.replace(/\.?0+$/, ''),`${item.currency} ${item.unit_cost}`,`${item.currency} ${item.line_total}`].forEach(value => textCell(row,value)); body.append(row); });
            const totals = modal.querySelector('[data-purchase-totals]'); totals.replaceChildren();
            for (const [currency,amount] of Object.entries(data.totals)) { const p = document.createElement('p'); p.textContent = `Total ${currency} ${amount}`; totals.append(p); }
            error(modal.querySelector('[data-purchase-modal-error]'));
            modal.hidden = false; document.body.classList.add('purchase-dialog-open'); modal.querySelector('#purchase-preview-title').focus();
        } catch (e) { error(form.querySelector('[data-purchase-error]'), e.message); }
        finally { busy = false; review.textContent = 'Review Selected Products'; count(); }
    });
    place.addEventListener('click', async () => {
        if (busy || !pending) return;
        busy = true; place.disabled = true; place.textContent = 'Placing Order…';
        try {
            const data = await post(form.dataset.storeUrl, {...pending,notes:modal.querySelector('#purchase-notes').value});
            window.location.assign(data.redirect);
        } catch (e) { error(modal.querySelector('[data-purchase-modal-error]'),e.message); }
        finally { busy = false; place.disabled = false; place.textContent = 'Place Supplier Order'; count(); }
    });
    count();
})();
