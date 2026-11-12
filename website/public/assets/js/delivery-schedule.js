(()=>{
 'use strict';
 document.querySelectorAll('[data-delivery-schedule]').forEach(root=>{
  const date=root.querySelector('[name="delivery_date"]'),time=root.querySelector('[name="delivery_time"]'),reference=root.querySelector('[name="delivery_time_reference"]');
  const fulfillment=document.querySelector(root.dataset.fulfillment),form=root.closest('form');
  const clockOffset=Number(root.dataset.clock)-Date.now();
  const formatter=new Intl.DateTimeFormat('en-CA',{timeZone:root.dataset.timezone,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
  let edited=root.dataset.edited==='1';
  function parts(instant){
   const p=Object.fromEntries(formatter.formatToParts(new Date(instant)).map(part=>[part.type,part.value]));
   return {date:p.year+'-'+p.month+'-'+p.day,time:p.hour+':'+p.minute};
  }
  function refreshDefault(){
   const now=Date.now()+clockOffset;
   const today=parts(now).date;
   date.min=root.dataset.savedDate && root.dataset.savedDate<today?root.dataset.savedDate:today;
   if(edited)return;
   const instant=Math.floor((now+2*60*60*1000)/60000)*60000,local=parts(instant);
   date.value=local.date;time.value=local.time;reference.value=String(instant/1000);
  }
  function setDelivery(delivery,locked=false){
   root.hidden=!delivery;
   [date,time,reference].forEach(input=>input.disabled=!delivery||locked);
   date.required=time.required=delivery;
  }
  [date,time].forEach(input=>input.addEventListener('input',()=>{edited=true;}));
  function sync(){setDelivery(fulfillment.value==='delivery');}
  fulfillment.addEventListener('change',()=>{refreshDefault();sync();});
  form.addEventListener('reset',()=>queueMicrotask(()=>{edited=!!root.dataset.savedDate;refreshDefault();sync();}));
  window.addEventListener('pageshow',()=>{refreshDefault();sync();});
  root.deliverySchedulePicker={refreshDefault,setDelivery};
  refreshDefault();sync();
 });
})();
