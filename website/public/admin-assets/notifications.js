(()=>{
 'use strict';
 const config=window.notificationEditor,form=document.getElementById('notification-editor');
 if(!config||!form)return;
 const fileInput=document.getElementById('notification-files'),newFiles=document.getElementById('notification-new-files'),fileError=document.getElementById('notification-file-error');
 const savedRows=Array.from(form.querySelectorAll('[data-saved-file]'));
 const sendButtons=Array.from(document.querySelectorAll('.nt-editor-layout aside form button')).map(button=>({button,disabled:button.disabled}));
 const fileNotice=document.createElement('p');fileNotice.className='alert alert-warning';fileNotice.hidden=true;fileNotice.textContent='Attachments have unsaved changes. Save the draft before testing or reviewing the send.';
 document.querySelector('.nt-editor-layout aside').prepend(fileNotice);
 let pendingFiles=[];
 const sizeLabel=bytes=>bytes>=1048576?(bytes/1048576).toFixed(2)+' MB':Math.max(0.1,bytes/1024).toFixed(1)+' KB';
 const selectedFiles=()=>[...savedRows.filter(row=>!row.querySelector('input').checked).map(row=>({name:row.dataset.name,size:Number(row.dataset.size)})),...pendingFiles];
 const refreshFiles=()=>{
  const transfer=new DataTransfer();pendingFiles.forEach(file=>transfer.items.add(file));fileInput.files=transfer.files;
  newFiles.replaceChildren();
  pendingFiles.forEach((file,index)=>{
   const row=document.createElement('li'),info=document.createElement('div'),name=document.createElement('strong'),size=document.createElement('small'),remove=document.createElement('button');
   info.className='nt-file-info';name.textContent=file.name;size.textContent=sizeLabel(file.size)+' · Ready to upload';info.append(name,size);
   remove.type='button';remove.className='btn btn-outline-secondary';remove.textContent='Remove';remove.setAttribute('aria-label','Remove '+file.name);
   remove.addEventListener('click',()=>{pendingFiles.splice(index,1);refreshFiles();});row.append(info,remove);newFiles.append(row);
  });
  savedRows.forEach(row=>row.classList.toggle('nt-file-removed',row.querySelector('input').checked));
  const files=selectedFiles(),bytes=files.reduce((sum,file)=>sum+file.size,0);
  document.getElementById('notification-file-summary').textContent=files.length+' of 5 files · '+sizeLabel(bytes)+' of 10 MB';
  const invalid=files.length>5?'Choose up to five attachments.':bytes>10485760?'The combined attachment size must be 10 MB or smaller.':pendingFiles.some(file=>!file.size)?'Empty files cannot be attached.':pendingFiles.some(file=>! /\.(pdf|jpe?g|png|webp|docx?|xlsx?)$/i.test(file.name))?'Use PDF, JPG, PNG, WebP, Word or Excel files.':'';
  fileInput.setCustomValidity(invalid);fileError.textContent=invalid;fileError.hidden=!invalid;
  const dirty=pendingFiles.length>0||savedRows.some(row=>row.querySelector('input').checked);
  fileNotice.hidden=!dirty;sendButtons.forEach(({button,disabled})=>button.disabled=disabled||dirty);
 };
 fileInput.addEventListener('change',()=>{pendingFiles.push(...Array.from(fileInput.files));refreshFiles();});
 savedRows.forEach(row=>row.querySelector('input').addEventListener('change',refreshFiles));
 refreshFiles();
 const $=window.jQuery;
 if($?.fn.select2){
  const renderRecipient=item=>{
   const line=document.createElement('span');
   if(item.number){const number=document.createElement('strong');number.textContent='#'+item.number+' · ';line.append(number);line.append(document.createTextNode((item.business?item.business+' · ':'')+item.name));}
   else line.textContent=item.text;
   window.mmcBusinessCustomerIcon.append(line,item.account_type||item.element?.dataset.accountType);
   if(item.email){const email=document.createElement('small');email.textContent=' · '+item.email;line.append(email);}
   return $(line);
  };
  $('#notification-recipients').select2({width:'100%',placeholder:'Search customer #, business, name or enter email',tags:true,tokenSeparators:[',',';'],maximumSelectionLength:500,
   ajax:{url:config.customers,dataType:'json',delay:250,data:params=>({q:params.term||'',page:params.page||1}),processResults:data=>data},
   createTag:params=>{const email=params.term.trim().toLowerCase();return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)?{id:'email:'+email,text:email,newTag:true}:null;},
   templateResult:renderRecipient, templateSelection:renderRecipient
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
   document.getElementById('notification-preview-frame').srcdoc=await response.text();
   const files=selectedFiles(),list=document.getElementById('notification-preview-files');list.replaceChildren();
   const heading=document.createElement('p');heading.textContent=files.length?'Attachments ('+files.length+'). Save file changes before sending a test.':'No attachments.';list.append(heading);
   if(files.length){const ul=document.createElement('ul');files.forEach(file=>{const li=document.createElement('li');li.textContent=file.name+' · '+sizeLabel(file.size);ul.append(li);});list.append(ul);}
   dialog.showModal();
  }catch(e){error.textContent=e.message||'Preview could not be generated.';error.hidden=false;}
  finally{button.disabled=false;}
 });
})();
