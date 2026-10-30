(() => {
    const directory = document.querySelector('[data-customer-directory]');
    if (!directory) return;
    const picker = directory.querySelector('#customer-column-picker');
    const checks = [...picker.querySelectorAll('[data-customer-column-check]')];
    const allowed = checks.map(input => input.value);
    const selected = () => checks.filter(input => input.checked).map(input => input.value);
    function apply() {
        const keys = selected(), csv = keys.join(',');
        directory.querySelectorAll('[data-customer-column]').forEach(cell => { cell.hidden = !keys.includes(cell.dataset.customerColumn); });
        directory.querySelector('[data-customer-column-count]').textContent = keys.length;
        directory.querySelector('[data-customer-columns-value]').value = csv;
        const empty = directory.querySelector('[data-customer-empty]');
        if (empty) empty.colSpan = keys.length + 1;
        directory.querySelectorAll('thead a, [data-customer-pagination] a').forEach(link => {
            const url = new URL(link.href, location.href);
            url.searchParams.set('columns', csv);
            link.href = url.href;
        });
        const url = new URL(location.href);
        url.searchParams.set('columns', csv);
        history.replaceState(history.state, '', url);
        try { localStorage.setItem(picker.dataset.storage, JSON.stringify(keys)); } catch {}
    }
    if (!new URL(location.href).searchParams.has('columns')) {
        try {
            const saved = JSON.parse(localStorage.getItem(picker.dataset.storage));
            if (Array.isArray(saved) && saved.every(key => allowed.includes(key))) checks.forEach(input => { input.checked = saved.includes(input.value); });
        } catch {}
    }
    checks.forEach(input => input.addEventListener('change', apply));
    picker.querySelectorAll('[data-customer-column-preset]').forEach(button => button.addEventListener('click', () => {
        const preset = button.dataset.customerColumnPreset;
        const keys = preset === 'all' ? allowed : preset === 'clear' ? [] : ['id', 'identity', 'phone', 'status'];
        checks.forEach(input => { input.checked = keys.includes(input.value); });
        apply();
    }));
    directory.querySelector('[data-customer-column-toggle]').addEventListener('click', event => {
        picker.hidden = !picker.hidden;
        event.currentTarget.setAttribute('aria-expanded', String(!picker.hidden));
    });
    apply();
})();
