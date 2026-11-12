(() => {
    'use strict';
    const cfg = window.pricingListConfig;
    if (!cfg) return;
    const $ = id => document.getElementById(id);
    const form = $('pricing-details'), dialog = $('pricing-confirm');
    const selected = new Set(cfg.initial.selected.map(Number));
    let tier = cfg.initial.pricing_tier, dirty = false, busy = false, previewToken = null, wantsPdf = false;
    const money = cents => cents == null ? '' : (cents / 100).toFixed(2);
    const cents = value => value === '' ? null : Math.round(Number(value) * 100);
    const samePrices = (a, b) => a.each_cents === b.each_cents && a.box_cents === b.box_cents;
    const products = cfg.catalog.map(item => {
        const saved = selected.has(Number(item.product_id));
        const prices = saved ? {each_cents: item.each_cents, box_cents: item.box_cents} : item.defaults[tier];
        return {...item, ...prices, custom: saved && !samePrices(prices, item.defaults[tier])};
    });
    const byId = new Map(products.map(p => [Number(p.product_id), p]));
    const categories = new Set((cfg.initial.filters.categories || []).map(Number));
    const fields = {title:'list-title', pricing_tier:'list-tier', effective_date:'list-date', valid_until:'list-until', recipient_name:'list-recipient', notes:'list-notes'};
    for (const [key, id] of Object.entries(fields)) $(id).value = cfg.initial[key];
    $('list-images').checked = cfg.initial.show_images;
    const customerOptions = [...$('pricing-customers').options];
    $('list-customer').value = customerOptions.find(o => Number(o.dataset.id) === Number(cfg.initial.customer_id))?.value || '';
    const filterFields = {q:'filter-q', supplier:'filter-supplier', active:'filter-active', selection:'filter-selection', price_state:'filter-price-state', min_price:'filter-min-price', max_price:'filter-max-price', sort:'filter-sort'};
    const element = (tag, text, className) => {
        const el = document.createElement(tag);
        if (text != null) el.textContent = text;
        if (className) el.className = className;
        return el;
    };
    [...new Set(products.map(p => p.supplier).filter(Boolean))].sort().forEach(value => {
        const option = element('option', value); option.value = value; $('filter-supplier').append(option);
    });
    for (const [key, id] of Object.entries(filterFields)) if (cfg.initial.filters[key] != null) $(id).value = cfg.initial.filters[key];
    cfg.categories.forEach(category => {
        const label = element('label'); const box = element('input'); box.type = 'checkbox'; box.value = category.id; box.checked = categories.has(category.id);
        label.append(box, document.createTextNode(' ' + category.name)); $('category-filters').append(label);
    });
    function filters() {
        const values = {categories: [...categories]};
        for (const [key, id] of Object.entries(filterFields)) values[key] = $(id).value;
        return values;
    }
    function visibleProducts() {
        const f = filters(), term = f.q.toLocaleLowerCase().trim();
        return products.filter(p => (!term || [p.code,p.name,p.category,p.pack].join(' ').toLocaleLowerCase().includes(term))
            && (!categories.size || p.category_ids.some(id => categories.has(Number(id))))
            && (!f.supplier || p.supplier === f.supplier)
            && (f.active === 'all' || Boolean(p.active) === (f.active === 'active'))
            && (f.selection === 'all' || selected.has(p.product_id) === (f.selection === 'selected'))
            && (f.price_state === 'all' || (p.box_cents != null || p.each_cents != null) === (f.price_state === 'priced'))
            && (f.min_price === '' || p.box_cents != null && p.box_cents >= cents(f.min_price))
            && (f.max_price === '' || p.box_cents != null && p.box_cents <= cents(f.max_price)))
            .sort((a,b) => a.category.localeCompare(b.category,undefined,{numeric:true}) || (f.sort === 'price'
                ? (a.box_cents ?? Infinity) - (b.box_cents ?? Infinity)
                : String(a[f.sort === 'code' ? 'code' : 'name'] || '').localeCompare(String(b[f.sort === 'code' ? 'code' : 'name'] || ''),undefined,{numeric:true})) || a.product_id-b.product_id);
    }
    function markDirty() { dirty = true; previewToken = null; }
    function render() {
        const visible = visibleProducts(), fragment = document.createDocumentFragment();
        const groups = new Map();
        visible.forEach(p => { if (!groups.has(p.category)) groups.set(p.category, []); groups.get(p.category).push(p); });
        for (const [name, rows] of groups) {
            const group = products.filter(p => p.category === name), chosen = group.filter(p => selected.has(p.product_id)).length;
            const tr = element('tr',null,'pricing-category'), th = element('th'), wrap = element('div',null,'pricing-category-label'); th.colSpan = 6;
            const label = element('label'), checkbox = element('input'); checkbox.type = 'checkbox'; checkbox.checked = chosen === group.length; checkbox.indeterminate = chosen > 0 && chosen < group.length; checkbox.dataset.category = name;
            checkbox.setAttribute('aria-label','Select entire category ' + name + ', including hidden products'); label.append(checkbox,document.createTextNode('Entire category')); wrap.append(label);
            wrap.append(element('span',name + ' · ' + rows.length + ' shown / ' + group.length + ' products')); th.append(wrap); tr.append(th); fragment.append(tr);
            rows.forEach(p => {
                const tr = element('tr',null,selected.has(p.product_id) ? 'pricing-selected' : ''); tr.dataset.product = p.product_id;
                const selection = element('td',null,'pricing-selection'), box = element('input'); box.type = 'checkbox'; box.checked = selected.has(p.product_id); box.dataset.select = p.product_id; box.setAttribute('aria-label','Select ' + p.name); selection.append(box); tr.append(selection);
                tr.append(element('td',p.code || '—'));
                const td = element('td'), info = element('div',null,'pricing-product');
                if (p.image) { const img = element('img'); img.src = p.image; img.alt = ''; info.append(img); }
                const text = element('div'); text.append(element('strong',p.name));
                if (!p.active) text.append(element('small','Inactive product'));
                if (tier === 'business' && p.business_fallback) text.append(element('small','Individual price used (no business price)'));
                if (p.custom) text.append(element('small','Custom list price','pricing-custom'));
                const reset = element('button','Reset to base price','pricing-row-reset'); reset.type = 'button'; reset.dataset.reset = p.product_id; text.append(reset); info.append(text); td.append(info); tr.append(td,element('td',p.pack));
                ['each','box'].forEach(kind => {
                    const td = element('td'), input = element('input'); input.type = 'number'; input.min = '0'; input.max = '1000000'; input.step = '.01'; input.className = 'form-control'; input.value = money(p[kind + '_cents']); input.placeholder = '—'; input.dataset.price = kind;
                    input.setAttribute('aria-label',kind + ' price for ' + p.name); td.append(input); tr.append(td);
                });
                fragment.append(tr);
            });
        }
        if (!visible.length) { const tr = element('tr'), td = element('td','No products match these filters. Your existing selections are retained.','text-center p-5'); td.colSpan = 6; tr.append(td); fragment.append(tr); }
        $('pricing-rows').replaceChildren(fragment);
        $('selection-count').textContent = selected.size + ' products selected';
        const hidden = [...selected].filter(id => !visible.some(p => p.product_id === id)).length;
        $('visible-count').textContent = visible.length + ' products shown' + (hidden ? ' · ' + hidden + ' selected products hidden by filters' : '');
        $('category-filter-count').textContent = categories.size ? '(' + categories.size + ' selected)' : '(all)';
    }
    const reset = p => { Object.assign(p,p.defaults[tier]); p.price_basis = p.pieces ? (p.defaults[tier].box_cents != null ? 'box' : 'each') : 'independent'; p.custom = false; };
    // Capture typing immediately; previews must never depend on a later blur event.
    $('pricing-rows').addEventListener('input', event => {
        const el = event.target;
        if (!el.dataset.price) return;
        el.setCustomValidity('');
        if (!el.validity.valid) return;
        const p = byId.get(Number(el.closest('tr').dataset.product)), kind = el.dataset.price, value = cents(el.value);
        const otherKind = kind === 'box' ? 'each' : 'box';
        const otherValue = !p.pieces ? p[otherKind + '_cents'] : value == null ? null : kind === 'box' ? Math.round(value/p.pieces) : value*p.pieces;
        if ((otherValue ?? 0) > 100000000) { el.setCustomValidity('The calculated price exceeds CAD 1,000,000.'); return; }
        p[kind + '_cents'] = value; p[otherKind + '_cents'] = otherValue;
        p.price_basis = p.pieces ? kind : 'independent'; p.custom = true;
        el.closest('tr').querySelector('[data-price="' + otherKind + '"]').value = money(otherValue);
        markDirty();
    });
    $('pricing-rows').addEventListener('change', event => {
        const el = event.target;
        if (el.dataset.category != null) products.filter(p => p.category === el.dataset.category).forEach(p => el.checked ? selected.add(p.product_id) : selected.delete(p.product_id));
        if (el.dataset.select) el.checked ? selected.add(Number(el.dataset.select)) : selected.delete(Number(el.dataset.select));
        if (el.dataset.price) {
            if (!el.reportValidity()) return;
            const p = byId.get(Number(el.closest('tr').dataset.product)); const kind = el.dataset.price; const value = cents(el.value);
            const pair = {...p, [kind + '_cents']:value};
            if (p.pieces) { pair.price_basis = kind; pair[kind === 'box' ? 'each_cents' : 'box_cents'] = value == null ? null : kind === 'box' ? Math.round(value/p.pieces) : value*p.pieces; }
            else pair.price_basis = 'independent';
            if ((pair.box_cents ?? 0) > 100000000) { el.setCustomValidity('The calculated box price exceeds CAD 1,000,000.'); el.reportValidity(); el.setCustomValidity(''); el.value = money(p[kind + '_cents']); return; }
            Object.assign(p,pair); p.custom = true;
        }
        markDirty(); render();
    });
    $('pricing-rows').addEventListener('click', event => { if (event.target.dataset.reset) { reset(byId.get(Number(event.target.dataset.reset))); markDirty(); render(); } });
    Object.values(filterFields).forEach(id => $(id).addEventListener('input',() => { markDirty(); render(); }));
    $('category-filters').addEventListener('change',event => { const id = Number(event.target.value); event.target.checked ? categories.add(id) : categories.delete(id); markDirty(); render(); });
    $('clear-filters').addEventListener('click',() => { Object.values(filterFields).forEach(id => $(id).value = ''); ['active','selection','price-state'].forEach(key => $('filter-' + key).value = 'all'); $('filter-sort').value = 'name'; categories.clear(); $('category-filters').querySelectorAll('input').forEach(el => el.checked = false); markDirty(); render(); });
    $('select-visible').addEventListener('click',() => { visibleProducts().forEach(p => selected.add(p.product_id)); markDirty(); render(); });
    $('clear-visible').addEventListener('click',() => { visibleProducts().forEach(p => selected.delete(p.product_id)); markDirty(); render(); });
    $('reset-prices').addEventListener('click',() => { if (selected.size && window.confirm('Reset all selected products to ' + tier + ' base prices?')) { selected.forEach(id => reset(byId.get(id))); markDirty(); render(); } });
    $('list-tier').addEventListener('change',() => { tier = $('list-tier').value; products.filter(p => !p.custom).forEach(reset); markDirty(); render(); });
    $('list-customer').addEventListener('input',() => { const option = customerOptions.find(o => o.value === $('list-customer').value); $('list-customer').setCustomValidity(''); if (option) $('list-recipient').value = option.dataset.recipient; });
    form.addEventListener('input',markDirty); form.addEventListener('submit',event => event.preventDefault());
    function payload() {
        const data = {items:[...selected].map(id => { const p = byId.get(id); return {product_id:id,each_price:money(p.each_cents),box_price:money(p.box_cents),price_basis:p.price_basis}; }), filters:filters(), show_images:$('list-images').checked, revision:cfg.initial.revision,draft_key:cfg.draft_key};
        for (const [key,id] of Object.entries(fields)) data[key] = $(id).value;
        const option = customerOptions.find(o => o.value === $('list-customer').value);
        data.customer_id = option ? Number(option.dataset.id) : null;
        return data;
    }
    async function request(url,method,data) {
        const response = await fetch(url,{method,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':cfg.csrf},body:JSON.stringify(data)});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join('\n') : result.message || 'Unable to complete the request. Please reload and try again.');
        return result;
    }
    function pending(value) { busy = value; $('pricing-editor').inert = value; ['review-save','review-pdf','confirm-save','close-confirm'].forEach(id => $(id).disabled = value); }
    async function review(pdf) {
        if (busy) return;
        $('list-customer').setCustomValidity($('list-customer').value && !customerOptions.some(o => o.value === $('list-customer').value) ? 'Select a customer from the suggestions, or clear this field and use Prepared for.' : '');
        if (!form.reportValidity()) return;
        for (const input of $('pricing-rows').querySelectorAll('input[type=number]')) if (!input.reportValidity()) return;
        $('pricing-errors').hidden = true;
        try {
            pending(true); const result = await request(cfg.preview_url,'POST',payload()); previewToken = result.token; wantsPdf = pdf;
            $('pricing-preview').srcdoc = result.html;
            $('list-reference').textContent = result.number;
            $('confirm-summary').textContent = result.number + ' · ' + result.count + ' selected products · ' + tier + ' pricing · ' + ($('list-recipient').value || 'General price list');
            $('confirm-error').textContent = ''; $('confirm-save').textContent = pdf ? 'Confirm, save & open PDF' : 'Confirm & save'; dialog.showModal();
        } catch(error) { $('pricing-errors').textContent = error.message; $('pricing-errors').hidden = false; $('pricing-errors').scrollIntoView({block:'center'}); }
        finally { pending(false); }
    }
    $('review-save').addEventListener('click',() => review(false)); $('review-pdf').addEventListener('click',() => review(true));
    $('close-confirm').addEventListener('click',() => { previewToken = null; dialog.close(); });
    dialog.addEventListener('cancel',event => { if (busy) event.preventDefault(); else previewToken = null; });
    $('confirm-save').addEventListener('click',async () => {
        if (busy || !previewToken) return;
        const popup = wantsPdf ? window.open('about:blank','_blank') : null;
        if (popup) popup.opener = null;
        try {
            pending(true); const result = await request(cfg.save_url,cfg.method,{preview_token:previewToken}); dirty = false;
            if (popup) popup.location.href = result.pdf_url;
            window.location.href = result.show_url;
        } catch(error) { if (popup) popup.close(); $('confirm-error').textContent = error.message; pending(false); }
    });
    window.addEventListener('beforeunload',event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    render();
})();
