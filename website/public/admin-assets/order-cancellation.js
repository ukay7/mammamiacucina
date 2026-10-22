(() => {
    const dialog = document.getElementById('order-cancellation-dialog');
    if (!dialog) return;
    let finish;
    window.mmcCancellationChoice = orderNumber => new Promise(resolve => {
        if (finish) { resolve(null); return; }
        const previousFocus = document.activeElement;
        dialog.querySelector('[data-cancellation-order]').textContent = orderNumber || 'this order';
        finish = choice => {
            finish = null;
            dialog.close();
            previousFocus?.focus({preventScroll: true});
            resolve(choice);
        };
        dialog.showModal();
        // Focus Go back; pressing Enter must not silently choose a stock action.
        dialog.querySelector('[data-cancellation-back]').focus();
    });
    dialog.addEventListener('click', event => {
        const choice = event.target.closest('[data-cancellation-choice]');
        if (choice) finish?.(choice.dataset.cancellationChoice);
        else if (event.target.closest('[data-cancellation-back]')) finish?.(null);
    });
    dialog.addEventListener('cancel', event => { event.preventDefault(); finish?.(null); });

    const needsChoice = form => {
        switch (form.dataset.cancellationKind) {
            case 'status': return form.elements.status.value === 'cancelled' && form.dataset.currentStatus !== 'cancelled';
            case 'resolution': return ['unpaid', 'refunded'].includes(form.elements.resolution.value);
            case 'refund': return form.dataset.cancellationEligible === '1' && (!form.elements.amount || Math.round(Number(form.elements.amount.value) * 100) >= Number(form.dataset.refundRemaining));
            case 'checkout': return true;
            default: return false;
        }
    };
    const approved = new WeakSet();
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.cancellationKind) return;
        if (approved.has(form)) { approved.delete(form); return; }
        // A previous attempt must not supply a stale choice to a different action.
        form.querySelector('input[data-cancellation-value]')?.remove();
        if (!needsChoice(form)) return;
        event.preventDefault(); event.stopImmediatePropagation();
        const submitter = event.submitter;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
        const choice = await window.mmcCancellationChoice(form.dataset.orderNumber);
        if (choice === null) return;
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'restock_inventory'; input.value = choice; input.dataset.cancellationValue = '';
        form.append(input);
        approved.add(form); form.dataset.cancellationConfirmed = '1';
        form.requestSubmit(submitter);
    }, true);
})();
