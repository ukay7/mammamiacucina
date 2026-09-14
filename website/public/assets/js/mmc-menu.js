(() => {
    const toggle = document.querySelector('.mmc-menu-toggle');
    const menu = document.querySelector('#mmc-menu');
    if (!toggle || !menu) return;
    const products = menu.querySelector('.mmc-products-menu');
    const productsToggle = menu.querySelector('.mmc-products-toggle');
    const submenu = menu.querySelector('#mmc-products-submenu');
    const setProductsOpen = open => {
        if (!productsToggle || !submenu) return;
        productsToggle.setAttribute('aria-expanded', String(open));
        submenu.hidden = !open;
    };
    const close = () => { toggle.setAttribute('aria-expanded', 'false'); menu.classList.remove('is-open'); setProductsOpen(false); };
    productsToggle?.addEventListener('click', () => setProductsOpen(submenu.hidden));
    products?.addEventListener('pointerenter', event => {
        if (event.pointerType === 'mouse' && window.matchMedia('(min-width: 1025px)').matches) setProductsOpen(true);
    });
    products?.addEventListener('pointerleave', event => {
        if (event.pointerType === 'mouse' && !products.contains(document.activeElement)) setProductsOpen(false);
    });
    productsToggle?.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setProductsOpen(true);
            submenu.querySelector('a')?.focus();
        }
    });
    products?.addEventListener('focusout', event => {
        if (!products.contains(event.relatedTarget)) setProductsOpen(false);
    });
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        menu.classList.toggle('is-open', open);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && submenu && !submenu.hidden) {
            setProductsOpen(false); productsToggle.focus(); return;
        }
        if (event.key === 'Escape' && menu.classList.contains('is-open')) { close(); toggle.focus(); }
    });
    document.addEventListener('click', event => {
        if (products && !products.contains(event.target)) setProductsOpen(false);
        if (!menu.contains(event.target) && !toggle.contains(event.target)) close();
    });
    menu.addEventListener('click', event => { if (event.target.closest('a')) close(); });
})();
