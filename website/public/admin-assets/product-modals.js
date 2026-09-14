(() => {
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&window.parent!==window)window.parent.postMessage('product-close',location.origin);});
 const dialog=document.querySelector('#product-dialog');
 if(dialog){
  const frame=dialog.querySelector('iframe');let dirty=false;
  document.addEventListener('click',e=>{const link=e.target.closest('[data-product-modal]');if(!link)return;e.preventDefault();dirty=false;frame.src=link.href;dialog.querySelector('h2').textContent=link.textContent.trim()+' Product';dialog.showModal();});
  dialog.querySelector('[data-close-modal]').addEventListener('click',()=>dialog.close());
  window.addEventListener('message',e=>{if(e.origin!==location.origin||e.source!==frame.contentWindow)return;if(e.data==='product-saved')dirty=true;if(e.data==='product-close')dialog.close();});
  dialog.addEventListener('close',()=>{frame.src='about:blank';if(dirty)location.reload();});
 }
 if(document.querySelector('[data-product-saved]')&&window.parent!==window)window.parent.postMessage('product-saved',location.origin);
 document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button[type="submit"],button:not([type])');if(button){button.disabled=true;button.textContent='Saving…';}}));
})();
