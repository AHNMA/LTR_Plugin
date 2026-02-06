(function(){
  function ready(fn){
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }

  function addEvt(el, type, fn, opts){
    if (!el || !el.addEventListener) return;
    try {
      el.addEventListener(type, fn, opts);
    } catch(e){
      var capture = false;
      if (opts === true) capture = true;
      else if (opts && opts.capture) capture = !!opts.capture;
      el.addEventListener(type, fn, capture);
    }
  }

  function isMobileBreakpoint(){
    if (!window.matchMedia) return false;
    return window.matchMedia("(max-width: 1430px)").matches;
  }

  function insertAfter(parent, newNode, refNode){
    if (!parent || !newNode || !refNode) return;
    if (refNode.nextSibling) parent.insertBefore(newNode, refNode.nextSibling);
    else parent.appendChild(newNode);
  }

  function isVisible(el){
    if (!el) return false;
    var cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
    return cs && cs.display !== "none" && cs.visibility !== "hidden";
  }

  function measureTextWidth(text, referenceEl){
    var span = document.createElement("span");
    var cs = window.getComputedStyle ? window.getComputedStyle(referenceEl) : null;
    span.style.position = "absolute";
    span.style.left = "-9999px";
    span.style.top = "-9999px";
    span.style.whiteSpace = "nowrap";
    if (cs){
      span.style.fontFamily = cs.fontFamily;
      span.style.fontSize = cs.fontSize;
      span.style.fontWeight = cs.fontWeight;
      span.style.letterSpacing = cs.letterSpacing;
      span.style.textTransform = cs.textTransform;
    }
    span.textContent = text;
    document.body.appendChild(span);
    var w = span.getBoundingClientRect().width;
    document.body.removeChild(span);
    return w;
  }

  function updateNameColWidth(table){
    if (!table) return;
    var cells = table.querySelectorAll("thead th:nth-child(2), tbody td:nth-child(2)");
    if (!cells || !cells.length) return;
    var maxNeeded = 0;
    for (var i=0;i<cells.length;i++){
      var cell = cells[i];
      var shortEl = cell.querySelector ? cell.querySelector(".f1wms-name-short") : null;
      var fullEl  = cell.querySelector ? cell.querySelector(".f1wms-name-full") : null;
      var pick = null;
      if (shortEl && isVisible(shortEl)) pick = shortEl;
      else if (fullEl && isVisible(fullEl)) pick = fullEl;
      else pick = cell;
      var text = (pick.textContent || "").trim();
      if (!text) continue;
      var csCell = window.getComputedStyle ? window.getComputedStyle(cell) : null;
      var padL = csCell ? (parseFloat(csCell.paddingLeft) || 0) : 0;
      var padR = csCell ? (parseFloat(csCell.paddingRight) || 0) : 0;
      var textW = measureTextWidth(text, pick);
      var needed = textW + padL + padR + 2;
      if (needed > maxNeeded) maxNeeded = needed;
    }
    if (maxNeeded > 0){
      table.style.setProperty("--nameW", Math.ceil(maxNeeded) + "px");
    }
  }

  function updateAllNameCols(){
    var tables = document.querySelectorAll(".f1wms-table.f1wms-standings");
    for (var i=0;i<tables.length;i++){
      updateNameColWidth(tables[i]);
    }
  }

  function initCustomDropdown(dd){
    if (!dd) return;
    var btn = dd.querySelector ? dd.querySelector(".f1wms-dd__btn") : null;
    var btnLabel = dd.querySelector ? dd.querySelector(".f1wms-dd__btnlabel") : null;
    var menu = dd.querySelector ? dd.querySelector(".f1wms-dd__menu") : null;
    var optNodes = dd.querySelectorAll ? dd.querySelectorAll(".f1wms-dd__opt") : null;
    if (!btn || !menu || !optNodes || !optNodes.length) return;
    var opts = [];
    for (var i=0;i<optNodes.length;i++) opts.push(optNodes[i]);

    function getSelectedIndex(){
      for (var i=0;i<opts.length;i++){
        if (opts[i].classList && opts[i].classList.contains("is-selected")) return i;
      }
      return 0;
    }

    function clearActive(){
      for (var i=0;i<opts.length;i++){
        opts[i].classList.remove("is-active");
      }
    }

    function focusNoScroll(el){
      if (!el) return;
      try { el.focus({ preventScroll: true }); }
      catch(e){ try { el.focus(); } catch(e2){} }
    }

    function setActive(i){
      if (i < 0) i = 0;
      if (i >= opts.length) i = opts.length - 1;
      clearActive();
      for (var j=0;j<opts.length;j++){ opts[j].setAttribute("tabindex","-1"); }
      var o = opts[i];
      o.classList.add("is-active");
      o.setAttribute("tabindex","0");
      focusNoScroll(o);
      try { o.scrollIntoView({ block: "nearest" }); } catch(e){}
    }

    function placeMenuFixed(){
      if (!menu || !btn) return;
      var r = btn.getBoundingClientRect();
      var vv = window.visualViewport || null;
      var vw = vv ? vv.width : (window.innerWidth || document.documentElement.clientWidth || 0);
      var vh = vv ? vv.height : (window.innerHeight || document.documentElement.clientHeight || 0);
      var offX = vv ? vv.offsetLeft : 0;
      var offY = vv ? vv.offsetTop : 0;
      var pad = 8;
      var gap = 6;
      var minLeft = offX + pad;
      var maxLeft = offX + vw - pad - r.width;
      menu.style.position = "fixed";
      menu.style.zIndex = "2147483647";
      menu.style.right = "auto";
      menu.style.left = "0px";
      menu.style.top = "0px";
      menu.style.bottom = "";
      menu.style.width = r.width + "px";
      var left = offX + r.left;
      if (left < minLeft) left = minLeft;
      if (left > maxLeft) left = Math.max(minLeft, maxLeft);
      menu.style.left = left + "px";
      var spaceBelow = vh - r.bottom - pad;
      var spaceAbove = r.top - pad;
      var maxHBelow = Math.max(120, Math.min(280, spaceBelow - gap));
      var maxHAbove = Math.max(120, Math.min(280, spaceAbove - gap));
      var openBelow = (spaceBelow >= 160) || (spaceBelow >= spaceAbove);
      if (openBelow){
        menu.style.maxHeight = maxHBelow + "px";
        menu.style.top = (offY + r.bottom + gap) + "px";
      } else {
        menu.style.maxHeight = maxHAbove + "px";
        var mh = menu.getBoundingClientRect().height || 0;
        var top = (offY + r.top - gap - mh);
        var minTop = offY + pad;
        if (top < minTop) top = minTop;
        menu.style.top = top + "px";
      }
    }

    function resetMenuPosition(){
      if (!menu) return;
      menu.style.position = ""; menu.style.zIndex = ""; menu.style.left = ""; menu.style.right = "";
      menu.style.top = ""; menu.style.bottom = ""; menu.style.width = ""; menu.style.maxHeight = "";
    }

    function open(){
      dd.classList.add("is-open");
      btn.setAttribute("aria-expanded","true");
      menu.hidden = false;
      menu.style.display = "block";
      placeMenuFixed();
      setActive(getSelectedIndex());
    }

    function close(focusBtn){
      dd.classList.remove("is-open");
      btn.setAttribute("aria-expanded","false");
      menu.hidden = true;
      menu.style.display = "none";
      clearActive();
      resetMenuPosition();
      if (focusBtn) { focusNoScroll(btn); }
    }

    function toggle(){
      if (dd.classList.contains("is-open")) close(true);
      else open();
    }

    function selectIndex(i){
      var o = opts[i];
      if (!o) return;
      var url = o.getAttribute("data-url");
      if (url) window.location.href = url;
    }

    menu.hidden = true;
    menu.style.display = "none";
    resetMenuPosition();

    addEvt(btn, "click", function(e){ e.preventDefault(); toggle(); });
    addEvt(btn, "keydown", function(e){
      var key = e.key;
      if (key === "ArrowDown" || key === "Down"){ e.preventDefault(); if (!dd.classList.contains("is-open")) open(); else setActive(Math.min(getSelectedIndex()+1, opts.length-1)); }
      else if (key === "ArrowUp" || key === "Up"){ e.preventDefault(); if (!dd.classList.contains("is-open")) open(); else setActive(Math.max(getSelectedIndex()-1, 0)); }
      else if (key === "Enter" || key === " "){ e.preventDefault(); toggle(); }
      else if (key === "Escape"){ e.preventDefault(); close(true); }
    });

    addEvt(menu, "keydown", function(e){
      var key = e.key;
      var activeIndex = 0;
      for (var i=0;i<opts.length;i++){ if (opts[i].classList.contains("is-active")) { activeIndex = i; break; } }
      if (key === "ArrowDown" || key === "Down"){ e.preventDefault(); setActive(activeIndex + 1); }
      else if (key === "ArrowUp" || key === "Up"){ e.preventDefault(); setActive(activeIndex - 1); }
      else if (key === "Home"){ e.preventDefault(); setActive(0); }
      else if (key === "End"){ e.preventDefault(); setActive(opts.length - 1); }
      else if (key === "Enter" || key === " "){ e.preventDefault(); selectIndex(activeIndex); }
      else if (key === "Escape"){ e.preventDefault(); close(true); }
      else if (key === "Tab"){ close(false); }
    });

    for (var idx=0; idx<opts.length; idx++){
      (function(i){
        var o = opts[i];
        addEvt(o, "click", function(e){ e.preventDefault(); selectIndex(i); });
        addEvt(o, "mouseenter", function(){ if (!dd.classList.contains("is-open")) return; setActive(i); });
      })(idx);
    }

    var selIdx = getSelectedIndex();
    if (btnLabel && opts[selIdx]) {
      btnLabel.textContent = (opts[selIdx].textContent || "").trim();
    }

    function onDocDown(e){
      if (!dd.classList.contains("is-open")) return;
      if (dd.contains(e.target)) return;
      close(false);
    }

    addEvt(document, "mousedown", onDocDown, true);
    addEvt(document, "touchstart", onDocDown, true);

    function onMove(){
      if (!dd.classList.contains("is-open")) return;
      placeMenuFixed();
    }
    addEvt(window, "resize", onMove);
    addEvt(window, "scroll", onMove, true);
    addEvt(window, "orientationchange", onMove);
    if (window.visualViewport){
      addEvt(window.visualViewport, "resize", onMove);
      addEvt(window.visualViewport, "scroll", onMove);
    }
    dd.classList.add("is-ready");
  }

  function rememberMount(dd){
    if (dd.__f1wmsMount) return dd.__f1wmsMount;
    dd.__f1wmsMount = { parent: dd.parentNode, next: dd.nextSibling };
    return dd.__f1wmsMount;
  }

  function forceClose(dd){
    dd.classList.remove("is-open");
    var btn = dd.querySelector(".f1wms-dd__btn");
    if (btn) btn.setAttribute("aria-expanded","false");
    var menu = dd.querySelector(".f1wms-dd__menu");
    if (menu) { menu.hidden = true; menu.style.display = "none"; menu.style.position = ""; menu.style.zIndex = ""; menu.style.left = ""; menu.style.right = ""; menu.style.top = ""; menu.style.bottom = ""; menu.style.width = ""; menu.style.maxHeight = ""; }
    var act = dd.querySelectorAll(".f1wms-dd__opt.is-active");
    for (var i=0;i<act.length;i++){ act[i].classList.remove("is-active"); act[i].setAttribute("tabindex","-1"); }
  }

  function syncDropdownToEntryHeader(){
    var mobile = isMobileBreakpoint();
    var header = document.querySelector(".entry-header");
    var dds = document.querySelectorAll(".f1wms-dd[data-f1wms-dd]");
    for (var i=0;i<dds.length;i++){
      var dd = dds[i];
      rememberMount(dd);
      if (mobile && header){
        var title = header.querySelector(".entry-title");
        var mount = header.querySelector(".f1wms-entry-dd");
        if (!mount){
          mount = document.createElement("div");
          mount.className = "f1wms-entry-dd";
          if (title && title.parentNode === header){ insertAfter(header, mount, title); }
          else { header.appendChild(mount); }
        } else {
          if (title && title.parentNode === header){ insertAfter(header, mount, title); }
        }
        if (dd.parentNode !== mount){ forceClose(dd); mount.appendChild(dd); }
        header.classList.add("f1wms-entryhdr");
      } else {
        var info = dd.__f1wmsMount;
        if (info && info.parent && dd.parentNode !== info.parent){
          forceClose(dd);
          if (info.next && info.next.parentNode === info.parent){ info.parent.insertBefore(dd, info.next); }
          else { info.parent.appendChild(dd); }
        }
        if (header){
          var mount2 = header.querySelector(".f1wms-entry-dd");
          if (mount2 && mount2.childNodes.length === 0){ mount2.parentNode.removeChild(mount2); }
          if (!header.querySelector(".f1wms-entry-dd")){ header.classList.remove("f1wms-entryhdr"); }
        }
      }
    }
  }

  ready(function(){
    var roots = document.querySelectorAll(".f1wms");
    for (var i=0;i<roots.length;i++){ roots[i].classList.add("f1wms--js"); }
    updateAllNameCols();
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function(){ updateAllNameCols(); }); }
    var dds = document.querySelectorAll("[data-f1wms-dd]");
    for (var j=0;j<dds.length;j++){ initCustomDropdown(dds[j]); }
    syncDropdownToEntryHeader();
    addEvt(window, "resize", function(){ updateAllNameCols(); syncDropdownToEntryHeader(); });

    var modal = document.getElementById("f1wmsSwipeModal");
    var autoCloseTimer = null;
    function openModal(){
      if (!modal) return;
      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      if (autoCloseTimer) clearTimeout(autoCloseTimer);
      autoCloseTimer = setTimeout(closeModal, 2400);
    }
    function closeModal(){
      if (!modal) return;
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      if (autoCloseTimer) { clearTimeout(autoCloseTimer); autoCloseTimer = null; }
    }
    if (modal){
      addEvt(modal, "click", function(){ closeModal(); });
      addEvt(modal, "touchstart", function(){ closeModal(); });
    }
    var storageKey = "f1wmsSwipeModalShown:" + location.pathname;
    var shells = document.querySelectorAll(".f1wms-shell");
    for (var s=0;s<shells.length;s++){
      (function(shell){
        var scroller = shell.querySelector("[data-f1wms-scroll]");
        if (!scroller) return;
        function maybeShow(){
          if (!isMobileBreakpoint()) return;
          if (sessionStorage.getItem(storageKey) === "1") return;
          if (scroller.clientWidth === 0 || scroller.offsetParent === null) return;
          if (scroller.scrollWidth <= scroller.clientWidth + 1) return;
          sessionStorage.setItem(storageKey, "1");
          openModal();
        }
        maybeShow();
        addEvt(scroller, "scroll", function(){ closeModal(); });
        addEvt(window, "resize", function(){ if (sessionStorage.getItem(storageKey) === "1") return; maybeShow(); });
      })(shells[s]);
    }
  });
})();
