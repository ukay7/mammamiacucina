(() => {
    const dialog = document.getElementById('report-order-dialog');
    if (!dialog) return;
    const body = document.getElementById('report-order-body');
    let controller;
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-report-order]');
        if (!button) return;
        controller?.abort();
        controller = new AbortController();
        body.textContent = 'Loading order breakdown…';
        if (!dialog.open) dialog.showModal();
        try {
            const response = await fetch(button.dataset.reportOrder, {credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},signal:controller.signal});
            if (!response.ok || response.redirected) throw new Error('Unable to load this order. Refresh the page or sign in again.');
            body.innerHTML = await response.text();
        } catch(error) {
            if (error.name !== 'AbortError') body.textContent = error.message;
        }
    });
    dialog.querySelector('[data-close-report]').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('close',()=>controller?.abort());
})();
