(() => {
    const modal = document.querySelector('[data-supplier-pdf-modal]');
    if (!modal) return;
    const form = modal.querySelector('[data-supplier-pdf-form]');
    const submit = modal.querySelector('[data-supplier-pdf-generate]');
    const choices = modal.querySelector('[data-supplier-pdf-choices]');
    const orderList = modal.querySelector('[data-supplier-pdf-order]');
    const preview = modal.querySelector('[data-supplier-pdf-preview]');
    const error = modal.querySelector('[data-supplier-pdf-error]');
    let data = null, selected = [], opener = null, request = null;
    const groups = () => {
        if (selected.length <= 8) return [selected];
        const identity = selected.filter(key => ['serial','product_code','barcode','name'].includes(key));
        const remaining = [...selected], sections = [];
        while (remaining.length) {
            const repeat = sections.length ? identity.filter(key => !remaining.includes(key)) : [];
            sections.push([...repeat, ...remaining.splice(0, 8 - repeat.length)]);
        }
        return sections;
    };
    function render(focusKey = null, direction = null) {
        const valid = data && selected.length > 0;
        submit.disabled = !valid;
        form.elements.columns.value = selected.join(',');
        choices.querySelectorAll('input').forEach(input => { input.checked = selected.includes(input.value); });
        orderList.replaceChildren(); preview.replaceChildren();
        selected.forEach((key, index) => {
            const li = document.createElement('li'), label = document.createElement('span');
            label.textContent = `${index + 1}. ${data.labels[key]}`; li.append(label);
            for (const [offset, text, action] of [[-1,'←','earlier'],[1,'→','later']]) {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary'; button.textContent = text;
                button.setAttribute('aria-label', `Move ${data.labels[key]} ${action}`);
                button.disabled = index + offset < 0 || index + offset >= selected.length;
                button.addEventListener('click', () => { [selected[index],selected[index + offset]] = [selected[index + offset],selected[index]]; render(key, action); });
                li.append(button);
                if (focusKey === key && direction === action) queueMicrotask(() => button.focus());
            }
            orderList.append(li);
        });
        const sections = valid ? groups() : [];
        modal.querySelector('[data-supplier-pdf-layout]').textContent = valid
            ? `${selected.length} columns selected · A4 ${selected.length > 6 ? 'landscape' : 'portrait'}${sections.length > 1 ? ` · ${sections.length} column sections, with selected identifiers repeated for matching rows` : ''}. Preview includes all ${data.rows.length} ordered products.`
            : 'Select at least one column to generate the PDF.';
        modal.querySelector('[data-supplier-pdf-legacy]').hidden = !data?.legacy_details || !selected.some(key => ['manufacturer','original_description','allocated_to'].includes(key));
        sections.forEach((columns, index) => {
            if (sections.length > 1) { const heading = document.createElement('h4'); heading.textContent = `Column section ${index + 1}`; preview.append(heading); }
            const table = document.createElement('table'); table.className = 'table';
            const head = table.createTHead().insertRow();
            columns.forEach(key => { const th = document.createElement('th'); th.textContent = data.labels[key]; head.append(th); });
            const body = table.createTBody();
            data.rows.forEach(values => { const row = body.insertRow(); columns.forEach(key => { const cell = row.insertCell(); cell.textContent = values[key] ?? '—'; }); });
            preview.append(table);
        });
        const totals = modal.querySelector('[data-supplier-pdf-totals]'); totals.replaceChildren();
        if (selected.includes('line_total')) Object.entries(data.totals).forEach(([currency, amount]) => {
            const p = document.createElement('p'); p.textContent = `Order total ${currency} ${amount}`; totals.append(p);
        });
    }
    const close = () => {
        request?.abort(); modal.hidden = true; document.body.classList.remove('purchase-dialog-open'); opener?.focus();
    };
    document.querySelectorAll('[data-supplier-pdf-open]').forEach(button => button.addEventListener('click', async () => {
        request?.abort(); request = new AbortController(); const current = request;
        opener = button; data = null; selected = []; submit.disabled = true;
        form.action = button.dataset.pdfUrl; error.hidden = true;
        modal.querySelector('[data-supplier-pdf-content]').hidden = true;
        modal.querySelector('[data-supplier-pdf-context]').textContent = 'Loading saved order…';
        modal.hidden = false; document.body.classList.add('purchase-dialog-open'); modal.querySelector('h2').focus();
        try {
            const response = await fetch(button.dataset.previewUrl, {headers:{Accept:'application/json'},signal:current.signal});
            if (!response.ok) throw new Error('Unable to load this supplier order. Refresh the page and try again.');
            const loaded = await response.json();
            if (current !== request) return;
            data = loaded; selected = [...data.defaults];
            modal.querySelector('[data-supplier-pdf-context]').textContent = `${data.number} · ${data.supplier} · ${data.status}`;
            choices.replaceChildren();
            Object.entries(data.labels).forEach(([key, text]) => {
                const label = document.createElement('label'), input = document.createElement('input');
                input.type = 'checkbox'; input.value = key;
                input.addEventListener('change', () => { selected = input.checked ? [...selected,key] : selected.filter(value => value !== key); render(); });
                label.append(input, document.createTextNode(text)); choices.append(label);
            });
            modal.querySelector('[data-supplier-pdf-content]').hidden = false; render();
        } catch (e) {
            if (e.name === 'AbortError') return;
            error.textContent = e.message; error.hidden = false;
            modal.querySelector('[data-supplier-pdf-context]').textContent = 'PDF preview unavailable.';
        }
    }));
    modal.querySelectorAll('[data-supplier-pdf-close]').forEach(button => button.addEventListener('click', close));
    modal.querySelectorAll('[data-supplier-pdf-preset]').forEach(button => button.addEventListener('click', () => {
        selected = button.dataset.supplierPdfPreset === 'all' ? Object.keys(data.labels) : button.dataset.supplierPdfPreset === 'clear' ? [] : [...data.defaults]; render();
    }));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key !== 'Tab') return;
        const controls = [...modal.querySelectorAll('button,input,a[href]')].filter(el => !el.disabled && el.type !== 'hidden' && el.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    form.addEventListener('submit', event => { if (!data || !selected.length) event.preventDefault(); });
})();
