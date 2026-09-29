(() => {
 const list=document.querySelector('[data-about-items]');if(!list)return;
 const add=document.querySelector('[data-add-about-item]'),feedback=document.querySelector('[data-about-feedback]');
 const sync=()=>{const rows=[...list.children];rows.forEach((row,i)=>{
  row.querySelector('[data-item-number]').textContent='Item '+(i+1);
  row.querySelectorAll('[data-field]').forEach(field=>field.name='items['+i+']['+field.dataset.field+']');
  row.querySelector('[data-move="-1"]').disabled=i===0;row.querySelector('[data-move="1"]').disabled=i===rows.length-1;
 });document.querySelector('[data-about-empty]').hidden=rows.length>0;add.disabled=rows.length>=100;};
 add.addEventListener('click',()=>{list.append(document.querySelector('#about-item-template').content.cloneNode(true));sync();list.lastElementChild.querySelector('input').focus();feedback.textContent='Item added. Save changes to publish.';});
 list.addEventListener('click',event=>{const button=event.target.closest('button');if(!button)return;const row=button.closest('[data-about-item]');
  if(button.hasAttribute('data-remove-item')){const next=row.nextElementSibling??row.previousElementSibling;row.remove();sync();(next?.querySelector('input')??add).focus();feedback.textContent='Item removed. Save changes to publish.';}
  if(button.dataset.move){const sibling=Number(button.dataset.move)<0?row.previousElementSibling:row.nextElementSibling;if(sibling){if(Number(button.dataset.move)<0)list.insertBefore(row,sibling);else list.insertBefore(sibling,row);sync();row.querySelector('input').focus();feedback.textContent='Item order changed. Save changes to publish.';}}
 });sync();
})();
