(() => {
    document.querySelectorAll('[data-etransfer-payment-form]').forEach(form => {
        const method = form.querySelector('select[name="method"]');
        const account = form.querySelector('[data-etransfer-account]');
        if (!method || !account) return;
        const update = () => { account.hidden = method.value !== 'etransfer'; };
        method.addEventListener('change', update);
        update();
    });
})();
