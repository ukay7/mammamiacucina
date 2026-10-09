
document.querySelectorAll('[data-theme-scope]').forEach(section=>{
 const fields=[...section.querySelectorAll('[data-color]')], preview=section.querySelector('.theme-preview');
 const lum=hex=>{const rgb=hex.slice(1).match(/../g).map(n=>parseInt(n,16)/255).map(n=>n<=.04045?n/12.92:((n+.055)/1.055)**2.4);return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722};
 const update=()=>{const c={};fields.forEach(f=>{if(/^#[a-f0-9]{6}$/i.test(f.value)){c[f.dataset.color]=f.value;preview.style.setProperty('--p-'+f.dataset.color,f.value);section.querySelector('[data-picker="'+f.dataset.color+'"]').value=f.value}});
 const bad=[];[['text','surface'],['text','background'],['heading','surface'],['link','surface'],['button_text','primary'],['button_text','hover'],['nav_text','header'],['nav_text','footer']].forEach(([a,b])=>{if(c[a]&&c[b]){const l=[lum(c[a]),lum(c[b])].sort((a,b)=>b-a);if((l[0]+.05)/(l[1]+.05)<4.5)bad.push(a.replaceAll('_',' ')+' / '+b)}});
 section.querySelector('[data-contrast]').textContent=bad.length?'Low contrast: '+bad.join(', ')+'. Try lighter text or a darker background.':'Text contrast looks good in the checked colour pairs.';};
 fields.forEach(f=>f.addEventListener('input',update));section.querySelectorAll('[data-picker]').forEach(p=>p.addEventListener('input',()=>{section.querySelector('[data-color="'+p.dataset.picker+'"]').value=p.value;update()}));
 section.querySelector('[data-preset]').addEventListener('change',e=>{if(!e.target.value)return;let c={...window.mmcThemeDefaults};const variants={forest:{header:'#173c30',footer:'#102b23',primary:'#236747',hover:'#174630',link:'#236747'},navy:{header:'#15243c',footer:'#101c30',primary:'#254b78',hover:'#193651',link:'#254b78'},terracotta:{header:'#553126',footer:'#3e241c',primary:'#a0442c',hover:'#76311f',link:'#a0442c'}};Object.assign(c,variants[e.target.value]||{});fields.forEach(f=>f.value=c[f.dataset.color]);update();});update();
});
