document.querySelectorAll('[data-payment-adjustment]').forEach(form => {
    const fields = form.querySelector('[data-payment-correct-fields]');
    const creditNote = form.querySelector('[data-payment-credit-note]');
    const sync = () => {
        const correction = form.elements.action.value === 'correct_method';
        const credit = correction && form.elements.method.value === 'credit';
        fields.hidden = !correction;
        form.elements.method.required = correction;
        form.elements.reference.required = correction && !['cash', 'credit'].includes(form.elements.method.value);
        creditNote.hidden = !credit;
    };
    form.elements.action.addEventListener('change', sync);
    form.elements.method.addEventListener('change', sync);
    form.elements.source.addEventListener('change', () => {
        const amount = form.elements.source.selectedOptions[0]?.dataset.available || '';
        form.elements.amount.value = amount;
        form.elements.amount.max = amount;
    });
    sync();
});
