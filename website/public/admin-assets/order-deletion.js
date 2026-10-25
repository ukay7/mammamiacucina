(() => {
    const dialog = document.getElementById('order-delete-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const form = dialog.querySelector('form');
    const reason = form.elements.reason;
    const submit = form.querySelector('[type="submit"]');
    const errors = dialog.querySelector('[data-delete-errors]');
    const failed = {
        orderId: dialog.dataset.failedOrder,
        reason: reason.value,
        stock: form.elements.restock_inventory.value,
    };
    let opener;

    const open = (trigger, restore = false) => {
        opener = trigger;
        form.reset();
        form.action = trigger.dataset.deleteUrl;
        form.elements._delete_order_id.value = trigger.dataset.orderId;
        // Grid status updates can advance the revision without reloading the list.
        form.elements.revision.value = trigger.closest('tr').querySelector('[name="revision"]')?.value ?? trigger.dataset.orderRevision;
        reason.value = restore ? failed.reason : '';
        reason.setCustomValidity('');
        form.querySelectorAll('[name="restock_inventory"]').forEach(input => { input.checked = restore && input.value === failed.stock; });
        form.elements.confirm.checked = false;
        submit.disabled = false;
        errors.hidden = !restore;
        dialog.querySelectorAll('[data-delete-order-number]').forEach(node => { node.textContent = trigger.dataset.orderNumber; });
        document.dispatchEvent(new Event('status:close'));
        dialog.showModal();
        reason.focus();
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-order-delete]');
        if (!trigger) return;
        event.preventDefault();
        open(trigger);
    });
    dialog.querySelectorAll('[data-delete-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => opener?.focus({preventScroll: true}));
    reason.addEventListener('input', () => reason.setCustomValidity(''));
    form.addEventListener('submit', event => {
        if (!reason.value.trim()) {
            event.preventDefault();
            reason.setCustomValidity('Enter a reason for deleting this order.');
            reason.reportValidity();
            return;
        }
        submit.disabled = true;
    });

    // A rejected deletion returns to the same filtered list with its explanation.
    if (failed.orderId) {
        const trigger = [...document.querySelectorAll('[data-order-delete]')].find(node => node.dataset.orderId === failed.orderId);
        if (trigger) open(trigger, true);
    }
})();
