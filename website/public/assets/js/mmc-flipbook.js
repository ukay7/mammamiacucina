(() => {
const CATALOGUE=window.MMC_FLIPBOOK;

const $ = id => document.getElementById(id)
const count = CATALOGUE.count
const sections = CATALOGUE.sections
const sidebarMedia = matchMedia("(min-width: 1101px)")
let wideSidebar = sidebarMedia.matches
let focusPage = 0
let selectedCategory = null
const pathFor = i => CATALOGUE.pages[i] || 'pages/page-' + String(i+1).padStart(2,'0') + '.webp'
const thumbFor = i => CATALOGUE.thumbs[i] || 'pages/thumb-' + String(i+1).padStart(2,'0') + '.webp'
const media = matchMedia('(max-width: 760px)')
let mobile = media.matches
let current = 0
let busy = false
let pendingNavigation = null
let pageWidth = 400
let pageHeight = 518
let zoomFactor = 1
let zoomPage = 0
let lastFocus = null
let loadedThumbs = false
const imageCache = new Map()
const normalize = index => { index=Math.max(0,Math.min(count-1,index)); return mobile || index===0 ? index : index%2===0 ? index-1 : index }
const visible = index => !mobile && index>0 && index+1<count ? [index,index+1] : [index]
let preloadTimer
function prepare(index, priority='high') {
 if(index<0 || index>=count) return Promise.resolve(true)
 if(!imageCache.has(index)) {
  const img=new Image(); img.fetchPriority=priority; img.decoding='async'; img.src=pathFor(index)
  imageCache.set(index,img.decode().then(()=>true).catch(()=>{imageCache.delete(index);return false}))
 }
 return imageCache.get(index)
}
function preloadNearby() {
 clearTimeout(preloadTimer)
 preloadTimer=setTimeout(async()=>{
  const anchor=current
  const candidates=[...visible(normalize(visible(current).at(-1)+1)),...visible(normalize(Math.max(0,current-1)))]
  for(const i of [...new Set(candidates)]) {
   if(busy || current!==anchor)return
   if(!visible(current).includes(i))await prepare(i,'low')
  }
 },200)
}
function makePage(i) {
 const el=document.createElement('div')
 el.className='page'
 const img=document.createElement('img')
 img.decoding='async'; img.fetchPriority='high'
 img.src=pathFor(i)
 img.alt=pageTitle(i)+', page '+(i+1)
 img.draggable=false
 el.append(img)
 el.setAttribute('role','button')
 el.setAttribute('tabindex','0')
 el.setAttribute('aria-label','Enlarge page '+(i+1))
 el.onclick=()=>{ if(!busy && !suppressClick) openZoom(i) }
 el.onkeydown=e=>{ if(e.key==='Enter'||e.key===' '){e.preventDefault();openZoom(i)} }
 return el
}
function layout() {
 const stage=$('stage')
 const availableW=stage.clientWidth-(mobile?80:148)
 const availableH=stage.clientHeight-(mobile?48:48)
 pageWidth=Math.max(80,Math.min(availableW/(mobile?1:2),availableH*612/792))
 pageHeight=pageWidth*792/612
 document.documentElement.style.setProperty('--pw',pageWidth+'px')
 document.documentElement.style.setProperty('--ph',pageHeight+'px')
}
function render() {
 const pages=visible(current)
 updateNavigation()
 $('book').classList.toggle('spread',pages.length===2)
 $('book').replaceChildren(...pages.map(makePage))
 $('previous').disabled=current===0
 $('first').disabled=current===0
 $('next').disabled=visible(current).at(-1)===count-1
 $('last').disabled=visible(current).at(-1)===count-1
 $('page-input').value=current+1
 $('progress').value=current+1
 $('page-tail').textContent=pages.length===2 ? ' + '+(current+2)+' / '+count : '/ '+count
 $('announcement').textContent=pages.length===2?'Pages '+(current+1)+' and '+(current+2)+' of '+count:'Page '+(current+1)+' of '+count
 if(loadedThumbs) document.querySelectorAll('.thumb').forEach((b,i)=>b.setAttribute('aria-current',String(pages.includes(i))))
 try { history.replaceState(null,'','#page='+(focusPage+1)) } catch {}
 preloadNearby()
}
function face(i,cls) {
 const el=document.createElement('div');el.className='face '+cls
 const img=new Image();img.src=pathFor(i);img.alt='';el.append(img);return el
}
async function go(index,animate=true) {
 index=Math.max(0,Math.min(count-1,Math.round(index)))
 const target=normalize(index)
 if(busy){pendingNavigation=[index,animate];return}
 focusPage=index
 if(target===current)animate=false
 busy=true
 $('book').setAttribute('aria-busy','true')
 $('hint').textContent='Loading pages…'
 const ready=await Promise.all(visible(target).map(i=>prepare(i)))
 $('book').setAttribute('aria-busy','false')
 if(ready.includes(false)) {
  busy=false; $('hint').replaceChildren(document.createTextNode('Unable to load this page. '))
  const retry=document.createElement('button');retry.textContent='Retry';retry.onclick=()=>go(index,animate);$('hint').append(retry)
  if(pendingNavigation){const pending=pendingNavigation;pendingNavigation=null;go(...pending)}
  return
 }
 const old=current,oldPages=visible(old),newPages=visible(target)
 const forward=target>old
 if(animate&&!matchMedia('(prefers-reduced-motion: reduce)').matches) {
  if(oldPages.length===2 && newPages.length===2) {
   const sheet=document.createElement('div');sheet.className='turn-sheet'
   sheet.style.left=forward?pageWidth+'px':'0px'
   sheet.style.transformOrigin=forward?'left center':'right center'
   sheet.append(face(forward?old+1:old,'front'),face(forward?target:target+1,'back'))
   const underlying=forward?[old,target+1]:[target,old+1]
   $('book').replaceChildren(...underlying.map(makePage),sheet)
   const anim=sheet.animate([{transform:'rotateY(0deg)'},{transform:'rotateY('+(forward?-180:180)+'deg)'}],{duration:600,easing:'cubic-bezier(.3,.05,.25,1)',fill:'forwards'})
   await anim.finished.catch(()=>{})
  } else {
   await $('book').animate([{opacity:1,transform:'rotateY(0deg)'},{opacity:.15,transform:'rotateY('+(forward?-22:22)+'deg)'}],{duration:180,easing:'ease-in'}).finished.catch(()=>{})
  }
 }
 current=target;render();busy=false
 if(pendingNavigation){const pending=pendingNavigation;pendingNavigation=null;go(...pending)}
}
const next=()=>go(mobile?current+1:current===0?1:current+2)
const previous=()=>go(mobile?current-1:current<=1?0:current-2)
$('next').onclick=next
$('previous').onclick=previous
$('first').onclick=()=>go(0)
$('last').onclick=()=>go(count-1)
$('progress').onchange=e=>go(Number(e.target.value)-1,false)
$('page-input').onchange=e=>{const n=Number(e.target.value);if(Number.isFinite(n)&&n>=1&&n<=count)go(n-1,false);else render()}
function setModalOpen(open) { $('app').inert=open; $('drawer').inert=!$('zoom').hidden }

function pageTitle(i) { return CATALOGUE.titles[i] || 'Catalogue page '+(i+1) }
function updateNavigation() {
 const active=sections.findIndex(s=>s.indices.includes(focusPage));
 document.querySelectorAll('#category-list .category').forEach((b,i)=>b.setAttribute('aria-current',String(i===active)));
 $('hint').textContent=pageTitle(focusPage)+' | '+(mobile?'Swipe to turn pages.':'Use the arrows to turn pages.');
}
const thumbnailObserver=new IntersectionObserver(entries=>{
 for(const entry of entries)if(entry.isIntersecting){
  const img=entry.target;thumbnailObserver.unobserve(img);img.src=img.dataset.src
 }
},{root:$('thumbs'),rootMargin:'300px 0px'})
function loadThumbnails() {
 if(loadedThumbs)return
 for(let i=0;i<count;i++) {
  const b=document.createElement('button')
  b.className='thumb'
  b.setAttribute('aria-label','Go to page '+(i+1)+', '+pageTitle(i))
  b.setAttribute('aria-current',String(visible(current).includes(i)))
  const img=new Image()
  img.dataset.src=thumbFor(i)
  img.fetchPriority='low';img.decoding='async';img.alt=''
  const preview=document.createElement('div');preview.className='thumb-preview is-loading'
  const status=document.createElement('span');status.className='thumb-status';status.textContent='Loading preview…'
  img.onload=()=>{preview.classList.remove('is-loading');status.remove()}
  img.onerror=()=>{preview.classList.remove('is-loading');status.textContent='Preview unavailable'}
  preview.append(img,status)
  const label=document.createElement('span')
  const number=document.createElement('strong')
  number.textContent='Page '+(i+1)
  label.append(number,document.createTextNode(pageTitle(i)))
  b.append(preview,label)
  b.onclick=()=>navigateTo(i)
  $('thumbs').append(b)
  thumbnailObserver.observe(img)
 }
 loadedThumbs=true
}
function selectTab(mode) {
 const all=mode==='pages'
 $('categories-panel').hidden=all
 $('thumbs').hidden=!all
 $('categories-tab').setAttribute('aria-selected',String(!all))
 $('all-pages-tab').setAttribute('aria-selected',String(all))
 if(all)loadThumbnails()
}
function openDrawer(mode='categories') {
 selectTab(mode)
 $('drawer').hidden=false
 if(wideSidebar)return
 lastFocus=document.activeElement
 $('scrim').hidden=false
 setModalOpen(true)
 $('close-drawer').focus()
}
function closeDrawer() {
 if(wideSidebar)return
 $('drawer').hidden=true
 $('scrim').hidden=true
 setModalOpen(false)
 lastFocus?.focus()
}
function navigateTo(i,section=null) {
 selectedCategory=section
 closeDrawer()
 go(i,false)
}
for(const [i,s] of sections.entries()) {
 const b=document.createElement('button')
 b.className='category'
 b.setAttribute('aria-label',s.name+', page '+s.page)
 for(const [cls,value] of [['number',s.num],['name',s.name],['pg',String(s.page)]]) {
  const span=document.createElement('span')
  span.className=cls
  span.textContent=value
  b.append(span)
 }
 b.onclick=()=>navigateTo(s.page-1,i)
 $('category-list').append(b)
}
$('categories-button').onclick=()=>openDrawer('categories')
$('pages-button').onclick=()=>openDrawer('pages')
$('categories-tab').onclick=()=>selectTab('categories')
$('all-pages-tab').onclick=()=>selectTab('pages')
for(const [id,mode] of [['categories-tab','pages'],['all-pages-tab','categories']]) {
 $(id).onkeydown=e=>{
  if(e.key==='ArrowLeft'||e.key==='ArrowRight') {
   e.preventDefault()
   selectTab(mode)
   $(mode==='pages'?'all-pages-tab':'categories-tab').focus()
  }
 }
}



$('close-drawer').onclick=closeDrawer
$('scrim').onclick=closeDrawer
function syncSidebar() {
 wideSidebar=sidebarMedia.matches
 $('drawer').setAttribute('role',wideSidebar?'complementary':'dialog')
 if(wideSidebar)$('drawer').removeAttribute('aria-modal')
 else $('drawer').setAttribute('aria-modal','true')
 $('drawer').hidden=!wideSidebar
 $('scrim').hidden=true
 if($('zoom').hidden)setModalOpen(false)
 layout()
}
sidebarMedia.addEventListener('change',syncSidebar)
function updateZoom() {
 const sc=$('zoom-scroll')
 const fit=Math.min(sc.clientWidth-32,(sc.clientHeight-32)*612/792)
 $('zoom-image').style.width=Math.round(fit*zoomFactor)+'px'
 $('zoom-level').textContent=Math.round(zoomFactor*100)+'%'
 $('zoom-out').disabled=zoomFactor<=1
 $('zoom-in').disabled=zoomFactor>=4
}
function openZoom(i) {
 lastFocus=document.activeElement;zoomPage=i;zoomFactor=mobile?1.5:1
 $('zoom-image').src=pathFor(i);$('zoom-image').alt='Catalogue page '+(i+1)
 $('zoom-label').textContent='Page '+(i+1)+' of '+count
 $('zoom').hidden=false;setModalOpen(true);updateZoom();$('zoom-scroll').scrollTo(0,0);$('close-zoom').focus()
}
function closeZoom(){ $('zoom').hidden=true;setModalOpen(false);lastFocus?.focus() }
function changeZoom(delta){zoomFactor=Math.max(1,Math.min(4,zoomFactor+delta));updateZoom()}
$('zoom-button').onclick=()=>openZoom(focusPage)
$('close-zoom').onclick=closeZoom
$('zoom-in').onclick=()=>changeZoom(.5)
$('zoom-out').onclick=()=>changeZoom(-.5)
$('zoom-fit').onclick=()=>{zoomFactor=1;updateZoom();$('zoom-scroll').scrollTo(0,0)}
$('zoom-image').ondblclick=()=>{zoomFactor=zoomFactor===1?2:1;updateZoom()}
let drag=null
$('zoom-scroll').onpointerdown=e=>{if(e.pointerType!=='mouse')return;drag={x:e.clientX,y:e.clientY,l:$('zoom-scroll').scrollLeft,t:$('zoom-scroll').scrollTop};$('zoom-scroll').setPointerCapture(e.pointerId);$('zoom-scroll').classList.add('dragging')}
$('zoom-scroll').onpointermove=e=>{if(drag){$('zoom-scroll').scrollLeft=drag.l-(e.clientX-drag.x);$('zoom-scroll').scrollTop=drag.t-(e.clientY-drag.y)}}
function endDrag(){drag=null;$('zoom-scroll').classList.remove('dragging')}
$('zoom-scroll').onpointerup=endDrag
$('zoom-scroll').onpointercancel=endDrag
if(!document.documentElement.requestFullscreen)$('fullscreen').hidden=true
$('fullscreen').onclick=async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else await document.documentElement.requestFullscreen()}catch{$('hint').textContent='Full screen is unavailable in this browser.'}}
document.addEventListener('fullscreenchange',()=>{$('fullscreen').setAttribute('aria-label',document.fullscreenElement?'Exit full screen':'Enter full screen');layout()})
let touch=null,suppressClick=false
$('book').addEventListener('pointerdown',e=>{if(e.pointerType==='touch')touch={x:e.clientX,y:e.clientY}})
$('book').addEventListener('pointerup',e=>{if(!touch)return;const dx=e.clientX-touch.x,dy=e.clientY-touch.y;touch=null;if(Math.abs(dx)>40&&Math.abs(dx)>Math.abs(dy)*1.5){suppressClick=true;dx<0?next():previous();setTimeout(()=>suppressClick=false,500)}})
$('book').addEventListener('pointercancel',()=>touch=null)
document.addEventListener('keydown',e=>{
 const modal=!$('zoom').hidden?$('zoom'):!wideSidebar&&!$('drawer').hidden?$('drawer'):null
 if(modal){
  if(e.key==='Escape'){e.preventDefault();modal===$('zoom')?closeZoom():closeDrawer();return}
  if(e.key==='Tab'){const f=Array.from(modal.querySelectorAll('button:not(:disabled),[tabindex="0"]')).filter(el=>el.getClientRects().length);const a=f[0],z=f[f.length-1];if(e.shiftKey&&document.activeElement===a){e.preventDefault();z.focus()}else if(!e.shiftKey&&document.activeElement===z){e.preventDefault();a.focus()}}
  if(modal===$('zoom')&&(e.key==='+'||e.key==='=')){e.preventDefault();changeZoom(.5)}
  if(modal===$('zoom')&&e.key==='-'){e.preventDefault();changeZoom(-.5)}
  return
 }
 if(e.target.tagName==='INPUT')return
 if(e.key==='ArrowRight'){e.preventDefault();next()}
 if(e.key==='ArrowLeft'){e.preventDefault();previous()}
 if(e.key==='Home'){e.preventDefault();go(0)}
 if(e.key==='End'){e.preventDefault();go(count-1)}
})
new ResizeObserver(()=>{layout();if(!$('zoom').hidden)updateZoom()}).observe($('stage'))
media.addEventListener('change',()=>{mobile=media.matches;current=normalize(focusPage);layout();render()})
const initial=/page=(\d+)/.exec(location.hash)
focusPage=Math.max(0,Math.min(count-1,initial?Number(initial[1])-1:0))
current=normalize(focusPage)
syncSidebar()
layout();render()
go(focusPage,false).finally(()=>$('loading').hidden=true)

})();
