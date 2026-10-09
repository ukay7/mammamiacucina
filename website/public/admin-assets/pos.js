(() => {
    'use strict';
    const app = document.querySelector('#pos-app');
    if (!app) return;
    const $ = selector => app.querySelector(selector);
    const cart = new Map();
    const money = cents => new Intl.NumberFormat('en-CA', {style:'currency',currency:'CAD'}).format(cents / 100);
    const checkout = $('#pos-checkout'), success = $('#pos-success'), form = $('#complete-sale');
    const customerForm=$('#pos-customer-form');
    let customerId=null, customerVersion=0, customerReady=false, frozenReceipt=null;
    let quote = null, submitting = false, reviewing = false, pendingScans = 0, frozenPayload = null;
    let quoteSerial=0, deliverySerial=0, deliveryTimer;
    const matrixDelivery=()=>app.dataset.matrix==='1' && $('#fulfillment').value==='delivery';
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
                headers:{Accept:'application/json',...(body instanceof FormData?{}:{'Content-Type':'application/json'}),'X-CSRF-TOKEN':app.dataset.csrf},
                ...(body === undefined ? {} : {body:body instanceof FormData?body:JSON.stringify(body)})});
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
    function customerGate(ready){
        customerReady=ready;
        app.querySelectorAll('.pos-discovery input,.pos-discovery button').forEach(el=>el.disabled=!ready);
        $('#review-sale').disabled=!ready||!cart.size;
    }
    $('#pos-customer-search').addEventListener('input',()=>{
        const term=$('#pos-customer-search').value.trim().toLowerCase();
        Array.from($('#pos-customer').options).forEach(option=>option.hidden=!!option.value && option.value!=='new' && !option.textContent.toLowerCase().includes(term));
    });
    $('#pos-customer').addEventListener('change',()=>{
        customerVersion++;searchSerial++;stopCamera();customerGate(false);quote=null;
        $('#pos-results').replaceChildren();
        const isNew=$('#pos-customer').value==='new';$('#pos-new-customer').hidden=!isNew;
        $('#pos-new-customer').querySelectorAll('input,select,textarea').forEach(el=>el.disabled=!isNew);
        customerForm.elements.first_name.required=isNew;customerForm.elements.email.required=isNew;customerForm.elements.phone.required=isNew;
        customerForm.elements.account_type.dispatchEvent(new Event('change'));
        $('#confirm-customer').textContent=isNew?'Create & select customer':'Use selected customer';
        $('#pos-customer-summary').textContent='Confirm customer to load the correct prices.';
    });
    customerForm.onsubmit=async event=>{
        event.preventDefault();if(submitting||checkout.open||frozenPayload)return;
        const select=$('#pos-customer');if(!select.value){feedback('Select a customer first.',true);return;}
        const button=$('#confirm-customer');button.disabled=true;select.disabled=true;$('#pos-customer-search').disabled=true;customerGate(false);
        try{
            if(select.value==='new'){
                const result=await request(app.dataset.customerStore,Object.fromEntries(new FormData(customerForm)));
                const c=result.customer,option=new Option('#'+c.id+' · '+c.name+' — '+c.email,c.id);
                option.dataset.profile=JSON.stringify(c);option.dataset.pending=result.pending?'1':'0';select.add(option);select.value=String(c.id);
                $('#pos-new-customer').hidden=true;$('#pos-new-customer').querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);
            }
            const option=select.selectedOptions[0],profile=JSON.parse(option.dataset.profile);
            if(option.dataset.pending==='1')throw Error('Business account created/selected. Admin approval is required before placing an order.');
            const id=Number(select.value);
            const url=new URL(app.dataset.products,location.href);url.searchParams.set('customer_id',id);
            const data=await request(url);
            if(cart.size){
                const reviewed=await request(app.dataset.quote,{customer_id:customerId,items:items(),fulfillment:'pickup',customer_id:id});
                reviewed.items.forEach(line=>{cart.get(line.id).unit_cents=line.unit_cents;});
            }
            customerId=id;customerVersion++;customerGate(true);quote=null;
            form.elements.customer_id.value=id;
            const parts=profile.name.split(' ');form.elements.first_name.value=parts.shift();form.elements.last_name.value=parts.join(' ');
            for(const key of ['email','phone','address','city','province','postal_code','country'])form.elements[key].value=profile[key]||'';
            if(!form.elements.country.value)form.elements.country.value='Canada';
            $('#pos-customer-summary').textContent=profile.name+' · '+(profile.account_type==='business'?'Business':'Individual')+' pricing'+(cart.size?' · Cart prices updated':'');
            $('#confirm-customer').textContent='Customer selected';
            renderResults(data.products);renderCart();$('#scan-code').focus();
            feedback('Customer selected. Search and scans now use '+profile.account_type+' prices.');
        }catch(error){feedback(error.message,true);$('#pos-customer-summary').textContent=error.message;}
        finally{button.disabled=false;select.disabled=false;$('#pos-customer-search').disabled=false;}
    };
    const items = () => Array.from(cart.values()).map(line => ({id:line.id,quantity:line.quantity}));
    function canEdit() { return customerReady && !checkout.open && !success.open && !reviewing && !submitting && !frozenPayload; }
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
        $('#review-sale').disabled = !customerReady || !cart.size || pendingScans > 0 || reviewing;
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
        const version=customerVersion;pendingScans++;renderCart();
        try {
            const url=new URL(app.dataset.products,location.href);url.searchParams.set('q',code.trim());url.searchParams.set('scan','1');url.searchParams.set('customer_id',customerId);
            const data=await request(url);
            if(!canEdit()||version!==customerVersion)return;
            if(data.products.length===1) { renderResults(data.products); add(data.products[0]); }
            else if(data.products.length>1){renderResults(data.products);feedback('This code matches more than one product. Select the correct product below.',true);}
            else feedback('No matching product for '+code+'. Try searching by name.',true);
        }catch(error){feedback(error.message,true);}
        finally{pendingScans--;renderCart();}
    }
    $('#pos-scan-form').onsubmit=event=>{event.preventDefault();const code=$('#scan-code').value;$('#scan-code').value='';scan(code);};
    $('#product-search').oninput=()=>{
        if(!customerReady)return;clearTimeout(searchTimer);const serial=++searchSerial;const term=$('#product-search').value.trim();
        if(!term){renderResults([],'Scan a code or search for a product.');return;}
        searchTimer=setTimeout(async()=>{
            try{
                const url=new URL(app.dataset.products,location.href);url.searchParams.set('q',term);url.searchParams.set('customer_id',customerId);
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
        if(data.delivery_route?.delivery_service_name && form.elements.delivery_service.selectedOptions[0])form.elements.delivery_service.selectedOptions[0].textContent=data.delivery_route.delivery_service_name+(form.elements.delivery_service.selectedOptions[0].dataset.description?' ('+form.elements.delivery_service.selectedOptions[0].dataset.description+')':'')+' — '+money(data.delivery);
        const lines=$('#quote-lines');lines.replaceChildren();
        data.items.forEach(line=>{const row=make('div',undefined,'pos-quote-line');row.append(make('span',line.name+' × '+line.quantity),make('strong',money(line.unit_cents*line.quantity)));lines.append(row);});
        const totals=$('#quote-totals');totals.replaceChildren();
        [['Product subtotal',data.subtotal],[($('#fulfillment').value==='pickup'?'Pick up':'Delivery')+(data.delivery_route?.delivery_service_name?' · '+data.delivery_route.delivery_service_name:''),data.delivery],['Tax ('+data.tax_percent+'%)',data.tax],['Total (CAD)',data.total]].forEach(([label,value])=>{
            const row=make('div');row.append(make('span',label),make('span',money(value)));totals.append(row);
        });
        $('#complete-button').textContent='Place order · '+money(data.total);
        $('#complete-button').disabled=false;
        $('#checkout-error').hidden=true;$('#refresh-quote').hidden=true;
    }
    function configurePayment(){
        const transfer=form.elements.payment_method.value==='etransfer',delivery=$('#fulfillment').value==='delivery';
        form.elements.payment_method.querySelector('[value=cash]').textContent=delivery?'Cash on delivery':'Cash on pickup';
        form.elements.payment_status.disabled=transfer;
        form.elements.payment_status.querySelector('[value=unpaid]').disabled=!transfer&&!delivery;
        form.elements.payment_status.querySelector('[value=unpaid]').textContent=transfer?'Unpaid — awaiting admin verification':'Unpaid · collect on delivery';
        form.elements.payment_status.value=transfer||delivery?'unpaid':'paid';
        $('#pos-transfer-upload').hidden=!transfer;
    }
    form.elements.payment_method.addEventListener('change',configurePayment);
    function configureDelivery(){
        const delivery=$('#fulfillment').value==='delivery';
        $('#delivery-fields').hidden=!delivery;
        $('#delivery-fields').querySelectorAll('input').forEach(input=>{input.disabled=!delivery;input.required=delivery;});
        form.elements.first_name.required=true;form.elements.phone.required=delivery;
        configurePayment();
        $('#customer-help').textContent=$('#pos-customer-summary').textContent;
        $('#pos-delivery-choice').hidden=!matrixDelivery();
        form.elements.delivery_service.disabled=!matrixDelivery();form.elements.delivery_service.required=matrixDelivery();
    }
    function clearQuote(){quote=null;$('#quote-lines').replaceChildren();cart.forEach(line=>{const row=make('div',undefined,'pos-quote-line');row.append(make('span',line.name+' × '+line.quantity),make('strong',money(line.unit_cents*line.quantity)));$('#quote-lines').append(row);});$('#complete-button').disabled=true;$('#complete-button').textContent='Complete sale';$('#quote-totals').textContent='Select a delivery service to calculate the total.';}
    async function refreshDelivery(){
        if(!matrixDelivery()||frozenPayload||submitting)return;
        const serial=++deliverySerial;quoteSerial++;clearQuote();
        const select=form.elements.delivery_service,selected=select.value;select.disabled=true;
        const message=$('#pos-delivery-description');message.textContent='Checking delivery services…';
        if(!form.elements.postal_code.value){message.textContent='Enter the delivery postal code to see services and prices.';return;}
        try{
            const data=await request(app.dataset.deliveryOptions,{postal_code:form.elements.postal_code.value,country:form.elements.country.value});
            if(serial!==deliverySerial)return;
            select.replaceChildren(new Option('Choose a delivery service',''));
            data.services.forEach(s=>{const option=new Option(s.name+(s.description?' ('+s.description+')':'')+' — '+(s.amount_cents===null?'Unavailable':money(s.amount_cents)),s.code);option.disabled=s.amount_cents===null;option.dataset.notes=s.notes||'';option.dataset.description=s.description||'';select.add(option);});
            select.disabled=false;
            if(data.services.some(s=>s.code===selected&&s.amount_cents!==null))select.value=selected;
            message.textContent=data.from_postal+' (Zone '+data.from_zone+') → '+data.to_postal+' (Zone '+data.to_zone+')';
            $('#checkout-error').hidden=true;
            if(select.value)review();
        }catch(error){if(serial!==deliverySerial)return;message.textContent='';$('#checkout-error').textContent=error.message;$('#checkout-error').hidden=false;}
    }
    async function review(){
        if(!customerReady || submitting || frozenPayload || !cart.size || pendingScans)return;
        stopCamera();configureDelivery();if(!checkout.open)checkout.showModal();
        const serial=++quoteSerial;clearQuote();
        if(matrixDelivery() && (!form.elements.postal_code.value || !form.elements.delivery_service.value)){refreshDelivery();return;}
        reviewing=true;renderCart();$('#review-sale').textContent='Checking prices & stock…';
        try{
            const data=await request(app.dataset.quote,{customer_id:customerId,items:items(),fulfillment:$('#fulfillment').value,postal_code:form.elements.postal_code.value,country:form.elements.country.value,delivery_service:form.elements.delivery_service.value});
            if(serial!==quoteSerial)return;
            fillQuote(data);
            if(data.delivery_route?.delivery_service_name)$('#pos-delivery-description').textContent=data.delivery_route.delivery_from_postal+' (Zone '+data.delivery_route.delivery_from_zone+') → '+data.delivery_route.delivery_to_postal+' (Zone '+data.delivery_route.delivery_to_zone+') · '+data.delivery_route.delivery_service_name+' · '+money(data.delivery);
        }catch(error){if(serial===quoteSerial){$('#checkout-error').textContent=error.message;$('#checkout-error').hidden=false;$('#refresh-quote').hidden=false;}}
        finally{reviewing=false;renderCart();$('#review-sale').textContent='Review & Checkout →';}
    }
    ['postal_code','country'].forEach(key=>form.elements[key].addEventListener('input',()=>{if(!matrixDelivery()||frozenPayload||submitting)return;quoteSerial++;deliverySerial++;clearQuote();form.elements.delivery_service.disabled=true;clearTimeout(deliveryTimer);deliveryTimer=setTimeout(refreshDelivery,350);}));
    form.elements.delivery_service.addEventListener('change',review);
    $('#review-sale').onclick=review;
    $('#refresh-quote').onclick=review;
    $('#close-checkout').onclick=()=>{if(!submitting&&!frozenPayload)checkout.close();};
    checkout.addEventListener('cancel',event=>{if(submitting||frozenPayload)event.preventDefault();});
    function lockForm(lock){
        form.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=lock || !!input.closest('[data-business-fields][hidden]') || (input.closest('#delivery-fields') && $('#fulfillment').value!=='delivery') || (input.name==='delivery_service'&&!matrixDelivery()));
        $('#close-checkout').disabled=lock;
        if(!lock && form.elements.payment_method.value==='etransfer')form.elements.payment_status.disabled=true;
    }
    form.onsubmit=async event=>{
        event.preventDefault();if(submitting||!quote)return;
        if(!window.confirm('Are you sure you want to proceed with the order?'))return;
        if(!frozenPayload){frozenPayload={...Object.fromEntries(new FormData(form)),customer_id:customerId,payment_status:form.elements.payment_method.value==='etransfer'?'unpaid':form.elements.payment_status.value,quote};frozenReceipt=$('#pos-transfer-file').files[0]||null;}
        submitting=true;lockForm(true);$('#complete-button').disabled=true;$('#checkout-error').hidden=true;$('#refresh-quote').hidden=true;
        try{
            const data=await request(app.dataset.store,frozenPayload);
            let uploadMessage='';
            if(data.payment_method==='etransfer' && frozenReceipt){
                const upload=new FormData();upload.append('revision',data.revision);upload.append('receipt',frozenReceipt);
                try{await request(data.receipt_url,upload);uploadMessage='Receipt submitted — awaiting admin verification.';}
                catch(error){uploadMessage='Order saved. Receipt was not uploaded: '+error.message+' Use View order to upload it.';}
            }
            frozenReceipt=null;frozenPayload=null;cart.clear();quote=null;checkout.close();renderCart();
            $('#success-number').textContent=data.number;$('#success-total').textContent=money(data.total);
            $('#success-heading').textContent=data.status==='Completed'?'Sale completed':'Order saved';
            $('#success-status').textContent=uploadMessage||data.status;
            $('#saved-order-link').href=data.order_url;
            $('#receipt-link').href=data.print_url;success.showModal();form.reset();customerForm.reset();customerId=null;customerVersion++;customerGate(false);$('#pos-new-customer').hidden=true;$('#pos-new-customer').querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);$('#pos-customer-summary').textContent='Select a customer to start the next sale.';$('#pos-results').replaceChildren();
            $('#product-search').value='';$('#pos-feedback').hidden=true;
        }catch(error){
            const certain=[401,403,419,422].includes(error.status);
            if(certain){frozenPayload=null;quote=null;lockForm(false);$('#refresh-quote').hidden=error.status!==422;}
            $('#checkout-error').textContent=certain?error.message:error.message+' The sale may already be saved. Use Retry confirmation below; it will reuse this sale reference and will not deduct stock twice.';
            $('#checkout-error').hidden=false;
            $('#complete-button').textContent=certain?'Complete sale':'Retry confirmation';
        }finally{submitting=false;$('#complete-button').disabled=!quote&&!frozenPayload;if(!frozenPayload)lockForm(false);}
    };
    $('#new-sale').onclick=()=>{success.close();$('#pos-customer-search').focus();};
    success.addEventListener('close',()=>$('#scan-code').focus());
    window.addEventListener('beforeunload',event=>{stopCamera();if(cart.size||frozenPayload){event.preventDefault();event.returnValue='';}});
    window.addEventListener('pagehide',stopCamera);
    document.addEventListener('visibilitychange',()=>{if(document.hidden)stopCamera();});
    customerGate(false);$('#pos-new-customer').querySelectorAll('input,select,textarea').forEach(el=>el.disabled=true);renderCart();
})();
