(() => {
const tabs=[...document.querySelectorAll('[data-dna-tab]')];
const openTab=name=>{
 tabs.forEach(t=>t.setAttribute('aria-pressed',String(t.dataset.dnaTab===name)));
 document.querySelectorAll('[data-dna-panel]').forEach(p=>p.hidden=p.dataset.dnaPanel!==name);
 document.querySelector('[data-pricing-footer]').hidden=name!=='pricing';
 document.querySelector('[data-product-save-footer]').hidden=name==='pricing';
};
tabs.forEach(t=>t.addEventListener('click',()=>openTab(t.dataset.dnaTab)));
const productForm=document.querySelector('#product-edit-form');
productForm.addEventListener('invalid',e=>{const panel=e.target.closest('[data-dna-panel]');if(panel)openTab(panel.dataset.dnaPanel);},true);
const root=document.querySelector('#dna-pricing'),message=root.querySelector('[data-pricing-message]'),choice=root.querySelector('#publish-choice');
let revision=Number(root.dataset.revision),dirty=false,busy=false;
const money=c=>new Intl.NumberFormat('en-CA',{style:'currency',currency:'CAD'}).format(c/100);
const tell=(text,error=false)=>{message.textContent=text;message.dataset.error=String(error);};
function changed(){dirty=true;choice.replaceChildren(new Option('Calculate a preview first',''));root.querySelector('[data-price-results]').replaceChildren();tell('Pricing changed. Calculate a new preview before saving.');}
root.querySelectorAll('[data-price-input]').forEach(i=>i.addEventListener('input',changed));
root.querySelector('[data-use-source-discount]').addEventListener('click',()=>{
 const source=root.querySelector('[data-price-input="source_discount"]').value.trim();
 if(!/^\d+(\.\d+)?(\+\d+(\.\d+)?)*$/.test(source)){tell('Enter a source discount such as 35+3 first.',true);return;}
 root.querySelector('[data-price-input="discount"]').value=source;changed();tell('Supplier discount copied. Calculate Preview to see the result.');
});
function payload(){return {revision,editor_revision:Number(productForm.querySelector('[name="editor_revision"]').value),choice:choice.value,inputs:Object.fromEntries([...root.querySelectorAll('[data-price-input]')].map(i=>[i.dataset.priceInput,i.value]))};}
async function send(action,data){
 const response=await fetch(root.dataset[action],{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':productForm.querySelector('input[name="_token"]').value},body:JSON.stringify(data)});
 let json;try{json=await response.json();}catch{throw new Error('Unable to save. Refresh the page and check your connection.');}
 if(!response.ok)throw new Error(json.errors?Object.values(json.errors).flat().join('\n'):json.message||'Unable to process pricing.');
 return json;
}
function render(result){
 const results=root.querySelector('[data-price-results]');results.replaceChildren();
 const labels={unit_eur:'Quoted EUR / piece',net_eur:'Discounted EUR / piece',cost_cad:'Purchase CAD / piece',freight_unit:'Freight CAD / piece',landed_cad:'Landed CAD / piece',unit_cents:'Selling CAD / piece (Individual)',carton_cents:'Selling CAD / carton (Individual)',business_unit_cents:'Selling CAD / piece (Business)',business_carton_cents:'Selling CAD / carton (Business)',discount_percent:'Effective supplier discount %',margin_percent:'Actual margin %'};
 Object.entries(labels).forEach(([key,label])=>{const div=document.createElement('div'),strong=document.createElement('strong');div.textContent=label;strong.textContent=result[key]===null?'—':key.endsWith('cents')?money(result[key]):Number(result[key]).toFixed(4);div.append(strong);results.append(div);});
 const old=choice.value;choice.replaceChildren(new Option('Choose the price to save',''),new Option('1 piece — Individual '+money(result.unit_cents)+' / Business '+money(result.business_unit_cents),'unit'),new Option('1 carton — Individual '+money(result.carton_cents)+' / Business '+money(result.business_carton_cents),'carton'));choice.value=old;
}
root.querySelectorAll('[data-pricing-action]').forEach(button=>button.addEventListener('click',async()=>{
 if(busy)return;busy=true;
 const controls=[...root.querySelectorAll('input,select,button')].map(el=>[el,el.disabled]);controls.forEach(([el])=>el.disabled=true);
 try{
  const action=button.dataset.pricingAction;
  if(action==='save'){
   if(!choice.value)throw new Error('Calculate Preview and choose the per-piece or per-carton selling price.');
   if(!window.confirm('Save both Individual and Business selling prices to the product?'))return;
   const response=await send('save',payload());revision=response.revision;dirty=false;
   productForm.querySelector('[name="editor_revision"]').value=response.editor_revision;
   productForm.querySelector('[name="total_selling_price_cad"]').value=response.price;
   document.querySelector('[data-current-price]').textContent='Individual: '+money(Number(response.price)*100)+' · Business: '+money(Number(response.business_price)*100);
   productForm.querySelector('[name="business_selling_price_cad"]').value=response.business_price;
   render(response.result);tell(response.message);
  }else{render(await send('quote',payload()));tell('Preview calculated. Click Save Price to apply the selected amount.');}
 }catch(e){tell(e.message,true);}finally{controls.forEach(([el,disabled])=>el.disabled=disabled);busy=false;}
}));
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
