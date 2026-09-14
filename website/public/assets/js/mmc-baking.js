(() => {
    const section = document.querySelector('.mmc-baking');
    if (!section) return;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const desktop = matchMedia('(min-width: 1025px)');
    let scheduled = false;
    function render() {
        scheduled = false;
        if (reduced.matches || !desktop.matches) {
            section.style.setProperty('--baking-parallax', '0px');
            return;
        }
        const rect = section.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > innerHeight) return;
        // Same scroll speed as Our Tradition, bounded by the 60px overscan.
        const offset = Math.max(-30, Math.min(30, (innerHeight / 2 - rect.top - rect.height / 2) * 0.35));
        section.style.setProperty('--baking-parallax', `${offset.toFixed(1)}px`);
    }
    function update() {
        if (!scheduled) { scheduled = true; requestAnimationFrame(render); }
    }
    addEventListener('scroll', update, {passive:true});
    addEventListener('resize', update);
    reduced.addEventListener('change', update);
    desktop.addEventListener('change', update);
    render();
})();

