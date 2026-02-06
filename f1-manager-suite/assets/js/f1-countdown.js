  (function(){
    var cfg = window.f1_countdown_cfg || {};
    var HTML = cfg.html || '';

    function pad2(n){
      n = Math.floor(Math.max(0, n));
      return (n < 10 ? "0"+n : ""+n);
    }

    var mq = window.matchMedia && window.matchMedia("(max-width: 520px)");

    function formatDiffDesktop(ms){
      ms = Math.max(0, ms);
      var total = Math.floor(ms/1000);

      var d = Math.floor(total/86400);
      var h = Math.floor((total%86400)/3600);
      var m = Math.floor((total%3600)/60);
      var s = Math.floor(total%60);

      return String(d) + "T " + pad2(h) + "S " + pad2(m) + "M " + pad2(s) + "S";
    }

    function formatDiffMobile(ms){
      ms = Math.max(0, ms);
      var total = Math.floor(ms/1000);

      var d = Math.floor(total/86400);
      var h = Math.floor((total%86400)/3600);
      var m = Math.floor((total%3600)/60);

      if (d > 0) return String(d) + " Tage";
      return pad2(h) + "S " + pad2(m) + "M";
    }

    function tick(){
      var els = document.querySelectorAll(".f1hdr-next__count[data-target-iso]");
      if (!els.length) return;

      var now = Date.now();
      var isMobile = mq ? mq.matches : (window.innerWidth <= 520);

      els.forEach(function(el){
        var iso = el.getAttribute("data-target-iso") || "";
        var target = Date.parse(iso);
        if (!target) return;

        var diff = target - now;
        el.textContent = isMobile ? formatDiffMobile(diff) : formatDiffDesktop(diff);
      });
    }

    function findOldVideoLink(root){
      if (!root) return null;
      var links = root.querySelectorAll(".custom-menu-link.aft-custom-fa-icon");
      if (!links || !links.length) return null;

      for (var i=0;i<links.length;i++){
        var l = links[i];
        var txt = (l.textContent || "").toUpperCase();
        if (txt.indexOf("AKTUELLES VIDEO") !== -1) return l;
        if (l.querySelector("i.fas.fa-play, i.fa.fa-play")) return l;
      }
      return links[links.length-1];
    }

    function replace(){
      var root = document.querySelector(".search-watch.bottom-bar-right");
      if (!root) return;

      if (root.querySelector("[data-f1hdr=\"1\"]")) return;

      var old = findOldVideoLink(root);
      if (!old) return;

      old.insertAdjacentHTML("beforebegin", HTML);
      old.parentNode.removeChild(old);

      var pre = document.getElementById("f1hdr-prehide-video");
      if (pre && pre.parentNode) pre.parentNode.removeChild(pre);

      tick();
    }

    if (document.readyState === "loading"){
      document.addEventListener("DOMContentLoaded", function(){
          replace();
          window.setInterval(tick, 1000);
      });
    } else {
      replace();
      window.setInterval(tick, 1000);
    }
  })();
