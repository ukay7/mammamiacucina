(()=>{
 if(!('serviceWorker' in navigator)||!window.isSecureContext)return;
 navigator.serviceWorker.register('/sw.js',{updateViaCache:'none'}).catch(()=>{});
 let promptEvent;const button=document.createElement('button');button.type='button';button.textContent='Install Mamma Mia Cucina';button.hidden=true;button.style.cssText='position:fixed;bottom:24px;left:16px;z-index:10000;background:#970c24;color:white;border:1px solid #d8b46b;border-radius:8px;padding:12px 16px';document.body.append(button);
 window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();promptEvent=event;if(!matchMedia('(display-mode: standalone)').matches)button.hidden=false;});
 button.addEventListener('click',async()=>{if(!promptEvent)return;button.hidden=true;await promptEvent.prompt();promptEvent=null;});
 window.addEventListener('appinstalled',()=>{button.hidden=true;promptEvent=null;});
 const notice=document.createElement('div');notice.setAttribute('role','alert');notice.textContent='You are offline. Reconnect before saving orders or packing updates.';notice.style.cssText='position:fixed;top:0;left:0;right:0;z-index:20000;padding:14px;background:#97152a;color:white;text-align:center';document.body.append(notice);
 const update=()=>notice.hidden=navigator.onLine;update();window.addEventListener('online',update);window.addEventListener('offline',update);
 document.addEventListener('submit',event=>{if(!navigator.onLine){event.preventDefault();event.stopImmediatePropagation();update();}},true);
})();
