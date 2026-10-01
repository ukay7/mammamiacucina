(() => {
 document.querySelectorAll('[data-business-fields]').forEach(group=>{
  const form=group.closest('form'),type=form?.querySelector('[name="account_type"]');if(!type)return;
  const update=()=>{const active=type.value==='business'&&!form.querySelector('[name="customer_id"]')?.value;
   group.hidden=!active;group.querySelectorAll('input').forEach(input=>{input.disabled=!active;input.required=active;});};
  form.addEventListener('change',update);form.addEventListener('reset',()=>setTimeout(update,0));update();
 });
})();
