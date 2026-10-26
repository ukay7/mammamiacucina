(() => {
    const picker = document.querySelector('#inventory-column-picker');
    if (!picker) return;
    const checks = [...picker.querySelectorAll('[data-inventory-check]')];
    const allowed = checks.map(input => input.value);
    const presets = JSON.parse(picker.dataset.presets);
    const selected = () => checks.filter(input => input.checked).map(input => input.value);
    function apply() {
        const keys = selected(), csv = keys.join(',');
        document.querySelectorAll('[data-inventory-column]').forEach(cell => cell.hidden = !keys.includes(cell.dataset.inventoryColumn));
        document.querySelector('[data-inventory-column-count]').textContent = keys.length + 1;
        document.querySelector('#inventory-filters [name="columns"]').value = csv;
        const empty = document.querySelector('[data-inventory-empty]');
        if (empty) empty.colSpan = keys.length + 4;
        document.querySelectorAll('[data-inventory-export], .inv-table-footer .pagination a').forEach(link => {
            const url = new URL(link.href, location.href);
            url.searchParams.set('columns', csv);
            link.href = url.href;
        });
        document.querySelectorAll('[data-inventory-export]').forEach(link => {
            link.setAttribute('aria-disabled', String(!keys.length));
            link.classList.toggle('disabled', !keys.length);
        });
        const url = new URL(location.href);
        url.searchParams.set('columns', csv);
        history.replaceState(history.state, '', url);
        try { localStorage.setItem(picker.dataset.storage, JSON.stringify(keys)); } catch {}
    }
    if (!new URL(location.href).searchParams.has('columns')) {
        try {
            const saved = JSON.parse(localStorage.getItem(picker.dataset.storage));
            if (Array.isArray(saved) && saved.every(key => allowed.includes(key))) checks.forEach(input => input.checked = saved.includes(input.value));
        } catch {}
    }
    checks.forEach(input => input.addEventListener('change', apply));
    picker.querySelectorAll('[data-inventory-preset]').forEach(button => button.addEventListener('click', () => {
        const preset = button.dataset.inventoryPreset;
        const keys = preset === 'all' ? allowed : preset === 'clear' ? [] : presets[preset];
        checks.forEach(input => input.checked = keys.includes(input.value));
        apply();
    }));
    document.querySelector('[data-inventory-toggle]').addEventListener('click', event => {
        picker.hidden = !picker.hidden;
        event.currentTarget.setAttribute('aria-expanded', String(!picker.hidden));
    });
    document.querySelectorAll('[data-inventory-export]').forEach(link => link.addEventListener('click', event => {
        if (!selected().length) event.preventDefault();
    }));
    apply();
})();
