(() => {
 const data=document.querySelector('#mmc-catalogue-data'); if(!data)return;
 const products=JSON.parse(data.textContent), key='mmc-preview-cart';
 let cart={};try{cart=JSON.parse(sessionStorage.getItem(key)||'{}')||{};}catch{}
 if(typeof cart!=='object'||Array.isArray(cart))cart={};
 Object.keys(cart).forEach(id=>{if(!products.some(p=>p.slug===id&&p.price_cents!==null)||!Number.isInteger(cart[id])||cart[id]<1)delete cart[id];else cart[id]=Math.min(cart[id],99);});
 const money=c=>'$'+(c/100).toFixed(2), rows=()=>products.filter(p=>cart[p.slug]);
 const element=(tag,text,className)=>{const n=document.createElement(tag);if(text)n.textContent=text;if(className)n.className=className;return n;};
 function render(skipCart=false){
  try{sessionStorage.setItem(key,JSON.stringify(cart));}catch{}
  const total=rows().reduce((n,p)=>n+p.price_cents*cart[p.slug],0), count=rows().reduce((n,p)=>n+cart[p.slug],0);
  document.querySelectorAll('.mmc-header-cart__count').forEach(n=>n.textContent=count);
  document.querySelectorAll('[data-cart-total]').forEach(n=>n.textContent=money(total));
  document.querySelectorAll('[data-mini-cart]').forEach(box=>{
   box.replaceChildren();
   rows().forEach(p=>{
    const link=element('a',null,'mmc-mini-cart-item');link.href='/product-detail?product='+encodeURIComponent(p.slug);
    const img=element('img');img.src='/'+p.image;img.alt=p.name;img.width=72;img.height=64;
    const details=element('span',null,'mmc-mini-cart-info');
    details.append(element('strong',p.name),element('span','Qty: '+cart[p.slug]+' · '+money(p.price_cents*cart[p.slug])));
    link.append(img,details);box.append(link);
   });
  });
  const mini=document.querySelector('.mmc-header-cart__panel > p');if(mini)mini.textContent=count?count+' item'+(count===1?'':'s')+' · '+money(total):'Your cart is currently empty.';
  document.querySelectorAll('[data-cart-items]').forEach(box=>{if(skipCart)return;box.replaceChildren();if(!count)box.append(element('p','Your cart is empty. Explore our products to find something sweet.'));
   rows().forEach(p=>{const row=element('div',null,'mmc-cart-row'),img=element('img');img.src='/'+p.image;img.alt=p.name;const info=element('div');const link=element('a');link.href='/product-detail?product='+encodeURIComponent(p.slug);link.append(element('h3',p.name));info.append(link,element('p',money(p.price_cents)));const remove=element('button','Remove');remove.type='button';remove.setAttribute('aria-label','Remove '+p.name);remove.onclick=()=>{delete cart[p.slug];render();};info.append(remove);const qty=element('input');qty.type='number';qty.min=1;qty.max=99;qty.value=cart[p.slug];qty.setAttribute('aria-label','Quantity for '+p.name);qty.oninput=()=>{cart[p.slug]=Math.max(1,Math.min(99,parseInt(qty.value,10)||1));render(true);};qty.onchange=()=>{qty.value=cart[p.slug];render(true);};row.append(img,info,qty);box.append(row);});
  });
  document.querySelectorAll('[data-checkout-items]').forEach(box=>{box.replaceChildren();if(!count)box.append(element('p','Your cart is empty.'));rows().forEach(p=>{const row=element('div',null,'mmc-order-row');row.append(element('span',p.name+' × '+cart[p.slug]),element('strong',money(p.price_cents*cart[p.slug])));box.append(row);});});
 }
 document.querySelectorAll('[data-add-product]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();const id=form.dataset.addProduct;const qty=Math.max(1,Math.min(99,parseInt(form.querySelector('input').value,10)||1));cart[id]=Math.min(99,(cart[id]||0)+qty);render();const status=form.querySelector('[data-add-status]');status.replaceChildren(element('span','Added to your cart. '));const link=element('a','View Cart →');link.href='/cart';status.append(link);}));
 document.querySelector('[data-checkout-preview]')?.addEventListener('submit',e=>{e.preventDefault();if(!rows().length){document.querySelector('[data-checkout-status]').textContent='Please add a product to your cart first.';return;}location.href='/order-success';});
 document.querySelector('[data-contact-preview]')?.addEventListener('submit',e=>{e.preventDefault();document.querySelector('[data-contact-status]').textContent='Your message preview is ready. Sending will be available when the contact service is connected.';});
 render();
})();
