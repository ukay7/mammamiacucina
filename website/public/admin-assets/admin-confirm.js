// Confirm changes before target-level form handlers can submit or lock the UI.
document.addEventListener('submit',event=>{
 const form=event.target;if(!(form instanceof HTMLFormElement)||form.method.toLowerCase()==='get'||form.id==='complete-sale')return;
 const action=event.submitter?.value, label=event.submitter?.textContent.trim()||'save these changes';
 const messages={save:'Save this packing progress?',return:'Return this order to admin with the item notes?',ready:'Confirm all items are packed and mark this order ready for dispatch?'};
 let message=event.submitter?.dataset.confirm||form.dataset.confirm;
 if(!message&&form.matches('[data-warehouse-form]'))message=messages[action];
 if(!message&&form.querySelector('[name="status"][value="warehouse_pending"]'))message='Approve this order for another warehouse packing check?';
 if(!message)message='Are you sure you want to '+label.replace(/\s+/g,' ').toLowerCase()+'?';
 if(!window.confirm(message)){event.preventDefault();event.stopImmediatePropagation();}
},true);
