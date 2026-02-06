<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Frontend Driver Profile View
 * Expects variables:
 * $name, $img_url, $flag_url, $flag_code, $team_name, $accent, $head,
 * $socials_left, $socials_stack, $rows, $bio, $editor_content_html,
 * $prev_post (object|null), $next_post (object|null),
 * $prev_color, $next_color
 */
?>
<style>
      .f1drv-wrap{
        --canvas:#EEEEEE;
        --card:#FFFFFF;
        --text:#111111;
        --head:#202020;
        --accent:#E00078;

        --hover-facebook:#1877F2;
        --hover-instagram:#E1306C;
        --hover-x:#6c6c6c;

        --flag-w:20px;
        --flag-h:13px;

        color: var(--text);
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      }

      .f1drv-wrap .f1drv-card{
        background: var(--card);
        border-radius: 0 !important;
        overflow:hidden;
        box-shadow:0 10px 26px rgba(0,0,0,.06);
        position:relative;
      }

      .f1drv-wrap .f1drv-card::before{
        content:"";
        position:absolute; top:0; left:0; right:0;
        height:49px;
        background: var(--head);
        z-index:0;
        pointer-events:none;
      }
      .f1drv-wrap .f1drv-card::after{
        content:"";
        position:absolute;
        top:45px;
        left:0; right:0;
        height:4px;
        background: var(--accent);
        z-index:1;
        pointer-events:none;
      }

      .f1drv-head{
        min-height:49px;
        position:relative;
        z-index:2;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:0 16px;
        box-sizing:border-box;
      }
      .f1drv-head__inner{
        width:100%;
        display:flex;
        align-items:center;
        justify-content:space-between;
        transform: translateY(0px);
        gap:14px;
      }
      .f1drv-head__label{
        font-size:13px;
        font-weight:900;
        letter-spacing:.12em;
        text-transform:uppercase;
        color:#fff;
        margin:0;
        white-space:nowrap;
      }

      .f1drv-back,
      .f1drv-back:link,
      .f1drv-back:visited,
      .f1drv-back:hover,
      .f1drv-back:focus{
        color:#fff !important;
        font-size:12px;
        font-weight:900;
        letter-spacing:.08em;
        text-transform:uppercase;
        text-decoration:none !important;
        display:inline-flex;
        align-items:center;
        gap:6px;
        opacity:.92;
        line-height:1;
        padding:8px 0;
      }

      .f1drv-body{
        padding:22px;
        display:flex;
        gap:22px;
        flex-wrap:wrap;
        align-items:flex-start;
      }

      .f1drv-media{
        width:257px;
        flex:0 0 257px;
        display:flex;
        flex-direction:column;
        align-items:center;
      }
      .f1drv-avatar{
        width:257px;
        height:257px;
        border-radius:0px;
        overflow:hidden;
        background: rgba(0,0,0,.00);
        display:flex;
        align-items:center;
        justify-content:center;
      }
      .f1drv-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
      .f1drv-avatar--empty{ font-weight:900; color:rgba(0,0,0,.5); font-size:12px; }

      .f1drv__social{
        margin-top:10px;
        display:flex;
        gap:5px;
        justify-content:center;
      }
      .f1drv__social a,
      .f1drv__social a:link,
      .f1drv__social a:visited{
        width:25px;
        height:25px;
        display:flex;
        align-items:center;
        justify-content:center;
        text-decoration:none !important;
        background:transparent !important;
        color: var(--text) !important;
        transition: background-color .18s ease;
      }
      .f1drv__social i{
        font-size:15px;
        line-height:1;
        color: currentColor !important;
      }
      .f1drv__social a[data-social="facebook"]:hover{ background:var(--hover-facebook) !important; }
      .f1drv__social a[data-social="instagram"]:hover{ background:var(--hover-instagram) !important; }
      .f1drv__social a[data-social="x"]:hover{ background:var(--hover-x) !important; }
      .f1drv__social a:hover i{ color:#fff !important; }

      .f1drv__social--stack{
        display:none !important;
        margin-top:8px !important;
      }

      .f1drv-content{
        flex:1 1 360px;
        min-width:280px;
      }

      .f1drv-name{
        font-size:30px;
        font-weight:900;
        display:inline-flex;
        align-items:center;
        gap:8px;
        margin:0 !important;
        line-height:1 !important;
        color: var(--text) !important;
      }
      .f1drv-flag{
        width:var(--flag-w);
        height:var(--flag-h);
        display:inline-flex;
        border:1px solid rgba(127,127,127,.45);
        background:#fff;
        transform: translateY(2px);
      }
      .f1drv-flag img{ width:100%; height:100%; object-fit:cover; display:block; }

      .f1drv-meta{
        list-style:none !important;
        margin:12px 0 0 0 !important;
        padding:0 !important;

        display:grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px 14px !important;

        color: var(--text) !important;
      }
      .f1drv-meta li{
        margin:0 !important;
        padding:10px 12px !important;

        background: rgba(0,0,0,.03);
        border-left: 3px solid var(--accent);

        display:flex;
        flex-direction:column;
        gap:4px;

        min-width:0;
      }

      .f1drv-meta li.is-team{
        grid-column: auto !important;
      }

      @media (max-width: 860px) and (min-width: 721px){
        .f1drv-meta li.is-team{
          grid-column: 1 / -1 !important;
        }
      }

      .f1drv-meta li.is-spacer{
        visibility: hidden !important;
        background: transparent !important;
        border-left-color: transparent !important;
        padding: 0 !important;
        min-height: 0 !important;
      }

      .f1drv-meta__label{
        font-size:11px;
        font-weight:900;
        letter-spacing:.10em;
        text-transform:uppercase;
        color: rgba(17,17,17,.65);
        line-height:1.1;
      }
      .f1drv-meta__val{
        font-size:14px;
        font-weight:900;
        color: var(--text);
        line-height:1.25;
        word-break: break-word;
        min-width:0;
      }

      @media (max-width: 720px){
        .f1drv-meta{ grid-template-columns: 1fr !important; }
        .f1drv-meta li.is-spacer{ display:none !important; }
      }

      .f1drv-divider{
        border-top:1px solid rgba(0,0,0,.08);
        margin:0;
      }

      .f1drv-bio{
        padding:16px 22px 22px;
      }
      .f1drv-bio__title{
        font-size:12px;
        font-weight:900;
        letter-spacing:.10em;
        text-transform:uppercase;
        color: rgba(17,17,17,.65);
        margin:0 0 10px;
      }
      .f1drv-bio__text{
        font-size:14px;
        font-weight:750;
        line-height:1.55;
        color: rgba(17,17,17,.92);
      }
      .f1drv-bio__text p{ margin:0 0 10px; }
      .f1drv-bio__text p:last-child{ margin-bottom:0; }

      /* ✅ Gutenberg/Editor-Content zwischen Bio und Prev/Next */
      .f1drv-editor{
        padding:16px 22px 22px;
        font-size:14px;
        font-weight:750;
        line-height:1.55;
        color: rgba(17,17,17,.92);
      }
      .f1drv-editor > *:first-child{ margin-top:0 !important; }
      .f1drv-editor > *:last-child{ margin-bottom:0 !important; }
      .f1drv-editor img{ max-width:100%; height:auto; display:block; }

      @media (max-width: 860px){
        .f1drv-body{
          flex-direction:column;
          align-items:center;
          text-align:center;
        }

        .f1drv-content{
          flex: 0 0 auto;
          min-width: 0;
          width: 100%;
          display:flex;
          flex-direction:column;
          align-items:center;
        }

        .f1drv-media .f1drv__social{
          display:none !important;
        }

        .f1drv__social--stack{
          display:flex !important;
          justify-content:center !important;
        }

        .f1drv-meta{
          width: 100%;
          max-width: 520px;
          margin-left:auto !important;
          margin-right:auto !important;
        }

        .f1drv-meta li.is-spacer{ display:none !important; }
      }

      .f1drv-navwrap{
        margin: 14px auto 0;
        width: 100%;
      }
      .f1drv-nav{
        display:grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        width: 100%;
      }
      .f1drv-nav__item{
        position:relative;
        display:block;
        background: var(--card);
        border: 1px solid rgba(0,0,0,.10);
        box-shadow: 0 10px 26px rgba(0,0,0,.06);
        padding: 14px 16px;
        text-decoration:none !important;
        color: var(--text) !important;
        overflow:hidden;
        transition: none !important;
        transform: none !important;
      }
      .f1drv-nav__item::before{
        content:"";
        position:absolute;
        top:0; bottom:0;
        left:0;
        width: 4px;
        background: var(--nav-accent, var(--accent));
        pointer-events:none;
      }
      .f1drv-nav__kicker{
        display:block;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .10em;
        text-transform: uppercase;
        color: rgba(17,17,17,.62);
        margin: 0 0 6px;
        line-height: 1.1;
      }
      .f1drv-nav__title{
        display:block;
        font-size: 16px;
        font-weight: 950;
        line-height: 1.15;
      }
      .f1drv-nav__item--next{
        text-align:right;
      }
      .f1drv-nav__item--next::before{
        left:auto;
        right:0;
      }
      .f1drv-nav__spacer{
        display:block;
        background: transparent;
      }

      .f1drv-nav__item:hover,
      .f1drv-nav__item:focus,
      .f1drv-nav__item:active{
        background: var(--card) !important;
        border-color: rgba(0,0,0,.10) !important;
        box-shadow: 0 10px 26px rgba(0,0,0,.06) !important;
        transform: none !important;
        outline: none !important;
      }

      @media (max-width: 720px){
        .f1drv-nav{
          grid-template-columns: 1fr;
        }
        .f1drv-nav__item--next{
          text-align:left;
        }
        .f1drv-nav__item--next::before{
          left:0;
          right:auto;
        }
      }
</style>

<div class="f1drv-wrap">
  <div class="f1drv-card" style="--accent: <?php echo esc_attr( $accent ); ?>; --head: <?php echo esc_attr( $head ); ?>;">
    <div class="f1drv-head">
      <div class="f1drv-head__inner">
        <div class="f1drv-head__label">Fahrerprofil</div>
        <a class="f1drv-back" href="<?php echo esc_url( $back_url ); ?>">← Zurück</a>
      </div>
    </div>

    <div class="f1drv-body">
      <div class="f1drv-media">
        <div class="f1drv-avatar">
          <?php if ( $img_url !== '' ): ?>
            <img decoding="async" loading="lazy" src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $name ); ?>">
          <?php else: ?>
            <div class="f1drv-avatar--empty">Kein Bild</div>
          <?php endif; ?>
        </div>

        <?php echo $socials_left ? $socials_left : ''; ?>
      </div>

      <div class="f1drv-content">
        <h1 class="f1drv-name">
          <?php echo esc_html( $name ); ?>
          <?php if ( $flag_url !== '' ): ?>
            <span class="f1drv-flag"><img decoding="async" src="<?php echo esc_url( $flag_url ); ?>" alt="<?php echo esc_attr( strtoupper( $flag_code ) ); ?>"></span>
          <?php endif; ?>
        </h1>

        <?php echo $socials_stack ? $socials_stack : ''; ?>

        <?php if ( ! empty( $rows ) ): ?>
          <ul class="f1drv-meta">
            <?php foreach ( $rows as $r ): ?>
              <li class="<?php
                echo ( $r[0] === 'Team' ) ? 'is-team' : '';
                echo ( $r[0] === '__spacer__' ) ? ' is-spacer' : '';
              ?>">
                <?php if ( $r[0] !== '__spacer__' ): ?>
                  <span class="f1drv-meta__label"><?php echo esc_html( $r[0] ); ?></span>
                  <span class="f1drv-meta__val"><?php echo esc_html( $r[1] ); ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <hr class="f1drv-divider"/>

    <div class="f1drv-bio">
      <div class="f1drv-bio__title">Biographie</div>
      <div class="f1drv-bio__text">
        <?php
          if ( $bio !== '' ) echo wpautop( $bio );
          else echo '<p style="opacity:.75; font-weight:800;">Keine Biographie hinterlegt.</p>';
        ?>
      </div>
    </div>

    <?php if ( $editor_content_html !== '' ): ?>
      <hr class="f1drv-divider"/>
      <div class="f1drv-editor">
        <?php echo $editor_content_html; ?>
      </div>
    <?php endif; ?>

  </div>

  <?php if ( $prev_post || $next_post ): ?>
    <div class="f1drv-navwrap">
      <div class="f1drv-nav">
        <?php if ( $prev_post ): ?>
          <a class="f1drv-nav__item f1drv-nav__item--prev"
             href="<?php echo esc_url( get_permalink( $prev_post->ID ) ); ?>"
             style="--nav-accent: <?php echo esc_attr( $prev_color ); ?>;">
            <span class="f1drv-nav__kicker">← Vorheriger Fahrer</span>
            <span class="f1drv-nav__title"><?php echo esc_html( get_the_title( $prev_post->ID ) ); ?></span>
          </a>
        <?php else: ?>
          <span class="f1drv-nav__spacer" aria-hidden="true"></span>
        <?php endif; ?>

        <?php if ( $next_post ): ?>
          <a class="f1drv-nav__item f1drv-nav__item--next"
             href="<?php echo esc_url( get_permalink( $next_post->ID ) ); ?>"
             style="--nav-accent: <?php echo esc_attr( $next_color ); ?>;">
            <span class="f1drv-nav__kicker">Nächster Fahrer →</span>
            <span class="f1drv-nav__title"><?php echo esc_html( get_the_title( $next_post->ID ) ); ?></span>
          </a>
        <?php else: ?>
          <span class="f1drv-nav__spacer" aria-hidden="true"></span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>
