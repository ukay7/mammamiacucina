(() => {
    const hero = document.querySelector('.mmc-hero');
    if (!hero) return;
    const slides = Array.from(hero.querySelectorAll('.mmc-slide'));
    if (slides.length < 2) return;
    const dots = Array.from(hero.querySelectorAll('.mmc-slider-dot'));
    const pause = hero.querySelector('.mmc-slider-pause');
    const track = hero.querySelector('.mmc-slides');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0, busy = false, timer, paused = reduced.matches;
    const stop = () => clearTimeout(timer);
    function schedule() {
        stop();
        if (!paused && !document.hidden && !(hero.contains(document.activeElement) && document.activeElement.matches(':focus-visible'))) {
            timer = setTimeout(() => go(current + 1, 1), 5000);
        }
    }
    function updatePause() {
        pause.setAttribute('aria-label', paused ? 'Play banner rotation' : 'Pause banner rotation');
        pause.firstElementChild.textContent = paused ? '▶' : 'Ⅱ';
    }
    async function go(index, direction = index > current ? 1 : -1) {
        if (busy) return;
        const next = (index + slides.length) % slides.length;
        if (next === current) return;
        stop();
        busy = true;
        const outgoing = slides[current], incoming = slides[next];
        outgoing.setAttribute('aria-hidden', 'true');
        outgoing.inert = true;
        incoming.hidden = false;
        incoming.inert = false;
        incoming.setAttribute('aria-hidden', 'false');
        dots[current].setAttribute('aria-current', 'false');
        dots[next].setAttribute('aria-current', 'true');
        current = next;
        const options = { duration: reduced.matches ? 0 : 650, easing: 'cubic-bezier(.22,.61,.36,1)' };
        const animations = [
            outgoing.animate([{transform:'translateX(0)'},{transform:`translateX(${-direction * 100}%)`}], options),
            incoming.animate([{transform:`translateX(${direction * 100}%)`},{transform:'translateX(0)'}], options)
        ];
        await Promise.all(animations.map(animation => animation.finished.catch(() => {})));
        outgoing.hidden = true;
        busy = false;
        schedule();
    }
    hero.querySelector('.mmc-slider-prev').addEventListener('click', () => go(current - 1, -1));
    hero.querySelector('.mmc-slider-next').addEventListener('click', () => go(current + 1, 1));
    dots.forEach((dot, index) => dot.addEventListener('click', () => go(index)));
    pause.addEventListener('click', () => { paused = !paused; updatePause(); schedule(); });


    hero.addEventListener('focusin', schedule);
    hero.addEventListener('focusout', () => setTimeout(schedule, 0));
    document.addEventListener('visibilitychange', schedule);
    reduced.addEventListener('change', () => { paused = reduced.matches; updatePause(); schedule(); });
    hero.addEventListener('keydown', event => {
        if (event.target.closest('.mmc-masthead')) return;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            go(current + direction, direction);
        }
    });
    let touchStart;
    track.addEventListener('touchstart', event => {
        if (event.touches.length !== 1) return;
        touchStart = {x:event.touches[0].clientX,y:event.touches[0].clientY};
        stop();
    }, {passive:true});
    track.addEventListener('touchend', event => {
        if (!touchStart) return;
        const dx = event.changedTouches[0].clientX - touchStart.x;
        const dy = event.changedTouches[0].clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) go(current + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
        else schedule();
    }, {passive:true});
    track.addEventListener('touchcancel', () => { touchStart = null; schedule(); }, {passive:true});
    hero.classList.add('is-ready');
    updatePause();
    schedule();
})();

