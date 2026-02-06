<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class F1_Ticker {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );

		add_shortcode( 'f1_wm_ticker', array( $this, 'render_shortcode' ) );

		add_action( 'wp_ajax_f1wmt_auto_fetch', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_f1wmt_auto_fetch', array( $this, 'handle_ajax' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/* =========================================================
	   ADMIN
	   ========================================================= */

	public function register_admin_menu() {
		add_menu_page(
			'Ticker',
			'Ticker',
			'manage_options',
			'f1-ticker',
			array( $this, 'render_admin_page' ),
			'dashicons-controls-repeat',
			34
		);
	}

	public function render_admin_page() {
		require_once plugin_dir_path( __FILE__ ) . 'admin/ticker-admin.php';
	}

	public function handle_save() {
		if ( ! is_admin() ) return;
		if ( ! isset( $_POST['f1wmt_save'] ) ) return;
		if ( ! check_admin_referer( 'f1wmt_save_options' ) ) wp_die( 'Nonce fail' );

		$sD = isset( $_POST['speed_drivers'] ) ? (int)$_POST['speed_drivers'] : 80000;
		$sT = isset( $_POST['speed_teams'] ) ? (int)$_POST['speed_teams'] : 80000;

		if ( $sD < 5000 ) $sD = 80000;
		if ( $sT < 5000 ) $sT = 80000;

		update_option( 'f1_ticker_speed_drivers', $sD );
		update_option( 'f1_ticker_speed_teams', $sT );

		wp_safe_redirect( add_query_arg( 'msg', 'saved', admin_url( 'admin.php?page=f1-ticker' ) ) );
		exit;
	}

	/* =========================================================
	   FRONTEND
	   ========================================================= */

	public function enqueue_assets() {
		wp_enqueue_style( 'f1-ticker', plugin_dir_url( __FILE__ ) . '../assets/css/f1-ticker.css', array(), '1.0.0' );
		wp_enqueue_script( 'f1-ticker', plugin_dir_url( __FILE__ ) . '../assets/js/f1-ticker.js', array( 'jquery' ), '1.0.0', true );

		$sD = (int)get_option( 'f1_ticker_speed_drivers', 80000 );
		$sT = (int)get_option( 'f1_ticker_speed_teams', 80000 );

		wp_localize_script( 'f1-ticker', 'f1_ticker_cfg', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'f1wmt_auto_nonce' ),
			'speedDrivers' => $sD,
			'speedTeams' => $sT,
		) );
	}

	public function render_shortcode( $atts ) {
		// Use default speed for initial render (Drivers)
		$sD = (int)get_option( 'f1_ticker_speed_drivers', 80000 );

		ob_start();
		?>
		<div class="banner-exclusive-posts-wrapper f1wmt-banner-exclusive-posts-wrapper" data-f1wmt-mode="drivers">
			<div class="container-wrapper">
				<div class="exclusive-posts f1wmt-exclusive-posts">
					<div class="exclusive-now primary-color">
						<div class="aft-box-ripple f1wmt-trophyWrap" aria-hidden="true">
							<svg class="f1wmt-trophy" viewBox="0 0 64 64" role="img" focusable="false" aria-hidden="true">
								<path class="f1wmt-trophy-cup" d="M22 12h20v10c0 8-4.8 14.6-10 16.7V42h6v6H26v-6h6v-3.3C26.8 36.6 22 30 22 22V12z"/>
								<path class="f1wmt-trophy-handle" d="M22 16h-6c0 9 4 14 10 16v-6c-3.4-1.5-4-5.7-4-10z"/>
								<path class="f1wmt-trophy-handle" d="M42 16h6c0 9-4 14-10 16v-6c3.4-1.5 4-5.7 4-10z"/>
								<path class="f1wmt-trophy-base" d="M20 50h24v8H20v-8z"/>
								<path class="f1wmt-trophy-spark f1wmt-s1" d="M12 18l2 2-2 2-2-2 2-2z"/>
								<path class="f1wmt-trophy-spark f1wmt-s2" d="M54 18l2 2-2 2-2-2 2-2z"/>
								<path class="f1wmt-trophy-spark f1wmt-s3" d="M12 34l2 2-2 2-2-2 2-2z"/>
								<path class="f1wmt-trophy-spark f1wmt-s4" d="M54 34l2 2-2 2-2-2 2-2z"/>
							</svg>
						</div>

						<span class="f1wmt-nowLabel" data-drivers="FAHRER WM" data-teams="TEAM WM">
							<span class="f1wmt-nowText">FAHRER WM</span>
							<button type="button" class="f1wmt-modeBtn" aria-label="Team-WM anzeigen" title="Team-WM anzeigen">
								<svg class="f1wmt-modeIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
									<g class="f1wmt-arrowUp">
										<path d="M7 21V5" /><path d="M7 5L3 9" /><path d="M7 5L11 9" />
									</g>
									<g class="f1wmt-arrowDown">
										<path d="M17 3v16" /><path d="M17 19l-4-4" /><path d="M17 19l4-4" />
									</g>
								</svg>
							</button>
						</span>
					</div>

					<div class="exclusive-slides" dir="ltr">
						<div class="f1wmt-marquee aft-flash-slide left" data-speed="<?php echo esc_attr( $sD ); ?>" data-direction="left">
							<div class="f1wmt-marquee-viewport">
								<div class="f1wmt-marquee-track">
									<div class="f1wmt-marquee-group">
										<span class="f1wmt-loading">Lade WM-Ticker …</span>
									</div>
								</div>
							</div>
						</div>
					</div><!-- .exclusive-slides -->
				</div><!-- .exclusive-posts -->
			</div><!-- .container-wrapper -->
		</div><!-- .banner-exclusive-posts-wrapper -->
		<?php
		return ob_get_clean();
	}

	/* =========================================================
	   AJAX
	   ========================================================= */

	public function handle_ajax() {
		check_ajax_referer( 'f1wmt_auto_nonce', 'nonce' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'drivers';
		$mode = ( $mode === 'teams' ) ? 'teams' : 'drivers';

		// Get Cached or Fresh Data
		$key = ( $mode === 'teams' ) ? 'f1wmt_rows_teams' : 'f1wmt_rows_drivers';
		$rows = get_transient( $key );

		if ( ! is_array( $rows ) ) {
			if ( $mode === 'teams' ) {
				$rows = $this->build_team_rows();
			} else {
				$rows = $this->build_driver_rows();
			}
			set_transient( $key, $rows, 60 ); // Cache 60s
		}

		$html = $this->render_items_html( $rows );

		wp_send_json_success( array(
			'html' => $html,
			'count' => count( $rows ),
			'mode' => $mode,
			'updated' => time(),
		) );
	}

	/* =========================================================
	   LOGIC (Adapted from Legacy)
	   ========================================================= */

	private function build_driver_rows() {
		if ( ! class_exists( 'F1_WM_Stand' ) ) return array();

		$stand = F1_WM_Stand::compute_standings();
		if ( empty( $stand['drivers'] ) ) return array();

		$races = $stand['races'];
		$drivers = $stand['drivers'];

		// Completed races IDs
		$completed = array();
		foreach ( $races as $r ) {
			$rid = (int)$r['id'];
			// Check if any driver has data for this race
			foreach ( $drivers as $d ) {
				if ( isset( $d['by_race'][$rid] ) ) { $completed[] = $rid; break; }
			}
		}

		$last_rid = ! empty( $completed ) ? (int)end( $completed ) : 0;
		$prev_rid = ( count( $completed ) >= 2 ) ? (int)$completed[ count( $completed ) - 2 ] : 0;

		$totals_curr = array();
		$totals_prev = array();

		foreach ( $drivers as $d ) {
			$name = $d['driver'];
			$curr = (int)$d['total'];
			$prev = $curr;
			if ( $prev_rid && $last_rid ) {
				$last_pts = isset( $d['by_race'][$last_rid] ) ? (int)$d['by_race'][$last_rid] : 0;
				$prev = $curr - $last_pts;
			}
			$totals_curr[$name] = $curr;
			$totals_prev[$name] = $prev;
		}

		$pos_curr = $this->positions_from_totals( $totals_curr );
		$pos_prev = $prev_rid ? $this->positions_from_totals( $totals_prev ) : $pos_curr;

		// Sort by current pos
		$names = array_keys( $pos_curr );
		usort( $names, function( $a, $b ) use ( $pos_curr ) {
			return $pos_curr[$a] <=> $pos_curr[$b];
		} );

		$rows = array();
		foreach ( $names as $name ) {
			$p_curr = $pos_curr[$name];
			$p_prev = $pos_prev[$name] ?? $p_curr;

			$trend = 'same';
			if ( $p_curr < $p_prev ) $trend = 'up';
			elseif ( $p_curr > $p_prev ) $trend = 'down';

			// Get Driver Meta via F1_WM_Stand helper logic or raw query
			// F1_WM_Stand doesn't expose meta easily publicly except names.
			// Re-implementing minimal lookup logic using F1_WM_Stand::get_driver_directory keys if needed
			// But simpler: Query post by title (F1_WM_Stand does this for canonicalization).
			// Here we need Image + Team Name.
			// Using direct WP_Query for drivers to match name.

			$info = $this->get_driver_info( $name ); // Helper

			$rows[] = array(
				'position' => $p_curr,
				'name' => $name,
				'team' => $info['team'],
				'points' => $totals_curr[$name],
				'trend' => $trend,
				'img_url' => $info['img'],
				'link' => $info['link'],
				'item_mode' => 'drivers',
			);
		}
		return $rows;
	}

	private function build_team_rows() {
		if ( ! class_exists( 'F1_WM_Stand' ) ) return array();

		$stand = F1_WM_Stand::compute_team_standings_auto();
		if ( empty( $stand['teams'] ) ) return array();

		$races = $stand['races'];
		$teams = $stand['teams'];

		$completed = array();
		foreach ( $races as $r ) {
			$rid = (int)$r['id'];
			foreach ( $teams as $t ) {
				if ( isset( $t['by_race'][$rid] ) ) { $completed[] = $rid; break; }
			}
		}

		$last_rid = ! empty( $completed ) ? (int)end( $completed ) : 0;
		$prev_rid = ( count( $completed ) >= 2 ) ? (int)$completed[ count( $completed ) - 2 ] : 0;

		$totals_curr = array();
		$totals_prev = array();

		foreach ( $teams as $t ) {
			$name = $t['team'];
			$curr = (int)$t['total'];
			$prev = $curr;
			if ( $prev_rid && $last_rid ) {
				$last_pts = isset( $t['by_race'][$last_rid] ) ? (int)$t['by_race'][$last_rid] : 0;
				$prev = $curr - $last_pts;
			}
			$totals_curr[$name] = $curr;
			$totals_prev[$name] = $prev;
		}

		$pos_curr = $this->positions_from_totals( $totals_curr );
		$pos_prev = $prev_rid ? $this->positions_from_totals( $totals_prev ) : $pos_curr;

		$names = array_keys( $pos_curr );
		usort( $names, function( $a, $b ) use ( $pos_curr ) {
			return $pos_curr[$a] <=> $pos_curr[$b];
		} );

		$rows = array();
		foreach ( $names as $name ) {
			$p_curr = $pos_curr[$name];
			$p_prev = $pos_prev[$name] ?? $p_curr;

			$trend = 'same';
			if ( $p_curr < $p_prev ) $trend = 'up';
			elseif ( $p_curr > $p_prev ) $trend = 'down';

			$info = $this->get_team_info( $name );

			$rows[] = array(
				'position' => $p_curr,
				'name' => $name,
				'team' => '',
				'points' => $totals_curr[$name],
				'trend' => $trend,
				'img_url' => $info['img'],
				'link' => $info['link'],
				'item_mode' => 'teams',
			);
		}
		return $rows;
	}

	private function positions_from_totals( $totals ) {
		$rows = array();
		foreach ( $totals as $n => $p ) $rows[] = array( 'n' => $n, 'p' => $p );
		usort( $rows, function( $a, $b ) {
			if ( $a['p'] !== $b['p'] ) return $b['p'] <=> $a['p'];
			return strcasecmp( $a['n'], $b['n'] );
		} );
		$pos = array(); $i = 1;
		foreach ( $rows as $r ) { $pos[$r['n']] = $i; $i++; }
		return $pos;
	}

	/* --- Info Helpers --- */

	private function get_driver_info( $name ) {
		// Attempt to match driver post
		$pid = 0;
		// Try exact title match first
		$p = get_page_by_title( $name, OBJECT, 'f1_driver' );
		if ( $p ) $pid = $p->ID;
		else {
			// Try F1_WM_Stand directory lookup if possible, but simplified here:
			// Loop through all drivers and match normalized
			$drivers = get_posts( array( 'post_type' => 'f1_driver', 'numberposts' => -1 ) );
			foreach ( $drivers as $d ) {
				// Simple check
				if ( stripos( $d->post_title, $name ) !== false ) { $pid = $d->ID; break; }
			}
		}

		$img = '';
		$link = '';
		$team = '—';

		if ( $pid ) {
			$meta = get_post_meta( $pid ); // get all
			// Image
			if ( ! empty( $meta['_f1drv_img'][0] ) ) {
				$img = $this->driver_img_url( $meta['_f1drv_img'][0] );
			}
			// Link
			$link = get_permalink( $pid );
			// Team
			if ( ! empty( $meta['_f1drv_team_id'][0] ) ) {
				$tid = (int)$meta['_f1drv_team_id'][0];
				if ( $tid ) $team = get_the_title( $tid );
			}
		}
		return array( 'img' => $img, 'link' => $link, 'team' => $team );
	}

	private function get_team_info( $name ) {
		$pid = 0;
		$p = get_page_by_title( $name, OBJECT, 'f1_team' );
		if ( $p ) $pid = $p->ID;
		else {
			$teams = get_posts( array( 'post_type' => 'f1_team', 'numberposts' => -1 ) );
			foreach ( $teams as $t ) {
				if ( stripos( $t->post_title, $name ) !== false ) { $pid = $t->ID; break; }
			}
		}

		$img = '';
		$link = '';

		if ( $pid ) {
			$link = get_permalink( $pid );
			$car = get_post_meta( $pid, '_f1team_carimg', true );
			if ( $car ) {
				// Car Img Helper
				$u = wp_upload_dir();
				$img = $u['baseurl'] . '/cars/' . rawurlencode( basename( $car ) );
			}
		}
		return array( 'img' => $img, 'link' => $link );
	}

	private function driver_img_url( $filename ) {
		// Mimic f1wmt_auto_driver_img_url_best logic simplified
		$u = wp_upload_dir();
		$base = $u['baseurl'];
		$path = $u['basedir'];

		// Check 40px
		if ( file_exists( $path . '/driver/40px/' . $filename ) ) return $base . '/driver/40px/' . $filename;
		// Check 440px
		if ( file_exists( $path . '/driver/440px/' . $filename ) ) return $base . '/driver/440px/' . $filename;

		return '';
	}

	/* --- Render Item HTML --- */

	private function render_items_html( $rows ) {
		if ( empty( $rows ) ) return '';
		ob_start();
		foreach ( $rows as $r ) {
			$pos = (int)$r['position'];
			$name = (string)$r['name'];
			$pts = (int)$r['points'];
			$trend = $r['trend'];
			$img = $r['img_url'];
			$href = $r['link'];
			$mode = $r['item_mode'];

			$cls = 'f1wmt-driver';
			if ( ! $href ) { $cls .= ' f1wmt-nolink'; $href = '#'; }
			if ( $mode === 'teams' ) $cls .= ' f1wmt-item--teams';

			$trend_icon = '';
			if ( $trend === 'up' ) $trend_icon = '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 5l7 8h-4v6H9v-6H5l7-8z"/></svg>';
			elseif ( $trend === 'down' ) $trend_icon = '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 19l-7-8h4V5h6v6h4l-7 8z"/></svg>';
			else $trend_icon = '<svg viewBox="0 0 24 24" width="10" height="10" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="6" fill="currentColor"/></svg>';

			$trend_cls = 'f1wmt-trend--' . $trend;
			?>
			<a class="<?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( $href ); ?>" data-href="<?php echo esc_attr( $href ); ?>" <?php if(!$href || $href==='#') echo 'data-nolink="1"'; ?>>
				<span class="f1wmt-rank"><?php echo (int)$pos; ?>.</span>
				<span class="circle-marq">
					<?php if ( $img ): ?>
						<img loading="lazy" width="40" height="40" src="<?php echo esc_url( $img ); ?>" alt="" decoding="async">
					<?php else: ?>
						<span class="f1wmt-noimg">—</span>
					<?php endif; ?>
				</span>
				<span class="f1wmt-info">
					<span class="f1wmt-nameRow">
						<span class="f1wmt-name"><?php echo esc_html( $name ); ?></span>
						<span class="f1wmt-trend <?php echo esc_attr( $trend_cls ); ?>">
							<?php echo $trend_icon; ?>
						</span>
					</span>
					<span class="f1wmt-points">
						<strong><?php echo (int)$pts; ?></strong> Punkte
					</span>
				</span>
			</a>
			<?php
		}
		return ob_get_clean();
	}
}
