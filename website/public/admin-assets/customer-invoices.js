(() => {
    const dialog = document.getElementById('invoice-selection');
    if (!dialog) return;

    const form = dialog.querySelector('form');
    const boxes = [...form.querySelectorAll('input[type="checkbox"]')];
    const total = form.querySelector('[data-invoice-total]');
    const submit = form.querySelector('button[type="submit"]');
    const money = new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' });

    function update() {
        const selected = boxes.filter(box => box.checked);
        const cents = selected.reduce((sum, box) => sum + Number(box.dataset.amount), 0);
        form.elements.expected_total.value = String(cents);
        total.textContent = 'CAD ' + money.format(cents / 100);
        submit.disabled = cents === 0 || selected.length > 100 || cents > 999999999;
        submit.textContent = selected.length > 100 || cents > 999999999 ? 'Select fewer invoices' : 'Continue to card payment';
    }

    document.querySelectorAll('[data-pay-invoices]').forEach(button => button.addEventListener('click', () => {
        const invoiceId = button.dataset.payInvoices;
        boxes.forEach(box => { box.checked = box.value === invoiceId; });
        update();
        dialog.showModal();
    }));
    dialog.querySelector('[data-close-invoices]').addEventListener('click', () => dialog.close());
    form.addEventListener('change', update);
    form.addEventListener('submit', () => { submit.disabled = true; });
})();
