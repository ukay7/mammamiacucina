(() => {
    'use strict';
    const source = document.currentScript.dataset.iconUrl;
    window.mmcBusinessCustomerIcon = {
        append(container, accountType) {
            if (!source || accountType !== 'business') return;
            const icon = document.createElement('img');
            icon.src = source;
            icon.alt = icon.title = 'Business customer';
            icon.className = 'business-customer-icon';
            icon.width = icon.height = 14;
            icon.style.cssText = 'display:inline-block;width:14px;height:14px;max-width:none;object-fit:contain;vertical-align:middle;margin-left:4px;border:0;border-radius:0;padding:0;flex-shrink:0';
            container.append(icon);
        },
    };
    document.addEventListener('DOMContentLoaded', () => {
        const $ = window.jQuery;
        if (!$?.fn.select2) return;
        document.querySelectorAll('[data-business-customer-select]').forEach(select => {
            const render = (item, selected = false) => {
                if (!item.id) return item.text;
                const line = document.createElement('span');
                line.className = 'business-customer-choice';
                const name = document.createElement('span');
                name.className = 'business-customer-choice-name';
                name.textContent = item.element?.dataset.label || item.text;
                line.append(name);
                window.mmcBusinessCustomerIcon.append(line, item.element?.dataset.accountType);
                if (!selected && item.element?.dataset.email) line.append(document.createTextNode(' · ' + item.element.dataset.email));
                return $(line);
            };
            $(select).select2({width:'100%', templateResult:item=>render(item), templateSelection:item=>render(item,true)});
            if (select.id === 'quote-customer') document.getElementById('customer-search').hidden = true;
            $(select).on('change.businessIcon', () => select.dispatchEvent(new Event('input', {bubbles:true})));
        });
    });
})();
