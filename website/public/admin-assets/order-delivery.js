(()=>{
 const form=document.querySelector('#order-amend-form'),select=document.querySelector('#amend-delivery-service');if(!form||!select)return;
 const feedback=document.querySelector('#amend-delivery-feedback');let serial=0,timer,valid=true;
 function pickup(){return form.elements.fulfillment.value==='pickup';}
 function mode(){select.disabled=pickup();select.required=!pickup();}
 function invalidate(){valid=false;form.elements.delivery.value='';form.elements.delivery.dispatchEvent(new Event('input',{bubbles:true}));feedback.textContent='Checking delivery price…';}
 async function quote(){
  const run=++serial;mode();if(pickup()){valid=true;feedback.textContent='Collected in store · no delivery charge.';return;}
  invalidate();const chosen=select.value;
  try{
   const response=await fetch(form.dataset.deliveryOptions,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.elements._token.value},body:JSON.stringify({postal_code:form.elements.postal_code.value,country:form.elements.country.value})});const data=await response.json();if(run!==serial)return;
   if(!response.ok)throw Error(data.errors?Object.values(data.errors).flat().join(' '):data.message);
   select.replaceChildren(new Option('Choose a service',''));
   data.services.forEach(s=>{const option=new Option(s.name+' — '+(s.amount_cents===null?'Unavailable':'CAD '+(s.amount_cents/100).toFixed(2)),s.code);option.disabled=s.amount_cents===null;select.add(option);});
   const selected=data.services.find(s=>s.code===chosen&&s.amount_cents!==null);
   if(!selected){feedback.textContent='Select an available service to calculate delivery.';return;}
   select.value=chosen;form.elements.delivery.value=(selected.amount_cents/100).toFixed(2);form.elements.delivery.dispatchEvent(new Event('input',{bubbles:true}));
   feedback.className='';feedback.textContent=data.from_postal+' (Zone '+data.from_zone+') → '+data.to_postal+' (Zone '+data.to_zone+') · '+selected.name;valid=true;
  }catch(error){if(run!==serial)return;feedback.className='alert alert-danger';feedback.textContent=error.message||'Unable to calculate delivery.';}
 }
 ['postal_code','country'].forEach(key=>form.elements[key].addEventListener('input',()=>{if(pickup())return;serial++;invalidate();clearTimeout(timer);timer=setTimeout(quote,350);}));
 select.addEventListener('change',quote);form.elements.fulfillment.addEventListener('change',quote);
 form.addEventListener('submit',e=>{if(!valid&&!pickup()){e.preventDefault();e.stopImmediatePropagation();feedback.focus();}},true);mode();
})();
