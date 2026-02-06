<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Frontend Team Profile View
 * Expects variables:
 * $name, $logo_url, $car_url, $flag_url, $team_color, $accent, $head,
 * $socials_left, $socials_stack, $rows, $bio, $editor_content_html,
 * $back_url, $prev_post, $next_post, $prev_color, $next_color, $prev_rgb, $next_rgb
 */
?>
      <style>
        .f1team-wrap{
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

        .f1team-wrap .f1team-card{
          background: var(--card);
          border-radius: 0 !important;
          overflow:hidden;
          box-shadow:0 10px 26px rgba(0,0,0,.06);
          position:relative;
        }

        .f1team-wrap .f1team-card::before{
          content:"";
          position:absolute; top:0; left:0; right:0;
          height:49px;
          background: var(--head);
          z-index:0;
          pointer-events:none;
        }
        .f1team-wrap .f1team-card::after{
          content:"";
          position:absolute;
          top:45px;
          left:0; right:0;
          height:4px;
          background: var(--accent);
          z-index:1;
          pointer-events:none;
        }

        .f1team-head{
          min-height:49px;
          position:relative;
          z-index:2;
          display:flex;
          align-items:center;
          justify-content:center;
          padding:0 16px;
          box-sizing:border-box;
        }
        .f1team-head__inner{
          width:100%;
          display:flex;
          align-items:center;
          justify-content:space-between;
          gap:14px;
        }
        .f1team-head__label{
          font-size:13px;
          font-weight:900;
          letter-spacing:.12em;
          text-transform:uppercase;
          color:#fff;
          margin:0;
          white-space:nowrap;
        }

        .f1team-back,
        .f1team-back:link,
        .f1team-back:visited,
        .f1team-back:hover,
        .f1team-back:focus{
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

        .f1team-body{
          padding:22px;
          display:grid !important;
          grid-template-columns: 220px minmax(320px, 520px) 1fr !important;
          gap:22px !important;
          align-items:start !important;
        }

        .f1team-left{
          width:auto !important;
          flex:0 0 auto !important;
          display:flex;
          flex-direction:column;
          align-items:center;
          gap:10px;
        }

        .f1team-logo{
          width:150px;
          height:150px;
          display:flex;
          align-items:center;
          justify-content:center;
          overflow:hidden;
        }
        .f1team-logo img{ width:100%; height:100%; object-fit:contain; display:block; }

        .f1team__social{
          margin-top:10px;
          display:flex;
          gap:6px;
          justify-content:center;
        }
        .f1team__social a,
        .f1team__social a:link,
        .f1team__social a:visited{
          width:28px;
          height:28px;
          display:flex;
          align-items:center;
          justify-content:center;
          text-decoration:none !important;
          background:transparent !important;
          color: var(--text) !important;
          transition: background-color .18s ease;
        }
        .f1team__social i{ font-size:16px; line-height:1; color: currentColor !important; }

        .f1team__social a[data-social="facebook"]:hover{ background:var(--hover-facebook) !important; }
        .f1team__social a[data-social="instagram"]:hover{ background:var(--hover-instagram) !important; }
        .f1team__social a[data-social="x"]:hover{ background:var(--hover-x) !important; }
        .f1team__social a:hover i{ color:#fff !important; }

        .f1team__social--stack{
          display:none !important;
          margin-top:8px !important;
        }

        .f1team-content{
          flex:0 0 auto !important;
          max-width:none !important;
          min-width:0 !important;

          display:grid !important;
          grid-template-rows: auto auto 1fr !important;
          row-gap: 10px !important;
          align-content:start !important;
        }

        .f1team-name{
          font-size:30px;
          font-weight:900;
          display:inline-flex;
          align-items:center;
          gap:8px;
          margin:0 !important;
          line-height:1 !important;
          color: var(--text) !important;
        }

        .f1team-flag{
          width:var(--flag-w);
          height:var(--flag-h);
          display:inline-flex;
          border:1px solid rgba(127,127,127,.45);
          background:#fff;
          transform: translateY(2px);
        }
        .f1team-flag img{ width:100%; height:100%; object-fit:cover; display:block; }

        .driver-card__teaminfo-grid{
          margin-top: 10px !important;
          display: grid !important;
          grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
          gap: 10px 14px !important;
          width: 520px !important;
          max-width:100% !important;
          grid-row: 3 !important;
        }

        .driver-card__teaminfo-grid .titem{
          padding: 10px 12px;
          background: rgba(0,0,0,.03);
          border-left: 3px solid var(--team-color);
          display:flex;
          flex-direction:column;
          gap: 4px;
        }
        .driver-card__teaminfo-grid .tlabel{
          font-size: 11px;
          font-weight: 900;
          letter-spacing: .10em;
          text-transform: uppercase;
          color: rgba(17,17,17,.65);
          line-height: 1.1;
        }

        .driver-card__teaminfo-grid .tval{
          font-size: 14px;
          font-weight: 900;
          color: var(--text);
          display:flex;
          align-items:center;
          gap: 8px;
          min-width: 0;
          word-break: break-word;
        }

        .f1team-right{
          flex:0 0 auto !important;
          min-width:0 !important;
          display:flex;
          align-items:center;
          justify-content:flex-end !important;
          padding-top: 38px !important;
        }
        .f1team-car{
          width:100% !important;
          max-width:790px !important;
          height:181px;
          display:flex;
          align-items:center;
          justify-content:center;
          overflow:hidden;
        }
        .f1team-car img{
          width:100% !important;
          height:100% !important;
          object-fit:contain !important;
          display:block;
        }

        .f1team-divider{
          border-top:1px solid rgba(0,0,0,.08);
          margin:0;
        }

        .f1team-bio{
          padding:16px 22px 22px;
        }
        .f1team-bio__title{
          font-size:12px;
          font-weight:900;
          letter-spacing:.10em;
          text-transform:uppercase;
          color: rgba(17,17,17,.65);
          margin:0 0 10px;
        }
        .f1team-bio__text{
          font-size:14px;
          font-weight:750;
          line-height:1.55;
          color: rgba(17,17,17,.92);
        }
        .f1team-bio__text p{ margin:0 0 10px; }
        .f1team-bio__text p:last-child{ margin-bottom:0; }

        /* ✅ NEU: Gutenberg Content Bereich zwischen Bio und Prev/Next */
        .f1team-editor{
          padding:16px 22px 22px;
          font-size:14px;
          font-weight:750;
          line-height:1.55;
          color: rgba(17,17,17,.92);
        }
        .f1team-editor > *:first-child{ margin-top:0 !important; }
        .f1team-editor > *:last-child{ margin-bottom:0 !important; }
        .f1team-editor img{ max-width:100%; height:auto; display:block; }

        .f1team-navwrap{
          margin: 14px auto 0;
          width: 100%;
        }
        .f1team-nav{
          display:grid;
          grid-template-columns: 1fr 1fr;
          gap: 12px;
          width: 100%;
        }
        .f1team-nav__item{
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

        .f1team-nav__item::before{
          content:"";
          position:absolute;
          top:0; bottom:0;
          left:0;
          width: 4px;
          background: var(--nav-accent, var(--accent));
          pointer-events:none;
        }

        .f1team-nav__kicker{
          display:block;
          font-size: 11px;
          font-weight: 900;
          letter-spacing: .10em;
          text-transform: uppercase;
          color: rgba(17,17,17,.62);
          margin: 0 0 6px;
          line-height: 1.1;
        }
        .f1team-nav__title{
          display:block;
          font-size: 16px;
          font-weight: 950;
          line-height: 1.15;
        }
        .f1team-nav__item--next{
          text-align:right;
        }
        .f1team-nav__item--next::before{
          left:auto;
          right:0;
        }

        .f1team-nav__item:hover,
        .f1team-nav__item:focus,
        .f1team-nav__item:active{
          background: var(--card) !important;
          border-color: rgba(0,0,0,.10) !important;
          box-shadow: 0 10px 26px rgba(0,0,0,.06) !important;
          transform: none !important;
          outline: none !important;
        }

        .f1team-nav__spacer{
          display:block;
          background: transparent;
        }

        @media (max-width: 720px){
          .f1team-nav{
            grid-template-columns: 1fr;
          }
          .f1team-nav__item--next{
            text-align:left;
          }
          .f1team-nav__item--next::before{
            left:0;
            right:auto;
          }
        }

        @media (max-width: 1200px){
          .f1team-body{
            display:grid !important;
            grid-template-columns: 1fr !important;
            gap:18px !important;
            text-align:center !important;
            justify-items:center !important;
            align-items:center !important;
          }

          .f1team-left,
          .f1team-content,
          .f1team-right{
            width:100% !important;
            justify-self:center !important;
            align-self:center !important;
          }

          .f1team-left .f1team__social{
            display:none !important;
          }

          .f1team__social--stack{
            display:flex !important;
            justify-content:center !important;
          }

          .f1team-left{
            align-items:center !important;
          }

          .f1team-content{
            display:flex !important;
            flex-direction:column !important;
            align-items:center !important;
            min-width:0 !important;
            max-width:none !important;
          }

          .driver-card__teaminfo-grid{
            margin-left:auto !important;
            margin-right:auto !important;
            width:100% !important;
            max-width:520px !important;
          }

          .driver-card__teaminfo-grid .titem{
            text-align:center !important;
            align-items:center !important;
          }
          .driver-card__teaminfo-grid .tlabel,
          .driver-card__teaminfo-grid .tval{
            justify-content:center !important;
            text-align:center !important;
          }

          .f1team-right{
            display:flex !important;
            justify-content:center !important;
            padding-top: 0 !important;
            width:100% !important;
          }
          .f1team-car{
            width:100% !important;
            max-width:520px !important;
          }
          .f1team-car img{
            width:100% !important;
            height:100% !important;
          }
        }

        @media (max-width: 720px){
          .driver-card__teaminfo-grid{
            grid-template-columns: 1fr !important;
            width: 100% !important;
            max-width: none !important;
            gap: 10px !important;
          }

          .f1team-car{
            width:100% !important;
            max-width:none !important;
          }
        }
      </style>

      <div class="f1team-wrap">
        <div class="f1team-card" style="--accent: <?php echo esc_attr( $accent ); ?>; --head: <?php echo esc_attr( $head ); ?>; --team-color: <?php echo esc_attr( $team_color ); ?>;">
          <div class="f1team-head">
            <div class="f1team-head__inner">
              <div class="f1team-head__label">Teamprofil</div>
              <a class="f1team-back" href="<?php echo esc_url( $back_url ); ?>">← Zurück</a>
            </div>
          </div>

          <div class="f1team-body">

            <div class="f1team-left">
              <?php if ( $logo_url !== '' ): ?>
                <div class="f1team-logo">
                  <img decoding="async" loading="lazy" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?> Logo">
                </div>
              <?php endif; ?>

              <?php echo $socials_left ? $socials_left : ''; ?>
            </div>

            <div class="f1team-content">
              <h1 class="f1team-name">
                <?php echo esc_html( $name ); ?>
                <?php if ( $flag_url !== '' ): ?>
                  <span class="f1team-flag">
                    <img decoding="async" src="<?php echo esc_url( $flag_url ); ?>" alt="Flag">
                  </span>
                <?php endif; ?>
              </h1>

              <?php echo $socials_stack ? $socials_stack : ''; ?>

              <?php if ( ! empty( $rows ) ): ?>
                <div class="driver-card__teaminfo-grid">
                  <?php foreach ( $rows as $r ): ?>
                    <div class="titem">
                      <div class="tlabel"><?php echo esc_html( $r[0] ); ?></div>
                      <div class="tval"><?php echo esc_html( $r[1] ); ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="f1team-right">
              <?php if ( $car_url !== '' ): ?>
                <div class="f1team-car">
                  <img decoding="async" loading="lazy" src="<?php echo esc_url( $car_url ); ?>" alt="<?php echo esc_attr( $name ); ?> Auto">
                </div>
              <?php endif; ?>
            </div>

          </div>

          <hr class="f1team-divider"/>

          <div class="f1team-bio">
            <div class="f1team-bio__title">Biographie</div>
            <div class="f1team-bio__text">
              <?php
                if ( $bio !== '' ) echo wpautop( $bio );
                else echo '<p style="opacity:.75; font-weight:800;">Keine Biographie hinterlegt.</p>';
              ?>
            </div>
          </div>

          <?php if ( $editor_content_html !== '' ): ?>
            <hr class="f1team-divider"/>
            <div class="f1team-editor">
              <?php echo $editor_content_html; ?>
            </div>
          <?php endif; ?>

        </div>

        <?php if ( $prev_post || $next_post ): ?>
          <div class="f1team-navwrap">
            <div class="f1team-nav">
              <?php if ( $prev_post ): ?>
                <a class="f1team-nav__item f1team-nav__item--prev"
                   href="<?php echo esc_url( get_permalink( $prev_post->ID ) ); ?>"
                   style="--nav-accent: <?php echo esc_attr( $prev_color ); ?>;">
                  <span class="f1team-nav__kicker">← Vorheriges Team</span>
                  <span class="f1team-nav__title"><?php echo esc_html( get_the_title( $prev_post->ID ) ); ?></span>
                </a>
              <?php else: ?>
                <span class="f1team-nav__spacer" aria-hidden="true"></span>
              <?php endif; ?>

              <?php if ( $next_post ): ?>
                <a class="f1team-nav__item f1team-nav__item--next"
                   href="<?php echo esc_url( get_permalink( $next_post->ID ) ); ?>"
                   style="--nav-accent: <?php echo esc_attr( $next_color ); ?>;">
                  <span class="f1team-nav__kicker">Nächstes Team →</span>
                  <span class="f1team-nav__title"><?php echo esc_html( get_the_title( $next_post->ID ) ); ?></span>
                </a>
              <?php else: ?>
                <span class="f1team-nav__spacer" aria-hidden="true"></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

      </div>
