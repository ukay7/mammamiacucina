document.querySelectorAll('[data-payment-adjustment]').forEach(form => {
    const fields = form.querySelector('[data-payment-correct-fields]');
    const sync = () => {
        const correction = form.elements.action.value === 'correct_method';
        fields.hidden = !correction;
        form.elements.method.required = correction;
        form.elements.reference.required = correction && form.elements.method.value !== 'cash';
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
