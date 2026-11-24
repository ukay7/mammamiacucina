(()=>{
 const form=document.querySelector('#order-amend-form');
 if(!form)return;
 let next=0,dirty=false; let previousFulfillment=form.elements.fulfillment.value; let lastDelivery=previousFulfillment==='delivery'?form.elements.delivery.value:form.dataset.defaultDelivery;
 const format=value=>'CAD '+(value/100).toLocaleString('en-CA',{minimumFractionDigits:2,maximumFractionDigits:2});
 const cents=input=>{
  if(!input.validity.valid||!/^\d{1,7}(\.\d{1,2})?$/.test(input.value))return null;
  const [whole,part='']=input.value.split('.');
  return Number(whole)*100+Number(part.padEnd(2,'0'));
 };
 function recalculate(){
  let subtotal=0,valid=true;
  form.querySelectorAll('[data-existing-item],[data-add-row]').forEach(row=>{
   const qty=row.querySelector('input[name$="[quantity]"]'),price=row.querySelector('input[name$="[unit_price]"]');
   const discount=row.querySelector('input[name$="[discount_percent]"]');
   const base=cents(price),discountBps=cents(discount),quantity=Number(qty.value);
   const rowValid=qty.value!==''&&qty.validity.valid&&Number.isInteger(quantity)&&base!==null&&discountBps!==null&&(!row.matches('[data-add-row]')||row.querySelector('select').value!=='');
   const lineTotal=rowValid ? quantity*(base-Math.round(base*discountBps/10000)) : null;
   row.querySelector('[data-line-total]').textContent=lineTotal===null?'—':format(lineTotal);
   if(rowValid)subtotal+=lineTotal;else valid=false;
  });
  const orderDiscount=Math.round(subtotal*Number(form.dataset.discountBps||0)/10000);
  const orderDiscountDisplay=form.querySelector('[data-total="order-discount"]');
  if(orderDiscountDisplay)orderDiscountDisplay.textContent=valid?format(-orderDiscount):'—';
  subtotal-=orderDiscount;
  const deliveryInput=form.elements.delivery,pickup=form.elements.fulfillment.value==='pickup';
  if(previousFulfillment==='pickup'&&!pickup)deliveryInput.value=lastDelivery;
  if(previousFulfillment==='delivery'&&pickup)lastDelivery=deliveryInput.value;
  previousFulfillment=pickup?'pickup':'delivery';
  if(pickup)deliveryInput.value='0.00';
  form.querySelector('[data-fulfillment-charge-label]').textContent=pickup?'Pick up CAD':'Delivery charge CAD';
  form.querySelector('[data-fulfillment-total-label]').textContent=pickup?'Pick up':'Delivery charge';
  deliveryInput.readOnly=pickup || form.dataset.matrixDelivery==='1';
  const delivery=pickup?0:cents(deliveryInput),autoTax=true;
  const taxInput=form.elements.tax;taxInput.readOnly=autoTax;
  const tax=autoTax?(valid?Math.floor((subtotal*Number(form.dataset.taxRate)+5000)/10000):null):cents(taxInput);
  if(autoTax&&tax!==null)taxInput.value=(tax/100).toFixed(2);
  const total=valid&&delivery!==null&&tax!==null?subtotal+delivery+tax:null;
  const received=Number(form.dataset.received),balance=total===null?null:total-Number(form.dataset.credits||0)-received;
  const values={subtotal:valid?subtotal:null,delivery,tax,grand:total,received,balance:balance===null?null:Math.abs(balance)};
  Object.entries(values).forEach(([key,value])=>form.querySelector('[data-total="'+key+'"]').textContent=value===null?'—':format(value));
  form.querySelector('[data-balance-label]').textContent=balance!==null&&balance<0?'Refund due':'Amount due';
  form.querySelector('[data-preview-note]').textContent=total===null?'Complete valid quantities, prices, discounts and charges to calculate the total.':'Preview only. Save Order Changes to apply.';
 }
 const markDirty=()=>{dirty=true;recalculate();};
 form.addEventListener('input',markDirty);
 form.addEventListener('change',markDirty);
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
 form.addEventListener('submit',e=>{
 const missing=Array.from(form.querySelectorAll('[data-add-row] select')).find(s=>!s.value);
 if(missing){e.preventDefault();missing.closest('tr').querySelector('[data-product-toggle]').click();return;}
 dirty=false;
});
 form.querySelectorAll('[data-remove-existing]').forEach(button=>{
  button.addEventListener('click',()=>{
   const row=button.closest('[data-existing-item]'),quantity=row.querySelector('input[name$="[quantity]"]');
   if(row.dataset.removed==='1'){
    quantity.value=row.dataset.previousQuantity;row.dataset.removed='0';
    row.style.opacity='';button.textContent='Remove';
   }else{
    if(!window.confirm('Remove this item from the order when you save changes?'))return;
    row.dataset.previousQuantity=quantity.value;quantity.value='0';row.dataset.removed='1';
    row.style.opacity='.55';button.textContent='Undo removal';
   }
   markDirty();
  });
 });
 const newItems=form.querySelector('#order-new-items');
 function addRow(values={}){
  const frag=document.querySelector('#order-add-template').content.cloneNode(true),row=frag.querySelector('[data-add-row]');
  row.querySelectorAll('[data-field]').forEach(e=>e.name='add_items['+next+']['+e.dataset.field+']');next++;
  const select=row.querySelector('select'),toggle=row.querySelector('[data-product-toggle]');
  const photo=row.querySelector('[data-product-photo]');
  const placeholder=row.querySelector('[data-product-placeholder]');
  const updateProduct=option=>{
   toggle.firstElementChild.textContent=option.textContent;
   row.querySelector('[data-field="unit_price"]').value=option.dataset.price ?? '';
   row.querySelector('[data-field="discount_percent"]').value=option.dataset.discount ?? '0';
   photo.hidden=!option.dataset.photo;
   placeholder.hidden=!photo.hidden;
   if(photo.hidden)photo.removeAttribute('src');else photo.src=option.dataset.photo;
  };
  if(values.product_id){
   select.value=String(values.product_id);
   if(select.selectedOptions[0])updateProduct(select.selectedOptions[0]);
  }
  for(const field of ['quantity','unit_price','discount_percent']){
   if(typeof values[field]==='string'||typeof values[field]==='number')row.querySelector('[data-field="'+field+'"]').value=values[field];
  }
  toggle.addEventListener('click',()=>{
   const prior=document.querySelector('.order-product-popup');if(prior)prior.dispatchEvent(new Event('close-picker'));
   const popup=document.createElement('div');popup.className='order-product-popup';
   const search=document.createElement('input');search.type='search';search.className='form-control';search.placeholder='Name, product code, barcode or ID';search.setAttribute('aria-label','Search products by name, code, barcode or ID');
   const list=document.createElement('div');list.className='order-product-options';list.setAttribute('role','listbox');
   popup.append(search,list);document.body.append(popup);toggle.setAttribute('aria-expanded','true');
   const rect=toggle.getBoundingClientRect();popup.style.width=Math.max(rect.width,260)+'px';popup.style.left=Math.min(rect.left,window.innerWidth-Math.max(rect.width,260)-10)+'px';
   if(window.innerHeight-rect.bottom<300)popup.style.bottom=(window.innerHeight-rect.top+4)+'px';else popup.style.top=(rect.bottom+4)+'px';
   const close=()=>{popup.remove();toggle.setAttribute('aria-expanded','false');document.removeEventListener('pointerdown',outside);window.removeEventListener('resize',close);};
   const outside=e=>{if(!popup.contains(e.target)&&!toggle.contains(e.target))close();};
   popup.addEventListener('close-picker',close);document.addEventListener('pointerdown',outside);window.addEventListener('resize',close);
   const render=()=>{
    list.replaceChildren();
    Array.from(select.options).slice(1).filter(o=>o.dataset.search.toLowerCase().includes(search.value.trim().toLowerCase())).forEach(o=>{
     const button=document.createElement('button');button.type='button';button.textContent=o.textContent;button.setAttribute('role','option');button.setAttribute('aria-selected',String(o.value===select.value));
     button.onclick=()=>{select.value=o.value;updateProduct(o);markDirty();close();toggle.focus();};list.append(button);
    });
    if(!list.children.length){const empty=document.createElement('p');empty.textContent='No matching products';list.append(empty);}
   };
   search.oninput=render;
   popup.addEventListener('keydown',e=>{
    if(e.key==='Escape'){e.preventDefault();close();toggle.focus();}
    if(e.key==='Enter'&&e.target===search){e.preventDefault();list.querySelector('button')?.click();}
    if(e.key==='ArrowDown'){e.preventDefault();(e.target===search?list.querySelector('button'):e.target.nextElementSibling)?.focus();}
    if(e.key==='ArrowUp'){e.preventDefault();(e.target.previousElementSibling||search).focus();}
   });
   render();search.focus();
  });
  row.querySelector('[data-remove-order-item]').addEventListener('click',()=>{
   if(!window.confirm('Remove this added item?'))return;
   row.remove();markDirty();
  });
  newItems.append(frag);
  return toggle;
 }
 for(const row of Object.values(JSON.parse(newItems.dataset.previousItems)||{})){
  if(row && typeof row==='object')addRow(row);
 }
 form.querySelector('[data-add-order-item]').addEventListener('click',()=>{
  addRow().focus();markDirty();
 });
 form.querySelector('[data-order-fulfillment]').addEventListener('change',e=>{
  recalculate();
 });
 recalculate();})();
