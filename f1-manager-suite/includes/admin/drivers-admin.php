<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Admin Panel View for F1 Drivers
 * Expects: $drivers, $teams, $media, $nonce
 * $this refers to F1_Drivers instance
 */
?>
<div class="wrap f1drv-wrap-admin">
    <h1 style="margin-bottom:6px;">F1 Fahrer – Admin Panel</h1>
    <div style="font-weight:800; opacity:.75; margin:0 0 14px;">
      Klick auf Eintrag "=" bearbeiten. Mit "↑ ↓" verschiebst du den ausgewählten Eintrag. Mit "✕" abwählen.
    </div>

    <style>
      .f1drv-wrap-admin{
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
      .f1drv-wrap-admin *{ box-sizing:border-box; }

      .f1drv-admin{
        margin-top: 14px;
        background: var(--ui-bg);
        border: 1px solid var(--ui-line);
        border-radius: 0;
        box-shadow:0 10px 26px rgba(0,0,0,.06);
        padding: 14px;
        width: 100%;
        max-width: 980px;
      }
      .f1drv-admin__surface{
        background: var(--ui-panel);
        border: 1px solid var(--ui-line);
        padding: 14px;
        position:relative;
        overflow:hidden;
      }
      .f1drv-admin__surface::before{
        content:"";
        position:absolute; top:0; left:0; right:0;
        height:10px;
        background: var(--head);
      }
      .f1drv-admin__surface::after{
        content:"";
        position:absolute; top:6px; left:0; right:0;
        height:4px;
        background: var(--accent);
      }
      .f1drv-admin h3{
        margin: 18px 0 10px;
        font-size: 16px;
        font-weight: 900;
        letter-spacing: .02em;
        text-transform: uppercase;
      }
      .f1drv-admin__hint{
        font-size: 12px;
        color: rgba(17,17,17,.65);
        font-weight: 800;
        margin-bottom: 10px;
      }

      .f1drv-admin__list{
        list-style:none;
        padding:0;
        margin:0 0 12px;
        max-height:320px;
        overflow:auto;
        border:1px solid rgba(0,0,0,.10);
        background:#fff;
      }
      .f1drv-admin__item{
        padding:10px 10px;
        border-bottom:1px solid rgba(0,0,0,.06);
        display:flex;
        gap:10px;
        align-items:center;
        cursor:pointer;
        user-select:none;
        background:#fff;
      }
      .f1drv-admin__item:last-child{ border-bottom:0; }
      .f1drv-admin__item.is-active{
        background: rgba(224,0,120,.10);
        outline: 2px solid rgba(224,0,120,.25);
        outline-offset: -2px;
      }

      .f1drv-admin__nr{
        width:26px;
        font-weight:900;
        color: rgba(17,17,17,.65);
        text-align:right;
        flex:0 0 auto;
      }
      .f1drv-admin__meta{ flex:1; min-width:0; }
      .f1drv-admin__title{
        font-weight:900;
        font-size:13px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }
      .f1drv-admin__sub{
        font-size:12px;
        font-weight:800;
        color: rgba(17,17,17,.65);
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
      }

      .f1drv-admin__arrows{
        display:none;
        flex:0 0 auto;
        gap:6px;
        align-items:center;
        justify-content:center;
      }
      .f1drv-admin__item.is-active .f1drv-admin__arrows{ display:flex; }

      .f1drv-arrow, .f1drv-x{
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
      .f1drv-arrow{ background: rgba(0,0,0,.55); }
      .f1drv-x{ background: var(--red); }
      .f1drv-arrow:active, .f1drv-x:active{ transform: translateY(1px); }

      /* ✅ NEU: Editor-Button (✎) */
      .f1drv-edit{
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
        background: var(--accent);
        text-decoration:none;
      }
      .f1drv-edit:active{ transform: translateY(1px); }

      .f1drv-admin label{
        display:block;
        font-size:12px;
        font-weight:900;
        margin:10px 0 4px;
        letter-spacing:.02em;
        text-transform: uppercase;
      }

      .f1drv-admin input[type="text"],
      .f1drv-admin input[type="url"],
      .f1drv-admin select,
      .f1drv-admin textarea{
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
      .f1drv-admin input[type="text"],
      .f1drv-admin input[type="url"],
      .f1drv-admin select{
        height:44px;
      }
      .f1drv-admin textarea{
        min-height:150px;
        resize:vertical;
        line-height:1.5;
      }

      .f1drv-admin__row2{
        display:grid;
        grid-template-columns: 1fr 1fr;
        gap:10px;
      }
      @media (max-width: 900px){
        .f1drv-admin__row2{ grid-template-columns:1fr; }
      }

      .f1drv-admin__divider{
        margin-top:12px;
        padding-top:10px;
        border-top:1px solid rgba(0,0,0,.08);
      }
      .f1drv-admin__sectiontitle{
        font-size:12px;
        font-weight:900;
        letter-spacing:.06em;
        text-transform:uppercase;
        color: rgba(17,17,17,.65);
        margin:0 0 8px;
      }

      .f1drv-admin__btns{
        display:flex;
        gap:10px;
        margin-top:12px;
        flex-wrap:wrap;
      }
      .f1drv-admin__btns button{
        border:0;
        border-radius:0;
        padding:11px 12px;
        font-weight:900;
        cursor:pointer;
        width:100%;
        color:#fff;
      }
      .f1drv-admin__btns button.primary{ background: var(--green); }
      .f1drv-admin__btns button.primary:hover{ background: var(--green2); }
      .f1drv-admin__btns button.danger{ background: var(--red); }
      .f1drv-admin__btns button.danger:hover{ background: var(--red2); }

      .f1drv-admin__msg{
        margin-top:10px;
        font-size:12px;
        font-weight:900;
        color: rgba(17,17,17,.75);
        min-height:16px;
      }

      /* Dropdown wie bei deinen Cards */
      .f1drv-mdd{ position:relative; width:100%; }
      .f1drv-mdd__btn{
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
      .f1drv-mdd__left{ display:flex; align-items:center; gap:10px; min-width:0; }
      .f1drv-mdd__thumb{
        width:28px; height:28px;
        display:flex; align-items:center; justify-content:center;
        background:#fff;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,.15);
        flex:0 0 auto;
        overflow:hidden;
      }
      .f1drv-mdd__thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
      .f1drv-mdd__label{
        font-size:13px;
        color: rgba(17,17,17,.92);
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        max-width: 75%;
      }
      .f1drv-mdd__chev{ opacity:.65; flex:0 0 auto; }

      .f1drv-mdd__panel{
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
      .f1drv-mdd.is-open .f1drv-mdd__panel{ display:block; }

      .f1drv-mdd__searchwrap{
        position: sticky;
        top: 0;
        background: #fff;
        padding: 8px;
        border-bottom: 1px solid rgba(0,0,0,.08);
        z-index: 1;
      }
      .f1drv-mdd__search{
        width:100%;
        height:40px;
        border:1px solid rgba(0,0,0,.12);
        background:#f8f9fb;
        padding:8px 10px;
        font-weight:900;
        border-radius:0;
        outline:none;
      }

      .f1drv-mdd__item{
        display:flex;
        align-items:center;
        gap:10px;
        padding:8px 10px;
        cursor:pointer;
        border-bottom:1px solid rgba(0,0,0,.06);
        font-weight:900;
      }
      .f1drv-mdd__item:last-child{ border-bottom:0; }
      .f1drv-mdd__item:hover{ background: rgba(0,0,0,.04); }
    </style>

    <div class="f1drv-admin" id="f1drvPanel">
      <div class="f1drv-admin__surface">
        <h3>Fahrer verwalten</h3>
        <div class="f1drv-admin__hint">
          Klick auf Eintrag "=" bearbeiten. Mit "↑ ↓" verschiebst du den ausgewählten Eintrag. Mit "✕" abwählen.
        </div>

        <ul class="f1drv-admin__list" id="f1drvList">
          <?php
          $n = 1;
          foreach ($drivers as $d) :
            $pid = (int)$d->ID;
            $m = $this->get_meta($pid);

            $title = get_the_title($pid);
            $sub = get_permalink($pid);
            ?>
            <li class="f1drv-admin__item"
              data-id="<?php echo esc_attr((string)$pid); ?>"
              data-name="<?php echo esc_attr($title); ?>"
              data-slug="<?php echo esc_attr($m['slug']); ?>"

              data-img="<?php echo esc_attr($m['img']); ?>"
              data-flag="<?php echo esc_attr($m['flag']); ?>"
              data-team-id="<?php echo esc_attr($m['team_id']); ?>"
              data-team-inactive="<?php echo esc_attr($m['team_inactive']); ?>"
              data-nationality="<?php echo esc_attr($m['nationality']); ?>"

              data-birthplace="<?php echo esc_attr($m['birthplace']); ?>"
              data-birthdate="<?php echo esc_attr($m['birthdate']); ?>"
              data-height="<?php echo esc_attr($m['height']); ?>"
              data-weight="<?php echo esc_attr($m['weight']); ?>"
              data-marital="<?php echo esc_attr($m['marital']); ?>"

              data-fb="<?php echo esc_attr($m['fb']); ?>"
              data-x="<?php echo esc_attr($m['x']); ?>"
              data-ig="<?php echo esc_attr($m['ig']); ?>"

              data-bio="<?php echo esc_attr($m['bio']); ?>"
            >
              <div class="f1drv-admin__nr"><?php echo esc_html($n.'.'); ?></div>
              <div class="f1drv-admin__meta">
                <div class="f1drv-admin__title"><?php echo esc_html($title); ?></div>
                <div class="f1drv-admin__sub"><?php echo esc_html($sub); ?></div>
              </div>
              <div class="f1drv-admin__arrows" aria-label="Aktionen">
                <a class="f1drv-edit"
                   href="<?php echo esc_url(get_edit_post_link($pid, '')); ?>"
                   target="_blank" rel="noopener"
                   title="Im WordPress-Editor öffnen">✎</a>

                <button class="f1drv-arrow" type="button" data-move="up" title="Nach oben">↑</button>
                <button class="f1drv-arrow" type="button" data-move="down" title="Nach unten">↓</button>
                <button class="f1drv-x" type="button" data-deselect="1" title="Auswahl aufheben">✕</button>
              </div>
            </li>
            <?php
            $n++;
          endforeach;
          ?>
        </ul>

        <input type="hidden" id="f1drv_post_id" value="0">

        <div class="f1drv-admin__divider">
          <div class="f1drv-admin__sectiontitle">Basis</div>

          <label>Name</label>
          <input type="text" id="f1drv_name" placeholder="Vorname Nachname">

          <label>URL-Slug (optional)</label>
          <input type="text" id="f1drv_slug" placeholder="vorname-nachname">

          <label>Team (Zuordnung)</label>
          <select id="f1drv_team_id">
            <option value="0">— Kein Team —</option>
            <?php foreach ($teams as $t): ?>
              <option value="<?php echo esc_attr((string)$t['id']); ?>"><?php echo esc_html($t['title']); ?></option>
            <?php endforeach; ?>
          </select>
          <div style="margin-top:6px; font-weight:900; font-size:12px; opacity:.75;">
            Teams werden automatisch aus deinem CPT <b>f1_team</b> geladen (URLs liegen unter /teams/teamname).
          </div>

          <label style="margin-top:10px;">Inaktiv im Team</label>
          <label style="display:flex; align-items:center; gap:10px; margin:6px 0 0; font-size:12px; font-weight:900; letter-spacing:.02em; text-transform:none;">
            <input type="checkbox" id="f1drv_team_inactive" value="1" style="width:18px; height:18px; margin:0;">
            Fahrer ist aktuell <span style="color:#202020;">nicht aktiv</span> im Team (Frontend: kein Team, Farbe <span style="color:#e72b99;">#e72b99</span>)
          </label>
          <div style="margin-top:6px; font-weight:900; font-size:12px; opacity:.75;">
            Nutzung: Bei Ersatzfahrern/Rotation über die Saison – Team bleibt im Backend gespeichert, wird im Frontend aber ignoriert.
          </div>

          <label>Fahrerbild (PNG aus /uploads/driver/440px/)</label>
          <input type="hidden" id="f1drv_img" value="">
          <div class="f1drv-mdd" id="f1drv_dd_img">
            <button type="button" class="f1drv-mdd__btn" aria-label="Fahrerbild auswählen">
              <span class="f1drv-mdd__left">
                <span class="f1drv-mdd__thumb" data-thumb><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span>
                <span class="f1drv-mdd__label" data-label>Kein Bild</span>
              </span>
              <span class="f1drv-mdd__chev">▾</span>
            </button>
            <div class="f1drv-mdd__panel" data-panel></div>
          </div>

          <div class="f1drv-admin__row2">
            <div>
              <label>Flagge (Nationalität)</label>
              <input type="hidden" id="f1drv_flag" value="">
              <div class="f1drv-mdd" id="f1drv_dd_flag">
                <button type="button" class="f1drv-mdd__btn" aria-label="Flagge auswählen">
                  <span class="f1drv-mdd__left">
                    <span class="f1drv-mdd__thumb" data-thumb><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span>
                    <span class="f1drv-mdd__label" data-label>Keine Flagge</span>
                  </span>
                  <span class="f1drv-mdd__chev">▾</span>
                </button>
                <div class="f1drv-mdd__panel" data-panel></div>
              </div>
            </div>

            <div>
              <label>Nationalität (Text)</label>
              <input type="text" id="f1drv_nationality" placeholder="Land">
            </div>
          </div>
        </div>

        <div class="f1drv-admin__divider">
          <div class="f1drv-admin__sectiontitle">Details</div>

          <div class="f1drv-admin__row2">
            <div>
              <label>Geburtsort</label>
              <input type="text" id="f1drv_birthplace" placeholder="Stadt (Land)">
            </div>
            <div>
              <label>Geburtsdatum</label>
              <input type="text" id="f1drv_birthdate" placeholder="XX.XX.XXXX (XX Jahre)">
            </div>
          </div>

          <div class="f1drv-admin__row2">
            <div>
              <label>Größe</label>
              <input type="text" id="f1drv_height" placeholder="X,XX m">
            </div>
            <div>
              <label>Gewicht</label>
              <input type="text" id="f1drv_weight" placeholder="XXX kg">
            </div>
          </div>

          <label>Familienstand</label>
          <input type="text" id="f1drv_marital" placeholder="Familienstand">
        </div>

        <div class="f1drv-admin__divider">
          <div class="f1drv-admin__sectiontitle">Socials</div>

          <label>Facebook</label>
          <input type="url" id="f1drv_fb" placeholder="https://facebook.com/...">

          <label>X / Twitter</label>
          <input type="url" id="f1drv_x" placeholder="https://x.com/...">

          <label>Instagram</label>
          <input type="url" id="f1drv_ig" placeholder="https://instagram.com/...">
        </div>

        <div class="f1drv-admin__divider">
          <div class="f1drv-admin__sectiontitle">Biographie (ca. 1500 Zeichen)</div>
          <textarea id="f1drv_bio" placeholder="Kurzer Text über den Fahrer…"></textarea>
          <div style="margin-top:6px; font-weight:900; font-size:12px; opacity:.75;">
            Tipp: Du kannst Absätze machen. Links sind erlaubt.
          </div>
        </div>

        <div class="f1drv-admin__btns">
          <div style="width:100%;"><button class="primary" id="f1drv_save" type="button">Speichern</button></div>
          <div style="width:100%;"><button class="danger" id="f1drv_delete" type="button">Löschen</button></div>
        </div>

        <div class="f1drv-admin__msg" id="f1drv_msg"></div>
      </div>
    </div>

    <script>
      (function(){
        const ajaxUrl = "<?php echo esc_js(admin_url('admin-ajax.php')); ?>";
        const nonce   = "<?php echo esc_js($nonce); ?>";
        const MEDIA = <?php echo wp_json_encode($media); ?>;

        const msg   = document.getElementById('f1drv_msg');
        const list  = document.getElementById('f1drvList');

        const postIdEl = document.getElementById('f1drv_post_id');

        const nameEl = document.getElementById('f1drv_name');
        const slugEl = document.getElementById('f1drv_slug');

        const teamEl = document.getElementById('f1drv_team_id');
        const teamInactiveEl = document.getElementById('f1drv_team_inactive');

        const imgEl  = document.getElementById('f1drv_img');
        const flagEl = document.getElementById('f1drv_flag');
        const natEl  = document.getElementById('f1drv_nationality');

        const birthplaceEl = document.getElementById('f1drv_birthplace');
        const birthdateEl  = document.getElementById('f1drv_birthdate');
        const heightEl     = document.getElementById('f1drv_height');
        const weightEl     = document.getElementById('f1drv_weight');
        const maritalEl    = document.getElementById('f1drv_marital');

        const fbEl = document.getElementById('f1drv_fb');
        const xEl  = document.getElementById('f1drv_x');
        const igEl = document.getElementById('f1drv_ig');

        const bioEl = document.getElementById('f1drv_bio');

        const btnSave = document.getElementById('f1drv_save');
        const btnDel  = document.getElementById('f1drv_delete');

        function setMsg(t){ if (msg) msg.textContent = t || ""; }

        function setActiveItem(item){
          const actives = document.querySelectorAll('.f1drv-admin__item.is-active');
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

        function buildMediaDropdown(wrapperEl, hiddenInputEl, options, cfg){
          const btn = wrapperEl.querySelector('.f1drv-mdd__btn');
          const panel = wrapperEl.querySelector('[data-panel]');
          const thumb = wrapperEl.querySelector('[data-thumb]');
          const label = wrapperEl.querySelector('[data-label]');

          const emptyLabel = (cfg && cfg.emptyLabel) ? cfg.emptyLabel : 'Keine Auswahl';
          const showCode   = !!(cfg && cfg.showCode);

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
            sw.className = 'f1drv-mdd__searchwrap';
            const si = document.createElement('input');
            si.className = 'f1drv-mdd__search';
            si.type = 'text';
            si.placeholder = 'Suchen…';
            si.value = String(filterText || '');
            sw.appendChild(si);
            panel.appendChild(sw);

            const none = document.createElement('div');
            none.className = 'f1drv-mdd__item';
            none.innerHTML = '<span class="f1drv-mdd__thumb"><span style="font-weight:900; font-size:12px; opacity:.6;">—</span></span><span class="f1drv-mdd__label">Keine</span>';
            none.addEventListener('click', function(){
              setSelected('');
              toggle(false);
            });
            panel.appendChild(none);

            let shown = 0;
            for (let i=0;i<items.length;i++){
              const o = items[i];
              const hay = (String(o.label||'') + ' ' + String(o.value||'') + ' ' + String(o.code||'')).toLowerCase();
              if (q && hay.indexOf(q) === -1) continue;

              const item = document.createElement('div');
              item.className = 'f1drv-mdd__item';

              const codeHtml = (showCode && o.code) ? '<span class="f1drv-mdd__code">'+String(o.code)+'</span>' : '';
              item.innerHTML =
                '<span class="f1drv-mdd__thumb"><img src="'+o.url+'" alt="'+(o.label||'')+'" loading="lazy"></span>' +
                '<span class="f1drv-mdd__label">'+(o.label||String(o.value||''))+'</span>' +
                codeHtml;

              item.addEventListener('click', function(){
                setSelected(String(o.value||''));
                toggle(false);
              });

              panel.appendChild(item);
              shown++;
            }

            if (shown === 0) {
              const no = document.createElement('div');
              no.className = 'f1drv-mdd__item';
              no.style.opacity = '0.75';
              no.textContent = 'Keine Treffer';
              panel.appendChild(no);
            }

            setTimeout(function(){ try{ si.focus(); }catch(e){} }, 0);
            si.addEventListener('input', function(){
              renderList(si.value);
            });
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

        const ddImg = buildMediaDropdown(
          document.getElementById('f1drv_dd_img'),
          imgEl,
          MEDIA.drivers440 || [],
          { emptyLabel: 'Kein Bild', showCode: false }
        );

        const ddFlag = buildMediaDropdown(
          document.getElementById('f1drv_dd_flag'),
          flagEl,
          MEDIA.flags || [],
          { emptyLabel: 'Keine Flagge', showCode: false }
        );

        function clearFormForNew(){
          postIdEl.value = "0";

          nameEl.value = "";
          slugEl.value = "";

          teamEl.value = "0";
          teamInactiveEl.checked = false;

          imgEl.value = "";
          ddImg.setSelected("");

          flagEl.value = "";
          ddFlag.setSelected("");

          natEl.value = "";
          birthplaceEl.value = "";
          birthdateEl.value = "";
          heightEl.value = "";
          weightEl.value = "";
          maritalEl.value = "";

          fbEl.value = "";
          xEl.value  = "";
          igEl.value = "";
          bioEl.value = "";

          const actives = document.querySelectorAll('.f1drv-admin__item.is-active');
          for (let i=0;i<actives.length;i++) actives[i].classList.remove('is-active');

          updateButtonVisibility();
          setMsg("Neuer Fahrer (Speichern legt an).");
        }

        function fillFromItem(item){
          postIdEl.value = item.dataset.id || "0";
          nameEl.value   = item.dataset.name || "";
          slugEl.value   = item.dataset.slug || "";

          teamEl.value = item.dataset.teamId || "0";
          teamInactiveEl.checked = (String(item.dataset.teamInactive || "0") === "1");

          imgEl.value    = item.dataset.img || "";
          ddImg.setSelected(imgEl.value);

          flagEl.value   = item.dataset.flag || "";
          ddFlag.setSelected(flagEl.value);

          natEl.value        = item.dataset.nationality || "";
          birthplaceEl.value = item.dataset.birthplace || "";
          birthdateEl.value  = item.dataset.birthdate || "";
          heightEl.value     = item.dataset.height || "";
          weightEl.value     = item.dataset.weight || "";
          maritalEl.value    = item.dataset.marital || "";

          fbEl.value = item.dataset.fb || "";
          xEl.value  = item.dataset.x || "";
          igEl.value = item.dataset.ig || "";

          bioEl.value = item.dataset.bio || "";

          updateButtonVisibility();
          setMsg("Bearbeite: " + (nameEl.value || "Fahrer"));
        }

        // ✅ NEU: Klick auf ✎ soll NICHT die Auswahl triggern
        if (list) list.addEventListener('click', function(e){
          const arrowBtn = e.target.closest('.f1drv-arrow');
          const xBtn = e.target.closest('.f1drv-x');
          const editBtn = e.target.closest('.f1drv-edit');
          if (arrowBtn || xBtn || editBtn) return;

          const item = e.target.closest('.f1drv-admin__item');
          if (!item) return;

          setActiveItem(item);
          fillFromItem(item);
        });

        if (list) list.addEventListener('click', function(e){
          const arrowBtn = e.target.closest('.f1drv-arrow');
          if (!arrowBtn) return;

          e.preventDefault();
          e.stopPropagation();

          const item = arrowBtn.closest('.f1drv-admin__item');
          if (!item) return;

          const pid = item.dataset.id;
          const dir = arrowBtn.dataset.move;

          setMsg("Verschiebe…");
          postForm('f1drv_move', { post_id: String(pid), dir: dir })
            .then(function(r){
              if (!r || !r.success){
                setMsg((r && r.data && r.data.message) ? r.data.message : "Fehler beim Verschieben.");
                return;
              }
              setMsg("Verschoben. Seite aktualisiert…");
              window.location.reload();
            });
        });

        if (list) list.addEventListener('click', function(e){
          const xBtn = e.target.closest('.f1drv-x');
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

            team_id: String(parseInt(teamEl.value || "0", 10) || 0),
            team_inactive: teamInactiveEl.checked ? "1" : "0",

            img: imgEl.value.trim(),
            flag: flagEl.value.trim(),
            nationality: natEl.value.trim(),

            birthplace: birthplaceEl.value.trim(),
            birthdate: birthdateEl.value.trim(),

            height: heightEl.value.trim(),
            weight: weightEl.value.trim(),
            marital: maritalEl.value.trim(),

            fb: fbEl.value.trim(),
            x:  xEl.value.trim(),
            ig: igEl.value.trim(),

            bio: bioEl.value
          };

          postForm('f1drv_save', payload).then(function(r){
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
          if (!pid) { setMsg("Kein Fahrer ausgewählt."); return; }
          if (!confirm("Wirklich löschen?")) return;

          setMsg("Lösche…");
          postForm('f1drv_delete', { post_id: String(pid) }).then(function(r){
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
