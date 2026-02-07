(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  function isDark(){
    return document.documentElement.getAttribute('data-dracula-scheme') === 'dark';
  }

  // ✅ Mobile Check (damit Desktop-Menü nie bp-nav-open setzt)
  function isMobileLike(){
    try{
      if(window.matchMedia) return window.matchMedia("(max-width: 991px)").matches;
      return (window.innerWidth || 0) <= 991;
    }catch(e){ return false; }
  }

  function getEls(){
    var nav = qs('#main-navigation-bar');
    if(!nav) return null;

    var btn  = qs('a.aft-void-menu', nav);
    var icon = qs('i.ham', nav);

    // ✅ NUR echtes Mobile-Offcanvas-Menü (keine Desktop-Fallbacks!)
    var menu =
      qs('ul#menu-hauptmenue.menu-mobile', nav) ||
      qs('.menu ul.menu-mobile', nav) ||
      null;

    return { nav: nav, btn: btn, icon: icon, menu: menu };
  }

  function isVisible(el){
    if(!el) return false;
    var cs = getComputedStyle(el);
    if(cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return false;
    var r = el.getBoundingClientRect();
    return (r.width > 2 && r.height > 2);
  }

  function setNavOpenState(open){
    var html = document.documentElement;
    if(!html || !html.classList) return;
    if(open) html.classList.add('bp-nav-open');
    else html.classList.remove('bp-nav-open');
  }

  var armTimer = 0;
  function armTickerBlock(ms){
    var html = document.documentElement;
    if(!html || !html.classList) return;

    html.classList.add('bp-nav-arming');

    clearTimeout(armTimer);
    armTimer = setTimeout(function(){
      if(!html.classList.contains('bp-nav-open')){
        html.classList.remove('bp-nav-arming');
      }
    }, Math.max(250, ms || 900));
  }

  var IGNORE_SELF = false;
  function withIgnore(fn){
    IGNORE_SELF = true;
    try { fn(); } finally {
      Promise.resolve().then(function(){ IGNORE_SELF = false; });
    }
  }

  function forceClosedIcon(icon){
    if(!icon) return;

    icon.classList.remove('exit');

    if(isDark()){
      icon.style.setProperty('background-color', '#ffffff', 'important');
      icon.style.setProperty('--dracula-inline-bgcolor', '#ffffff', 'important');
      icon.setAttribute('data-bp-midline-fixed', '1');
    } else {
      if(icon.getAttribute('data-bp-midline-fixed') === '1'){
        icon.style.removeProperty('background-color');
        icon.style.removeProperty('--dracula-inline-bgcolor');
        icon.removeAttribute('data-bp-midline-fixed');
      }
    }

    icon.style.removeProperty('box-shadow');
    icon.style.removeProperty('filter');
    icon.style.removeProperty('-webkit-filter');
  }

  function forceOpenIcon(icon){
    if(!icon) return;

    icon.classList.add('exit');

    if(isDark()){
      icon.style.setProperty('background-color', 'transparent', 'important');
      icon.style.setProperty('box-shadow', 'none', 'important');
      icon.style.setProperty('--dracula-inline-bgcolor', 'transparent', 'important');
    } else {
      if(icon.getAttribute('data-bp-midline-fixed') === '1'){
        icon.style.removeProperty('background-color');
        icon.style.removeProperty('--dracula-inline-bgcolor');
        icon.removeAttribute('data-bp-midline-fixed');
      }
      icon.style.removeProperty('box-shadow');
    }
  }

  function applyMenuBackground(menu, open){
    if(!menu) return;

    if(open && isDark()){
      menu.style.setProperty('background-color', '#181a1b', 'important');
      menu.style.setProperty('--dracula-inline-bgcolor', '#181a1b', 'important');
      menu.setAttribute('data-bp-dark-bg-fixed', '1');

      var lis = qsa('li.menu-item', menu);
      for(var i=0;i<lis.length;i++){
        lis[i].style.setProperty('background-color', '#181a1b', 'important');
        lis[i].style.setProperty('background-image', 'none', 'important');
        lis[i].setAttribute('data-bp-item-bg-fixed', '1');
      }

      var links = qsa('li.menu-item > a', menu);
      for(var j=0;j<links.length;j++){
        links[j].style.setProperty('background-color', '#181a1b', 'important');
        links[j].style.setProperty('background-image', 'none', 'important');
        links[j].setAttribute('data-bp-item-bg-fixed', '1');
      }

    } else {
      if(menu.getAttribute('data-bp-dark-bg-fixed') === '1'){
        menu.style.removeProperty('background-color');
        menu.style.removeProperty('--dracula-inline-bgcolor');
        menu.removeAttribute('data-bp-dark-bg-fixed');
      }

      var fixedLis = qsa('li.menu-item[data-bp-item-bg-fixed="1"]', menu);
      for(var k=0;k<fixedLis.length;k++){
        fixedLis[k].style.removeProperty('background-color');
        fixedLis[k].style.removeProperty('background-image');
        fixedLis[k].removeAttribute('data-bp-item-bg-fixed');
      }

      var fixedLinks = qsa('li.menu-item > a[data-bp-item-bg-fixed="1"]', menu);
      for(var m=0;m<fixedLinks.length;m++){
        fixedLinks[m].style.removeProperty('background-color');
        fixedLinks[m].style.removeProperty('background-image');
        fixedLinks[m].removeAttribute('data-bp-item-bg-fixed');
      }
    }
  }

  function applyState(){
    var els = getEls();
    if(!els) return;

    // ✅ OPEN NUR im Mobile-Viewport
    var open = isMobileLike() && isVisible(els.menu);

    withIgnore(function(){
      setNavOpenState(open);

      if(els.icon){
        if(open) forceOpenIcon(els.icon);
        else forceClosedIcon(els.icon);
      }

      applyMenuBackground(els.menu, open);
    });
  }

  var rafId = 0;
  var until = 0;

  function tick(){
    applyState();
    if(Date.now() < until){
      rafId = requestAnimationFrame(tick);
    } else {
      rafId = 0;
      setTimeout(applyState, 120);
      setTimeout(applyState, 320);
      setTimeout(applyState, 650);
    }
  }

  function startStabilizer(ms){
    until = Date.now() + ms;
    if(!rafId){
      rafId = requestAnimationFrame(tick);
    }
  }

  var rt = null;
  function onResize(){
    clearTimeout(rt);
    rt = setTimeout(function(){
      applyState();
      startStabilizer(600);
    }, 120);
  }

  function init(){
    applyState();

    var els = getEls();
    if(!els) return;

    if(els.btn){
      els.btn.addEventListener('pointerdown', function(){ armTickerBlock(900); }, true);
      els.btn.addEventListener('mousedown', function(){ armTickerBlock(900); }, true);
      els.btn.addEventListener('touchstart', function(){ armTickerBlock(900); }, {passive:true});
      els.btn.addEventListener('click', function(){ armTickerBlock(900); startStabilizer(900); }, true);
    }

    if(els.menu){
      els.menu.addEventListener('pointerdown', function(){ armTickerBlock(900); }, true);
      els.menu.addEventListener('mousedown', function(){ armTickerBlock(900); }, true);
      els.menu.addEventListener('click', function(){ armTickerBlock(900); }, true);

      // ✅ Selector nur für MOBILE-MENU – kein Desktop-UL mehr
      document.addEventListener('pointerdown', function(e){
        if(!els.menu) return;
        if(e && e.target && e.target.closest){
          var hit = e.target.closest(
            '#main-navigation-bar ul#menu-hauptmenue.menu-mobile li, '+
            '#main-navigation-bar ul#menu-hauptmenue.menu-mobile a, '+
            '#main-navigation-bar .menu ul.menu-mobile li, '+
            '#main-navigation-bar .menu ul.menu-mobile a'
          );
          if(hit) armTickerBlock(900);
        }
      }, true);
    }

    if(window.MutationObserver){
      var obs = new MutationObserver(function(){
        if(IGNORE_SELF) return;
        startStabilizer(350);
      });

      obs.observe(document.documentElement, { attributes:true, attributeFilter:['data-dracula-scheme'] });

      if(els.menu){
        obs.observe(els.menu, { attributes:true, attributeFilter:['class','style'] });
        obs.observe(els.menu, { childList:true, subtree:true });
      }

      if(els.icon){
        obs.observe(els.icon, { attributes:true, attributeFilter:['class','style'] });
      }
    }

    window.addEventListener('resize', onResize, {passive:true});
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
