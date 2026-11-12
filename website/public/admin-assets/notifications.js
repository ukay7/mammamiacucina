(()=>{
 'use strict';
 const config=window.notificationEditor,form=document.getElementById('notification-editor');
 if(!config||!form)return;
 const $=window.jQuery;
 if($?.fn.select2){
  $('#notification-recipients').select2({width:'100%',placeholder:'Search customer #, business, name or enter email',tags:true,tokenSeparators:[',',';'],maximumSelectionLength:500,
   ajax:{url:config.customers,dataType:'json',delay:250,data:params=>({q:params.term||'',page:params.page||1}),processResults:data=>data},
   createTag:params=>{const email=params.term.trim().toLowerCase();return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)?{id:'email:'+email,text:email,newTag:true}:null;},
   templateResult:item=>{if(!item.number)return item.text;const line=$('<div class="nt-choice"></div>');line.append($('<strong></strong>').text('#'+item.number));line.append(document.createTextNode((item.business?item.business+' · ':'')+item.name));line.append($('<small></small>').text(item.email));return line;}
  });
  // Accept a typed email immediately, even when the remote customer search is still pending.
  form.addEventListener('keydown',event=>{
   if(event.key!=='Enter'||!event.target.matches('.select2-search__field'))return;
   const email=event.target.value.trim().toLowerCase();
   if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))return;
   event.preventDefault();event.stopImmediatePropagation();
   const recipients=$('#notification-recipients'),id='email:'+email,values=recipients.val()||[];
   if(values.length>=500&&!values.includes(id))return;
   if(!Array.from(recipients[0].options).some(option=>option.value===id))recipients.append(new Option(email,id,true,true));
   else recipients.val(Array.from(new Set([...values,id])));
   recipients.trigger('change');recipients.select2('close');event.target.value='';
  },true);
 }
 const dialog=document.getElementById('notification-preview-dialog'),button=document.getElementById('notification-preview-button'),error=document.getElementById('notification-preview-error');
 document.getElementById('notification-close-preview').addEventListener('click',()=>dialog.close());
 button.addEventListener('click',async()=>{
  error.hidden=true;button.disabled=true;
  try{
   const response=await fetch(config.preview,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':config.csrf},body:JSON.stringify({subject:form.elements.subject.value,body:form.elements.body.value})});
   if(!response.ok){const result=await response.json();throw new Error(Object.values(result.errors||{}).flat().join(' ')||'Preview could not be generated.');}
   document.getElementById('notification-preview-frame').srcdoc=await response.text();dialog.showModal();
  }catch(e){error.textContent=e.message||'Preview could not be generated.';error.hidden=false;}
  finally{button.disabled=false;}
 });
})();
