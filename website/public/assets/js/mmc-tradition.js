(() => {
    const section = document.querySelector('.mmc-tradition');
    if (!section) return;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const desktop = matchMedia('(min-width: 901px)');
    let scheduled = false;
    function render() {
        scheduled = false;
        if (reduced.matches || !desktop.matches) {
            section.style.setProperty('--tradition-parallax', '0px');
            return;
        }
        const rect = section.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > innerHeight) return;
        // Stronger scroll movement, bounded by the background's extra height.
        const spare = (Math.max(section.clientWidth / 3, section.clientHeight + 180) - section.clientHeight) / 2;
        const offset = Math.max(-spare, Math.min(spare, (innerHeight / 2 - rect.top - rect.height / 2) * 0.35));
        section.style.setProperty('--tradition-parallax', `${offset.toFixed(1)}px`);
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



