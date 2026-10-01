(()=>{
 function init(){document.querySelectorAll('#delivery-service,#pos-delivery-service,#amend-delivery-service').forEach(select=>{
  const note=document.createElement('small');note.setAttribute('role','status');note.setAttribute('aria-live','polite');note.id=select.id+'-notes';note.style.cssText='display:block;margin:8px 0;color:#72512b;white-space:pre-line;font-weight:normal;line-height:1.5';select.insertAdjacentElement('afterend',note);
  select.setAttribute('aria-describedby',((select.getAttribute('aria-describedby')||'')+' '+note.id).trim());
  function update(){const text=!select.disabled&&select.value?(select.selectedOptions[0]?.dataset.notes||'').trim():'';note.textContent=text;note.hidden=!text;note.style.display=text?'block':'none';}
  select.addEventListener('change',update);new MutationObserver(update).observe(select,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','selected','data-notes']});update();
 });}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
