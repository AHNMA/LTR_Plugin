  (function(){
    var BP_ACC_MSG = window.BP_ACC_MSG || {};
    var CFG = window.f1_login_vars || {};

    function msg(path, fallback){
      try{
        var parts = (path || '').split('.');
        var cur = BP_ACC_MSG;
        for (var i=0; i<parts.length; i++){
          if (!cur || typeof cur !== 'object') return fallback || '';
          cur = cur[parts[i]];
        }
        return (typeof cur === 'string') ? cur : (fallback || '');
      }catch(e){
        return fallback || '';
      }
    }

    function setOpenState(isOpen){
      var html = document.documentElement;
      if (!html) return;
      html.classList.toggle('bp-acc-open', !!isOpen);
    }

    function getOverlay(){
      var ov = document.querySelector('.bp-acc-overlay');
      if (ov) return ov;

      ov = document.createElement('div');
      ov.className = 'bp-acc-overlay';
      document.body.appendChild(ov);

      ov.addEventListener('click', function(e){
        e.preventDefault();
        closeAll();
      });

      return ov;
    }
    function overlayOn(){ getOverlay().classList.add('is-on'); }
    function overlayOff(){
      var ov = document.querySelector('.bp-acc-overlay');
      if (ov) ov.classList.remove('is-on');
    }

    function closeAll(){
      var root = document.querySelector('[data-bp-account]');
      if (root) {
        root.classList.remove('is-open');
        var btn = root.querySelector('[data-bp-account-btn]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
      setOpenState(false);
      overlayOff();
    }

    function captureShieldInstall(){
      if (window.__bpAccCaptureShield === true) return;
      window.__bpAccCaptureShield = true;
      var interactionStartedInside = false;

      function isOpen(){
        return document.documentElement && document.documentElement.classList.contains('bp-acc-open');
      }
      function getRoot(){ return document.querySelector('[data-bp-account]'); }

      function shouldBlock(e){
        if (!isOpen()) return false;
        var root = getRoot();
        if (!root) return false;
        if (root.contains(e.target)) return false;
        return true;
      }

      document.addEventListener('mousedown', function(e){
        if (!isOpen()) return;
        var root = getRoot();
        if (root && root.contains(e.target)) {
            interactionStartedInside = true;
        } else {
            interactionStartedInside = false;
        }
      }, true);

      function blocker(e){
        if (interactionStartedInside) {
            interactionStartedInside = false;
            return;
        }
        if (!shouldBlock(e)) return;
        try{ e.preventDefault(); }catch(err){}
        try{ e.stopPropagation(); }catch(err){}
        try{ e.stopImmediatePropagation(); }catch(err){}
        closeAll();
      }
      document.addEventListener('click', blocker, true);
    }

    function getActivePaneName(root){
      var active = root ? root.querySelector('.bp-account__pane.is-active[data-bp-acc-pane]') : null;
      return active ? (active.getAttribute('data-bp-acc-pane') || '') : '';
    }

    function openIt(root){
      root.classList.add('is-open');
      var btn = root.querySelector('[data-bp-account-btn]');
      if (btn) btn.setAttribute('aria-expanded', 'true');

      captureShieldInstall();
      setOpenState(true);
      overlayOn();

      try{
        var pn = getActivePaneName(root);
        if (pn) setTimeout(function(){ resetTurnstileInPane(root, pn); }, 0);
      }catch(e){}
    }

    function setActivePane(root, name){
      var panes = root.querySelectorAll('[data-bp-acc-pane]');
      panes.forEach(function(p){
        p.classList.toggle('is-active', p.getAttribute('data-bp-acc-pane') === name);
      });
      setTimeout(function(){
        resetTurnstileInPane(root, name);
      }, 0);
    }

    function showLoginError(root, msgText){
      var box = root.querySelector('[data-bp-login-msg]');
      if (!box) return;
      box.textContent = msgText || msg('common.please_check', 'Bitte prüfen.');
      box.style.display = 'block';
    }
    function clearLoginError(root){
      var box = root.querySelector('[data-bp-login-msg]');
      if (!box) return;
      box.textContent = '';
      box.style.display = 'none';
    }

    function lostMsgClear(root){
      var ok = root.querySelector('[data-bp-lost-ok]');
      var err = root.querySelector('[data-bp-lost-err]');
      if (ok){ ok.textContent = ''; ok.style.display = 'none'; }
      if (err){ err.textContent = ''; err.style.display = 'none'; }
    }
    function lostMsgOk(root, msgText){
      var ok = root.querySelector('[data-bp-lost-ok]');
      var err = root.querySelector('[data-bp-lost-err]');
      if (err){ err.textContent = ''; err.style.display = 'none'; }
      if (!ok) return;
      ok.textContent = msgText || msg('common.ok', 'OK');
      ok.style.display = 'block';
    }
    function lostMsgErr(root, msgText){
      var ok = root.querySelector('[data-bp-lost-ok]');
      var err = root.querySelector('[data-bp-lost-err]');
      if (ok){ ok.textContent = ''; ok.style.display = 'none'; }
      if (!err) return;
      err.textContent = msgText || msg('common.please_check', 'Bitte prüfen.');
      err.style.display = 'block';
    }

    function renderTurnstileWithin(root){
      if (!root) return;
      function doRender(){
        try{
          if (!window.turnstile || typeof window.turnstile.render !== 'function') return false;
          var els = root.querySelectorAll('.bp-turnstile');
          if (!els || !els.length) return true;
          els.forEach(function(el){
            if (el.getAttribute('data-ts-rendered') === '1') return;
            if (el.querySelector('input[type="hidden"][name]')) {
              el.setAttribute('data-ts-rendered', '1');
              return;
            }
            var id = window.turnstile.render(el, {
              sitekey: el.getAttribute('data-sitekey') || '',
              theme: el.getAttribute('data-theme') || 'auto',
              size: el.getAttribute('data-size') || 'flexible',
              action: el.getAttribute('data-action') || '',
              "response-field-name": el.getAttribute('data-response-field-name') || 'cf-turnstile-response'
            });
            el.setAttribute('data-ts-rendered', '1');
            el.setAttribute('data-ts-id', String(id));
          });
          return true;
        }catch(e){ return false; }
      }
      var n = 0;
      (function tick(){
        n++;
        var ok = doRender();
        if ((!ok || !window.turnstile || typeof window.turnstile.render !== 'function') && n < 40) {
          setTimeout(tick, 50);
        }
      })();
    }

    function resetTurnstileInForm(form){
      try{
        if (!form || !window.turnstile || typeof window.turnstile.reset !== 'function') return;
        var w = form.querySelector('.bp-turnstile[data-ts-id]');
        if (w) {
          var id = w.getAttribute('data-ts-id');
          if (id !== null && id !== '') {
            window.turnstile.reset(id);
            return;
          }
        }
        window.turnstile.reset();
      }catch(e){}
    }

    function resetTurnstileInPane(root, paneName){
      try{
        if (!root || !paneName) return;
        var pane = root.querySelector('[data-bp-acc-pane="' + paneName + '"]');
        if (!pane) return;
        var form = pane.querySelector('form');
        if (!form) return;
        resetTurnstileInForm(form);
      }catch(e){}
    }

    function bindRegisterTokenBridge(root){
      try{
        var pane = root.querySelector('[data-bp-acc-pane="register"]');
        if (!pane) return;
        var form = pane.querySelector('form');
        if (!form) return;
        form.addEventListener('submit', function(){
          try{
            var src = form.querySelector('input[name="ts_register"]');
            if (!src) return;
            var val = (src.value || '').trim();
            if (!val) return;
            var dst = form.querySelector('input[name="cf-turnstile-response"]');
            if (!dst) {
              dst = document.createElement('input');
              dst.type = 'hidden';
              dst.name = 'cf-turnstile-response';
              form.appendChild(dst);
            }
            dst.value = val;
          }catch(e){}
        }, true);
      }catch(e){}
    }

    function ajaxLogin(root){
      var form = root.querySelector('[data-bp-login-form]');
      if (!form) return;

      var userEl = form.querySelector('#bp_login_user');
      var passEl = form.querySelector('#bp_login_pass');
      var remEl  = form.querySelector('input[name="bp_login_remember"]');
      var nonceEl= form.querySelector('input[name="bp_acc_login_nonce"]');
      var btn    = form.querySelector('[data-bp-login-submit]');

      form.addEventListener('submit', function(e){
        e.preventDefault();
        clearLoginError(root);

        var user = userEl ? (userEl.value || '').trim() : '';
        var pass = passEl ? (passEl.value || '').trim() : '';
        var remember = (remEl && remEl.checked) ? '1' : '0';
        var nonce = nonceEl ? (nonceEl.value || '') : '';

        if (!user || !pass){
          showLoginError(root, msg('login.need_user_pass', 'Nutzername/E-Mail und Passwort ausfüllen.'));
          return;
        }
        if (!nonce){
          showLoginError(root, msg('common.sec_fail', 'Sicherheitscheck fehlgeschlagen.'));
          return;
        }

        var tsEl = form.querySelector('input[name="ts_login"], input[name="cf-turnstile-response"]');
        var ts = tsEl ? (tsEl.value || '').trim() : '';
        if (!ts){
          showLoginError(root, msg('turnstile.missing', 'Bitte bestätige, dass du ein Mensch bist.'));
          return;
        }

        if (btn){
          btn.disabled = true;
          btn.style.opacity = '0.85';
        }

        var body = new URLSearchParams();
        body.set('action', 'f1_login');
        body.set('nonce', nonce);
        body.set('user', user);
        body.set('pass', pass);
        body.set('remember', remember);
        body.set('turnstile', ts);

        fetch(CFG.ajax_url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString()
        })
        .then(function(r){ return r.json().catch(function(){ return null; }); })
        .then(function(data){
          if (!data || typeof data !== 'object') {
            showLoginError(root, msg('common.unexpected', 'Unerwartete Antwort.'));
            resetTurnstileInForm(form);
            return;
          }
          if (data.success && data.data && data.data.redirect){
            window.location.href = data.data.redirect;
            return;
          }
          var m = (data.data && data.data.message) ? data.data.message : msg('login.failed', 'Login fehlgeschlagen.');
          showLoginError(root, m);
          resetTurnstileInForm(form);
        })
        .catch(function(){
          showLoginError(root, msg('common.net_fail', 'Netzwerkfehler.'));
          resetTurnstileInForm(form);
        })
        .finally(function(){
          if (btn){
            btn.disabled = false;
            btn.style.opacity = '';
          }
        });
      });
    }

    function ajaxLostPass(root){
      var form = root.querySelector('[data-bp-lost-form]');
      if (!form) return;

      var userEl = form.querySelector('#bp_lost_user');
      var nonceEl= form.querySelector('input[name="bp_acc_lostpass_nonce"]');
      var btn    = form.querySelector('[data-bp-lost-submit]');

      form.addEventListener('submit', function(e){
        e.preventDefault();
        lostMsgClear(root);

        var user = userEl ? (userEl.value || '').trim() : '';
        var nonce = nonceEl ? (nonceEl.value || '') : '';

        if (!user){
          lostMsgErr(root, msg('lost.need_user', 'Benutzername oder E-Mail eingeben.'));
          return;
        }
        if (!nonce){
          lostMsgErr(root, msg('common.sec_fail', 'Sicherheitscheck fehlgeschlagen.'));
          return;
        }

        var tsEl = form.querySelector('input[name="ts_lost"], input[name="cf-turnstile-response"]');
        var ts = tsEl ? (tsEl.value || '').trim() : '';
        if (!ts){
          lostMsgErr(root, msg('turnstile.missing', 'Bitte bestätige, dass du ein Mensch bist.'));
          return;
        }

        if (btn){
          btn.disabled = true;
          btn.style.opacity = '0.85';
        }

        var body = new URLSearchParams();
        body.set('action', 'f1_lostpass');
        body.set('nonce', nonce);
        body.set('user', user);
        body.set('turnstile', ts);

        fetch(CFG.ajax_url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString()
        })
        .then(function(r){ return r.json().catch(function(){ return null; }); })
        .then(function(data){
          if (!data || typeof data !== 'object') {
            lostMsgErr(root, msg('common.unexpected', 'Unerwartete Antwort.'));
            resetTurnstileInForm(form);
            return;
          }
          if (data.success && data.data && data.data.message){
            lostMsgOk(root, data.data.message);
            resetTurnstileInForm(form);
            return;
          }
          var m = (data.data && data.data.message) ? data.data.message : msg('lost.cannot', 'Konnte Anfrage nicht verarbeiten.');
          lostMsgErr(root, m);
          resetTurnstileInForm(form);
        })
        .catch(function(){
          lostMsgErr(root, msg('common.net_fail', 'Netzwerkfehler.'));
          resetTurnstileInForm(form);
        })
        .finally(function(){
          if (btn){
            btn.disabled = false;
            btn.style.opacity = '';
          }
        });
      });
    }

    function ajaxRegLiveCheck(root){
      var pane = root.querySelector('[data-bp-acc-pane="register"]');
      if (!pane) return;
      var form = pane.querySelector('form');
      if (!form) return;

      var userEl  = form.querySelector('#bp_reg_user');
      var emailEl = form.querySelector('#bp_reg_email');
      var nonceEl = form.querySelector('input[name="bp_acc_regcheck_nonce"]');
      var submit  = form.querySelector('button[type="submit"]');

      var userMsg  = form.querySelector('[data-bp-reg-user-msg]');
      var emailMsg = form.querySelector('[data-bp-reg-email-msg]');
      var consentEl = form.querySelector('#bp_reg_accept');

      if (!userEl || !emailEl || !nonceEl || !submit || !userMsg || !emailMsg) return;

      var debounceTimer = null;
      var currentController = null;

      var state = { user: 'unknown', email: 'unknown' };

      function setMsg(el, type, text){
        el.classList.remove('is-ok','is-err','is-wait');
        if (!text){
          el.textContent = '';
          el.style.visibility = 'hidden';
          return;
        }
        el.textContent = text;
        el.style.visibility = 'visible';
        if (type) el.classList.add(type);
      }

      function setFieldState(input, s){
        input.classList.remove('bp-is-invalid','bp-is-ok');
        if (s === 'taken' || s === 'invalid') input.classList.add('bp-is-invalid');
        if (s === 'ok') input.classList.add('bp-is-ok');
      }

      function syncSubmit(){
        var consentOk = consentEl ? !!consentEl.checked : true;
        var bad = (state.user === 'taken' || state.user === 'invalid' || state.email === 'taken' || state.email === 'invalid' || !consentOk);
        submit.disabled = bad;
        submit.style.opacity = bad ? '0.65' : '';
        submit.style.cursor = bad ? 'not-allowed' : '';
      }

      function doCheck(){
        var user = (userEl.value || '').trim();
        var email = (emailEl.value || '').trim();
        var nonce = (nonceEl.value || '').trim();

        if (!nonce) return;

        if (!user && !email) {
            setMsg(userMsg, '', '');
            setMsg(emailMsg, '', '');
            state.user = 'unknown';
            state.email = 'unknown';
            syncSubmit();
            return;
        }

        setMsg(userMsg, 'is-wait', user ? msg('regcheck.checking_user', 'Prüfe Name…') : '');
        setMsg(emailMsg, 'is-wait', email ? msg('regcheck.checking_email', 'Prüfe E-Mail…') : '');

        if (currentController) {
            currentController.abort();
        }
        currentController = new AbortController();
        var signal = currentController.signal;

        var body = new URLSearchParams();
        body.set('action', 'f1_reg_check');
        body.set('nonce', nonce);
        body.set('user', user);
        body.set('email', email);

        fetch(CFG.ajax_url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString(),
          signal: signal
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!data || !data.success || !data.data) return;

          if (data.data.user && typeof data.data.user.status === 'string'){
            state.user = data.data.user.status;
            var um = data.data.user.message || '';
            if (user) setMsg(userMsg, (state.user === 'ok' ? 'is-ok' : (state.user === 'taken' || state.user === 'invalid' ? 'is-err' : 'is-wait')), um);
            else { setMsg(userMsg, '', ''); state.user = 'unknown'; }
            setFieldState(userEl, state.user);
          }

          if (data.data.email && typeof data.data.email.status === 'string'){
            state.email = data.data.email.status;
            var em = data.data.email.message || '';
            if (email) setMsg(emailMsg, (state.email === 'ok' ? 'is-ok' : (state.email === 'taken' || state.email === 'invalid' ? 'is-err' : 'is-wait')), em);
            else { setMsg(emailMsg, '', ''); state.email = 'unknown'; }
            setFieldState(emailEl, state.email);
          }
          syncSubmit();
        })
        .catch(function(err){
          if (err.name === 'AbortError') return;
          if (user) setMsg(userMsg, 'is-wait', msg('regcheck.cant_check', 'Fehler beim Prüfen.'));
          if (email) setMsg(emailMsg, 'is-wait', msg('regcheck.cant_check', 'Fehler beim Prüfen.'));
          syncSubmit();
        });

        syncSubmit();
      }

      function schedule(){
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doCheck, 400);
      }

      userEl.addEventListener('input', schedule);
      emailEl.addEventListener('input', schedule);

      if (consentEl) consentEl.addEventListener('change', syncSubmit);

      syncSubmit();
    }

    function bind(root){
      var btn = root.querySelector('[data-bp-account-btn]');
      var panel = root.querySelector('[data-bp-account-panel]');
      var closeBtn = root.querySelector('[data-bp-account-close]');
      if (!btn || !panel) return;

      panel.addEventListener('click', function(e){ e.stopPropagation(); });
      panel.addEventListener('pointerdown', function(e){ e.stopPropagation(); });
      panel.addEventListener('touchstart', function(e){ e.stopPropagation(); });

      // Trigger button within the modal structure (desktop dropdown behavior)
      btn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        if (root.classList.contains('is-open')) closeAll();
        else openIt(root);
      });

      if (closeBtn){
        closeBtn.addEventListener('click', function(e){
          e.preventDefault();
          closeAll();
        });
      }

      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeAll();
      });

      var switches = root.querySelectorAll('[data-bp-acc-switch]');
      switches.forEach(function(s){
        s.addEventListener('click', function(e){
          e.preventDefault();
          var target = s.getAttribute('data-bp-acc-switch');
          if (target === 'login' || target === 'register' || target === 'lost') {
            setActivePane(root, target);
            if (target !== 'lost') lostMsgClear(root);
          }
        });
      });

      var def = root.getAttribute('data-bp-default-tab');
      if (def === 'register' || def === 'login') setActivePane(root, def);
      else setActivePane(root, 'login');

      ajaxLogin(root);
      ajaxLostPass(root);
      ajaxRegLiveCheck(root);

      bindRegisterTokenBridge(root);

      try{
        var autoOpen = root.getAttribute('data-bp-auto-open');
        if (autoOpen === '1') {
          openIt(root);
          setActivePane(root, 'register');
        }
      }catch(e){}
    }

    function createGoogleBtn(text) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bp-social-btn bp-social-btn--google';
        btn.innerHTML = `
            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
            </svg>
            <span>` + text + `</span>
        `;
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleSocialClick(btn);
        });
        return btn;
    }

    function createDivider() {
        var d = document.createElement('div');
        d.className = 'bp-social-divider';
        d.innerText = 'ODER';
        return d;
    }

    function handleSocialClick(btnClicked) {
        var regPane = btnClicked.closest('[data-bp-acc-pane="register"]');
        var consentGiven = 0;

        if (regPane) {
            var consentCb = document.getElementById('bp_reg_accept');
            if (consentCb && !consentCb.checked) {
                consentCb.focus();
                if (typeof consentCb.reportValidity === 'function') {
                    consentCb.setCustomValidity('Bitte akzeptiere den Datenschutz & die AGB, um dich mit Google zu registrieren.');
                    consentCb.reportValidity();
                    consentCb.addEventListener('input', function(){ consentCb.setCustomValidity(''); }, {once:true});
                } else {
                    alert('Bitte akzeptiere zuerst Datenschutz & AGB.');
                }
                var label = consentCb.closest('label');
                if (label) {
                    var oldColor = label.style.color;
                    label.style.color = '#dc3545';
                    label.style.transition = 'color 0.2s ease';
                    setTimeout(function(){ label.style.color = oldColor; }, 2500);
                }
                return;
            }
            consentGiven = 1;
        }

        btnClicked.classList.add('is-loading');
        var target = CFG.google_auth_url || '/?bp_social_auth=google';
        if (consentGiven === 1) target += '&consent=1';
        window.location.href = target;
    }

    function injectSocialButtons(root) {
        var loginForm = root.querySelector('[data-bp-login-form]');
        if (loginForm && !loginForm.querySelector('.bp-social-btn--google')) {
            var linksContainer = loginForm.querySelector('.bp-account__links') || loginForm.querySelector('.bp-account__tabs');
            if (linksContainer) {
                var container = document.createElement('div');
                container.appendChild(createDivider());
                container.appendChild(createGoogleBtn('Mit Google einloggen'));
                linksContainer.parentNode.insertBefore(container, linksContainer.nextSibling);
            }
        }

        var regPane = root.querySelector('[data-bp-acc-pane="register"] form');
        if (regPane && !regPane.querySelector('.bp-social-btn--google')) {
             var tabsContainer = regPane.querySelector('.bp-account__tabs') || regPane.querySelector('.bp-account__btnRegister');
             if (tabsContainer) {
                 var container = document.createElement('div');
                 container.appendChild(createDivider());
                 container.appendChild(createGoogleBtn('Mit Google registrieren'));
                 tabsContainer.parentNode.insertBefore(container, tabsContainer.nextSibling);
             }
        }
    }

    function init(){
        var root = document.querySelector('[data-bp-account]');
        if(!root) return;

        bind(root);
        renderTurnstileWithin(root);
        injectSocialButtons(root);

        // Bind External Triggers (Shortcode Buttons)
        document.addEventListener('click', function(e){
            if(e.target.closest('.js-f1-login-trigger')){
                e.preventDefault();
                openIt(root);
            }
        });
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

  })();
