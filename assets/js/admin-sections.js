(function(){
'use strict';
const main=document.querySelector('.main'),nav=document.getElementById('nav'),menu=document.getElementById('menu');
const sections=Array.from(main.querySelectorAll(':scope > section[id]'));
const routes=new Set(sections.map(s=>s.id));

function show(id,focus=false){
 if(id==='payments')id='details';if(!routes.has(id))id=id==='details'?'reservations':'overview';
 sections.forEach(s=>s.hidden=s.id!==id);
 nav.querySelectorAll('a[href^="#"]').forEach(a=>{const on=a.hash==='#'+(id==='details'?'reservations':id);a.classList.toggle('active',on);if(on)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');});
 nav.classList.remove('visible');menu.setAttribute('aria-expanded','false');
 main.scrollTop=0;window.scrollTo(0,0);
 if(focus){const section=document.getElementById(id);section.setAttribute('tabindex','-1');section.focus({preventScroll:true});}
}
function route(){const params=new URLSearchParams(location.search);show(location.hash.slice(1)||(params.has('view')?'details':params.has('search')||params.has('status')||params.has('page')?'reservations':'overview'));}
menu.addEventListener('click',()=>{const open=nav.classList.toggle('visible');menu.setAttribute('aria-expanded',String(open));});
document.addEventListener('click',e=>{const a=e.target.closest('a[href^="#"]');if(!a||e.defaultPrevented||e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;const id=a.hash.slice(1);if(!routes.has(id))return;e.preventDefault();if(location.hash!==a.hash)history.pushState(null,'',a.hash);show(id,true);});
window.addEventListener('popstate',route);window.addEventListener('hashchange',route);
document.documentElement.classList.add('admin-routing');route();
})();