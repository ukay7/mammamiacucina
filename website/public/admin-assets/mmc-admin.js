const menu=document.querySelector('.mmc-admin-menu');
menu?.addEventListener('click',()=>{const open=document.body.classList.toggle('sidebar-open');menu.setAttribute('aria-expanded',String(open));});
document.addEventListener('click',e=>{if(document.body.classList.contains('sidebar-open')&&!e.target.closest('.page-sidebar')&&!e.target.closest('.mmc-admin-menu')){document.body.classList.remove('sidebar-open');menu?.setAttribute('aria-expanded','false');}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.body.classList.remove('sidebar-open');menu?.setAttribute('aria-expanded','false');}});
document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));

document.querySelector('[data-select-products]')?.addEventListener('change', function(){document.querySelectorAll('input[name="product_ids[]"]').forEach(input=>input.checked=this.checked);});
