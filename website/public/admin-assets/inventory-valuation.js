(() => {
    const picker = document.querySelector('#valuation-column-picker');
    if (!picker) return;
    const checks = [...picker.querySelectorAll('[data-valuation-check]')];
    const allowed = checks.map(input => input.value);
    const toggle = document.querySelector('[data-valuation-toggle]');
    function apply() {
        const selected = checks.filter(input => input.checked).map(input => input.value);
        const columns = selected.join(',');
        document.querySelectorAll('[data-valuation-column]').forEach(cell => cell.hidden = !selected.includes(cell.dataset.valuationColumn));
        document.querySelector('[data-valuation-column-count]').textContent = 6 + selected.length;
        document.querySelector('[data-valuation-filters] [name="columns"]').value = columns;
        document.querySelectorAll('[data-valuation-export], .pagination a').forEach(link => {
            const url = new URL(link.href, location.href);
            url.searchParams.set('columns', columns);
            link.href = url.href;
        });
        const url = new URL(location.href);
        url.searchParams.set('columns', columns);
        history.replaceState(history.state, '', url);
        try { localStorage.setItem(picker.dataset.storage, JSON.stringify(selected)); } catch {}
    }
    if (!new URL(location.href).searchParams.has('columns')) {
        try {
            const saved = JSON.parse(localStorage.getItem(picker.dataset.storage));
            if (Array.isArray(saved)) checks.forEach(input => input.checked = saved.filter(key => allowed.includes(key)).includes(input.value));
        } catch {}
    }
    checks.forEach(input => input.addEventListener('change', apply));
    picker.querySelectorAll('[data-valuation-select]').forEach(button => button.addEventListener('click', () => {
        checks.forEach(input => input.checked = button.dataset.valuationSelect === 'all');
        apply();
    }));
    toggle.addEventListener('click', () => {
        picker.hidden = !picker.hidden;
        toggle.setAttribute('aria-expanded', String(!picker.hidden));
    });
    apply();
})();
