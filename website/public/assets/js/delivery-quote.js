(()=>{
 const form=document.querySelector('[data-place-order]');if(!form)return;
 const service=form.querySelector('#delivery-service'),fulfillment=form.elements.fulfillment,payment=form.elements.payment_method;
 const pickup=()=>fulfillment.value==='pickup';
 const postal=form.elements.postal_code,country=form.elements.country,button=form.querySelector('[type=submit]'),feedback=document.querySelector('#delivery-quote-feedback');
 let serial=0,timer,valid=false;
 const money=c=>new Intl.NumberFormat('en-CA',{style:'currency',currency:'CAD'}).format(c/100);
 function invalidate(){if(pickup()||!service)return;valid=false;button.disabled=true;document.querySelector('#checkout-delivery-amount').textContent='Select a service';document.querySelector('#checkout-grand-total').textContent='Awaiting delivery quote';}
 async function post(url,data){const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':form.elements._token.value},body:JSON.stringify(data)});const d=await response.json();if(!response.ok)throw Error(d.errors?Object.values(d.errors).flat().join(' '):d.message||'Unable to calculate delivery.');return d;}
 async function quote(){
  const run=++serial;clearTimeout(timer);
  const isPickup=pickup();
  form.querySelectorAll('[data-delivery-field]').forEach(el=>{el.hidden=isPickup;el.querySelectorAll('input,select').forEach(input=>{input.disabled=isPickup;input.required=!isPickup;});});
  document.querySelector('#checkout-pickup-address').hidden=!isPickup;
  document.querySelector('#checkout-delivery-label').textContent=isPickup?'Pick up':'Delivery';
  payment.querySelector('[value=cash]').textContent=isPickup?'Cash on pick up':'Cash on delivery';
  if(isPickup||!service){
   const delivery=isPickup?0:Number(form.dataset.flatDelivery);
   document.querySelector('#checkout-delivery-amount').textContent=money(delivery);
   document.querySelector('#checkout-grand-total').textContent=money(Number(form.dataset.baseTotal)+delivery);
   valid=true;button.disabled=false;return;
  }
  invalidate();feedback.className='';
  if(!postal.value){service.disabled=true;feedback.textContent='Enter your postal code to see delivery services and prices.';return;}
  feedback.textContent='Checking delivery services…';const selected=service.value;service.disabled=true;
  try{
   const data={postal_code:postal.value,country:country.value};
   const options=await post(window.deliveryOptionsUrl,data);if(run!==serial)return;
   service.replaceChildren(new Option('Choose a delivery service',''));
   options.services.forEach(s=>{const option=new Option(s.name+(s.description?' ('+s.description+')':'')+' — '+(s.amount_cents===null?'Unavailable':money(s.amount_cents)),s.code);option.disabled=s.amount_cents===null;option.dataset.notes=s.notes||'';option.dataset.description=s.description;service.add(option);});
   service.disabled=false;
   if(options.services.some(s=>s.code===selected&&s.amount_cents!==null))service.value=selected;
   const route=options.from_postal+' (Zone '+options.from_zone+') → '+options.to_postal+' (Zone '+options.to_zone+')';
   if(!service.value){feedback.textContent=route+'. Select an available service to calculate your total.';return;}
   const d=await post(window.deliveryQuoteUrl,{...data,delivery_service:service.value});if(run!==serial)return;
   service.selectedOptions[0].textContent=d.delivery_service_name+(service.selectedOptions[0].dataset.description?' ('+service.selectedOptions[0].dataset.description+')':'')+' — '+money(d.delivery_cents);
   document.querySelector('#checkout-delivery-amount').textContent=money(d.delivery_cents);document.querySelector('#checkout-grand-total').textContent=money(d.total_cents);
   feedback.textContent=route+' · '+d.delivery_service_name+' · '+money(d.delivery_cents)+'. '+(service.selectedOptions[0].dataset.description||'');valid=true;button.disabled=false;
  }catch(e){if(run!==serial)return;service.disabled=true;feedback.textContent=e.message||'Unable to calculate delivery.';feedback.className='mmc-error-alert';}
 }
 [postal,country].forEach(el=>el.addEventListener('input',()=>{serial++;invalidate();if(service)service.disabled=true;clearTimeout(timer);timer=setTimeout(quote,350);}));
 form.addEventListener('submit',e=>{if(!valid){e.preventDefault();e.stopImmediatePropagation();quote();}},true);
 window.addEventListener('pageshow',quote);service?.addEventListener('change',quote);fulfillment.addEventListener('change',quote);
 function paymentNote(){document.querySelector('#checkout-transfer-note').hidden=payment.value!=='etransfer';}
 payment.addEventListener('change',paymentNote);paymentNote();quote();
})();
