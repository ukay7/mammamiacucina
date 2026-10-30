(() => {
    const modal = document.querySelector('[data-receive-modal]');
    if (!modal) return;
    const form = modal.querySelector('[data-supplier-receiving]');
    const opener = document.querySelector('[data-receive-open]');
    const choice = form.querySelector('[name="add_to_inventory"]');
    const stock = form.querySelector('[data-receive-stock]');
    const warehouse = form.querySelector('[name="warehouse_id"]');
    let busy = false;
    const configure = () => {
        const add = choice.value === '1';
        stock.hidden = !add; stock.disabled = !add; warehouse.required = add;
        form.querySelector('[data-receive-effect]').textContent = add
            ? `The ordered quantities will be added once to ${warehouse.selectedOptions[0]?.value ? warehouse.selectedOptions[0].textContent : 'the warehouse you select'}.`
            : (choice.value === '0' ? 'The order will be marked Received. No inventory quantities will change.' : 'Choose whether inventory should be updated.');
    };
    const open = () => { modal.hidden = false; document.body.classList.add('purchase-dialog-open'); configure(); modal.querySelector('h2').focus(); };
    const close = () => { if (busy) return; modal.hidden = true; document.body.classList.remove('purchase-dialog-open'); opener.focus(); };
    opener.addEventListener('click', open);
    modal.querySelectorAll('[data-receive-close]').forEach(button => button.addEventListener('click', close));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key !== 'Tab') return;
        const controls = [...modal.querySelectorAll('button,input,select,textarea')].filter(el => !el.matches(':disabled') && el.type !== 'hidden' && el.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    choice.addEventListener('change', configure); warehouse.addEventListener('change', configure);
    form.addEventListener('submit', event => {
        if (busy) { event.preventDefault(); return; }
        busy = true;
        const submit = form.querySelector('[data-receive-submit]'); submit.disabled = true; submit.textContent = 'Recording Receipt…';
    });
    configure();
    if (modal.dataset.openOnError === '1') open();
})();
