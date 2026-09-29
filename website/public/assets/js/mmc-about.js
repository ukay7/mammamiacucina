(() => {
 const section=document.querySelector('[data-about-carousel]');if(!section)return;
 const track=section.querySelector('.mmc-about-values__track'),controls=section.querySelector('.mmc-about-values__controls');
 const buttons=[...controls.querySelectorAll('button')];
 const update=()=>{const max=track.scrollWidth-track.clientWidth;controls.hidden=max<=2;buttons[0].disabled=track.scrollLeft<=2;buttons[1].disabled=track.scrollLeft>=max-2;};
 buttons.forEach(button=>button.addEventListener('click',()=>track.scrollBy({left:Number(button.dataset.direction)*(track.firstElementChild.getBoundingClientRect().width+24),behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'})));
 track.addEventListener('scroll',update,{passive:true});new ResizeObserver(update).observe(track);update();
})();
