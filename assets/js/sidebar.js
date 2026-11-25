(function(){
 'use strict';
 var sidebar=document.getElementById('customer-sidebar'),nav=document.getElementById('main-nav'),toggle=document.getElementById('menu-toggle'),backdrop=document.getElementById('sidebar-backdrop'),content=document.querySelector('.customer-content'),main=content.querySelector('main');
 var mobile=window.matchMedia('(max-width:1000px)');
 var links=Array.from(nav.querySelectorAll('a[href^="#"]'));
 var sections=Array.from(main.children).filter(function(s){return s.tagName==='SECTION'&&s.id;});
 var current='';
 function active(id){links.forEach(function(a){var yes=a.hash==='#'+id;a.classList.toggle('is-active',yes);if(yes)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');});}
 function setOpen(open,restore){open=!!open&&mobile.matches;sidebar.classList.toggle('is-open',open);backdrop.hidden=!open;document.body.classList.toggle('sidebar-open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close navigation':'Open navigation');toggle.firstElementChild.textContent=open?'×':'☰';if(open){var first=nav.querySelector('.is-active')||nav.querySelector('a');if(first)first.focus({preventScroll:true});}else if(restore&&mobile.matches){toggle.focus({preventScroll:true});}}
 function resetScroll(){content.scrollTop=0;main.scrollTop=0;window.scrollTo({top:0,left:0,behavior:'instant'});}
 function show(id,focus){var target=sections.find(function(s){return s.id===id;})||sections.find(function(s){return s.id==='home';});if(!target)return;
  current=target.id;sections.forEach(function(s){s.hidden=s!==target;});active(current);content.dataset.page=current;setOpen(false,false);resetScroll();
  if(focus){target.setAttribute('tabindex','-1');target.focus({preventScroll:true});}
 }
 function route(){var id=location.hash.slice(1)||'home';if(id!==current)show(id,false);}
 toggle.addEventListener('click',function(){setOpen(!sidebar.classList.contains('is-open'),true);});
 backdrop.addEventListener('click',function(){setOpen(false,true);});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'&&sidebar.classList.contains('is-open'))setOpen(false,true);});
 mobile.addEventListener('change',function(){setOpen(false,false);resetScroll();});
 document.addEventListener('click',function(e){var a=e.target.closest('a[href^="#"]');if(!a||e.defaultPrevented||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||e.button!==0)return;var id=a.hash.slice(1);if(!sections.some(function(s){return s.id===id;}))return;e.preventDefault();if(location.hash!=='#'+id)history.pushState(null,'','#'+id);show(id,true);});
 window.addEventListener('popstate',route);window.addEventListener('hashchange',route);
 if('scrollRestoration' in history)history.scrollRestoration='manual';
 document.documentElement.classList.add('section-routing');show(location.hash.slice(1)||'home',false);
})();