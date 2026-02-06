<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Frontend Calendar View
 * Expects $races, $nrmap, $nonce, $media to be available.
 * $this refers to F1_Manager_Calendar.
 */
?>
<style>
    :root{ --f1wmsAccent: #E00078; }
    .f1site-cal{
      --canvas:#EEEEEE; --card:#FFFFFF; --ink:#202020; --text:#111111;
      --muted:rgba(0,0,0,.65); --accent: var(--f1wmsAccent);
      --headPadTop: 16px; --headPadBottom: 17px; --cellPadY: 12px; --padX: 10px;
      --padNrL: var(--padX); --padNrR: var(--padX); --padStatusL: var(--padX); --padStatusR: var(--padX);
      --padGpL: var(--padX); --padGpR: var(--padX); --padSessionsL: var(--padX); --padSessionsR: var(--padX);
      --fs-nr: 12px; --fs-status-text: 11px; --fs-gp: 12px; --fs-sub: 12px;
      --fs-format: 11px; --fs-day-date: 11px; --fs-day-time: 12px; --fs-day-name: 13px;
      --fs-card-label: 12px; --fs-card-sessionshead: 12px;
      --stack-gap: 6px; --stack-lh: 1.25;
      font-family: inherit; font-size: 14px; line-height: 1.45; color: var(--text);
    }
    .f1site-cal *{ box-sizing:border-box; }
    .f1site-cal a:hover, .f1site-cal a:active, .f1site-cal a:focus{
      color: inherit !important; background: inherit !important; box-shadow: none !important;
      outline: none !important; filter: none !important; transform: none !important; text-decoration: none !important;
    }
    .f1site-cal img, .f1site-cal__card, .f1cal-card{ transition: none !important; }
    .f1site-cal__card:hover, .f1cal-card:hover{ box-shadow: 0 10px 26px rgba(0,0,0,.06) !important; transform: none !important; filter: none !important; }
    .f1site-cal__card:active, .f1cal-card:active{ transform: none !important; filter: none !important; }

    .f1site-cal__card{ background: var(--card); border: 0; border-radius: 0; overflow: hidden; box-shadow: 0 10px 26px rgba(0,0,0,.06); margin: 0 0 16px 0; }
    .f1site-cal__scroll{ overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; background: var(--card); outline: none; }
    .f1site-cal table{ border-collapse: collapse; border-spacing: 0; }
    .f1site-cal table, .f1site-cal thead, .f1site-cal tbody, .f1site-cal tr, .f1site-cal th, .f1site-cal td{ float: none !important; border: 0 !important; }
    table.f1site-cal__table{ width: 100%; min-width: 920px; table-layout: fixed; }

    .f1site-cal__table thead th{
      background: var(--ink); color:#fff; padding-top: var(--headPadTop); padding-bottom: var(--headPadBottom);
      padding-left: var(--padX); padding-right: var(--padX); font-size: 14px; font-weight: 900;
      letter-spacing: .02em; text-transform: uppercase; line-height: 1.1; vertical-align: middle;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-shadow: inset 0 -4px 0 var(--accent);
      border-bottom: 0; border-radius: 0;
    }
    .f1site-cal__table tbody td{ padding-top: var(--cellPadY); padding-bottom: var(--cellPadY); padding-left: var(--padX); padding-right: var(--padX); font-size: 13px; vertical-align: top; background: #fff; }
    .f1site-cal__table tbody tr:nth-child(even) td{ background: #F2F2F2; }
    .f1site-cal__table tbody tr:hover td{ background: #fff !important; }
    .f1site-cal__table tbody tr:nth-child(even):hover td{ background: #F2F2F2 !important; }

    .f1site-cal__nr{ width: 52px; white-space: nowrap; color: var(--muted); font-weight: 800; font-size: var(--fs-nr); }
    .f1site-cal__status{ width: 100px; white-space: nowrap; text-align:center; }
    .f1site-cal__gpcol{ width: 265px; }
    .f1site-cal__sessionscol{ width: auto; }

    .f1site-cal__table thead th.f1site-cal__status{ text-align:center; }
    .f1site-cal__table tbody td.f1site-cal__status{ vertical-align: middle; }
    .f1site-cal__table thead th.f1site-cal__nr, .f1site-cal__table tbody td.f1site-cal__nr{ text-align: center; vertical-align: middle; padding-left: var(--padNrL); padding-right: var(--padNrR); }
    .f1site-cal__table thead th.f1site-cal__status, .f1site-cal__table tbody td.f1site-cal__status{ padding-left: var(--padStatusL); padding-right: var(--padStatusR); }
    .f1site-cal__table thead th.f1site-cal__gpcol, .f1site-cal__table tbody td.f1site-cal__gpcol{ padding-left: var(--padGpL); padding-right: var(--padGpR); }
    .f1site-cal__table thead th.f1site-cal__sessionscol, .f1site-cal__table tbody td.f1site-cal__sessionscol{ padding-left: var(--padSessionsL); padding-right: var(--padSessionsR); position: relative; }
    .f1site-cal__table thead th.f1site-cal__sessionscol::before{ content:""; position:absolute; left: 0; top: 6px; bottom: 6px; width: 0px; background: rgba(255,255,255,.18); pointer-events: none; }
    .f1site-cal__table tbody td.f1site-cal__sessionscol::before{ content:""; position:absolute; left: 0; top: 15px; bottom: 15px; width: 1px; background: rgba(0,0,0,.08); pointer-events: none; }

    .f1site-cal__gp{ display: inline-flex; align-items: center; gap: 8px; font-weight: 900; color: var(--accent); line-height: var(--stack-lh); min-width: 0; flex-wrap: nowrap; font-size: var(--fs-gp); }
    .f1site-cal__gp > span{ display:inline-block; min-width:0; }
    .f1site-cal__flag{ width: 20px; height: 12px; object-fit: cover; display: inline-block; vertical-align: middle; border-radius: 0; border: 1px solid rgba(0,0,0,.25); box-shadow: none; flex: 0 0 auto; }
    .f1site-cal__sub{ display:block; margin-top: var(--stack-gap); color: var(--muted); font-weight: 700; font-size: var(--fs-sub); line-height: var(--stack-lh); }
    .f1site-cal__format{ display:block; margin-top: var(--stack-gap); font-size: var(--fs-format); font-weight: 900; letter-spacing: .06em; text-transform: uppercase; color: rgba(0,0,0,.55); line-height: var(--stack-lh); }

    .f1cal-status{ --scol: var(--accent); display:inline-flex; flex-direction: column; align-items:center; justify-content:center; gap: 6px; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; font-size: 11px; line-height: 1; color: var(--scol); white-space: nowrap; user-select:none; }
    .f1cal-status__icon{ width: 28px; height: 18px; position: relative; display:flex; align-items:center; justify-content:center; background: transparent; border: 0; border-radius: 0; padding: 0; margin: 0; }
    .f1cal-status__text{ font-size: var(--fs-status-text); letter-spacing: .08em; }
    .f1cal-status--live{ --scol: var(--accent); }
    .f1cal-live-dot{ width: 7px; height: 7px; border-radius: 999px; background: var(--scol); box-shadow: 0 0 0 2px rgba(224,0,120,.12); animation: f1cal_live_pulse 1.05s ease-in-out infinite; will-change: transform, opacity; }
    @keyframes f1cal_live_pulse{ 0%,100%{ transform: scale(1); opacity: .85; } 50%{ transform: scale(1.35); opacity: 1; } }
    .f1cal-status--next{ --scol: var(--accent); }
    .f1cal-next-arrows{ display:inline-flex; align-items:center; gap: 4px; height: 12px; transform: translateX(-2px); }
    .f1cal-next-chev{ width: 8px; height: 8px; border-right: 3px solid var(--scol); border-bottom: 3px solid var(--scol); transform: rotate(-45deg); opacity: .18; animation: f1cal_next_fade 1.15s ease-in-out infinite; will-change: opacity; }
    .f1cal-next-chev:nth-child(1){ animation-delay: 0s; } .f1cal-next-chev:nth-child(2){ animation-delay: .18s; } .f1cal-next-chev:nth-child(3){ animation-delay: .36s; }
    @keyframes f1cal_next_fade{ 0%{ opacity: .18; } 18%{ opacity: 1; } 45%{ opacity: .18; } 100%{ opacity: .18; } }
    .f1cal-status--cancelled{ --scol: rgba(0,0,0,.62); }
    .f1cal-cancel-svg{ width: 18px; height: 16px; display:block; color: var(--scol); animation: f1cal_cancel_wobble 1.35s ease-in-out infinite; transform-origin: 50% 60%; will-change: transform; }
    @keyframes f1cal_cancel_wobble{ 0%,100%{ transform: rotate(0deg); } 50%{ transform: rotate(-6deg); } }
    .f1cal-status--completed{ --scol: rgba(0,0,0,.62); }
    .f1cal-flag-svg{ width: 22px; height: 18px; display:block; color: var(--scol); }
    @media (prefers-reduced-motion: reduce){ .f1cal-live-dot, .f1cal-next-chev, .f1cal-cancel-svg{ animation: none !important; } }

    .f1site-cal__table tr.is-live td{ background: rgba(224,0,120,.14) !important; box-shadow: inset 0 0 0 2px rgba(224,0,120,.18); }
    .f1site-cal__table tr.is-next td{ background: rgba(224,0,120,.08) !important; }
    .f1site-cal__table tr.is-live td:first-child, .f1site-cal__table tr.is-next td:first-child{ position: relative; }
    .f1site-cal__table tr.is-live td:first-child::before, .f1site-cal__table tr.is-next td:first-child::before{ content:""; position:absolute; left:0; top:0; bottom:0; width: 4px; background: var(--accent); }
    .f1site-cal__table tr.is-cancelled td{ opacity: .55; text-decoration: line-through; text-decoration-thickness: 2px; }
    .f1site-cal__table tr.is-completed td{ opacity: .72; }

    .f1cal-days{ display: grid; grid-template-columns: repeat(var(--f1cal-cols, 3), minmax(0, 1fr)); gap: 14px; align-items: start; }
    .f1cal-daycol{ position: relative; padding-right: 14px; }
    .f1cal-daycol:not(:last-child)::after{ content:""; position:absolute; top: 4px; right: 0; bottom: 4px; width: 1px; background: rgba(0,0,0,.08); pointer-events:none; }
    .f1cal-daycol__date{ font-size: var(--fs-day-date); font-weight: 900; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: var(--stack-gap); }
    .f1cal-daycol__line{ display: grid; grid-template-columns: 86px 1fr; gap: 10px; line-height: var(--stack-lh); padding: 1px 0; }
    .f1cal-daycol__time{ font-size: var(--fs-day-time); font-weight: 900; letter-spacing: .03em; text-transform: uppercase; color: rgba(0,0,0,.72); white-space: nowrap; }
    .f1cal-daycol__name{ font-size: var(--fs-day-name); font-weight: 900; letter-spacing: .01em; color: rgba(0,0,0,.92); }

    .f1cal-cardswrap{ display:none; }
    @media (max-width: 1280px){
      .f1site-cal__card{ display:none; } .f1cal-cardswrap{ display:block; }
      .f1site-cal{ --fs-nr: 12px; --fs-status-text: 11px; --fs-gp: 12px; --fs-sub: 12px; --fs-format: 11px; --fs-day-date: 11px; --fs-day-time: 12px; --fs-day-name: 12px; --fs-card-label: 11px; --fs-card-sessionshead: 11px; }
    }
    .f1cal-cards{ padding: 0 12px; display: grid; gap: 12px; }
    .f1cal-card{ background: #fff; border: 0; border-radius: 0; box-shadow: 0 10px 26px rgba(0,0,0,.06); padding: 12px; }
    .f1cal-card.is-live{ outline: 2px solid rgba(224,0,120,.22); background: rgba(224,0,120,.06); }
    .f1cal-card.is-next{ outline: 2px solid rgba(224,0,120,.12); background: rgba(224,0,120,.03); }
    .f1cal-card.is-completed{ opacity: .82; }
    .f1cal-card__top{ display:flex; align-items:center; justify-content: space-between; gap: 10px; padding: 2px 2px 10px; border-bottom: 1px solid rgba(0,0,0,.08); margin-bottom: 10px; }
    .f1cal-card__nr{ display:flex; align-items: baseline; gap: 8px; font-weight: 900; }
    .f1cal-card__nrlabel{ font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: rgba(0,0,0,.60); font-weight: 900; }
    .f1cal-card__nrval{ font-size: var(--fs-nr); color: rgba(0,0,0,.92); }
    .f1cal-card__status{ display:flex; align-items:center; }
    .f1cal-card__block{ margin-top: 10px; }
    .f1cal-card__label{ font-size: var(--fs-card-label); letter-spacing: .08em; text-transform: uppercase; color: rgba(0,0,0,.55); font-weight: 900; margin-bottom: 4px; }
    .f1cal-card__value{ font-weight: 900; font-size: 16px; color: rgba(0,0,0,.95); line-height: 1.2; }
    .f1cal-card__gp{ display:flex; align-items:center; gap: 10px; flex-wrap: nowrap; min-width: 0; }
    .f1cal-card__gpname{ font-size: var(--fs-gp); display:inline-block; min-width:0; overflow:hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--accent); }
    .f1cal-card__flag{ width: 26px; height: 16px; object-fit: cover; border: 1px solid rgba(0,0,0,.25); }
    .f1cal-card__format{ margin-top: 6px; font-size: var(--fs-format); font-weight: 900; letter-spacing: .06em; text-transform: uppercase; color: rgba(0,0,0,.60); }
    .f1cal-card__sessions{ margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,.08); }
    .f1cal-card__sessionshead{ font-size: var(--fs-card-sessionshead); font-weight: 900; letter-spacing: .08em; text-transform: uppercase; color: rgba(0,0,0,.55); margin-bottom: 8px; }

    @media (max-width: 1280px){
      .f1cal-days{ grid-template-columns: 1fr; gap: 10px; } .f1cal-daycol{ padding-right: 0; } .f1cal-daycol:not(:last-child)::after{ display:none; }
      .f1cal-daycol{ border: 1px solid rgba(0,0,0,.08); padding: 10px; background: rgba(0,0,0,.015); }
    }
</style>

<div class="f1site-cal">
  <div class="f1site-cal__card">
    <div class="f1site-cal__scroll">
      <table class="f1site-cal__table" aria-label="Formel 1 Rennkalender">
        <thead>
          <tr>
            <th class="f1site-cal__nr">Nr.</th>
            <th class="f1site-cal__status">Status</th>
            <th class="f1site-cal__gpcol">Grand Prix</th>
            <th class="f1site-cal__sessionscol">Sessions (Deutsche Zeit)</th>
          </tr>
        </thead>
        <tbody>
        <?php
        if ( empty( $races ) ) {
            echo '<tr><td class="f1site-cal__nr">–</td><td class="f1site-cal__status">–</td><td class="f1site-cal__gpcol">Keine Einträge</td><td class="f1site-cal__sessionscol">–</td></tr>';
        } else {
            $i = 1;
            foreach ( $races as $race ) {
                $pid  = (int) $race->ID;
                $m    = $this->get_meta( $pid );
                $stat = $this->sanitize_status( ! empty( $m['status'] ) ? $m['status'] : 'none' );
                $wt   = $this->sanitize_weekend_type( ! empty( $m['weekend_type'] ) ? $m['weekend_type'] : 'normal' );

                $row_class = '';
                if ( $stat === 'live' ) $row_class = 'is-live';
                if ( $stat === 'next' ) $row_class = 'is-next';
                if ( $stat === 'cancelled' ) $row_class = 'is-cancelled';
                if ( $stat === 'completed' ) $row_class = 'is-completed';

                $status_html = $this->render_status_badge( $stat );
                $gp = ( $m['gp'] !== '' ) ? $m['gp'] : get_the_title( $pid );
                $circuit = ( $m['circuit'] !== '' ) ? $m['circuit'] : '';
                $flag_url = ! empty( $m['flag_url'] ) ? $m['flag_url'] : '';
                $format_label = ( $wt === 'sprint' ) ? 'Sprintformat' : 'Klassisches Format';
                $sessions_html = $this->render_sessions_columns( $m );
                $nr = ( isset( $nrmap[$pid] ) ) ? (int) $nrmap[$pid] : (int) $i;
                ?>
                <tr class="<?php echo esc_attr( $row_class ); ?>">
                  <td class="f1site-cal__nr"><?php echo esc_html( $nr . '.' ); ?></td>
                  <td class="f1site-cal__status"><?php echo $status_html; ?></td>
                  <td class="f1site-cal__gpcol">
                    <span class="f1site-cal__gp">
                      <span><?php echo esc_html( $gp ); ?></span>
                      <?php if ( $flag_url !== '' ) : ?>
                        <img class="f1site-cal__flag" src="<?php echo esc_url( $flag_url ); ?>" alt="" loading="lazy">
                      <?php endif; ?>
                    </span>
                    <?php if ( $circuit !== '' ) : ?>
                      <span class="f1site-cal__sub"><?php echo esc_html( $circuit ); ?></span>
                    <?php endif; ?>
                    <span class="f1site-cal__format"><?php echo esc_html( $format_label ); ?></span>
                  </td>
                  <td class="f1site-cal__sessionscol"><?php echo $sessions_html; ?></td>
                </tr>
                <?php
                $i++;
            }
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="f1cal-cardswrap">
    <?php echo $this->render_cards( $races, $nrmap ); ?>
  </div>
  <?php
    if ( current_user_can( F1_Manager_Calendar::CAPABILITY ) ) {
        $this->render_admin_panel( $races, $nonce, $media );
    }
  ?>
</div>
