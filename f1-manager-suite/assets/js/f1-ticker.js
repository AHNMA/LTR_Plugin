(function($){
	var CFG = window.f1_ticker_cfg || {};
	// Defaults if not set
	CFG.refreshMs = 60000;
	if(typeof CFG.speedDrivers === 'undefined') CFG.speedDrivers = 80000;
	if(typeof CFG.speedTeams === 'undefined') CFG.speedTeams = 80000;

	var MODE_KEY = "f1wmt_mode";
	var SWITCHING = false;

	function getMode(){
		try{
			var m = window.localStorage ? localStorage.getItem(MODE_KEY) : null;
			return (m === "teams") ? "teams" : "drivers";
		}catch(e){ return "drivers"; }
	}
	function setMode(mode){
		mode = (mode === "teams") ? "teams" : "drivers";
		try{ if(window.localStorage) localStorage.setItem(MODE_KEY, mode); }catch(e){}
		return mode;
	}

	function setModeUI(wrapper, mode, applyAttr){
		if(!wrapper) return;
		if(applyAttr !== false) wrapper.setAttribute("data-f1wmt-mode", mode);

		var label = wrapper.querySelector(".f1wmt-nowLabel");
		if(label){
			var txt = (mode === "teams") ? (label.getAttribute("data-teams") || "TEAM WM") : (label.getAttribute("data-drivers") || "FAHRER WM");
			var textEl = label.querySelector(".f1wmt-nowText");
			if(textEl) textEl.textContent = txt;
			else label.textContent = txt;
		}
		var btn = wrapper.querySelector(".f1wmt-modeBtn");
		if(btn){
			var next = (mode === "teams") ? "Fahrer-WM anzeigen" : "Team-WM anzeigen";
			btn.setAttribute("aria-label", next);
			btn.setAttribute("title", next);
		}
	}

	function setFade(wrapper, on){
		try{
			var mq = wrapper.querySelector(".f1wmt-marquee");
			if(mq){
				if(on) mq.classList.add("is-fading");
				else mq.classList.remove("is-fading");
			}
		}catch(e){}

		try{
			var ex = wrapper.querySelector(".exclusive-now");
			if(ex){
				if(on) ex.classList.add("is-fading");
				else ex.classList.remove("is-fading");
			}
		}catch(e){}
	}

	function bindModeButton(wrapper){
		var btn = wrapper.querySelector(".f1wmt-modeBtn");
		if(!btn) return;

		// Clean previous listeners just in case
		var clone = btn.cloneNode(true);
		btn.parentNode.replaceChild(clone, btn);
		btn = clone;

		btn.addEventListener("click", function(e){
			e.preventDefault(); e.stopPropagation();
			try{ btn.blur(); }catch(err){}

			if(SWITCHING) return;
			SWITCHING = true;

			/* Click-Animation */
			try{
				btn.classList.remove("is-anim");
				void btn.offsetWidth;
				btn.classList.add("is-anim");
				// Using timeout to remove class
				setTimeout(function(){ btn.classList.remove("is-anim"); }, 260);
			}catch(err){}

			var current = getMode();
			var next = (current === "teams") ? "drivers" : "teams";
			next = setMode(next);

			/* Soft Fade */
			setFade(wrapper, true);

			setTimeout(function(){
				/* Text swap while invisible */
				setModeUI(wrapper, next, false);

				fetchAndUpdate(wrapper, next).finally(function(){
					setTimeout(function(){
						/* Fade back in */
						setFade(wrapper, false);
						SWITCHING = false;
					}, 30);
				});
			}, 200); // slightly longer fade out for better effect
		});
	}

	function initMarquee(wrapper, mode){
		var marquees = wrapper.querySelectorAll(".f1wmt-marquee");
		var speedVal = (mode === 'teams') ? parseInt(CFG.speedTeams) : parseInt(CFG.speedDrivers);
		if(isNaN(speedVal) || speedVal < 5000) speedVal = 80000;

		for(var i=0; i<marquees.length; i++){
			(function(mq){
				// Re-calc even if inited, because content/speed might change
				mq.setAttribute("data-speed", speedVal);

				var track = mq.querySelector(".f1wmt-marquee-track");
				var group = mq.querySelector(".f1wmt-marquee-group");
				if(!track || !group) return;

				// Clone logic: Ensure we have exactly 2 groups (original + clone)
				// Legacy code just appended clone. If run multiple times, it might duplicate endlessly.
				// Let's reset track content to just one group first if possible, or check if clone exists.
				var groups = track.querySelectorAll(".f1wmt-marquee-group");
				if(groups.length < 2) {
					var clone = group.cloneNode(true);
					clone.setAttribute('aria-hidden', 'true'); // Accessibility
					track.appendChild(clone);
				}

				function measure(){
					var g = mq.querySelector(".f1wmt-marquee-group");
					if(!g) return;
					var w = g.getBoundingClientRect().width;
					if(!w || w < 50) return;

					mq.style.setProperty("--f1wmt-shift", w + "px");
					mq.style.setProperty("--f1wmt-duration", (speedVal / 1000) + "s");
				}
				measure();
				// Debounced resize could be better, but sticking to simple as legacy
				// We attach listener to window, but need to be careful not to pile up listeners
				if(!mq.__f1wmtResized){
					mq.__f1wmtResized = true;
					window.addEventListener("resize", function(){ measure(); });
				}
			})(marquees[i]);
		}
	}

	function replaceTickerItems(wrapper, itemsHtml){
		var mq = wrapper.querySelector(".f1wmt-marquee");
		var track = wrapper.querySelector(".f1wmt-marquee-track");
		if(!mq || !track) return;

		// Remove all groups, create one new
		track.innerHTML = '<div class="f1wmt-marquee-group">' + (itemsHtml || "") + '</div>';

		// initMarquee will add the clone
		initMarquee(wrapper, getMode());
	}

	function fetchAndUpdate(wrapper, mode){
		if(!CFG.ajaxUrl) return Promise.resolve();
		mode = (mode === "teams") ? "teams" : "drivers";

		// Update Data Attribute for speed reading in initMarquee immediately?
		// No, initMarquee uses passed mode or config.

		var data = {
			action: "f1wmt_auto_fetch",
			nonce: CFG.nonce,
			mode: mode
		};

		return $.ajax({
			url: CFG.ajaxUrl,
			method: "POST",
			data: data,
			dataType: "json"
		}).then(function(res){
			if(res.success && res.data){
				replaceTickerItems(wrapper, res.data.html || "");
				setModeUI(wrapper, res.data.mode || mode, true);
			}
		}).fail(function(){
			console.warn("F1 Ticker Fetch Failed");
		});
	}

	function ensureLinkFix(wrapper){
		if(wrapper.__f1wmtLinkFixBound) return;
		wrapper.__f1wmtLinkFixBound = true;

		wrapper.addEventListener("click", function(e){
			// Check if nav blocked/account open logic is needed?
			// Legacy had extensive check for body classes.
			var html = document.documentElement;
			if(html.classList.contains("bp-acc-open") || html.classList.contains("bp-nav-open") || html.classList.contains("bp-nav-arming")) {
				return; // Blocked interaction
			}

			if(e.target.closest(".f1wmt-modeBtn")) return; // Let button handle it

			var a = e.target.closest("a.f1wmt-driver");
			if(!a) return;

			var isNoLink = a.getAttribute("data-nolink") === "1";
			var href = a.getAttribute("data-href") || a.getAttribute("href") || "";

			if(!href || href === "#" || isNoLink){
				e.preventDefault();
				return;
			}

			// Allow default navigation if it's a real link
		}, true);
	}

	// Main Init
	$(document).ready(function(){
		var wrappers = document.querySelectorAll(".f1wmt-banner-exclusive-posts-wrapper");
		if(!wrappers.length) return;

		var mode = getMode();

		wrappers.forEach(function(wrapper){
			setModeUI(wrapper, mode, true);
			bindModeButton(wrapper);
			// Initial fetch
			fetchAndUpdate(wrapper, mode);
			ensureLinkFix(wrapper);
		});

		// Auto Refresh
		setInterval(function(){
			if(!SWITCHING && document.visibilityState === "visible"){
				var m = getMode();
				wrappers.forEach(function(w){ fetchAndUpdate(w, m); });
			}
		}, CFG.refreshMs);
	});

})(jQuery);
