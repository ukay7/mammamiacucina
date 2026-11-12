(()=>{
 'use strict';
 const fields=['address','city','province','postal_code','country'];
 document.querySelectorAll('[data-delivery-address]').forEach(root=>{
  const inputs=Object.fromEntries(fields.map(key=>[key,root.querySelector('[name="'+key+'"]')]));
  const companyRadio=root.querySelector('[value="company"]'),alternateRadio=root.querySelector('[value="alternate"]');
  let company=JSON.parse(root.dataset.company),alternate=JSON.parse(root.dataset.alternate),delivery=true,locked=false;
  const full=()=>fields.every(key=>String(company[key]||'').trim());
  const selected=()=>companyRadio.checked?'company':'alternate';
  function apply(){
   if(!full())alternateRadio.checked=true;
   const isCompany=selected()==='company',address=isCompany?company:alternate;
   root.hidden=!delivery;companyRadio.disabled=!delivery||locked||!full();alternateRadio.disabled=!delivery||locked;
   root.querySelector('[data-company-summary]').hidden=!isCompany;
   root.querySelector('[data-company-summary]').textContent=fields.map(key=>company[key]||'').filter(Boolean).join('\n');
   root.querySelector('[data-company-missing]').hidden=!!full();
   root.querySelector('[data-alternate-fields]').hidden=isCompany;
   fields.forEach(key=>{inputs[key].value=address[key]||'';inputs[key].readOnly=isCompany;inputs[key].disabled=!delivery||locked;inputs[key].required=delivery;});
  }
  function changed(field=null){root.dispatchEvent(new CustomEvent('delivery-address-change',{bubbles:true,detail:{field}}));}
  root.querySelectorAll('[name="delivery_address_type"]').forEach(radio=>radio.addEventListener('change',()=>{apply();changed();}));
  fields.forEach(key=>inputs[key].addEventListener('input',()=>{if(selected()==='alternate')alternate[key]=inputs[key].value;changed(key);}));
  root.deliveryAddressPicker={
   setDelivery(value,lock=false){delivery=value;locked=lock;apply();},
   setCustomer(profile){
    company=Object.fromEntries(fields.map(key=>[key,profile[key]||'']));
    alternate={...(profile.delivery_address||{})};
    if(!alternate.country)alternate.country=profile.country||'Canada';
    root.querySelector('[data-company-label]').textContent=profile.account_type==='business'?'Company address':'Profile address';
    companyRadio.checked=!!full();alternateRadio.checked=!full();apply();
   }
  };
  if(!alternate.country)alternate.country=company.country||'Canada';
  apply();
 });
})();
