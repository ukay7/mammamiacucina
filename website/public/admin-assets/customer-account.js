document.addEventListener('DOMContentLoaded',function(){
 function activate(key){if(key==='overview'||key==='profile')key='details';const tab=document.getElementById('customer-tab-'+key);if(tab&&window.jQuery)window.jQuery(tab).tab('show');}
 document.querySelectorAll('[data-account-tab]').forEach(button=>button.addEventListener('click',()=>activate(button.dataset.accountTab)));
 if(new URLSearchParams(location.search).has('page'))activate('orders');
 if(location.hash.startsWith('#customer-'))activate(location.hash.slice(10));
 if(window.jQuery){window.jQuery('.customer-tab-panel a[data-toggle="tab"]').on('shown.bs.tab',function(){history.replaceState(null,'',this.hash);});window.jQuery('#customer-note-modal').on('shown.bs.modal',function(){document.getElementById('customer-note-body').focus();});}
 if(document.querySelector('#customer-note-body')?.value.trim() && document.querySelector('.alert-danger') && window.jQuery)window.jQuery('#customer-note-modal').modal('show');
});
