<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Admin Panel View for F1 Teams
 * Expects: $teams, $media, $nonce
 * $this refers to F1_Teams instance
 */
?>
<div class="wrap f1team-wrap-admin">
    <h1 style="margin-bottom:6px;">F1 Teams – Admin Panel</h1>
    <div style="font-weight:800; opacity:.75; margin:0 0 14px;">
      Klick auf Eintrag "=" bearbeiten. Mit "↑ ↓" verschiebst du den ausgewählten Eintrag. Mit "✕" abwählen. Mit "✎" öffnest du den Gutenberg Editor.
    </div>

    <style>
      .f1team-wrap-admin{
        --ui-bg: #f2f3f5;
        --ui-panel: #ffffff;
        --ui-line: rgba(0,0,0,.08);

        --accent:#E00078;
        --head:#202020;

        --green: #1f9d55;
        --green2: #178247;
        --red: #d83a3a;
        --red2: #b92f2f;

        font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        color: #111;
      }
      .f1team-wrap-admin *{ box-sizing:border-box; }

      .f1team-admin{
        margin-top: 14px;
        background: var(--ui-bg);
        border: 1px solid var(--ui-line);
        border-radius: 0;
        box-shadow:0 10px 26px rgba(0,0,0,.06);
        padding: 14px;
        width: 100%;
        max-width: 980px;
      }
      .f1team-admin__surface{
        background: var(--ui-panel);
        border: 1px solid var(--ui-line);
        padding: 14px;
        position:relative;
        overflow:hidden;
      }
      .f1team-admin__surface::before{
        content:"";
        position:absolute; top:0; left:0; right:0;
        height:10px;
        background: var(--head);
      }
      .f1team-admin__surface::after{
        content:"";
        position:absolute; top:6px; left:0; right:0;
        height:4px;
        background: var(--accent);
      }
      .f1team-admin h3{
        margin: 18px 0 10px;
        font-size: 16px;
        font-weight: 900;
        letter-spacing: .02em;
        text-transform: uppercase;
      }
      .f1team-admin__hint{
        font-size: 12px;
        color: rgba(17,17,17,.65);
        font-weight: 800;
        margin-bottom: 10px;
      }

      .f1team-admin__list{
        list-style:none;
        padding:0;
        margin:0 0 12px;
        max-height:320px;
        overflow:auto;
        border:1px solid rgba(0,0,0,.10);
        background:#fff;
      }
      .f1team-admin__item{
        padding:10px 10px;
        border-bottom:1px solid rgba(0,0,0,.06);
        display:flex;
        gap:10px;
        align-items:center;
        cursor:pointer;
        user-select:none;
        background:#fff;
      }
      .f1team-admin__item:last-child{ border-bottom:0; }
      .f1team-admin__item.is-active{
        background: rgba(224,0,120,.10);
        outline: 2px solid rgba(224,0,120,.25);
        outline-offset: -2px;
      }

      .f1team-admin__nr{
        width:26px;
        font-weight:900;
        color: rgba(17,17,17,.65);
        text-align:right;
        flex:0 0 auto;
      }
      .f1team-admin__meta{ flex:1; min-width:0; }
      .f1team-admin__title{
        font-weight:900;
        font-size:13px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }
      .f1team-admin__sub{
        font-size:12px;
        font-weight:800;
        color: rgba(17,17,17,.65);
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }

      .f1team-admin__arrows{
        display:none;
        flex:0 0 auto;
        gap:6px;
        align-items:center;
        justify-content:center;
      }
      .f1team-admin__item.is-active .f1team-admin__arrows{ display:flex; }

      .f1team-arrow, .f1team-x, .f1team-edit{
        width:30px;
        height:30px;
        border:0;
        border-radius:0;
        font-weight:900;
        cursor:pointer;
        padding:0;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        line-height:1;
        color:#fff;
      }
      .f1team-arrow{ background: rgba(0,0,0,.55); }
      .f1team-edit{ background: #202020; }
      .f1team-x{ background: var(--red); }
      .f1team-arrow:active, .f1team-x:active, .f1team-edit:active{ transform: translateY(1px); }

      .f1team-admin label{
        display:block;
        font-size:12px;
        font-weight:900;
        margin:10px 0 4px;
        letter-spacing:.02em;
        text-transform: uppercase;
      }

      .f1team-admin input[type="text"],
      .f1team-admin input[type="url"],
      .f1team-admin textarea{
        width:100%;
        padding:10px 10px;
        border:1px solid rgba(0,0,0,.12);
        border-radius:0;
        font-size:13px;
        line-height:1.2;
        font-weight:750;
        outline:none;
        background:#f8f9fb;
        appearance:none;
        -webkit-appearance:none;
      }
      .f1team-admin input[type="text"],
      .f1team-admin input[type="url"]{
        height:44px;
      }
      .f1team-admin textarea{
        min-height:150px;
        resize:vertical;
        line-height:1.5;
      }

      .f1team-admin__row2{
        display:grid;
        grid-template-columns: 1fr 1fr;
        gap:10px;
      }
      @media (max-width: 900px){
        .f1team-admin__row2{ grid-template-columns:1fr; }
      }

      .f1team-admin__divider{
        margin-top:12px;
        padding-top:10px;
        border-top:1px solid rgba(0,0,0,.08);
      }
      .f1team-admin__sectiontitle{
        font-size:12px;
        font-weight:900;
        letter-spacing:.06em;
        text-transform:uppercase;
        color: rgba(17,17,17,.65);
        margin:0 0 8px;
      }

      .f1team-admin__btns{
        display:flex;
        gap:10px;
        margin-top:12px;
        flex-wrap:wrap;
      }
      .f1team-admin__btns button{
        border:0;
        border-radius:0;
        padding:11px 12px;
        font-weight:900;
        cursor:pointer;
        width:100%;
        color:#fff;
      }
      .f1team-admin__btns button.primary{ background: var(--green); }
      .f1team-admin__btns button.primary:hover{ background: var(--green2); }
      .f1team-admin__btns button.danger{ background: var(--red); }
      .f1team-admin__btns button.danger:hover{ background: var(--red2); }

      .f1team-admin__msg{
        margin-top:10px;
        font-size:12px;
        font-weight:900;
        color: rgba(17,17,17,.75);
        min-height:16px;
      }

      /* Dropdown */
      .f1team-mdd{ position:relative; width:100%; }
      .f1team-mdd__btn{
        width:100%;
        height:44px;
        border:1px solid rgba(0,0,0,.12);
        background:#f8f9fb;
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding:8px 10px;
        cursor:pointer;
        font-weight:900;
        letter-spacing:.02em;
        gap:10px;
      }
      .f1team-mdd__left{ display:flex; align-items:center; gap:10px; min-width:0; }
      .f1team-mdd__thumb{
        width:28px; height:28px;
        display:flex; align-items:center; justify-content:center;
        background:#fff;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,.15);
        flex:0 0 auto;
        overflow:hidden;
      }
      .f1team-mdd__thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
      .f1team-mdd__label{
        font-size:13px;
        color: rgba(17,17,17,.92);
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        max-width: 75%;
      }
      .f1team-mdd__chev{ opacity:.65; flex:0 0 auto; }

      .f1team-mdd__panel{
        position:absolute;
        left:0; right:0;
        top: calc(44px + 6px);
        border:1px solid rgba(0,0,0,.12);
        background:#fff;
        z-index:9999;
        max-height: 320px;
        overflow:auto;
        box-shadow: 0 10px 26px rgba(0,0,0,.12);
        display:none;
      }
      .f1team-mdd.is-open .f1team-mdd__panel{ display:block; }

      .f1team-mdd__searchwrap{
        position: sticky;
        top: 0;
        background: #fff;
        padding: 8px;
        border-bottom: 1px solid rgba(0,0,0,.08);
        z-index: 1;
      }
      .f1team-mdd__search{
        width:100%;
        height:40px;
        border:1px solid rgba(0,0,0,.12);
        background:#f8f9fb;
        padding:8px 10px;
        font-weight:900;
        border-radius:0;
        outline:none;
      }

      .f1team-mdd__item{
        display:flex;
        align-items:center;
        gap:10px;
        padding:8px 10px;
        cursor:pointer;
        border-bottom:1px solid rgba(0,0,0,.06);
        font-weight:900;
      }
      .f1team-mdd__item:last-child{ border-bottom:0; }
      .f1team-mdd__item:hover{ background: rgba(0,0,0,.04); }
    </style>

    <div class="f1team-admin" id="f1teamPanel">
      <div class="f1team-admin__surface">
        <h3>Teams verwalten</h3>
        <div class="f1team-admin__hint">
          Klick auf Eintrag "=" bearbeiten. Mit "↑ ↓" verschiebst du den ausgewählten Eintrag. Mit "✕" abwählen. Mit "✎" Gutenberg öffnen.
        </div>

        <ul class="f1team-admin__list" id="f1teamList">
          <?php
          $n = 1;
          foreach ($teams as $t) :
            $pid = (int)$t->ID;
            $m = $this->get_meta($pid);

            $title = get_the_title($pid);
            $sub = get_permalink($pid);
            $edit = get_edit_post_link($pid, ''); // ✅ Gutenberg/Edit-Link
            ?>
            <li class="f1team-admin__item"
              data-id="<?php echo esc_attr((string)$pid); ?>"
              data-editlink="<?php echo esc_attr((string)$edit); ?>"
              data-name="<?php echo esc_attr($title); ?>"
              data-slug="<?php echo esc_attr($m['slug']); ?>"

              data-teamlogo="<?php echo esc_attr($m['teamlogo']); ?>"
              data-carimg="<?php echo esc_attr($m['carimg']); ?>"
              data-flag="<?php echo esc_attr($m['flag']); ?>"

              data-nationality="<?php echo esc_attr($m['nationality']); ?>"
              data-entry_year="<?php echo esc_attr($m['entry_year']); ?>"
              data-teamchief="<?php echo esc_attr($m['teamchief']); ?>"
              data-base="<?php echo esc_attr($m['base']); ?>"
              data-chassis="<?php echo esc_attr($m['chassis']); ?>"
              data-powerunit="<?php echo esc_attr($m['powerunit']); ?>"

              data-fb="<?php echo esc_attr($m['fb']); ?>"
              data-x="<?php echo esc_attr($m['x']); ?>"
              data-ig="<?php echo esc_attr($m['ig']); ?>"

              data-teamcolor="<?php echo esc_attr($m['teamcolor']); ?>"

              data-bio="<?php echo esc_attr($m['bio']); ?>"
            >
              <div class="f1team-admin__nr"><?php echo esc_html($n.'.'); ?></div>
              <div class="f1team-admin__meta">
                <div class="f1team-admin__title"><?php echo esc_html($title); ?></div>
                <div class="f1team-admin__sub"><?php echo esc_html($sub); ?></div>
              </div>
              <div class="f1team-admin__arrows" aria-label="Verschieben">
                <button class="f1team-arrow" type="button" data-move="up" title="Nach oben">↑</button>
                <button class="f1team-arrow" type="button" data-move="down" title="Nach unten">↓</button>
                <button class="f1team-edit" type="button" data-edit="1" title="Gutenberg öffnen">✎</button>
                <button class="f1team-x" type="button" data-deselect="1" title="Auswahl aufheben">✕</button>
              </div>
            </li>
            <?php
            $n++;
          endforeach;
          ?>
        </ul>

        <input type="hidden" id="f1team_post_id" value="0">

        <div class="f1team-admin__divider">
          <div class="f1team-admin__sectiontitle">Basis</div>

          <label>Name</label>
          <input type="text" id="f1team_name" placeholder="Teamname">

          <label>URL-Slug (optional)</label>
          <input type="text" id="f1team_slug" placeholder="teamname">

          <label>Teamfarbe (HEX / Picker)</label>
          <div style="display:flex; gap:10px; align-items:center;">
            <input type="color" id="f1team_teamcolor_picker" value="#E00078" style="width:54px; height:44px; padding:0; border:1px solid rgba(0,0,0,.12); background:#fff;">
            <input type="text" id="f1team_teamcolor" placeholder="#E00078" style="height:44px; flex:1;">
          </div>
          <div style="margin-top:6px; font-weight:900; font-size:12px; opacity:.75;">
            Erlaubt: #RRGGBB (z.B. #FF8700). Leer = keine Teamfarbe.
          </div>

          <div class="f1team-admin__row2">
            <div>
              <label>Team-Logo (PNG aus /uploads/teams/)</label>
              <input type="hidden" id="f1team_teamlogo" value="">
              <div class="f1team-mdd" id="f1team_dd_teamlogo">
                <button type="button" class="f1team-mdd__btn" aria-label="Team-Logo auswählen">
                  <span class="f1team-mdd__left">
                    <span class="f1team-mdd__thumb" data-thumb><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span>
                    <span class="f1team-mdd__label" data-label>Kein Logo</span>
                  </span>
                  <span class="f1team-mdd__chev">▾</span>
                </button>
                <div class="f1team-mdd__panel" data-panel></div>
              </div>
            </div>

            <div>
              <label>Auto-Bild (PNG aus /uploads/cars/)</label>
              <input type="hidden" id="f1team_carimg" value="">
              <div class="f1team-mdd" id="f1team_dd_carimg">
                <button type="button" class="f1team-mdd__btn" aria-label="Auto-Bild auswählen">
                  <span class="f1team-mdd__left">
                    <span class="f1team-mdd__thumb" data-thumb><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span>
                    <span class="f1team-mdd__label" data-label>Kein Auto-Bild</span>
                  </span>
                  <span class="f1team-mdd__chev">▾</span>
                </button>
                <div class="f1team-mdd__panel" data-panel></div>
              </div>
            </div>
          </div>

          <div class="f1team-admin__row2">
            <div>
              <label>Flagge (Nationalität)</label>
              <input type="hidden" id="f1team_flag" value="">
              <div class="f1team-mdd" id="f1team_dd_flag">
                <button type="button" class="f1team-mdd__btn" aria-label="Flagge auswählen">
                  <span class="f1team-mdd__left">
                    <span class="f1team-mdd__thumb" data-thumb><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span>
                    <span class="f1team-mdd__label" data-label>Keine Flagge</span>
                  </span>
                  <span class="f1team-mdd__chev">▾</span>
                </button>
                <div class="f1team-mdd__panel" data-panel></div>
              </div>
            </div>

            <div>
              <label>Nationalität (Text)</label>
              <input type="text" id="f1team_nationality" placeholder="Land">
            </div>
          </div>

          <div class="f1team-admin__row2">
            <div>
              <label>Eintrittsjahr</label>
              <input type="text" id="f1team_entry_year" placeholder="XXXX">
            </div>
            <div>
              <label>Teamchef</label>
              <input type="text" id="f1team_teamchief" placeholder="Vorname Nachname">
            </div>
          </div>

          <label>Basis-Standort</label>
          <input type="text" id="f1team_base" placeholder="Stadt (Land)">

          <div class="f1team-admin__row2">
            <div>
              <label>Chassis</label>
              <input type="text" id="f1team_chassis" placeholder="Chassisname">
            </div>
            <div>
              <label>Power Unit</label>
              <input type="text" id="f1team_powerunit" placeholder="Power Unit">
            </div>
          </div>

          <div style="margin-top:10px; font-weight:900; font-size:12px; opacity:.75;">
            ✅ Gutenberg-Inhalte (z.B. Galerie) bearbeitest du über den ✎ Button am Eintrag (öffnet Editor).
          </div>
        </div>

        <div class="f1team-admin__divider">
          <div class="f1team-admin__sectiontitle">Socials</div>

          <label>Facebook</label>
          <input type="url" id="f1team_fb" placeholder="https://facebook.com/...">

          <label>X / Twitter</label>
          <input type="url" id="f1team_x" placeholder="https://x.com/...">

          <label>Instagram</label>
          <input type="url" id="f1team_ig" placeholder="https://instagram.com/...">
        </div>

        <div class="f1team-admin__divider">
          <div class="f1team-admin__sectiontitle">Biographie (ca. 1500 Zeichen)</div>
          <textarea id="f1team_bio" placeholder="Kurzer Text über das Team…"></textarea>
          <div style="margin-top:6px; font-weight:900; font-size:12px; opacity:.75;">
            Tipp: Du kannst Absätze machen. Links sind erlaubt.
          </div>
        </div>

        <div class="f1team-admin__btns">
          <div style="width:100%;"><button class="primary" id="f1team_save" type="button">Speichern</button></div>
          <div style="width:100%;"><button class="danger" id="f1team_delete" type="button">Löschen</button></div>
        </div>

        <div class="f1team-admin__msg" id="f1team_msg"></div>
      </div>
    </div>

    <script>
      (function(){
        const ajaxUrl = "<?php echo esc_js(admin_url('admin-ajax.php')); ?>";
        const nonce   = "<?php echo esc_js($nonce); ?>";
        const MEDIA   = <?php echo wp_json_encode($media); ?>;

        const msg   = document.getElementById('f1team_msg');
        const list  = document.getElementById('f1teamList');

        const postIdEl = document.getElementById('f1team_post_id');

        const nameEl = document.getElementById('f1team_name');
        const slugEl = document.getElementById('f1team_slug');

        const teamlogoEl = document.getElementById('f1team_teamlogo');
        const carimgEl   = document.getElementById('f1team_carimg');
        const flagEl     = document.getElementById('f1team_flag');

        const natEl      = document.getElementById('f1team_nationality');
        const entryEl    = document.getElementById('f1team_entry_year');
        const chiefEl    = document.getElementById('f1team_teamchief');
        const baseEl     = document.getElementById('f1team_base');
        const chassisEl  = document.getElementById('f1team_chassis');
        const puEl       = document.getElementById('f1team_powerunit');

        const fbEl = document.getElementById('f1team_fb');
        const xEl  = document.getElementById('f1team_x');
        const igEl = document.getElementById('f1team_ig');

        const teamColorEl = document.getElementById('f1team_teamcolor');
        const teamColorPickerEl = document.getElementById('f1team_teamcolor_picker');

        const bioEl = document.getElementById('f1team_bio');

        const btnSave = document.getElementById('f1team_save');
        const btnDel  = document.getElementById('f1team_delete');

        function setMsg(t){ if (msg) msg.textContent = t || ""; }

        function setActiveItem(item){
          const actives = document.querySelectorAll('.f1team-admin__item.is-active');
          for (let i=0;i<actives.length;i++) actives[i].classList.remove('is-active');
          if (item) item.classList.add('is-active');
        }

        function postForm(action, dataObj){
          const fd = new FormData();
          fd.append('action', action);
          fd.append('nonce', nonce);
          for (const k in dataObj) fd.append(k, dataObj[k]);
          return fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' }).then(r => r.json());
        }

        function updateButtonVisibility(){
          const hasSelection = (parseInt(postIdEl.value || "0", 10) > 0);
          if (btnSave) btnSave.style.display = '';
          if (btnDel)  btnDel.style.display  = hasSelection ? '' : 'none';
        }

        function normalizeHex(v){
          v = String(v || '').trim();
          if (!v) return '';
          if (v[0] !== '#') v = '#'+v;
          v = v.toUpperCase();
          if (!/^#[0-9A-F]{6}$/.test(v)) return '';
          return v;
        }

        function setTeamColorUI(hex){
          const clean = normalizeHex(hex);
          teamColorEl.value = clean ? clean : (hex ? String(hex).trim() : '');
          if (teamColorPickerEl){
            teamColorPickerEl.value = clean ? clean : '#E00078';
          }
        }

        if (teamColorPickerEl){
          teamColorPickerEl.addEventListener('input', function(){
            setTeamColorUI(teamColorPickerEl.value);
          });
        }
        if (teamColorEl){
          teamColorEl.addEventListener('input', function(){
            const clean = normalizeHex(teamColorEl.value);
            if (clean) teamColorPickerEl.value = clean;
          });
        }

        function buildMediaDropdown(wrapperEl, hiddenInputEl, options, cfg){
          const btn = wrapperEl.querySelector('.f1team-mdd__btn');
          const panel = wrapperEl.querySelector('[data-panel]');
          const thumb = wrapperEl.querySelector('[data-thumb]');
          const label = wrapperEl.querySelector('[data-label]');

          const emptyLabel = (cfg && cfg.emptyLabel) ? cfg.emptyLabel : 'Keine Auswahl';

          function setSelected(value){
            hiddenInputEl.value = value || '';

            if (!value) {
              thumb.innerHTML = '<span style="font-weight:900; font-size:12px; opacity:.6;">—</span>';
              label.textContent = emptyLabel;
              return;
            }

            const found = (options || []).find(o => String(o.value) === String(value));
            if (!found) {
              thumb.innerHTML = '<span style="font-weight:900; font-size:12px; opacity:.6;">?</span>';
              label.textContent = String(value);
              return;
            }

            thumb.innerHTML = '<img src="'+found.url+'" alt="'+(found.label||'')+'" loading="lazy">';
            label.textContent = found.label || String(value);
          }

          function toggle(open){
            const isOpen = wrapperEl.classList.contains('is-open');
            const next = (typeof open === 'boolean') ? open : !isOpen;
            if (next) wrapperEl.classList.add('is-open');
            else wrapperEl.classList.remove('is-open');
          }

          function renderList(filterText){
            const q = String(filterText || '').trim().toLowerCase();
            const items = Array.isArray(options) ? options : [];

            panel.innerHTML = '';

            const sw = document.createElement('div');
            sw.className = 'f1team-mdd__searchwrap';
            const si = document.createElement('input');
            si.className = 'f1team-mdd__search';
            si.type = 'text';
            si.placeholder = 'Suchen…';
            si.value = String(filterText || '');
            sw.appendChild(si);
            panel.appendChild(sw);

            const none = document.createElement('div');
            none.className = 'f1team-mdd__item';
            none.innerHTML = '<span class="f1team-mdd__thumb"><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span><span class="f1team-mdd__label">Keine</span>';
            none.addEventListener('click', function(){
              setSelected('');
              toggle(false);
            });
            panel.appendChild(none);

            let shown = 0;
            for (let i=0;i<items.length;i++){
              const o = items[i];
              const hay = (String(o.label||'') + ' ' + String(o.value||'')).toLowerCase();
              if (q && hay.indexOf(q) === -1) continue;

              const item = document.createElement('div');
              item.className = 'f1team-mdd__item';
              item.innerHTML =
                '<span class="f1team-mdd__thumb"><img src="'+o.url+'" alt="'+(o.label||'')+'" loading="lazy"></span>' +
                '<span class="f1team-mdd__label">'+(o.label||String(o.value||''))+'</span>';

              item.addEventListener('click', function(){
                setSelected(String(o.value||''));
                toggle(false);
              });

              panel.appendChild(item);
              shown++;
            }

            if (shown === 0) {
              const no = document.createElement('div');
              no.className = 'f1team-mdd__item';
              no.style.opacity = '0.75';
              no.textContent = 'Keine Treffer';
              panel.appendChild(no);
            }

            setTimeout(function(){ try{ si.focus(); }catch(e){} }, 0);
            si.addEventListener('input', function(){ renderList(si.value); });
          }

          btn.addEventListener('click', function(e){
            e.preventDefault();
            const willOpen = !wrapperEl.classList.contains('is-open');
            toggle(willOpen);
            if (willOpen) renderList('');
          });

          document.addEventListener('click', function(e){
            if (!wrapperEl.contains(e.target)) toggle(false);
          });

          setSelected(hiddenInputEl.value);
          return { setSelected };
        }

        const ddLogo = buildMediaDropdown(
          document.getElementById('f1team_dd_teamlogo'),
          teamlogoEl,
          MEDIA.teamlogos || [],
          { emptyLabel: 'Kein Logo' }
        );

        const ddCar = buildMediaDropdown(
          document.getElementById('f1team_dd_carimg'),
          carimgEl,
          MEDIA.cars || [],
          { emptyLabel: 'Kein Auto-Bild' }
        );

        const ddFlag = buildMediaDropdown(
          document.getElementById('f1team_dd_flag'),
          flagEl,
          MEDIA.flags || [],
          { emptyLabel: 'Keine Flagge' }
        );

        function clearFormForNew(){
          postIdEl.value = "0";
          nameEl.value = "";
          slugEl.value = "";

          teamlogoEl.value = ""; ddLogo.setSelected("");
          carimgEl.value   = ""; ddCar.setSelected("");
          flagEl.value     = ""; ddFlag.setSelected("");

          natEl.value = "";
          entryEl.value = "";
          chiefEl.value = "";
          baseEl.value = "";
          chassisEl.value = "";
          puEl.value = "";

          fbEl.value = "";
          xEl.value  = "";
          igEl.value = "";

          setTeamColorUI('');
          bioEl.value = "";

          const actives = document.querySelectorAll('.f1team-admin__item.is-active');
          for (let i=0;i<actives.length;i++) actives[i].classList.remove('is-active');

          updateButtonVisibility();
          setMsg("Neues Team (Speichern legt an).");
        }

        function fillFromItem(item){
          postIdEl.value = item.dataset.id || "0";
          nameEl.value   = item.dataset.name || "";
          slugEl.value   = item.dataset.slug || "";

          teamlogoEl.value = item.dataset.teamlogo || ""; ddLogo.setSelected(teamlogoEl.value);
          carimgEl.value   = item.dataset.carimg || "";   ddCar.setSelected(carimgEl.value);
          flagEl.value     = item.dataset.flag || "";     ddFlag.setSelected(flagEl.value);

          natEl.value     = item.dataset.nationality || "";
          entryEl.value   = item.dataset.entry_year || "";
          chiefEl.value   = item.dataset.teamchief || "";
          baseEl.value    = item.dataset.base || "";
          chassisEl.value = item.dataset.chassis || "";
          puEl.value      = item.dataset.powerunit || "";

          fbEl.value = item.dataset.fb || "";
          xEl.value  = item.dataset.x || "";
          igEl.value = item.dataset.ig || "";

          setTeamColorUI(item.dataset.teamcolor || "");
          bioEl.value = item.dataset.bio || "";

          updateButtonVisibility();
          setMsg("Bearbeite: " + (nameEl.value || "Team"));
        }

        if (list) list.addEventListener('click', function(e){
          const arrowBtn = e.target.closest('.f1team-arrow');
          const xBtn = e.target.closest('.f1team-x');
          const editBtn = e.target.closest('.f1team-edit');
          if (arrowBtn || xBtn || editBtn) return;

          const item = e.target.closest('.f1team-admin__item');
          if (!item) return;

          setActiveItem(item);
          fillFromItem(item);
        });

        if (list) list.addEventListener('click', function(e){
          const arrowBtn = e.target.closest('.f1team-arrow');
          if (!arrowBtn) return;

          e.preventDefault();
          e.stopPropagation();

          const item = arrowBtn.closest('.f1team-admin__item');
          if (!item) return;

          const pid = item.dataset.id;
          const dir = arrowBtn.dataset.move;

          setMsg("Verschiebe…");
          postForm('f1team_move', { post_id: String(pid), dir: dir })
            .then(function(r){
              if (!r || !r.success){
                setMsg((r && r.data && r.data.message) ? r.data.message : "Fehler beim Verschieben.");
                return;
              }
              setMsg("Verschoben. Seite aktualisiert…");
              window.location.reload();
            });
        });

        // ✅ NEU: Gutenberg öffnen (wie bei Fahrern)
        if (list) list.addEventListener('click', function(e){
          const editBtn = e.target.closest('.f1team-edit');
          if (!editBtn) return;

          e.preventDefault();
          e.stopPropagation();

          const item = editBtn.closest('.f1team-admin__item');
          if (!item) return;

          const url = item.dataset.editlink || "";
          if (!url) { setMsg("Kein Editor-Link gefunden."); return; }

          window.open(url, '_blank', 'noopener');
        });

        if (list) list.addEventListener('click', function(e){
          const xBtn = e.target.closest('.f1team-x');
          if (!xBtn) return;

          e.preventDefault();
          e.stopPropagation();

          clearFormForNew();
          setMsg("Auswahl aufgehoben (Speichern legt neu an).");
        });

        btnSave.addEventListener('click', function(){
          setMsg("Speichere…");

          const payload = {
            post_id: postIdEl.value || "0",
            name: nameEl.value.trim(),
            slug: slugEl.value.trim(),

            teamlogo: teamlogoEl.value.trim(),
            carimg:   carimgEl.value.trim(),
            flag:     flagEl.value.trim(),

            nationality: natEl.value.trim(),
            entry_year:  entryEl.value.trim(),
            teamchief:   chiefEl.value.trim(),
            base:        baseEl.value.trim(),
            chassis:     chassisEl.value.trim(),
            powerunit:   puEl.value.trim(),

            fb: fbEl.value.trim(),
            x:  xEl.value.trim(),
            ig: igEl.value.trim(),

            teamcolor: teamColorEl.value.trim(),
            bio: bioEl.value
          };

          postForm('f1team_save', payload).then(function(r){
            if (!r || !r.success){
              setMsg((r && r.data && r.data.message) ? r.data.message : "Fehler beim Speichern.");
              return;
            }
            setMsg("Gespeichert. Seite aktualisiert…");
            window.location.reload();
          });
        });

        btnDel.addEventListener('click', function(){
          const pid = parseInt(postIdEl.value, 10);
          if (!pid) { setMsg("Kein Team ausgewählt."); return; }
          if (!confirm("Wirklich löschen?")) return;

          setMsg("Lösche…");
          postForm('f1team_delete', { post_id: String(pid) }).then(function(r){
            if (!r || !r.success){
              setMsg((r && r.data && r.data.message) ? r.data.message : "Fehler beim Löschen.");
              return;
            }
            setMsg("Gelöscht. Seite aktualisiert…");
            window.location.reload();
          });
        });

        clearFormForNew();
        updateButtonVisibility();
      })();
    </script>
</div>
