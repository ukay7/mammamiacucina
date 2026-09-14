(() => {
    const section = document.querySelector('.mmc-arrivals');
    if (!section) return;
    const dialog = section.querySelector('dialog');
    let trigger;
    section.querySelectorAll('.mmc-arrival__quickview').forEach(button => {
        button.addEventListener('click', () => {
            trigger = button;
            const card = button.closest('.mmc-arrival');
            const image = dialog.querySelector('.mmc-arrivals__preview-image');
            image.src = card.querySelector('img').src;
            image.alt = card.dataset.name;
            dialog.querySelector('h2').textContent = card.dataset.name;
            dialog.querySelector('.mmc-arrivals__preview-price').textContent = card.dataset.price;
            dialog.querySelector('.mmc-arrivals__details').href = card.dataset.url;
            dialog.showModal();
        });
    });
    dialog.querySelector('.mmc-arrivals__close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        const box = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => trigger?.focus());
})();

(() => {
    const section = document.querySelector('.mmc-arrivals');
    if (!section) return;
    const track = section.querySelector('.mmc-arrivals__grid');
    const cards = Array.from(track.querySelectorAll('.mmc-arrival'));
    const controls = section.querySelector('.mmc-arrivals__slider-controls');
    const pause = controls.querySelector('[data-arrival-pause]');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let paused = reduced.matches;
    let timer;
    const maximum = () => Math.max(0, track.scrollWidth - track.clientWidth);
    const step = () => cards[0]?.getBoundingClientRect().width || track.clientWidth;
    function update() {
        controls.hidden = maximum() < 2;
        const first = Math.round(track.scrollLeft / step()) + 1;
        const visible = Math.max(1, Math.round(track.clientWidth / step()));
        controls.querySelector('.mmc-arrivals__position').textContent = `${first}–${Math.min(cards.length, first + visible - 1)} / ${cards.length}`;
    }
    function schedule() {
        clearTimeout(timer);
        if (paused || document.hidden || maximum() < 2 || section.querySelector('dialog[open]') || section.contains(document.activeElement)) return;
        timer = setTimeout(() => move(1), 5000);
    }
    function move(direction) {
        const end = maximum();
        const position = track.scrollLeft;
        const destination = direction > 0
            ? (position >= end - 2 ? 0 : Math.min(end, position + step()))
            : (position <= 2 ? end : Math.max(0, position - step()));
        track.scrollTo({left: destination, behavior: reduced.matches ? 'instant' : 'smooth'});
        schedule();
    }
    controls.querySelector('[data-arrival-prev]').addEventListener('click', () => move(-1));
    controls.querySelector('[data-arrival-next]').addEventListener('click', () => move(1));
    pause.addEventListener('click', () => {
        paused = !paused;
        pause.textContent = paused ? '▶' : 'Ⅱ';
        pause.setAttribute('aria-label', paused ? 'Play product rotation' : 'Pause product rotation');
        schedule();
    });
    track.addEventListener('keydown', event => {
        if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        move(event.key === 'ArrowRight' ? 1 : -1);
    });
    track.addEventListener('scroll', update, {passive:true});
    track.addEventListener('pointerdown', () => clearTimeout(timer), {passive:true});
    track.addEventListener('pointerup', schedule, {passive:true});
    track.addEventListener('pointercancel', schedule, {passive:true});
    section.addEventListener('focusin', () => clearTimeout(timer));
    section.addEventListener('focusout', () => setTimeout(schedule, 0));
    document.addEventListener('visibilitychange', schedule);
    reduced.addEventListener('change', () => {
        paused = reduced.matches;
        pause.textContent = paused ? '▶' : 'Ⅱ';
        pause.setAttribute('aria-label', paused ? 'Play product rotation' : 'Pause product rotation');
        schedule();
    });
    new ResizeObserver(() => { update(); schedule(); }).observe(track);
    pause.textContent = paused ? '▶' : 'Ⅱ';
    pause.setAttribute('aria-label', paused ? 'Play product rotation' : 'Pause product rotation');
    update();
    schedule();
})();
