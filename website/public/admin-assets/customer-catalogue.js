(() => {
    const form = document.querySelector('[data-catalogue]');
    if (!form) return;

    const search = form.querySelector('[data-catalogue-search]');
    const category = form.querySelector('[data-catalogue-category]');
    const groups = [...form.querySelectorAll('[data-category-group]')];
    const rows = [...form.querySelectorAll('[data-catalogue-product]')].map(element => ({
        element,
        checkbox: element.querySelector('input[type="checkbox"]'),
        quantity: element.querySelector('input[type="number"]'),
        amount: element.querySelector('[data-line-amount]'),
        cents: Number(element.dataset.unitCents),
        categories: element.dataset.categories.split(' '),
        search: element.dataset.search.toLowerCase(),
    }));
    const empty = form.querySelector('[data-catalogue-empty]');
    const count = form.querySelector('[data-selection-count]');
    const summary = form.querySelector('[data-selection-summary]');
    const subtotalDisplay = form.querySelector('[data-selection-subtotal]');
    const discountDisplay = form.querySelector('[data-selection-discount]');
    const taxDisplay = form.querySelector('[data-selection-tax]');
    const invalidSelection = form.querySelector('[data-selection-invalid]');
    const total = form.querySelector('[data-selection-total]');
    const review = form.querySelector('[data-review-selection]');
    const discountBps = Number(form.dataset.discountBps);
    const taxBps = Number(form.dataset.taxBps);
    const unlistedSubtotal = Number(form.dataset.unlistedSubtotal);
    const unlistedCount = Number(form.dataset.unlistedCount);
    const visibleCartCount = Number(form.dataset.visibleCartCount);
    const cad = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });
    const money = cents => cad.format(cents / 100);

    function filter() {
        const query = search.value.trim().toLowerCase();
        for (const row of rows) {
            row.element.hidden = !row.search.includes(query) || (category.value !== '' && !row.categories.includes(category.value));
        }
        for (const group of groups) {
            group.hidden = !group.querySelector('[data-catalogue-product]:not([hidden])');
        }
        empty.hidden = rows.some(row => !row.element.hidden);
    }

    function showSelection() {
        const selected = rows.filter(row => row.checkbox.checked);
        let subtotal = unlistedSubtotal;
        for (const row of rows) {
            const line = row.checkbox.checked && row.quantity.validity.valid ? row.cents * row.quantity.valueAsNumber : null;
            row.amount.textContent = line === null ? '—' : money(line);
            subtotal += line ?? 0;
        }
        count.textContent = `${selected.length} selected`;
        review.disabled = selected.length === 0 && visibleCartCount === 0;
        review.textContent = selected.length || visibleCartCount === 0 ? 'Review order' : 'Update cart';
        const valid = selected.every(row => row.quantity.validity.valid);
        invalidSelection.hidden = valid;
        summary.hidden = !valid || (!selected.length && !unlistedCount);
        if (summary.hidden) return;

        const discount = Math.round(subtotal * discountBps / 10000);
        const discountedSubtotal = subtotal - discount;
        const tax = Math.round(discountedSubtotal * taxBps / 10000);
        subtotalDisplay.textContent = money(subtotal);
        if (discountDisplay) discountDisplay.textContent = money(-discount || 0);
        taxDisplay.textContent = money(tax);
        total.textContent = money(discountedSubtotal + tax);
    }

    search.addEventListener('input', filter);
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter') event.preventDefault();
    });
    category.addEventListener('change', filter);
    form.addEventListener('input', event => {
        if (event.target.closest('[data-catalogue-product]')) showSelection();
    });
    form.addEventListener('submit', event => {
        const invalid = rows.find(row => row.checkbox.checked && !row.quantity.validity.valid);
        if (invalid) {
            event.preventDefault();
            if (invalid.element.hidden) {
                search.value = '';
                category.value = '';
                filter();
            }
            invalid.quantity.reportValidity();
            return;
        }
        review.disabled = true;
    });
    form.addEventListener('formdata', event => {
        for (const row of rows) {
            if (row.quantity && !row.checkbox.checked) event.formData.delete(row.quantity.name);
        }
    });
    window.addEventListener('pageshow', showSelection);
    filter();
    showSelection();

    const details = document.querySelector('[data-details-dialog]');
    form.addEventListener('click', event => {
        const link = event.target.closest('[data-product-details]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !details.showModal) return;
        event.preventDefault();
        const template = link.closest('[data-catalogue-product]').querySelector('[data-product-template]');
        details.querySelector('h2').textContent = link.textContent.trim();
        details.querySelector('[data-details-content]').replaceChildren(template.content.cloneNode(true));
        details.showModal();
    });

    const checkoutDialog = document.querySelector('[data-checkout-dialog]');
    if (checkoutDialog) {
        const checkout = checkoutDialog.querySelector('[data-place-order]');
        checkoutDialog.querySelector('[data-catalogue-remove]').addEventListener('formdata', event => {
            for (const [name, value] of new FormData(checkout)) {
                if (typeof value === 'string' && name !== '_token' && name !== 'checkout_token') {
                    event.formData.set(name, value);
                }
            }
            if (checkout.elements.receipt.files.length) event.formData.set('receipt_selected', '1');
        });
        checkoutDialog.showModal();
    }
})();
