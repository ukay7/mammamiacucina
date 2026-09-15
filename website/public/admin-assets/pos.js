(() => {
    'use strict';
    const app = document.querySelector('#pos-app');
    if (!app) return;
    const $ = selector => app.querySelector(selector);
    const cart = new Map();
    const money = cents => new Intl.NumberFormat('en-CA', {style:'currency',currency:'CAD'}).format(cents / 100);
    const checkout = $('#pos-checkout'), success = $('#pos-success'), form = $('#complete-sale');
    let quote = null, submitting = false, reviewing = false, pendingScans = 0, frozenPayload = null;
    let searchSerial = 0, searchTimer, cameraControls, cameraGeneration = 0, lastCode = '', lastSeen = 0;
    const make = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    function feedback(message, error = false) {
        const node = $('#pos-feedback');
        node.textContent = message; node.hidden = false; node.classList.toggle('error', error);
    }
    async function request(url, body) {
        let response;
        try {
            response = await fetch(url, {method: body === undefined ? 'GET' : 'POST', credentials:'same-origin',
                headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':app.dataset.csrf},
                ...(body === undefined ? {} : {body:JSON.stringify(body)})});
        } catch (error) { throw new Error('Connection interrupted. Please try again.'); }
        let data;
        try { data = await response.json(); }
        catch (error) {
            const failure = new Error('The server could not confirm the request. Check your connection or sign-in session.');
            failure.status = response.status; throw failure;
        }
        if (!response.ok) {
            const failure = new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Request failed.'));
            failure.status = response.status; throw failure;
        }
        return data;
    }
    const items = () => Array.from(cart.values()).map(line => ({id:line.id,quantity:line.quantity}));
    function canEdit() { return !checkout.open && !success.open && !reviewing && !submitting && !frozenPayload; }
    function renderCart() {
        const container = $('#pos-lines');
        container.replaceChildren();
        let subtotal = 0, count = 0;
        cart.forEach(line => {
            subtotal += line.quantity * line.unit_cents; count += line.quantity;
            const row = make('div',undefined,'pos-line');
            const description = make('div'); description.append(make('h3',line.name),make('small',money(line.unit_cents)+' each'));
            row.append(description,make('strong',money(line.quantity * line.unit_cents)));
            const actions = make('div',undefined,'pos-line-actions');
            const minus = make('button','−'), plus = make('button','+'), remove = make('button','Remove','pos-remove');
            [minus,plus,remove].forEach(button => button.type = 'button');
            minus.setAttribute('aria-label','Reduce quantity of '+line.name);
            plus.setAttribute('aria-label','Increase quantity of '+line.name);
            const quantity = document.createElement('input');
            Object.assign(quantity,{type:'number',min:'1',max:'9999',step:'1',value:String(line.quantity)});
            quantity.setAttribute('aria-label','Quantity for '+line.name);
            minus.onclick = () => changeQuantity(line.id,line.quantity - 1);
            plus.onclick = () => changeQuantity(line.id,line.quantity + 1);
            quantity.onchange = () => { changeQuantity(line.id,Number(quantity.value)); quantity.value = String(cart.get(line.id)?.quantity || 1); };
            remove.onclick = () => { if(canEdit()){cart.delete(line.id);quote=null;renderCart();} };
            actions.append(minus,quantity,plus,remove);row.append(actions);container.append(row);
        });
        if (!cart.size) container.append(make('p','No products added yet.','pos-empty'));
        $('#pos-subtotal').textContent = money(subtotal);
        $('#pos-item-count').textContent = String(count);
        $('#review-sale').disabled = !cart.size || pendingScans > 0 || reviewing;
    }
    function changeQuantity(id, quantity) {
        if (!canEdit()) return;
        const line = cart.get(id);
        if (!Number.isInteger(quantity) || quantity < 1 || quantity > 9999) {
            feedback('Quantity must be between 1 and 9,999. Use Remove to delete an item.',true);return;
        }
        if (line.stock !== null && quantity > Number(line.stock)) {
            feedback('Available stock for '+line.name+': '+line.stock,true);return;
        }
        line.quantity=quantity;quote=null;renderCart();
    }
    function add(product) {
        if (!canEdit()) return;
        if (!product.active || product.unit_cents === null || product.unit_cents < 0) {
            feedback(product.name+' is inactive or has no selling price.',true);return;
        }
        if (!cart.has(product.id) && cart.size >= 100) {feedback('A sale can contain up to 100 different products.',true);return;}
        const quantity=(cart.get(product.id)?.quantity || 0)+1;
        if (quantity > 9999 || (product.stock !== null && quantity > Number(product.stock))) {
            feedback('Not enough stock for '+product.name+'. Available: '+(product.stock ?? '9,999 per sale'),true);return;
        }
        cart.set(product.id,{...product,quantity});quote=null;renderCart();
        feedback('Added '+product.name+' · quantity '+quantity);
    }
    function renderResults(products, emptyMessage='No products found.') {
        const container=$('#pos-results');container.replaceChildren();
        if (!products.length) {container.append(make('p',emptyMessage,'pos-empty'));return;}
        products.forEach(product => {
            const card=make('article',undefined,'pos-result');
            if(product.image){const img=document.createElement('img');img.src=product.image;img.alt=product.name;img.loading='lazy';card.append(img);}
            else card.append(make('span','No image','pos-image-empty'));
            card.append(make('h3',product.name),make('small',product.barcode),
                make('small',product.stock === null ? 'Stock not tracked' : 'Available: '+product.stock+(product.uom ? ' '+product.uom : '')),
                make('span',product.unit_cents === null ? 'Price on request' : money(product.unit_cents),'pos-result-price'));
            const button=make('button','Add to sale','btn btn-outline-primary');button.type='button';
            if(!product.active || product.unit_cents === null || product.unit_cents < 0 || (product.stock !== null && Number(product.stock)<1)){
                button.disabled=true;button.textContent=!product.active?'Inactive':product.unit_cents===null?'No selling price':'Unavailable';
            }
            button.onclick=()=>add(product);card.append(button);container.append(card);
        });
    }
    async function scan(code) {
        if(!canEdit() || !code.trim())return;
        pendingScans++;renderCart();
        try {
            const url=new URL(app.dataset.products,location.href);url.searchParams.set('q',code.trim());url.searchParams.set('scan','1');
            const data=await request(url);
            if(!canEdit())return;
            if(data.products.length===1) { renderResults(data.products); add(data.products[0]); }
            else if(data.products.length>1){renderResults(data.products);feedback('This code matches more than one product. Select the correct product below.',true);}
            else feedback('No matching product for '+code+'. Try searching by name.',true);
        }catch(error){feedback(error.message,true);}
        finally{pendingScans--;renderCart();}
    }
    $('#pos-scan-form').onsubmit=event=>{event.preventDefault();const code=$('#scan-code').value;$('#scan-code').value='';scan(code);};
    $('#product-search').oninput=()=>{
        clearTimeout(searchTimer);const serial=++searchSerial;const term=$('#product-search').value.trim();
        if(!term){renderResults([],'Scan a code or search for a product.');return;}
        searchTimer=setTimeout(async()=>{
            try{
                const url=new URL(app.dataset.products,location.href);url.searchParams.set('q',term);
                const data=await request(url);if(serial===searchSerial)renderResults(data.products);
            }catch(error){if(serial===searchSerial)feedback(error.message,true);}
        },250);
    };
    $('#clear-sale').onclick=()=>{if(canEdit() && cart.size && confirm('Clear all items from this unfinished sale?')){cart.clear();quote=null;renderCart();}};
    $('#fulfillment').onchange=()=>{quote=null;};
    function stopCamera(){
        cameraGeneration++;cameraControls?.stop();cameraControls=null;
        const video=$('#pos-video');
        if(video.srcObject)video.srcObject.getTracks().forEach(track=>track.stop());
        video.srcObject=null;$('#camera-area').hidden=true;$('#camera-start').disabled=false;
        lastCode='';lastSeen=0;
    }
    $('#camera-stop').onclick=stopCamera;
    $('#camera-start').onclick=async()=>{
        if(!canEdit())return;
        if(!window.isSecureContext || !navigator.mediaDevices?.getUserMedia){
            feedback('Camera scanning needs HTTPS (or localhost). You can still use a connected scanner or search.',true);return;
        }
        const generation=++cameraGeneration;$('#camera-start').disabled=true;$('#camera-area').hidden=false;
        try{
            const reader=new ZXingBrowser.BrowserMultiFormatReader(undefined,{delayBetweenScanAttempts:150,delayBetweenScanSuccess:250});
            const controls=await reader.decodeFromConstraints({video:{facingMode:{ideal:'environment'}},audio:false},$('#pos-video'),(result)=>{
                if(!result || generation!==cameraGeneration)return;
                const code=result.getText(),now=Date.now();
                const duplicate=code===lastCode && now-lastSeen<1800;
                lastCode=code;lastSeen=now;
                if(!duplicate)scan(code);
            });
            if(generation!==cameraGeneration)controls.stop();else cameraControls=controls;
        }catch(error){
            if(generation===cameraGeneration){stopCamera();feedback('Camera could not start. Allow camera access, or use the scanner/search field.',true);}
        }
    };
    function fillQuote(data) {
        quote=data.quote;
        const lines=$('#quote-lines');lines.replaceChildren();
        data.items.forEach(line=>{const row=make('div',undefined,'pos-quote-line');row.append(make('span',line.name+' × '+line.quantity),make('strong',money(line.unit_cents*line.quantity)));lines.append(row);});
        const totals=$('#quote-totals');totals.replaceChildren();
        [['Product subtotal',data.subtotal],['Delivery',data.delivery],['Tax ('+data.tax_percent+'%)',data.tax],['Total (CAD)',data.total]].forEach(([label,value])=>{
            const row=make('div');row.append(make('span',label),make('span',money(value)));totals.append(row);
        });
        $('#complete-button').textContent='Complete sale · '+money(data.total);
        const delivery=$('#fulfillment').value==='delivery';
        $('#delivery-fields').hidden=!delivery;
        $('#delivery-fields').querySelectorAll('input').forEach(input=>{input.disabled=!delivery;input.required=delivery;});
        form.elements.first_name.required=delivery;form.elements.phone.required=delivery;
        form.elements.payment_status.querySelector('[value="unpaid"]').disabled=!delivery;
        if(!delivery)form.elements.payment_status.value='paid';
        $('#customer-help').textContent=delivery?'Enter the customer name, phone and full delivery address.':'Walk-in sale: customer details are optional. Items are marked collected when completed.';
        $('#checkout-error').hidden=true;$('#refresh-quote').hidden=true;
    }
    async function review(){
        if(reviewing || submitting || frozenPayload || !cart.size || pendingScans)return;
        stopCamera();reviewing=true;renderCart();$('#review-sale').textContent='Checking prices & stock…';
        try{
            const data=await request(app.dataset.quote,{items:items(),fulfillment:$('#fulfillment').value});
            fillQuote(data);if(!checkout.open)checkout.showModal();
        }catch(error){
            if(checkout.open){$('#checkout-error').textContent=error.message;$('#checkout-error').hidden=false;}
            else feedback(error.message,true);
        }finally{reviewing=false;renderCart();$('#review-sale').textContent='Review & Checkout →';}
    }
    $('#review-sale').onclick=review;
    $('#refresh-quote').onclick=review;
    $('#close-checkout').onclick=()=>{if(!submitting&&!frozenPayload)checkout.close();};
    checkout.addEventListener('cancel',event=>{if(submitting||frozenPayload)event.preventDefault();});
    function lockForm(lock){
        form.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=lock || (input.closest('#delivery-fields') && $('#fulfillment').value!=='delivery'));
        $('#close-checkout').disabled=lock;
    }
    form.onsubmit=async event=>{
        event.preventDefault();if(submitting||!quote)return;
        if(!frozenPayload){frozenPayload={...Object.fromEntries(new FormData(form)),quote};}
        submitting=true;lockForm(true);$('#complete-button').disabled=true;$('#checkout-error').hidden=true;$('#refresh-quote').hidden=true;
        try{
            const data=await request(app.dataset.store,frozenPayload);
            frozenPayload=null;cart.clear();quote=null;checkout.close();renderCart();
            $('#success-number').textContent=data.number;$('#success-total').textContent=money(data.total);
            $('#receipt-link').href=data.print_url;success.showModal();form.reset();$('#pos-results').replaceChildren();
            $('#product-search').value='';$('#pos-feedback').hidden=true;
        }catch(error){
            const certain=[401,403,419,422].includes(error.status);
            if(certain){frozenPayload=null;lockForm(false);$('#refresh-quote').hidden=error.status!==422;}
            $('#checkout-error').textContent=certain?error.message:error.message+' The sale may already be saved. Use Retry confirmation below; it will reuse this sale reference and will not deduct stock twice.';
            $('#checkout-error').hidden=false;
            $('#complete-button').textContent=certain?'Complete sale':'Retry confirmation';
        }finally{submitting=false;$('#complete-button').disabled=false;if(!frozenPayload)lockForm(false);}
    };
    $('#new-sale').onclick=()=>{success.close();$('#scan-code').focus();};
    success.addEventListener('close',()=>$('#scan-code').focus());
    window.addEventListener('beforeunload',event=>{stopCamera();if(cart.size||frozenPayload){event.preventDefault();event.returnValue='';}});
    window.addEventListener('pagehide',stopCamera);
    document.addEventListener('visibilitychange',()=>{if(document.hidden)stopCamera();});
    renderCart();
})();
