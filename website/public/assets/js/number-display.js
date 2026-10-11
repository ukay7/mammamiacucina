(() => {
    'use strict';
    const state = new WeakMap();
    const bound = new WeakSet();
    const decimal = (value, mode = 'decimal') => {
        if (value === null || value === undefined || value === '') return '';
        if (!/^-?\d+(\.\d+)?$/.test(String(value)) || !Number.isFinite(Number(value))) return String(value);
        const places = mode === 'quantity' ? 3 : 2;
        let result = (Number(value) + Math.sign(Number(value)) * Number.EPSILON).toFixed(places);
        if (Number(result) === 0) result = (0).toFixed(places);
        return mode === 'quantity' ? result.replace(/\.?0+$/, '') : result;
    };
    const raw = input => {
        const item = state.get(input);
        return item && input.value === item.display ? item.raw : input.value;
    };
    const present = input => {
        if (document.activeElement === input) return;
        const value = raw(input), display = decimal(value, input.dataset.numberDisplay);
        state.set(input, {raw: value, display});
        input.value = display;
    };
    const attach = (input, mode) => {
        if (mode) input.dataset.numberDisplay = mode;
        if (!input.dataset.numberDisplay || input.type === 'hidden') return;
        if (!bound.has(input)) {
            bound.add(input);
            input.addEventListener('focus', () => { input.value = raw(input); state.delete(input); });
            input.addEventListener('input', () => state.delete(input));
            input.addEventListener('blur', () => present(input));
        }
        present(input);
    };
    const set = (input, value) => { state.delete(input); input.value = value ?? ''; present(input); };
    const mode = key => /weight|grams|quantity|pieces|cartons|net_carton_kg|units_per_pack|threshold/.test(key) ? 'quantity' : 'decimal';
    window.MMCNumber = {decimal, raw, attach, set, mode};
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('input[data-number-display]').forEach(input => attach(input)));
    // Native submissions and FormData must carry original precision when the user did not edit a field.
    document.addEventListener('submit', event => {
        const inputs = event.target.querySelectorAll('input[data-number-display]');
        inputs.forEach(input => { const value = raw(input); state.delete(input); input.value = value; });
        setTimeout(() => inputs.forEach(input => present(input)), 0);
    }, true);
    document.addEventListener('formdata', event => {
        event.target.querySelectorAll('input[data-number-display][name]').forEach(input => {
            if (!input.disabled) event.formData.set(input.name, raw(input));
        });
    }, true);
})();
