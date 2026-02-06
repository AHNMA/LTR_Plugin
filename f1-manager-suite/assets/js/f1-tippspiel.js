(function(){
    const root = document.querySelector('.f1tips-wrap');
    if(!root) return;

    const cfg = window.F1TIPS_CFG || {};
    const restRootRaw = (cfg.rest || '').toString();
    const nonce = cfg.nonce || '';
    const year = cfg.year;
    const league_id = cfg.league_id;
    const me_id = parseInt(cfg.me_id || '0', 10);

    const driverMap = {};
    const teamMap   = {};

    function buildRestUrl(path){
      const base = restRootRaw || '/wp-json/';
      const baseNorm = base.replace(/\/+$/, '') + '/';
      const p = String(path || '').replace(/^\/+/, '');
      return new URL(p, baseNorm).toString();
    }

    function api(path, opts){
      opts = opts || {};
      opts.method = opts.method || 'GET';
      opts.credentials = opts.credentials || 'same-origin';
      opts.headers = Object.assign({}, opts.headers || {}, {'X-WP-Nonce': nonce});

      const url = buildRestUrl(path);

      return fetch(url, opts).then(async (r) => {
        const ct = (r.headers.get('content-type') || '').toLowerCase();
        if(ct.includes('application/json')) return await r.json();
        const text = await r.text();
        return { ok:false, status:r.status, message: text ? text.slice(0,200) : ('HTTP '+r.status) };
      }).catch((err) => {
        return { ok:false, message: (err && err.message) ? err.message : 'Netzwerkfehler' };
      });
    }

    function toast(msg, ok){
      const el = document.createElement('div');
      el.className = 'f1tips-note';
      el.style.marginBottom = '10px';
      el.style.borderColor = ok ? 'rgba(0,0,0,.12)' : 'rgba(216,58,58,.35)';
      el.style.background = ok ? 'rgba(0,0,0,.03)' : 'rgba(216,58,58,.08)';
      el.textContent = msg;
      const spot = root.querySelector('[data-toast]') || root;
      spot.prepend(el);
      setTimeout(()=> el.remove(), 3500);
    }

    function escapeHtml(str){
      return String(str || '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
    }

    function buildDriverMap(){
      root.querySelectorAll('select.f1tips-select option').forEach(o=>{
        const v = parseInt(o.value || '0', 10);
        if(!v) return;
        if(!driverMap[v]){
          const txt = (o.textContent || '').trim();
          if(txt) driverMap[v] = txt;
        }
      });
    }
    function nameOfDriver(id){ return driverMap[id] || ('#'+id); }

    function buildTeamMap(){
      const tsel = root.querySelector('select[data-team-template]');
      if(!tsel) return;
      Array.from(tsel.options).forEach(o=>{
        const v = parseInt(o.value || '0', 10);
        if(!v) return;
        if(!teamMap[v]){
          const txt = (o.textContent || '').trim();
          if(txt) teamMap[v] = txt;
        }
      });
    }
    function nameOfTeam(id){ return teamMap[id] || ('#'+id); }

    function usersToMap(usersArr){
      const m = {};
      (usersArr || []).forEach(u=>{
        const uid = parseInt(u.user_id || 0, 10);
        if(!uid) return;
        m[uid] = u;
      });
      return m;
    }

    function pillsFromIds(ids){
      if(!Array.isArray(ids) || !ids.length) return '';
      return ids.map((did, idx)=>{
        const id = parseInt(did || 0, 10);
        if(!id) return '';
        return `<span class="f1tips-pill"><strong>P${idx+1}</strong> ${escapeHtml(nameOfDriver(id))}</span>`;
      }).filter(Boolean).join(' ');
    }

    function syncTopHeights(){
      const isDesktop = window.matchMedia && window.matchMedia('(min-width: 981px)').matches;
      const playerCard = root.querySelector('.f1tips-stack .f1tips-card');
      const rightCol   = root.querySelector('.f1tips-side');

      if(!playerCard || !rightCol) return;
      playerCard.style.height = '';

      if(!isDesktop) return;

      const h = rightCol.getBoundingClientRect().height;
      if(h && h > 0){
        playerCard.style.height = Math.round(h) + 'px';
      }
    }

    let _syncTO = null;
    window.addEventListener('resize', function(){
      clearTimeout(_syncTO);
      _syncTO = setTimeout(syncTopHeights, 80);
    });

    async function loadMyStats(){
      const meNameEl = root.querySelector('[data-me-name]');
      const meIdEl   = root.querySelector('[data-me-id]');
      const meAvatar = root.querySelector('[data-me-avatar]');

      const posTotal = root.querySelector('[data-pos-total]');
      const ptsTotal = root.querySelector('[data-points-total]');
      const winsTotal = root.querySelector('[data-wins-total]');

      if(meNameEl) meNameEl.textContent = cfg.me_name ? String(cfg.me_name) : '—';
      if(meIdEl) meIdEl.textContent = me_id ? String(me_id) : '—';

      if(meAvatar){
        const src = (cfg.me_avatar || '').toString();
        if(src){ meAvatar.src = src; meAvatar.hidden = false; }
        else { meAvatar.hidden = true; }
      }

      if(!posTotal && !ptsTotal && !winsTotal){ syncTopHeights(); return; }

      if(!cfg.logged_in || !me_id){
        if(posTotal) posTotal.textContent = '—';
        if(ptsTotal) ptsTotal.textContent = '—';
        if(winsTotal) winsTotal.textContent = '—';
        syncTopHeights();
        return;
      }

      const res = await api('/f1tips/v1/leaderboard?year='+encodeURIComponent(year)+'&league_id='+encodeURIComponent(league_id));
      if(!res || !res.ok || !Array.isArray(res.items)){
        if(posTotal) posTotal.textContent = '—';
        if(ptsTotal) ptsTotal.textContent = '—';
        if(winsTotal) winsTotal.textContent = '—';
        syncTopHeights();
        return;
      }

      const idx = res.items.findIndex(x => parseInt(x.user_id||0,10) === me_id);
      if(posTotal) posTotal.textContent = (idx >= 0) ? String(idx+1) : '—';

      const meRow = (idx >= 0) ? res.items[idx] : null;
      if(ptsTotal) ptsTotal.textContent = (meRow && meRow.points != null) ? String(meRow.points) : '—';

      if(winsTotal){
        let winsVal = null;
        if(meRow){
          if(meRow.wins != null) winsVal = meRow.wins;
          else if(meRow.victories != null) winsVal = meRow.victories;
          else if(meRow.win_count != null) winsVal = meRow.win_count;
        }
        winsTotal.textContent = (winsVal != null && winsVal !== '' && !isNaN(Number(winsVal))) ? String(parseInt(winsVal,10)) : '—';
      }
      syncTopHeights();
    }

    function getAnyDriverSelect(){
      return root.querySelector('select.f1tips-select[data-session]');
    }
    function getTeamTemplateSelect(){
      return root.querySelector('select[data-team-template]');
    }

    function driverOptionsHtml(selectedId){
      const sel = getAnyDriverSelect();
      if(!sel) return '<option value="">Fahrer wählen</option>';
      const sid = parseInt(selectedId||'0',10) || 0;

      const opts = Array.from(sel.options).map(o=>{
        const v = parseInt(o.value||'0',10);
        const label = (o.textContent||'').trim();
        if(!v) return `<option value="" ${sid? '' : 'selected'}>Fahrer wählen</option>`;
        return `<option value="${v}" ${v===sid?'selected':''}>${escapeHtml(label)}</option>`;
      });
      return opts.join('');
    }

    function teamOptionsHtml(selectedId){
      const sel = getTeamTemplateSelect();
      if(!sel) return '<option value="">Team wählen</option>';
      const sid = parseInt(selectedId||'0',10) || 0;

      const opts = Array.from(sel.options).map(o=>{
        const v = parseInt(o.value||'0',10);
        const label = (o.textContent||'').trim();
        if(!v) return `<option value="" ${sid? '' : 'selected'}>Team wählen</option>`;
        return `<option value="${v}" ${v===sid?'selected':''}>${escapeHtml(label)}</option>`;
      });
      return opts.join('');
    }

    function selectOptionsHtml(optionsArr, selectedValue){
      const sv = (selectedValue == null) ? '' : String(selectedValue);
      const opts = ['<option value="" '+(sv===''?'selected':'')+'>Bitte wählen</option>'];
      (optionsArr||[]).forEach(o=>{
        const v = String(o||'').trim();
        if(!v) return;
        opts.push(`<option value="${escapeHtml(v)}" ${v===sv?'selected':''}>${escapeHtml(v)}</option>`);
      });
      return opts.join('');
    }

    function bonusStatusBadge(q){
      const status = String(q.status||'open');
      const locked = !!q.locked;
      let label = 'Offen'; let state = 'open';
      if(status === 'revealed'){ label = 'Ergebnis'; state = 'result'; }
      else if(locked){ label = 'Geschlossen'; state = 'closed'; }
      return `<span class="f1tips-state" data-state="${state}">${label}</span>`;
    }

    function normKey(s){
      return String(s||'').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,' ').replace(/\s+/g,' ').trim();
    }
    function isNumericLike(v){ return (String(v||'').trim() !== '') && !isNaN(Number(v)); }

    function shortBonusTitle(raw){
      const t = String(raw || '').trim();
      if(t === 'Wer wird Fahrer-Weltmeister?') return 'Fahrer-WM';
      if(t === 'Wer wird Konstrukteurs-Weltmeister?') return 'Team-WM';
      const k = normKey(t);
      if(k.includes('fahrer') && k.includes('weltmeister')) return 'Fahrer-WM';
      if((k.includes('konstrukteur') || k.includes('konstrukteurs')) && k.includes('weltmeister')) return 'Team-WM';
      return t || 'Bonusfrage';
    }

    function renderBonusIntoRace(wrap, bonus){
      if(!wrap) return;
      const box = wrap.querySelector('[data-bonus-card]');
      if(!box) return;
      const body = box.querySelector('[data-bonus-body]');
      if(!body) return;

      const items = (bonus && Array.isArray(bonus.items)) ? bonus.items : [];
      if(!items.length){ box.hidden = true; return; }
      box.hidden = false;

      let html = '';
      html += `<div class="f1tips-bonus-grid">`;

      items.forEach(q=>{
        const id = parseInt(q.id||0,10);
        const type = String(q.question_type||'select');
        const points = parseInt(q.points||0,10) || 0;
        const locked = !!q.locked;
        const my = (q.my_answer == null) ? '' : String(q.my_answer);
        const correct = (q.correct_value == null) ? '' : String(q.correct_value);
        const myPts = (q.my_points == null) ? null : parseInt(q.my_points,10);

        let control = '';
        if(type === 'driver'){
          const sid = parseInt(my||'0',10) || 0;
          control = `<select class="f1tips-select" data-bonus-input="${id}" ${locked?'disabled':''}>${driverOptionsHtml(sid)}</select>`;
        } else if(type === 'team'){
          const tid = parseInt(my||'0',10) || 0;
          control = `<select class="f1tips-select" data-bonus-input="${id}" ${locked?'disabled':''}>${teamOptionsHtml(tid)}</select>`;
        } else {
          control = `<select class="f1tips-select" data-bonus-input="${id}" ${locked?'disabled':''}>${selectOptionsHtml(q.options||[], my)}</select>`;
        }

        let infoInline = '';
        if(String(q.status||'') === 'revealed' && correct){
          let right = false;
          let correctLabel = correct;
          if(type === 'driver'){
            if(isNumericLike(correct)) correctLabel = nameOfDriver(parseInt(correct,10));
            if(isNumericLike(my) && isNumericLike(correct)) right = (parseInt(my,10) === parseInt(correct,10));
            else right = (normKey(my) === normKey(correct));
          } else if(type === 'team'){
            if(isNumericLike(correct)) correctLabel = nameOfTeam(parseInt(correct,10));
            if(isNumericLike(my) && isNumericLike(correct)) right = (parseInt(my,10) === parseInt(correct,10));
            else {
              const myK = normKey(my);
              const cK = normKey(correct);
              right = (myK === cK);
              if(!right && isNumericLike(correct)) right = (myK === normKey(nameOfTeam(parseInt(correct,10))));
            }
          } else {
            right = (my && my === correct);
          }
          const ptsText = (myPts == null) ? '' : ` · <span class="f1tips-points">${myPts} P</span>`;
          infoInline = `<div class="f1tips-bonus-small">Lösung: <strong>${escapeHtml(correctLabel)}</strong>${my ? (right ? ' · <strong>Richtig</strong>' : ' · <strong>Falsch</strong>') : ''}${ptsText}</div>`;
        } else if(locked){
          infoInline = `<div class="f1tips-bonus-small">Diese Bonusfrage ist geschlossen.</div>`;
        }

        html += `
          <div class="f1tips-card">
            <div class="f1tips-head">
              <div class="title">${escapeHtml(shortBonusTitle(q.question_text || ''))}</div>
              <div class="f1tips-bonus-meta">${bonusStatusBadge(q)}<span class="f1tips-points">${points} P</span></div>
            </div>
            <div class="f1tips-body">
              ${control}
              <div class="f1tips-bonus-actions">
                <button class="f1tips-btn f1tips-btn-primary" type="button" data-bonus-save="${id}" ${locked ? 'disabled' : ''} ${cfg.logged_in ? '' : 'disabled'}><span class="f1tips-btn-label">Antwort speichern</span></button>
                <div class="f1tips-bonus-inline">${infoInline}</div>
                ${!cfg.logged_in ? `<div class="f1tips-bonus-small" style="margin-top:0;flex:1 1 100%;">Bitte einloggen, um zu antworten.</div>` : ``}
              </div>
            </div>
          </div>
        `;
      });
      html += `</div>`;
      body.innerHTML = html;
      syncTopHeights();
    }

    function updateNoDuplicateOptionsForSession(wrap, sessionSlug){
      if(!wrap || !sessionSlug) return;
      const selects = Array.from(wrap.querySelectorAll('select[data-session="'+sessionSlug+'"]'));
      if(!selects.length) return;
      const chosen = selects.map(s => parseInt(s.value || '0', 10)).filter(v => !!v);
      selects.forEach(sel=>{
        const myVal = parseInt(sel.value || '0', 10);
        Array.from(sel.options).forEach(opt=>{
          const v = parseInt(opt.value || '0', 10);
          if(!v){ opt.disabled = false; return; }
          if(v === myVal){ opt.disabled = false; return; }
          opt.disabled = chosen.includes(v);
        });
      });
    }

    function initNoDuplicateBindings(){
      root.querySelectorAll('[data-race]').forEach(wrap=>{
        const sessionSlugs = new Set();
        wrap.querySelectorAll('select[data-session]').forEach(sel=>{
          const slug = sel.getAttribute('data-session');
          if(slug) sessionSlugs.add(slug);
          sel.addEventListener('change', ()=>{ updateNoDuplicateOptionsForSession(wrap, slug); });
        });
        sessionSlugs.forEach(slug=>{ updateNoDuplicateOptionsForSession(wrap, slug); });
      });
    }

    const raceSelect = root.querySelector('[data-race-select]');

    function refreshNoDuplicateForVisibleRace(){
      const rid = raceSelect ? parseInt(raceSelect.value||'0',10) : 0;
      if(!rid) return;
      const wrap = root.querySelector('[data-race="'+rid+'"]');
      if(!wrap) return;
      const slugs = new Set();
      wrap.querySelectorAll('select[data-session]').forEach(sel=>{
        const slug = sel.getAttribute('data-session');
        if(slug) slugs.add(slug);
      });
      slugs.forEach(slug=> updateNoDuplicateOptionsForSession(wrap, slug));
    }

    function updateWeekendPointsBoard(raceId, res){
      const box = root.querySelector('[data-weekend-points]');
      if(!box) return;
      const wrap = raceId ? root.querySelector('[data-race="'+raceId+'"]') : null;
      function labelForSlug(slug){
        switch(String(slug||'')){
          case 'sq': return 'Sprint-Qualifying';
          case 'sprint': return 'Sprint';
          case 'quali': return 'Qualifying';
          case 'race': return 'Rennen';
          default: return String(slug||'');
        }
      }

      if(!wrap){
        for(let i=1;i<=4;i++){
          const item = box.querySelector('[data-weekend-item="'+i+'"]');
          const labelEl = box.querySelector('[data-weekend-label="'+i+'"]');
          const span = box.querySelector('[data-weekend-session="'+i+'"]');
          if(item) item.classList.add('is-empty');
          if(labelEl) labelEl.textContent = '—';
          if(span) span.textContent = '—';
        }
        const totalSpan = box.querySelector('[data-weekend-total]');
        if(totalSpan) totalSpan.textContent = '—';
        return;
      }

      const badges = Array.from(wrap.querySelectorAll('[data-session-badge]'));
      const slugsInOrder = badges.map(b=> String(b.getAttribute('data-session-badge')||'').trim()).filter(Boolean);

      for(let i=1;i<=4;i++){
        const idx = i-1;
        const item = box.querySelector('[data-weekend-item="'+i+'"]');
        const labelEl = box.querySelector('[data-weekend-label="'+i+'"]');
        const span = box.querySelector('[data-weekend-session="'+i+'"]');
        if(!item || !span) continue;

        if(idx >= slugsInOrder.length){
          item.classList.add('is-empty');
          if(labelEl) labelEl.textContent = '—';
          span.textContent = '—';
          continue;
        }

        const slug = slugsInOrder[idx];
        item.classList.remove('is-empty');
        if(labelEl) labelEl.textContent = labelForSlug(slug);

        let val = null;
        const rawState = (res && res.states && res.states[slug]) ? String(res.states[slug]) : '';
        const ended = (rawState === 'ended');

        if(cfg.logged_in && me_id && ended){
          const raw = (res && res.my_points && res.my_points[slug] != null) ? parseInt(res.my_points[slug], 10) : null;
          if(raw !== null && !isNaN(raw)) val = raw;
        }

        if(val === null){ span.textContent = '—'; } else { span.textContent = String(val) + ' P'; }
      }

      const totalSpan = box.querySelector('[data-weekend-total]');
      if(totalSpan){
        const t = (res && res.my_points_total != null) ? parseInt(res.my_points_total, 10) : null;
        totalSpan.textContent = (t !== null && !isNaN(t)) ? (String(t) + ' P') : '—';
      }
    }

    const viewMain = root.querySelector('[data-view="main"]');
    const viewOthers = root.querySelector('[data-view="others"]');

    function showView(which){
      if(viewMain) viewMain.hidden = (which !== 'main');
      if(viewOthers) viewOthers.hidden = (which !== 'others');
      window.scrollTo({top:0, behavior:'instant'});
    }

    function renderSelectedRace(){
      const rid = raceSelect ? parseInt(raceSelect.value||'0',10) : 0;
      root.querySelectorAll('[data-race]').forEach(w=>{
        const id = parseInt(w.getAttribute('data-race')||'0',10);
        w.hidden = (id !== rid);
      });
      refreshNoDuplicateForVisibleRace();
    }

    function setSelectedRace(raceId){
      if(!raceSelect) return;
      raceSelect.value = String(raceId);
      renderSelectedRace();
      updateRaceWeekendBoard();
    }

    if(raceSelect) raceSelect.addEventListener('change', ()=>{ renderSelectedRace(); updateRaceWeekendBoard(); });

    async function loadRaceData(raceId){
      const wrap = root.querySelector('[data-race="'+raceId+'"]');
      if(!wrap) return null;

      const res = await api('/f1tips/v1/tips?year='+encodeURIComponent(year)+'&league_id='+encodeURIComponent(league_id)+'&race_post_id='+encodeURIComponent(raceId));
      if(!res || !res.ok) return null;

      if(res && res.my_tips){
        ['quali','sq','sprint','race'].forEach(slug=>{
          const my = res.my_tips[slug];
          if(!Array.isArray(my) || !my.length) return;
          const selects = Array.from(wrap.querySelectorAll('select[data-session="'+slug+'"]'));
          if(!selects.length) return;
          selects.forEach((sel, idx)=>{
            const v = parseInt(my[idx] || '0', 10);
            if(v > 0) sel.value = String(v);
          });
          updateNoDuplicateOptionsForSession(wrap, slug);
        });
      }

      ['quali','sq','sprint','race'].forEach(slug=>{
        const badge = wrap.querySelector('[data-session-badge="'+slug+'"]');
        if(!badge) return;
        const locked = !!(res.locked && res.locked[slug]);
        const rawState = (res.states && res.states[slug]) ? String(res.states[slug]) : (locked ? 'closed' : 'open');
        const ended = (rawState === 'ended');
        let state = 'open'; let label = 'Offen';
        if(ended){ state = 'result'; label = 'Ergebnis'; }
        else if(rawState === 'closed' || locked){ state = 'closed'; label = 'Geschlossen'; }
        badge.classList.add('f1tips-state');
        badge.dataset.state = state;
        badge.textContent = label;

        const othersBtn = wrap.querySelector('button[data-open-others="'+slug+'"]');
        if(othersBtn){
          const hasTips = !!(res.tips && Array.isArray(res.tips[slug]) && res.tips[slug].length);
          const show = ended && hasTips;
          othersBtn.classList.toggle('f1tips-hidden', !show);
        }

        const myPtsLine = wrap.querySelector('[data-my-session-points="'+slug+'"]');
        if(myPtsLine){
          myPtsLine.hidden = true;
          if(cfg.logged_in && me_id && ended){
            const raw = (res && res.my_points && res.my_points[slug] != null) ? parseInt(res.my_points[slug], 10) : null;
            if(raw !== null && !isNaN(raw)){
              const span = myPtsLine.querySelector('.f1tips-points');
              if(span) span.textContent = String(raw) + ' P';
              myPtsLine.hidden = false;
            }
          }
        }
      });

      if(res && res.bonus){ renderBonusIntoRace(wrap, res.bonus); }
      else { const card = wrap.querySelector('[data-bonus-card]'); if(card) card.hidden = true; }

      updateWeekendPointsBoard(raceId, res);
      refreshNoDuplicateForVisibleRace();
      syncTopHeights();
      return res;
    }

    function bindSaveButtons(){
      root.querySelectorAll('[data-save]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          e.preventDefault();
          if(!cfg.logged_in){ toast('Bitte einloggen, um zu tippen.', false); return; }
          const wrap = btn.closest('[data-race]');
          const race = parseInt(wrap.getAttribute('data-race'),10);
          const session = btn.getAttribute('data-save');
          const N = parseInt(btn.getAttribute('data-topn'),10);
          const selects = wrap.querySelectorAll('select[data-session="'+session+'"]');
          const tip = [];
          selects.forEach(s => tip.push(parseInt(s.value||'0',10)));

          if(tip.length !== N || tip.some(v=>!v)){ toast('Bitte alle Positionen ausfüllen.', false); return; }

          btn.disabled = true;
          const saveRes = await api('/f1tips/v1/tip', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({year, league_id, race_post_id: race, session_slug: session, tip})
          });
          btn.disabled = false;

          if(saveRes && saveRes.ok){
            toast('Tipp gespeichert.', true);
            await loadRaceData(race);
            await loadMyStats();
          }else{
            toast((saveRes && saveRes.message) ? saveRes.message : 'Speichern fehlgeschlagen.', false);
          }
        });
      });
    }

    function bindBonusButtons(){
      root.addEventListener('click', async (e)=>{
        const btn = e.target.closest('[data-bonus-save]');
        if(!btn) return;
        e.preventDefault();
        if(!cfg.logged_in){ toast('Bitte einloggen, um Bonusfragen zu beantworten.', false); return; }
        const qid = parseInt(btn.getAttribute('data-bonus-save')||'0',10);
        if(!qid) return;
        const rid = raceSelect ? parseInt(raceSelect.value||'0',10) : 0;
        const wrap = rid ? root.querySelector('[data-race="'+rid+'"]') : null;
        if(!wrap){ toast('Konnte Rennwochenende nicht bestimmen.', false); return; }
        const input = wrap.querySelector('[data-bonus-input="'+qid+'"]');
        if(!input){ toast('Eingabefeld nicht gefunden.', false); return; }
        let answer = String(input.value||'').trim();
        if(!answer){ toast('Bitte eine Antwort auswählen/eingeben.', false); return; }

        btn.disabled = true;
        const saveRes = await api('/f1tips/v1/bonus-answer', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({year, league_id, question_id: qid, answer})
        });
        btn.disabled = false;

        if(saveRes && saveRes.ok){
          toast('Bonus-Antwort gespeichert.', true);
          await loadRaceData(rid);
          await loadMyStats();
        } else {
          toast((saveRes && saveRes.message) ? saveRes.message : 'Speichern fehlgeschlagen.', false);
        }
      });
    }

    function updateRaceWeekendBoard(){
      const rid = raceSelect ? parseInt(raceSelect.value||'0',10) : 0;
      if(!rid) return;
      return loadRaceData(rid);
    }

    const othersTitle = root.querySelector('[data-others-title]');
    const othersBody  = root.querySelector('[data-others-body]');
    const btnBack     = root.querySelector('[data-others-back]');

    if(btnBack){
      btnBack.addEventListener('click', (e)=>{ e.preventDefault(); showView('main'); });
    }

    function bindOthersButtons(){
      root.querySelectorAll('[data-open-others]').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          e.preventDefault();
          const raceWrap = btn.closest('[data-race]');
          const raceId = parseInt(raceWrap.getAttribute('data-race')||'0',10);
          const sessionSlug = String(btn.getAttribute('data-open-others')||'');
          showView('others');
          if(othersTitle) othersTitle.textContent = 'Andere Tipps – ' + (btn.getAttribute('data-session-label') || sessionSlug);
          if(othersBody)  othersBody.innerHTML = '<div class="f1tips-mut">Lade …</div>';

          const payload = await api('/f1tips/v1/tips?year='+encodeURIComponent(year)+'&league_id='+encodeURIComponent(league_id)+'&race_post_id='+encodeURIComponent(raceId));
          if(!payload || !payload.ok){ if(othersBody) othersBody.innerHTML = '<div class="f1tips-note">Konnte Daten nicht laden.</div>'; return; }

          const locked = !!(payload.locked && payload.locked[sessionSlug]);
          const tips = (payload.tips && payload.tips[sessionSlug]) ? payload.tips[sessionSlug] : [];
          const usersMap = usersToMap(payload.users || []);
          const results = (payload.results && payload.results[sessionSlug]) ? payload.results[sessionSlug] : [];
          const pointsBySession = (payload.points && payload.points[sessionSlug]) ? payload.points[sessionSlug] : {};

          let html = '';
          if(!locked){ html += '<div class="f1tips-note"><strong>Hinweis:</strong> Diese Session ist noch offen. Du siehst hier keine Tipps anderer.</div>'; }
          if(Array.isArray(results) && results.length){
            html += `<div class="f1tips-card" style="box-shadow:none;margin-bottom:12px;"><div class="f1tips-head"><div class="title">Ergebnis</div></div><div class="f1tips-body">${pillsFromIds(results)}</div></div>`;
          }
          if(!tips.length){ html += '<div class="f1tips-mut">Keine Tipps vorhanden.</div>'; if(othersBody) othersBody.innerHTML = html; return; }

          html += `<div class="f1tips-list f1tips-list-others f1tips-list-scroll25"><div class="f1tips-list-head"><div>Spieler</div><div>Punkte</div><div>Tipps</div></div><div class="f1tips-list-body">`;
          tips.forEach(r=>{
            const uid = parseInt(r.user_id||0,10);
            const u = usersMap[uid] || {};
            const name = u.display_name ? String(u.display_name) : ('User #'+uid);
            const tip = Array.isArray(r.tip) ? r.tip : [];
            const picks = tip.map((did, idx)=>{
              const id = parseInt(did||0,10); if(!id) return '';
              return `<span class="f1tips-pill"><strong>P${idx+1}</strong> ${escapeHtml(nameOfDriver(id))}</span>`;
            }).filter(Boolean).join(' ');
            const ptsRaw = (pointsBySession && pointsBySession[uid] != null) ? parseInt(pointsBySession[uid],10) : null;
            const pts = (ptsRaw === null) ? '<span class="f1tips-mut">—</span>' : `<span class="f1tips-points">${ptsRaw} P</span>`;
            html += `<div class="f1tips-list-row"><div><strong>${escapeHtml(name)}</strong><div class="f1tips-mut">#${uid}</div></div><div>${pts}</div><div>${picks || '<span class="f1tips-mut">—</span>'}</div></div>`;
          });
          html += '</div></div>';
          if(othersBody) othersBody.innerHTML = html;
        });
      });
    }

    buildDriverMap();
    buildTeamMap();
    initNoDuplicateBindings();
    bindSaveButtons();
    bindOthersButtons();
    bindBonusButtons();

    (function initRaceDefault(){
      if(!raceSelect) return;
      let rid = parseInt(raceSelect.getAttribute('data-default-race')||'0',10) || 0;
      if(!rid){ rid = parseInt((raceSelect.options[0] && raceSelect.options[0].value) || '0', 10) || 0; }
      if(rid) raceSelect.value = String(rid);
      renderSelectedRace();
    })();

    loadMyStats();
    updateRaceWeekendBoard();
    showView('main');
    syncTopHeights();
  })();
