window.MmcPosProducts=function(app,adapter){
    'use strict';
    const $=s=>app.querySelector(s),shelf=$('#pos-results'),input=$('#product-search'),dialog=$('#pos-product-details');
    const money=c=>new Intl.NumberFormat('en-CA',{style:'currency',currency:'CAD'}).format(c/100);
    const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
    let category='',serial=0,detailSerial=0,suggestionSerial=0,timer,nextPage=null,total=0,loading=false,exactResults=false;
    const products=new Map(),quantities=new Map();
    const status=text=>$('#pos-result-status').textContent=text;
    const available=p=>p.active&&p.unit_cents!==null&&p.unit_cents>=0&&(p.stock===null||Number(p.stock)>=1);
    const unavailable=p=>!p.active?'Inactive':p.unit_cents===null||p.unit_cents<0?'Price unavailable':'Out of stock';
    function setEnabled(){
        app.querySelectorAll('.pos-product-toolbar input,.pos-product-toolbar button,.pos-category-strip button').forEach(e=>e.disabled=!adapter.ready());
        $('#pos-recommendations').inert=!adapter.ready();
    }
    function chooseCategory(value){
        category=String(value||'');
        if(![...app.querySelectorAll('[data-category]')].some(e=>e.dataset.category===category))category='';
        app.querySelectorAll('[data-category]').forEach(e=>{e.classList.toggle('is-selected',e.dataset.category===category);e.setAttribute('aria-pressed',String(e.dataset.category===category));});
    }
    function photo(p){
        const wrap=node('div',undefined,'pos-product-photo');
        if(p.image){const img=node('img');img.src=p.image;img.alt=p.name;img.loading='lazy';img.onerror=()=>img.replaceWith(node('span','No photo'));wrap.append(img);}
        else wrap.append(node('span','No photo'));
        return wrap;
    }
    function stock(p){return p.stock===null?'Stock not tracked':MMCNumber.decimal(p.stock,'quantity')+' '+(p.uom||'units')+' available';}
    function price(p){
        const row=node('div',undefined,'pos-product-price-row');row.append(node('strong',p.unit_cents===null?'On request':money(p.unit_cents)),node('small','CAD'+(p.uom?' / '+p.uom:'')));return row;
    }
    function quantityControl(p,onAdded){
        const box=node('div',undefined,'pos-product-buy'),stepper=node('div',undefined,'pos-product-quantity');
        const minus=node('button','−'),plus=node('button','+'),qty=node('input'),add=node('button',undefined,'btn btn-primary'),message=node('p','', 'pos-product-message');
        minus.type=plus.type=add.type='button';qty.type='number';qty.min='1';qty.max='9999';qty.step='1';qty.inputMode='numeric';qty.value=String(quantities.get(p.id)||1);
        qty.setAttribute('aria-label','Quantity for '+p.name);minus.setAttribute('aria-label','Reduce quantity for '+p.name);plus.setAttribute('aria-label','Increase quantity for '+p.name);
        add.setAttribute('aria-label','Add '+p.name+' to sale');message.hidden=true;
        const label=node('span',available(p)?'Add':unavailable(p)),amount=node('span');add.append(label,amount);
        function sync(){
            const value=Number(qty.value),valid=Number.isInteger(value)&&value>=1&&value<=9999;
            if(valid)quantities.set(p.id,value);
            minus.disabled=!available(p)||(valid&&value<=1);plus.disabled=!available(p)||(valid&&value>=9999);qty.disabled=add.disabled=!available(p);
            amount.textContent=available(p)&&valid?money(p.unit_cents*value):'';
        }
        minus.onclick=()=>{qty.value=String(Math.max(1,(Number(qty.value)||1)-1));sync();};plus.onclick=()=>{qty.value=String(Math.min(9999,(Number(qty.value)||0)+1));sync();};
        qty.oninput=()=>{message.hidden=true;sync();};
        add.onclick=()=>{
            if(!adapter.canEdit())return;
            if(!qty.reportValidity()){qty.focus();return;}
            const value=Number(qty.value);
            if(!Number.isInteger(value)||value<1||value>9999)return;
            if(p.stock!==null&&value>Number(p.stock)){message.textContent='Only '+MMCNumber.decimal(p.stock,'quantity')+' available. Reduce the quantity.';message.hidden=false;return;}
            if(adapter.add(p,value)){
                message.hidden=true;quantities.set(p.id,1);qty.value='1';sync();onAdded?.();
                input.focus();
                input.select();
            }
            else{message.textContent='Could not add this quantity. Check available stock and the quantity already in your sale.';message.hidden=false;}
        };
        qty.addEventListener('keydown',e=>{
            if(e.key!=='Enter'||e.isComposing)return;
            e.preventDefault();
            if(!e.repeat)add.click();
        });
        stepper.append(node('span','Qty'),minus,qty,plus);box.append(stepper,add,message);sync();return box;
    }
    function productRow(p,kind){
        const row=node('article',undefined,'pos-recommendation-item');
        if(kind==='browse'){
            row.classList.add('pos-browse-item');
            row.dataset.productId=p.id;
        }else row.dataset.recommendProductId=p.id;
        const info=node('button',undefined,'pos-recommendation-image');info.type='button';info.title='View '+p.name;info.setAttribute('aria-label','View details for '+p.name);
        if(p.image){const image=node('img');image.src=p.image;image.alt='';image.loading='lazy';image.onerror=()=>image.remove();info.append(image);}
        const icon=node('i',undefined,'fas fa-info');icon.setAttribute('aria-hidden','true');info.append(icon);info.onclick=()=>details(p);
        const identity=node('div'),title=node('div',p.name,'pos-recommendation-name');title.title=p.name;
        let metric;
        if(kind==='browse'){
            metric=['Item '+(p.code||p.barcode||'#'+p.id), available(p)?stock(p):unavailable(p)].join(' · ');
            if(p.pack) identity.title=p.pack;
        }else if(kind==='frequent'){
            metric='In '+p.order_count+' completed '+(p.order_count===1?'order':'orders');
        }else{
            metric=MMCNumber.decimal(p.stock,'quantity')+(p.uom?' '+p.uom:'')+' in stock · '+MMCNumber.decimal(p.sold_units,'quantity')+' sold';
        }
        identity.append(title,node('small',metric,'pos-recommendation-metric'));
        row.append(info,identity,quantityControl(p));return row;
    }
    function clearSuggestions(message='Select a customer to see suggestions and their prices.'){
        suggestionSerial++;$('#pos-recommendation-retry').hidden=true;
        for(const id of ['#pos-favourites','#pos-stock-suggestions'])$(id).replaceChildren(node('p',message,'pos-recommendation-empty'));
    }
    async function loadSuggestions(){
        if(!adapter.ready())return;
        clearSuggestions('Finding suggestions…');const ticket=suggestionSerial,customer=adapter.customerId();
        const url=new URL($('#pos-recommendations').dataset.url,location.href);url.searchParams.set('customer_id',customer);
        try{
            const data=await adapter.request(url);
            if(ticket!==suggestionSerial||customer!==adapter.customerId()||!adapter.ready())return;
            for(const [key,id,empty] of [['frequent','#pos-favourites','No available favourites yet. Products appear here from completed orders.'],['slow','#pos-stock-suggestions','No excess-stock products to suggest right now.']]){
                const list=$(id);list.replaceChildren(...data[key].map(p=>productRow(p,key)));
                if(!data[key].length)list.append(node('p',empty,'pos-recommendation-empty'));
            }
        }catch(e){if(ticket!==suggestionSerial)return;clearSuggestions('Suggestions could not be loaded. You can still browse products below.');$('#pos-recommendation-retry').hidden=false;}
    }
    $('#pos-recommendation-retry').onclick=loadSuggestions;
    function render(data,append=false){
        const previousCount=products.size;
        if(!append){products.clear();shelf.replaceChildren();shelf.scrollTop=0;}
        shelf.querySelector('.pos-product-more')?.remove();
        data.products.forEach(p=>{if(!products.has(p.id)){products.set(p.id,p);shelf.append(productRow(p,'browse'));}});
        nextPage=data.next_page;total=data.total??products.size;
        if(!products.size)shelf.append(node('p','No products found. Try another name, code or category.','pos-empty'));
        if(nextPage){
            const more=node('div',undefined,'pos-product-more'),button=node('button','Load more products','btn btn-outline-primary');button.type='button';button.onclick=()=>load({append:true});
            more.append(button);shelf.append(more);
        }
        status(products.size===total?total+' products':products.size+' of '+total+' products');
        if(append) requestAnimationFrame(()=>shelf.querySelectorAll('.pos-browse-item')[previousCount]?.scrollIntoView({block:'nearest'}));
    }
    async function load({append=false,exact=false}={}){
        if(!adapter.ready()||(append&&(loading||!nextPage)))return;
        clearTimeout(timer);const ticket=++serial,customer=adapter.customerId();loading=true;adapter.busy(true);
        if(!append){products.clear();shelf.replaceChildren(node('p','Finding your products…','pos-empty'));shelf.scrollTop=0;}
        else shelf.querySelector('.pos-product-more button').disabled=true;
        status('Finding products…');
        const term=input.value.trim(),url=new URL(app.dataset.products,location.href);url.searchParams.set('customer_id',customer);url.searchParams.set('q',term);
        if(category)url.searchParams.set('category_id',category);
        if(append){url.searchParams.set('page',nextPage);exact=exactResults;}
        if(exact)url.searchParams.set('scan','1');
        try{
            let data=await adapter.request(url);
            if(ticket!==serial||customer!==adapter.customerId()||!adapter.ready())return;
            if(exact&&!data.products.length){url.searchParams.delete('scan');exact=false;data=await adapter.request(url);}
            if(ticket!==serial||customer!==adapter.customerId()||!adapter.ready())return;
            exactResults=exact;if(exact)chooseCategory('');render(data,append);
            if(exact&&data.products.length===1){const qty=shelf.querySelector('input');qty?.focus({preventScroll:true});qty?.select();adapter.feedback('Found '+data.products[0].name+'. Choose quantity and Add.');}
        }catch(e){
            if(ticket!==serial)return;status('Products could not be loaded. Try Find again.');adapter.feedback(e.message,true);
            if(!append)shelf.replaceChildren(node('p',e.message,'pos-empty'));else if(shelf.querySelector('.pos-product-more button'))shelf.querySelector('.pos-product-more button').disabled=false;
        }finally{if(ticket===serial)loading=false;adapter.busy(false);}
    }
    async function details(p){
        if(!adapter.canEdit())return;
        adapter.openDetails();const ticket=++detailSerial,customer=adapter.customerId();
        $('#pos-product-details-title').textContent=p.name;$('#pos-product-detail-body').replaceChildren(node('p','Loading product details…'));$('#pos-product-detail-actions').hidden=true;dialog.showModal();
        try{
            const url=new URL(p.details_url,location.href);url.searchParams.set('customer_id',customer);const data=await adapter.request(url);
            if(ticket!==detailSerial||!dialog.open||customer!==adapter.customerId())return;
            const product=data.product,summary=node('div',undefined,'pos-product-detail-summary'),identity=node('div');
            identity.append(node('div',product.category,'pos-product-category'),price(product),node('p',available(product)?stock(product):unavailable(product)));
            summary.append(photo(product),identity);const facts=node('dl',undefined,'pos-product-facts');
            Object.entries(data.details).forEach(([label,value])=>{const row=node('div');row.append(node('dt',label),node('dd',value));facts.append(row);});
            $('#pos-product-detail-body').replaceChildren(summary,facts);
            const actions=$('#pos-product-detail-actions');actions.replaceChildren(quantityControl(product,()=>{
                dialog.close();if(products.has(product.id))products.set(product.id,product);
                shelf.querySelector('[data-product-id="'+product.id+'"]')?.replaceWith(productRow(product,'browse'));
                app.querySelectorAll('[data-recommend-product-id="'+product.id+'"] .pos-product-buy').forEach(control=>control.replaceWith(quantityControl(product)));
            }));actions.hidden=false;
        }catch(e){if(ticket===detailSerial)$('#pos-product-detail-body').replaceChildren(node('p',e.message,'pos-error'));}
    }
    $('#close-pos-product').onclick=()=>dialog.close();dialog.addEventListener('close',()=>detailSerial++);
    $('#pos-product-search-form').onsubmit=e=>{e.preventDefault();if(adapter.canEdit())load({exact:!!input.value.trim()});};
    input.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.repeat)e.preventDefault();});
    input.oninput=()=>{clearTimeout(timer);serial++;timer=setTimeout(()=>load(),250);};
    $('#pos-categories').onclick=e=>{const button=e.target.closest('[data-category]');if(!button||!adapter.canEdit())return;chooseCategory(button.dataset.category);load();};
    function reset(){serial++;detailSerial++;clearSuggestions();clearTimeout(timer);loading=false;products.clear();quantities.clear();input.value='';chooseCategory('');nextPage=null;total=0;dialog.close();shelf.replaceChildren(node('p','Select a customer to browse their products and prices.','pos-empty'));status('Select a customer to browse products.');$('#pos-pricing-label').textContent='Select a customer';}
    return {setEnabled,reset,
        show(data){serial++;loading=false;chooseCategory('');input.value='';render(data);$('#pos-pricing-label').textContent=(data.customer.account_type==='business'?'Business':'Individual')+' pricing';loadSuggestions();},
        scan(code){if(!adapter.canEdit())return;input.value=code;chooseCategory('');load({exact:true});},
        snapshot:()=>({product_search:input.value,product_category:category?Number(category):null}),
        restore(state,profile){input.value=state.product_search||'';chooseCategory(state.product_category);$('#pos-pricing-label').textContent=profile?(profile.account_type==='business'?'Business':'Individual')+' pricing':'Select a customer';loadSuggestions();},
        reload:()=>load()
    };
};
