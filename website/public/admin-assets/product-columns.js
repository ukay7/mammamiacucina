(() => {
 const panel=document.querySelector('#product-column-picker');if(!panel)return;
 const checks=[...panel.querySelectorAll('[data-column-check]')],presets=JSON.parse(panel.dataset.presets),allowed=checks.map(c=>c.value);
 const selected=()=>checks.filter(c=>c.checked).map(c=>c.value);
 function apply(){
  const keys=selected(),csv=keys.join(',');
  document.querySelectorAll('[data-product-column]').forEach(cell=>cell.hidden=!keys.includes(cell.dataset.productColumn));
  document.querySelector('[data-column-count]').textContent=keys.length;
  document.querySelector('[data-product-filters] [name="columns"]').value=csv;
  document.querySelectorAll('[data-column-export],.pagination a').forEach(link=>{const url=new URL(link.href,location.href);url.searchParams.set('columns',csv);link.href=url.href;});
  document.querySelectorAll('[data-column-export]').forEach(link=>{link.setAttribute('aria-disabled',String(!keys.length));link.classList.toggle('disabled',!keys.length);});
  try{localStorage.setItem(panel.dataset.columnStorage,JSON.stringify(keys));}catch{}
  const url=new URL(location.href);url.searchParams.set('columns',csv);history.replaceState(history.state,'',url);
 }
 const requested=new URL(location.href).searchParams.get('columns');
 if(requested!==null){const keys=requested.split(',').filter(k=>allowed.includes(k));checks.forEach(c=>c.checked=keys.includes(c.value));}else{
  try{const saved=JSON.parse(localStorage.getItem(panel.dataset.columnStorage));if(Array.isArray(saved)&&saved.every(k=>allowed.includes(k)))checks.forEach(c=>c.checked=saved.includes(c.value));}catch{}
 }
 checks.forEach(c=>c.addEventListener('change',apply));
 panel.querySelectorAll('[data-column-preset]').forEach(b=>b.addEventListener('click',()=>{const keys=b.dataset.columnPreset==='all'?allowed:b.dataset.columnPreset==='clear'?[]:presets[b.dataset.columnPreset];checks.forEach(c=>c.checked=keys.includes(c.value));apply();}));
 document.querySelector('[data-toggle-columns]').addEventListener('click',e=>{panel.hidden=!panel.hidden;e.currentTarget.setAttribute('aria-expanded',String(!panel.hidden));});
 document.querySelectorAll('[data-column-export]').forEach(a=>a.addEventListener('click',e=>{if(!selected().length)e.preventDefault();}));
 apply();
})();
