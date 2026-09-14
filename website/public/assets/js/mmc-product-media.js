(() => {
 document.querySelectorAll('[data-product-media]').forEach(gallery => {
  const photo=gallery.querySelector('[data-media-photo]'),image=gallery.querySelector('[data-media-image]'),video=gallery.querySelector('[data-media-video]'),caption=gallery.querySelector('[data-media-caption]');
  const buttons=[...gallery.querySelectorAll('.mmc-media-thumbnails button')];
  buttons.forEach((button,index)=>{
   button.addEventListener('click',()=>{
    buttons.forEach(other=>other.setAttribute('aria-pressed',String(other===button)));
    video.replaceChildren();
    const isVideo=Boolean(button.dataset.video);photo.hidden=isVideo;video.hidden=!isVideo;
    if(isVideo){const frame=document.createElement('iframe');frame.src='https://www.youtube-nocookie.com/embed/'+button.dataset.video;frame.title='Italian recipe inspiration';frame.allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';frame.allowFullscreen=true;frame.referrerPolicy='strict-origin-when-cross-origin';video.append(frame);}
    else {image.src=button.dataset.photo;image.alt=button.dataset.caption;photo.classList.toggle('is-closeup',button.dataset.closeup==='true');}
    caption.textContent=button.dataset.caption;
   });
   button.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const next=event.key==='Home'?0:event.key==='End'?buttons.length-1:(index+(event.key==='ArrowRight'?1:-1)+buttons.length)%buttons.length;buttons[next].focus();buttons[next].click();});
  });
 });
})();
