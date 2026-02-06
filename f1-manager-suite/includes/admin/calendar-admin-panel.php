<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Admin Panel View
 * Expects: $races, $nonce, $media (variables available in scope)
 * $this refers to F1_Manager_Calendar instance
 */
?>
  <style>
    .section-block-upper .f1cal-admin{ display: block; margin-top: 18px; background: #f2f3f5; border: 1px solid rgba(0,0,0,.08); border-radius: 0; box-shadow:0 10px 26px rgba(0,0,0,.06); padding: 14px; width: 100%; }
    .section-block-upper .f1cal-admin{ width: 50%; margin-left: auto; margin-right: auto; }
    body.wp-admin .section-block-upper .f1cal-admin{ width: 100%; max-width: 980px; margin-left: 0; margin-right: auto; }
    @media (max-width: 980px){ .section-block-upper .f1cal-admin{ width: 100%; } body.wp-admin .section-block-upper .f1cal-admin{ max-width: 100%; } }
    .f1cal-admin__surface{ background: #ffffff; border: 1px solid rgba(0,0,0,.08); padding: 14px; }
    .f1cal-admin h3{ margin: 0 0 10px; font-size: 16px; font-weight: 900; letter-spacing: .02em; text-transform: uppercase; }
    .f1cal-admin__hint{ font-size: 12px; color: rgba(17,17,17,.65); font-weight: 700; margin-bottom: 10px; }
    .f1cal-admin__list{ list-style:none; padding:0; margin:0 0 12px; max-height:260px; overflow:auto; border:1px solid rgba(0,0,0,.10); background:#fff; }
    .f1cal-admin__item{ padding:10px 10px; border-bottom:1px solid rgba(0,0,0,.06); display:flex; gap:10px; align-items:center; cursor:pointer; user-select:none; background:#fff; position:relative; }
    .f1cal-admin__item:last-child{ border-bottom:0; }
    .f1cal-admin__item.is-active{ background: rgba(31,157,85,.10); outline: 2px solid rgba(31,157,85,.25); outline-offset: -2px; }
    .f1cal-admin__item.is-live{ background: rgba(224,0,120,.10); box-shadow: inset 0 0 0 2px rgba(224,0,120,.18); }
    .f1cal-admin__item.is-next{ background: rgba(224,0,120,.06); box-shadow: inset 0 0 0 2px rgba(224,0,120,.10); }
    .f1cal-admin__item.is-live::before, .f1cal-admin__item.is-next::before{ content:""; position:absolute; left:0; top:0; bottom:0; width: 4px; background: #E00078; opacity: .95; }
    .f1cal-admin__item.is-cancelled{ opacity:.62; text-decoration: line-through; text-decoration-thickness: 2px; }
    .f1cal-admin__item.is-completed{ opacity:.82; }
    .f1cal-admin__nr{ width:26px; font-weight:900; color: rgba(17,17,17,.65); text-align:right; flex:0 0 auto; }
    .f1cal-admin__meta{ flex:1; min-width:0; }
    .f1cal-admin__title{ font-weight:900; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .f1cal-admin__sub{ font-size:12px; font-weight:800; color: rgba(17,17,17,.65); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .f1cal-admin__badge{ flex: 0 0 auto; display:inline-flex; align-items:center; justify-content:center; height: 26px; padding: 0 10px; border-radius: 999px; font-weight: 900; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; background: rgba(0,0,0,.06); color: rgba(0,0,0,.70); white-space: nowrap; }
    .f1cal-admin__badge--live{ background: rgba(224,0,120,.14); color: #E00078; }
    .f1cal-admin__badge--next{ background: rgba(224,0,120,.10); color: #E00078; }
    .f1cal-admin__badge--completed{ background: rgba(0,0,0,.08); color: rgba(0,0,0,.75); }
    .f1cal-admin__badge--cancelled{ background: rgba(0,0,0,.08); color: rgba(0,0,0,.62); }
    .f1cal-admin__arrows{ display:none; flex:0 0 auto; gap:6px; align-items:center; justify-content:center; }
    .f1cal-admin__item.is-active .f1cal-admin__arrows{ display:flex; }
    .f1cal-arrow, .f1cal-x{ width:30px; height:30px; border:0; border-radius:0; font-weight:900; cursor:pointer; padding:0; display:inline-flex; align-items:center; justify-content:center; line-height:1; color:#fff; }
    .f1cal-arrow{ background: rgba(0,0,0,.55); } .f1cal-x{ background: #d83a3a; }
    .f1cal-arrow:active, .f1cal-x:active{ transform: translateY(1px); }
    .f1cal-admin label{ display:block; font-size:12px; font-weight:900; margin:10px 0 4px; letter-spacing:.02em; text-transform: uppercase; }
    .f1cal-admin input[type="text"], .f1cal-admin input[type="url"]{ width:100%; height:44px; padding:10px 10px; border:1px solid rgba(0,0,0,.12); border-radius:0; font-size:13px; line-height:1.2; font-weight:750; outline:none; background:#f8f9fb; appearance:none; -webkit-appearance:none; }
    .f1cal-admin select{ width:100%; height:44px; padding:10px 10px; border:1px solid rgba(0,0,0,.12); border-radius:0; font-size:13px; line-height:1.2; font-weight:750; outline:none; background:#f8f9fb; appearance:none; -webkit-appearance:none; font-family: inherit; color: inherit; text-transform: uppercase; }
    .f1cal-admin select option{ text-transform: uppercase; }
    .f1cal-admin__divider{ margin-top:12px; padding-top:10px; border-top:1px solid rgba(0,0,0,.08); }
    .f1cal-admin__btns{ display:flex; gap:10px; margin-top:12px; flex-wrap:wrap; }
    .f1cal-admin__btns button{ border:0; border-radius:0; padding:11px 12px; font-weight:900; cursor:pointer; width:100%; color:#fff; }
    .f1cal-admin__btns button.primary{ background: #1f9d55; } .f1cal-admin__btns button.primary:hover{ background: #178247; }
    .f1cal-admin__btns button.danger{ background: #d83a3a; } .f1cal-admin__btns button.danger:hover{ background: #b92f2f; }
    #f1cal_delete{ display:none; }
    .f1cal-admin__msg{ margin-top:10px; font-size:12px; font-weight:900; color: rgba(17,17,17,.75); min-height:16px; }
    /* Media DD */
    .f1dc-mdd{ position:relative; width:100%; }
    .f1dc-mdd__btn{ width:100%; height:44px; border:1px solid rgba(0,0,0,.12); background:#f8f9fb; display:flex; align-items:center; justify-content:space-between; padding:8px 10px; cursor:pointer; font-weight:900; letter-spacing:.02em; gap:10px; }
    .f1dc-mdd__left{ display:flex; align-items:center; gap:10px; min-width:0; }
    .f1dc-mdd__thumb{ width:28px; height:28px; display:flex; align-items:center; justify-content:center; background:#fff; box-shadow: inset 0 0 0 1px rgba(0,0,0,.15); flex:0 0 auto; overflow:hidden; }
    .f1dc-mdd__thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
    .f1dc-mdd__label{ font-size:13px; color: rgba(17,17,17,.92); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 75%; text-transform: uppercase; }
    .f1dc-mdd__chev{ opacity:.65; flex:0 0 auto; }
    .f1dc-mdd__panel{ position:absolute; left:0; right:0; top: calc(44px + 6px); border:1px solid rgba(0,0,0,.12); background:#fff; z-index:9999; max-height: 320px; overflow:auto; box-shadow: 0 10px 26px rgba(0,0,0,.12); display:none; }
    .f1dc-mdd.is-open .f1dc-mdd__panel{ display:block; }
    .f1dc-mdd__searchwrap{ position: sticky; top: 0; background: #fff; padding: 8px; border-bottom: 1px solid rgba(0,0,0,.08); z-index: 1; }
    .f1dc-mdd__search{ width:100%; height:40px; border:1px solid rgba(0,0,0,.12); background:#f8f9fb; padding:8px 10px; font-weight:900; border-radius:0; outline:none; }
    .f1dc-mdd__item{ display:flex; align-items:center; gap:10px; padding:8px 10px; cursor:pointer; border-bottom:1px solid rgba(0,0,0,.06); font-weight:900; }
    .f1dc-mdd__item:last-child{ border-bottom:0; }
    .f1dc-mdd__item:hover{ background: rgba(0,0,0,.04); }
    .f1cal-admin button[disabled]{ opacity: .45; cursor: not-allowed; filter: grayscale(35%); }
    .f1cal-fields-group{ margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,.08); }
    .f1cal-fields-title{ font-size: 12px; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; color: rgba(17,17,17,.65); margin: 0 0 8px; }
    /* Dark Mode Support */
    html[data-dusky-dark-mode="dark"] .f1cal-admin{ background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.12); color: #fff; }
    html[data-dusky-dark-mode="dark"] .f1cal-admin__surface{ background: #0f0f0f; border-color: rgba(255,255,255,.12); }
    html[data-dusky-dark-mode="dark"] .f1cal-admin__list{ background: #0f0f0f; border-color: rgba(255,255,255,.14); }
    html[data-dusky-dark-mode="dark"] .f1cal-admin__item{ background: #0f0f0f; border-bottom-color: rgba(255,255,255,.10); }
    html[data-dusky-dark-mode="dark"] .f1cal-admin__sub, html[data-dusky-dark-mode="dark"] .f1cal-admin__hint, html[data-dusky-dark-mode="dark"] .f1cal-fields-title{ color: rgba(255,255,255,.70); }
    html[data-dusky-dark-mode="dark"] .f1cal-admin input, html[data-dusky-dark-mode="dark"] .f1cal-admin select{ background: #0b0b0b; color: #fff; border-color: rgba(255,255,255,.18); }
    html[data-dusky-dark-mode="dark"] .f1cal-admin__badge{ background: rgba(255,255,255,.10); color: rgba(255,255,255,.80); }
    html[data-dusky-dark-mode="dark"] .f1cal-admin__badge--live, html[data-dusky-dark-mode="dark"] .f1cal-admin__badge--next{ background: rgba(224,0,120,.18); color: #ff66bb; }
  </style>

  <div class="section-block-upper">
    <aside class="f1cal-admin" id="f1calPanel" aria-label="Kalender bearbeiten">
      <div class="f1cal-admin__surface">
        <h3>Kalender bearbeiten</h3>
        <div class="f1cal-admin__hint">Klick auf Eintrag "=" bearbeiten. Mit "↑ ↓" verschiebst du. Mit "x" abwählen.</div>
        <ul class="f1cal-admin__list" id="f1calList">
          <?php
          $n = 1;
          foreach ($races as $race) :
            $pid  = (int) $race->ID;
            $m    = $this->get_meta($pid);
            $stat = $this->sanitize_status(!empty($m['status']) ? $m['status'] : 'none');
            $wt   = $this->sanitize_weekend_type(!empty($m['weekend_type']) ? $m['weekend_type'] : 'normal');
            $title = ($m['gp'] !== '') ? $m['gp'] : get_the_title($pid);
            $raceDate = !empty($m['race_date']) ? $this->weekday_date_de($m['race_date']) : '—';
            $sub = $raceDate . ' • ' . (($wt === 'sprint') ? 'Sprintformat' : 'Klassisches Format');

            $li_class = '';
            if ($stat === 'live') $li_class = 'is-live';
            if ($stat === 'next') $li_class = 'is-next';
            if ($stat === 'cancelled') $li_class = 'is-cancelled';
            if ($stat === 'completed') $li_class = 'is-completed';

            $badge_text = ''; $badge_class = '';
            if ($stat === 'live') { $badge_text = 'Live'; $badge_class = 'f1cal-admin__badge--live'; }
            else if ($stat === 'next') { $badge_text = 'Next'; $badge_class = 'f1cal-admin__badge--next'; }
            else if ($stat === 'completed') { $badge_text = 'Abgeschlossen'; $badge_class = 'f1cal-admin__badge--completed'; }
            else if ($stat === 'cancelled') { $badge_text = 'Abgesagt'; $badge_class = 'f1cal-admin__badge--cancelled'; }
            else { $badge_text = 'Normal'; $badge_class = ''; }
            ?>
            <li class="f1cal-admin__item <?php echo esc_attr($li_class); ?>"
                data-id="<?php echo esc_attr($pid); ?>"
                data-gp="<?php echo esc_attr($m['gp']); ?>"
                data-circuit="<?php echo esc_attr($m['circuit']); ?>"
                data-status="<?php echo esc_attr($stat); ?>"
                data-weekend_type="<?php echo esc_attr($wt); ?>"
                data-flag_file="<?php echo esc_attr((string)$m['flag_file']); ?>"
                data-flag_url="<?php echo esc_attr((string)$m['flag_url']); ?>"
                data-fp1_date="<?php echo esc_attr($m['fp1_date']); ?>" data-fp1_time="<?php echo esc_attr($m['fp1_time']); ?>"
                data-fp2_date="<?php echo esc_attr($m['fp2_date']); ?>" data-fp2_time="<?php echo esc_attr($m['fp2_time']); ?>"
                data-fp3_date="<?php echo esc_attr($m['fp3_date']); ?>" data-fp3_time="<?php echo esc_attr($m['fp3_time']); ?>"
                data-sq_date="<?php echo esc_attr($m['sq_date']); ?>"   data-sq_time="<?php echo esc_attr($m['sq_time']); ?>"
                data-sprint_date="<?php echo esc_attr($m['sprint_date']); ?>" data-sprint_time="<?php echo esc_attr($m['sprint_time']); ?>"
                data-quali_date="<?php echo esc_attr($m['quali_date']); ?>" data-quali_time="<?php echo esc_attr($m['quali_time']); ?>"
                data-race_date="<?php echo esc_attr($m['race_date']); ?>"   data-race_time="<?php echo esc_attr($m['race_time']); ?>">
              <div class="f1cal-admin__nr"><?php echo esc_html($n.'.'); ?></div>
              <div class="f1cal-admin__meta">
                <div class="f1cal-admin__title"><?php echo esc_html($title); ?></div>
                <div class="f1cal-admin__sub"><?php echo esc_html($sub); ?></div>
              </div>
              <div class="f1cal-admin__badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_text); ?></div>
              <div class="f1cal-admin__arrows" aria-label="Verschieben">
                <button class="f1cal-arrow" type="button" data-move="up" title="Nach oben">↑</button>
                <button class="f1cal-arrow" type="button" data-move="down" title="Nach unten">↓</button>
                <button class="f1cal-x" type="button" data-deselect="1" title="Auswahl aufheben">✕</button>
              </div>
            </li>
          <?php $n++; endforeach; ?>
        </ul>

        <input type="hidden" id="f1cal_post_id" value="0">
        <label>Grand Prix</label><input type="text" id="f1cal_gp" placeholder="Land / Stadt">
        <label>Strecke</label><input type="text" id="f1cal_circuit" placeholder="Streckenname">
        <label>Flagge</label><input type="hidden" id="f1cal_flag_file" value="">
        <div class="f1dc-mdd" id="f1cal_dd_flag">
          <button type="button" class="f1dc-mdd__btn" aria-label="Flagge auswählen">
            <span class="f1dc-mdd__left">
              <span class="f1dc-mdd__thumb" data-thumb><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span>
              <span class="f1dc-mdd__label" data-label>Keine Flagge</span>
            </span>
            <span class="f1dc-mdd__chev">▾</span>
          </button>
          <div class="f1dc-mdd__panel" data-panel></div>
        </div>

        <label class="f1cal-label-weekend">Wochenende</label>
        <select id="f1cal_weekend_type"><option value="normal">Klassisches Format</option><option value="sprint">Sprintformat</option></select>
        <label for="f1cal_status">Status</label>
        <select id="f1cal_status">
          <option value="none">Normal</option><option value="next">Next</option>
          <option value="live">Live</option><option value="completed">Abgeschlossen</option><option value="cancelled">Abgesagt</option>
        </select>

        <div class="f1cal-fields-group" id="f1cal_normal_fields">
          <div class="f1cal-fields-title">Klassisches Format</div>
          <label>1. Freies Training</label><div class="f1cal-admin__row"><input type="date" id="f1cal_fp1_date"><input type="time" id="f1cal_fp1_time"></div>
          <label>2. Freies Training</label><div class="f1cal-admin__row"><input type="date" id="f1cal_fp2_date"><input type="time" id="f1cal_fp2_time"></div>
          <label>3. Freies Training</label><div class="f1cal-admin__row"><input type="date" id="f1cal_fp3_date"><input type="time" id="f1cal_fp3_time"></div>
        </div>

        <div class="f1cal-fields-group" id="f1cal_sprint_fields" style="display:none;">
          <div class="f1cal-fields-title">Sprintformat</div>
          <label>1. Freies Training</label><div class="f1cal-admin__row"><input type="date" id="f1cal_fp1_date_s"><input type="time" id="f1cal_fp1_time_s"></div>
          <label>Sprint Qualifying</label><div class="f1cal-admin__row"><input type="date" id="f1cal_sq_date"><input type="time" id="f1cal_sq_time"></div>
          <label>Sprint</label><div class="f1cal-admin__row"><input type="date" id="f1cal_sprint_date"><input type="time" id="f1cal_sprint_time"></div>
        </div>

        <div class="f1cal-fields-group">
          <div class="f1cal-fields-title">Qualifying & Rennen</div>
          <label>Das Qualifying</label><div class="f1cal-admin__row"><input type="date" id="f1cal_quali_date"><input type="time" id="f1cal_quali_time"></div>
          <label>Das Rennen (Pflicht)</label><div class="f1cal-admin__row"><input type="date" id="f1cal_race_date"><input type="time" id="f1cal_race_time"></div>
        </div>

        <div class="f1cal-admin__btns">
          <button class="primary" id="f1cal_save" type="button">Speichern</button>
          <button class="danger" id="f1cal_delete" type="button" disabled>Löschen</button>
        </div>
        <div class="f1cal-admin__msg" id="f1cal_msg"></div>
      </div>
    </aside>
  </div>
  <script>
    (function(){
      const ajaxUrl="<?php echo esc_js(admin_url('admin-ajax.php')); ?>";
      const nonce="<?php echo esc_js($nonce); ?>";
      const MEDIA=<?php echo wp_json_encode($media); ?>;
      const list=document.getElementById('f1calList'); const msg=document.getElementById('f1cal_msg');
      const postIdEl=document.getElementById('f1cal_post_id');
      const gpEl=document.getElementById('f1cal_gp'); const cirEl=document.getElementById('f1cal_circuit');
      const wtEl=document.getElementById('f1cal_weekend_type'); const stEl=document.getElementById('f1cal_status');
      const flagFileEl=document.getElementById('f1cal_flag_file'); const ddFlagWrap=document.getElementById('f1cal_dd_flag');
      let ddFlag=null;
      const fp1d=document.getElementById('f1cal_fp1_date'); const fp1t=document.getElementById('f1cal_fp1_time');
      const fp2d=document.getElementById('f1cal_fp2_date'); const fp2t=document.getElementById('f1cal_fp2_time');
      const fp3d=document.getElementById('f1cal_fp3_date'); const fp3t=document.getElementById('f1cal_fp3_time');
      const fp1dS=document.getElementById('f1cal_fp1_date_s'); const fp1tS=document.getElementById('f1cal_fp1_time_s');
      const sqd=document.getElementById('f1cal_sq_date'); const sqt=document.getElementById('f1cal_sq_time');
      const spd=document.getElementById('f1cal_sprint_date'); const spt=document.getElementById('f1cal_sprint_time');
      const qd=document.getElementById('f1cal_quali_date'); const qt=document.getElementById('f1cal_quali_time');
      const rd=document.getElementById('f1cal_race_date'); const rt=document.getElementById('f1cal_race_time');
      const normalBox=document.getElementById('f1cal_normal_fields'); const sprintBox=document.getElementById('f1cal_sprint_fields');
      const btnSave=document.getElementById('f1cal_save'); const btnDel=document.getElementById('f1cal_delete');

      function setMsg(t){ if(msg)msg.textContent=t||""; }
      function setDeleteEnabled(e){ if(btnDel){ btnDel.disabled=!e; btnDel.style.display=e?'block':'none'; } }
      function syncFp1BetweenModes(){
        if(!wtEl)return;
        if(wtEl.value==='sprint'){ fp1dS.value=fp1d.value; fp1tS.value=fp1t.value; }else{ fp1d.value=fp1dS.value; fp1t.value=fp1tS.value; }
      }
      function toggleWeekendFields(){
        syncFp1BetweenModes();
        const s=(wtEl&&wtEl.value==='sprint');
        if(normalBox)normalBox.style.display=s?'none':'block';
        if(sprintBox)sprintBox.style.display=s?'block':'none';
      }
      if(wtEl)wtEl.addEventListener('change',toggleWeekendFields);
      function syncStatusOptionLock(){
        if(!stEl)return;
        const l=stEl.querySelector('option[value="live"]'); const n=stEl.querySelector('option[value="next"]');
        if(!l||!n)return; l.disabled=false; n.disabled=false;
        if(stEl.value==='live') n.disabled=true; else if(stEl.value==='next') l.disabled=true;
      }
      if(stEl)stEl.addEventListener('change',syncStatusOptionLock);

      function clearFormForNew(){
        if(postIdEl)postIdEl.value="0"; if(gpEl)gpEl.value=""; if(cirEl)cirEl.value="";
        if(wtEl)wtEl.value="normal"; if(stEl)stEl.value="none";
        if(flagFileEl)flagFileEl.value=""; if(ddFlag)ddFlag.setSelected("");
        fp1d.value="";fp1t.value=""; fp2d.value="";fp2t.value=""; fp3d.value="";fp3t.value="";
        fp1dS.value="";fp1tS.value=""; sqd.value="";sqt.value=""; spd.value="";spt.value="";
        qd.value="";qt.value=""; rd.value="";rt.value="";
        const a=document.querySelectorAll('.f1cal-admin__item.is-active'); for(let i=0;i<a.length;i++)a[i].classList.remove('is-active');
        toggleWeekendFields(); syncStatusOptionLock(); setDeleteEnabled(false); setMsg("Neuer Eintrag.");
      }

      function fillFromItem(item){
        if(!item)return;
        postIdEl.value=item.dataset.id||"0"; gpEl.value=item.dataset.gp||""; cirEl.value=item.dataset.circuit||"";
        wtEl.value=item.dataset.weekend_type||"normal"; stEl.value=item.dataset.status||"none";
        flagFileEl.value=item.dataset.flag_file||""; if(ddFlag)ddFlag.setSelected(item.dataset.flag_file||"");
        fp1d.value=item.dataset.fp1_date||""; fp1t.value=item.dataset.fp1_time||""; fp1dS.value=item.dataset.fp1_date||""; fp1tS.value=item.dataset.fp1_time||"";
        fp2d.value=item.dataset.fp2_date||""; fp2t.value=item.dataset.fp2_time||"";
        fp3d.value=item.dataset.fp3_date||""; fp3t.value=item.dataset.fp3_time||"";
        sqd.value=item.dataset.sq_date||""; sqt.value=item.dataset.sq_time||"";
        spd.value=item.dataset.sprint_date||""; spt.value=item.dataset.sprint_time||"";
        qd.value=item.dataset.quali_date||""; qt.value=item.dataset.quali_time||"";
        rd.value=item.dataset.race_date||""; rt.value=item.dataset.race_time||"";
        toggleWeekendFields(); syncStatusOptionLock(); setDeleteEnabled(true); setMsg("Bearbeite: "+(gpEl.value||"Eintrag"));
      }

      function setActiveItem(item){
        const a=document.querySelectorAll('.f1cal-admin__item.is-active'); for(let i=0;i<a.length;i++)a[i].classList.remove('is-active');
        if(item)item.classList.add('is-active');
      }

      function postForm(a,d){
        const fd=new FormData(); fd.append('action',a); fd.append('nonce',nonce);
        for(const k in d)fd.append(k,d[k]);
        return fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json());
      }

      function buildMediaDropdown(w,h,ops,cfg){
        const btn=w.querySelector('.f1dc-mdd__btn'); const pan=w.querySelector('[data-panel]');
        const th=w.querySelector('[data-thumb]'); const lb=w.querySelector('[data-label]');
        function setSelected(v){
          h.value=v||'';
          if(!v){ th.innerHTML='<span style="font-weight:900;font-size:12px;opacity:.6;">—</span>'; lb.textContent=(cfg&&cfg.emptyLabel)||'Keine Auswahl'; return; }
          const f=(ops||[]).find(o=>String(o.value)===String(v));
          if(!f){ th.innerHTML='?'; lb.textContent=String(v); return; }
          th.innerHTML='<img src="'+f.url+'" alt="" loading="lazy">'; lb.textContent=f.label||String(v);
        }
        function toggle(o){
          if(typeof o==='boolean'){ if(o)w.classList.add('is-open'); else w.classList.remove('is-open'); }
          else w.classList.toggle('is-open');
        }
        function renderList(q){
          pan.innerHTML=''; q=String(q||'').trim().toLowerCase();
          const sw=document.createElement('div'); sw.className='f1dc-mdd__searchwrap';
          const si=document.createElement('input'); si.className='f1dc-mdd__search'; si.placeholder='Suchen…'; si.value=q;
          sw.appendChild(si); pan.appendChild(sw);
          const none=document.createElement('div'); none.className='f1dc-mdd__item';
          none.innerHTML='<span class="f1dc-mdd__thumb"><span style="font-weight:900;font-size:12px;opacity:.6;">—</span></span><span class="f1dc-mdd__label">Keine</span>';
          none.addEventListener('click',function(){ setSelected(''); toggle(false); }); pan.appendChild(none);
          let shown=0;
          for(let i=0;i<(ops||[]).length;i++){
            const o=ops[i]; const hay=(String(o.label||'')+' '+String(o.value||'')).toLowerCase();
            if(q&&hay.indexOf(q)===-1)continue;
            const it=document.createElement('div'); it.className='f1dc-mdd__item';
            it.innerHTML='<span class="f1dc-mdd__thumb"><img src="'+o.url+'" alt="" loading="lazy"></span><span class="f1dc-mdd__label">'+(o.label||String(o.value||''))+'</span>';
            it.addEventListener('click',function(){ setSelected(String(o.value||'')); toggle(false); });
            pan.appendChild(it); shown++;
          }
          if(shown===0){ const no=document.createElement('div'); no.className='f1dc-mdd__item'; no.style.opacity='.75'; no.textContent='Keine Treffer'; pan.appendChild(no); }
          setTimeout(function(){ try{ si.focus(); }catch(e){} },0);
          si.addEventListener('input',function(){ renderList(si.value); });
        }
        btn.addEventListener('click',function(e){ e.preventDefault(); const wo=!w.classList.contains('is-open'); toggle(wo); if(wo)renderList(''); });
        document.addEventListener('click',function(e){ if(!w.contains(e.target))toggle(false); });
        setSelected(h.value); return {setSelected};
      }

      if(ddFlagWrap&&flagFileEl) ddFlag=buildMediaDropdown(ddFlagWrap,flagFileEl,(MEDIA&&MEDIA.flags)?MEDIA.flags:[],{emptyLabel:"Keine Flagge"});

      if(list)list.addEventListener('click',function(e){
        if(e.target.closest('.f1cal-arrow')||e.target.closest('.f1cal-x'))return;
        const it=e.target.closest('.f1cal-admin__item'); if(it){ setActiveItem(it); fillFromItem(it); }
      });
      if(list)list.addEventListener('click',function(e){
        const arr=e.target.closest('.f1cal-arrow'); if(!arr)return;
        e.preventDefault(); e.stopPropagation();
        const it=arr.closest('.f1cal-admin__item'); if(!it)return;
        setMsg("Verschiebe…");
        postForm('f1cal_move',{post_id:it.dataset.id, dir:arr.dataset.move}).then(r=>{
          if(!r||!r.success){ setMsg((r&&r.data&&r.data.message)||'Fehler'); return; }
          setMsg("Verschoben. Reload…"); window.location.reload();
        });
      });
      if(list)list.addEventListener('click',function(e){
        if(e.target.closest('.f1cal-x')){ e.preventDefault(); e.stopPropagation(); clearFormForNew(); setMsg("Auswahl aufgehoben."); }
      });
      if(btnSave)btnSave.addEventListener('click',function(){
        setMsg("Speichere…");
        const s=(wtEl.value==='sprint');
        const pl={
          post_id:postIdEl.value, gp:gpEl.value.trim(), circuit:cirEl.value.trim(),
          flag_file:(flagFileEl?flagFileEl.value:""), flag_id:"0",
          status:stEl.value, weekend_type:wtEl.value,
          fp1_date:(s?fp1dS.value:fp1d.value), fp1_time:(s?fp1tS.value:fp1t.value),
          fp2_date:(s?"":fp2d.value), fp2_time:(s?"":fp2t.value),
          fp3_date:(s?"":fp3d.value), fp3_time:(s?"":fp3t.value),
          sq_date:(s?sqd.value:""), sq_time:(s?sqt.value:""),
          sprint_date:(s?spd.value:""), sprint_time:(s?spt.value:""),
          quali_date:qd.value, quali_time:qt.value, race_date:rd.value, race_time:rt.value
        };
        postForm('f1cal_save',pl).then(r=>{
          if(!r||!r.success){ setMsg((r&&r.data&&r.data.message)||'Fehler'); return; }
          setMsg("Gespeichert. Reload…"); window.location.reload();
        });
      });
      if(btnDel)btnDel.addEventListener('click',function(){
        const pid=parseInt(postIdEl.value,10);
        if(!pid||btnDel.disabled||!confirm("Wirklich löschen?"))return;
        setMsg("Lösche…");
        postForm('f1cal_delete',{post_id:String(pid)}).then(r=>{
          if(!r||!r.success){ setMsg((r&&r.data&&r.data.message)||'Fehler'); return; }
          setMsg("Gelöscht. Reload…"); window.location.reload();
        });
      });

      toggleWeekendFields(); clearFormForNew(); syncStatusOptionLock();
    })();
  </script>
