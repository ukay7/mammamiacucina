(() => {
 const root=document.querySelector('[data-live-catalogue]');if(!root)return;
 const content=root.querySelector('[data-catalogue-content]'),status=root.querySelector('[data-catalogue-status]'),error=root.querySelector('[data-catalogue-error]');
 let controller,serial=0,lastAttempt=location.href;
 async function load(url,push=true){
  url=new URL(url,location.href);if(url.origin!==location.origin)return;
  lastAttempt=url.href;controller?.abort();controller=new AbortController();const id=++serial;
  root.classList.add('is-loading');content.setAttribute('aria-busy','true');status.hidden=false;error.hidden=true;
  const focused=document.activeElement;const focusId=focused?.id;
  try{
   const response=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},signal:controller.signal});
   if(!response.ok){let message='Unable to load products. Please try again.';if(response.status===422){const body=await response.json();message=Object.values(body.errors||{}).flat().join(' ')||message;}throw new Error(message);}
   const body=await response.json();if(id!==serial)return;
   content.innerHTML=body.html;if(push)history.pushState({},'',url);
   const focusTarget=focusId?content.querySelector('#'+CSS.escape(focusId)):null;
   (focusTarget||content.querySelector('.mmc-results'))?.focus({preventScroll:true});
  }catch(e){if(e.name!=='AbortError'&&id===serial){error.querySelector('span').textContent=e.message;error.hidden=false;}}
  finally{if(id===serial){root.classList.remove('is-loading');content.setAttribute('aria-busy','false');status.hidden=true;}}
 }
 document.addEventListener('click',e=>{const link=e.target.closest('a');if(!link||(!root.contains(link)&&!link.closest('#mmc-products-submenu'))||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||e.button!==0)return;const url=new URL(link.href);if(url.pathname!==root.dataset.gridPath)return;e.preventDefault();load(url);});
 root.addEventListener('submit',e=>{const form=e.target;if(!form.matches('.mmc-price-filter,.mmc-shop-toolbar,.mmc-allergy-filter'))return;e.preventDefault();const params=new URLSearchParams(new FormData(form));if(e.submitter?.name)params.set(e.submitter.name,e.submitter.value);if(!params.has('view'))params.set('view',new URL(location.href).searchParams.get('view')||'grid');params.delete('page');load(form.action+'?'+params);});
 root.addEventListener('change',e=>{if(e.target.matches('.mmc-shop-toolbar select,.mmc-allergy-filter select'))e.target.form.requestSubmit();});
 error.querySelector('button').addEventListener('click',()=>load(lastAttempt));
 window.addEventListener('popstate',()=>load(location.href,false));
})();
