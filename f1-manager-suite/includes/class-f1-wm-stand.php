<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'F1WMS_CAPABILITY' ) ) {
	define( 'F1WMS_CAPABILITY', 'manage_f1_wm_stand' );
}

if ( ! defined( 'F1WMS_JSON_FLAGS' ) ) {
	define( 'F1WMS_JSON_FLAGS', JSON_UNESCAPED_UNICODE );
}

class F1_WM_Stand {

	const DRIVER_DIR_VER = 'v2';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'add_capabilities' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		add_shortcode( 'f1_wm_stand', array( $this, 'shortcode_driver_standings' ) );
		add_shortcode( 'f1_team_wm_stand', array( $this, 'shortcode_team_standings' ) );
	}

	public function add_capabilities() {
		if ( ! is_admin() ) return;
		$roles = array( 'administrator', 'editor', 'author' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( F1WMS_CAPABILITY ) ) {
				$role->add_cap( F1WMS_CAPABILITY );
			}
		}
	}

	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'f1-wm-stand', plugin_dir_url( __FILE__ ) . '../assets/css/f1-wm-stand.css', array(), '1.0.0' );
		wp_enqueue_script( 'f1-wm-stand', plugin_dir_url( __FILE__ ) . '../assets/js/f1-wm-stand.js', array(), '1.0.0', true );
	}

	/* =========================================================
	   ADMIN
	   ========================================================= */

	public function register_admin_menu() {
		add_menu_page(
			'WM-Stand',
			'WM-Stand',
			F1WMS_CAPABILITY,
			'f1-wm-stand',
			array( $this, 'render_admin_page' ),
			'dashicons-plus-alt',
			29
		);
	}

	public function render_admin_page() {
		require_once plugin_dir_path( __FILE__ ) . 'admin/wm-stand-admin.php';
	}

	public function handle_admin_actions() {
		if ( ! is_admin() ) return;
		if ( ! current_user_can( F1WMS_CAPABILITY ) ) return;

		// 1. Driver Directory Refresh
		if ( ! empty( $_POST['f1wms_dir_action'] ) && $_POST['f1wms_dir_action'] === 'refresh_driver_directory' ) {
			if ( ! wp_verify_nonce( $_POST['f1wms_dir_nonce'] ?? '', 'f1wms_dir_refresh' ) ) wp_die( 'Nonce fail' );

			$ver  = self::DRIVER_DIR_VER;
			$tKey = 'f1wms_driver_directory_' . $ver;
			$oKey = 'f1wms_driver_directory_' . $ver;
			$oTs  = 'f1wms_driver_directory_' . $ver . '_ts';

			delete_transient( $tKey );
			delete_option( $oKey );
			delete_option( $oTs );

			$dir = self::get_driver_directory();
			$count = is_array( $dir ) ? count( $dir ) : 0;
			$msg = $count > 0 ? "Fahrer-Referenz aktualisiert ($count Einträge)." : "Fehler: 0 Einträge.";

			$redirect = add_query_arg( array(
				'page' => 'f1-wm-stand',
				'race_id' => isset($_POST['race_id']) ? absint($_POST['race_id']) : null,
				'f1wms_notice' => rawurlencode( $msg ),
			), admin_url( 'admin.php' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		// 2. Data Actions
		if ( empty( $_POST['f1wms_action'] ) ) return;
		if ( isset( $_GET['page'] ) && $_GET['page'] !== 'f1-wm-stand' ) return;

		$action = sanitize_key( $_POST['f1wms_action'] );
		if ( ! wp_verify_nonce( $_POST['f1wms_nonce'] ?? '', 'f1wms_admin_save' ) ) wp_die( 'Nonce fail' );

		$race_id = isset( $_POST['race_id'] ) ? absint( $_POST['race_id'] ) : 0;
		$slug    = isset( $_POST['session_slug'] ) ? sanitize_key( $_POST['session_slug'] ) : '';

		if ( $action === 'reset_all' ) {
			$all_slugs = array( 'fp1', 'fp2', 'fp3', 'sq', 'sprint_grid', 'sprint', 'quali', 'grid', 'race' );
			$races = self::get_races();
			$del_count = 0;
			foreach ( $races as $r ) {
				$rid = (int)$r['id'];
				if ( ! $rid ) continue;
				foreach ( $all_slugs as $s ) {
					delete_post_meta( $rid, self::meta_key_rows( $s ) );
					delete_post_meta( $rid, self::meta_key_raw( $s ) );
					$del_count++;
				}
			}
			wp_safe_redirect( add_query_arg( array(
				'page' => 'f1-wm-stand',
				'f1wms_notice' => rawurlencode("Reset done (Sessions deleted: $del_count).")
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! $race_id || $slug === '' ) return;

		if ( $action === 'import' ) {
			$raw = isset( $_POST['f1wms_raw'] ) ? wp_unslash( $_POST['f1wms_raw'] ) : '';
			$rows = self::parse_dataset( $raw, $slug );
			self::save_session_results( $race_id, $slug, $rows, $raw );
			wp_safe_redirect( add_query_arg( array(
				'page' => 'f1-wm-stand', 'race_id' => $race_id, 'f1wms_notice' => rawurlencode( "Importiert: $slug" )
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $action === 'save_manual' ) {
			$rows_in = isset( $_POST['f1wms_rows'] ) ? (array)$_POST['f1wms_rows'] : array();
			$del = isset( $_POST['f1wms_delete_rows'] ) ? array_map( 'absint', (array)$_POST['f1wms_delete_rows'] ) : array();
			$rows = array();
			foreach ( $rows_in as $i => $r ) {
				if ( in_array( (int)$i, $del, true ) ) continue;
				$rows[] = is_array( $r ) ? $r : array();
			}
			self::save_session_results( $race_id, $slug, $rows, null );
			wp_safe_redirect( add_query_arg( array(
				'page' => 'f1-wm-stand', 'race_id' => $race_id, 'f1wms_notice' => rawurlencode( "Gespeichert: $slug" )
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $action === 'delete_session' ) {
			delete_post_meta( $race_id, self::meta_key_rows( $slug ) );
			delete_post_meta( $race_id, self::meta_key_raw( $slug ) );
			wp_safe_redirect( add_query_arg( array(
				'page' => 'f1-wm-stand', 'race_id' => $race_id, 'f1wms_notice' => rawurlencode( "Gelöscht: $slug" )
			), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/* =========================================================
	   SHORTCODES
	   ========================================================= */

	public function shortcode_driver_standings() {
		return $this->render_shortcode( 'drivers' );
	}

	public function shortcode_team_standings() {
		return $this->render_shortcode( 'teams' );
	}

	public function render_shortcode( $mode ) {
		$page_url = get_permalink();
		$page_url = set_url_scheme( $page_url, 'https' );
		$race_id  = isset( $_GET['race'] ) ? absint( $_GET['race'] ) : 0;
		$session  = isset( $_GET['session'] ) ? sanitize_key( $_GET['session'] ) : '';

		// Prepare Vars for View
		$view_mode = ''; // 'standings' or 'race'
		$data = array();

		if ( $race_id && get_post( $race_id ) && get_post_type( $race_id ) === 'f1_race' ) {
			// Detail View
			$view_mode = 'race';
			$race_title = get_the_title( $race_id );
			$sessions = self::get_sessions_for_race( $race_id );

			if ( $session === '' || empty( $sessions[$session] ) ) {
				$session = 'race';
				if ( empty( $sessions[$session] ) ) {
					$k = array_keys( $sessions );
					$session = ! empty( $k[0] ) ? $k[0] : '';
				}
			}

			// Load data for session
			$session_rows = self::get_session_results( $race_id, $session );
			$schema = self::session_schema( $session );

			$data = compact( 'race_id', 'race_title', 'sessions', 'session', 'session_rows', 'schema', 'page_url' );

		} else {
			// Standings View
			$view_mode = 'standings';
			if ( $mode === 'teams' ) {
				$calc = self::compute_team_standings_auto();
				$rows = $calc['teams'];
				$races = $calc['races'];
				$name_label = 'Team';
			} else {
				$calc = self::compute_standings();
				$rows = $calc['drivers'];
				$races = $calc['races'];
				$name_label = 'Fahrer';
			}
			$data = compact( 'mode', 'rows', 'races', 'name_label', 'page_url' );
		}

		ob_start();
		// Extract data to be available in view
		extract( $data );
		require plugin_dir_path( __FILE__ ) . 'frontend/wm-stand-view.php';
		return ob_get_clean();
	}

	/* =========================================================
	   HELPER & PARSER
	   ========================================================= */

	public static function meta_key_rows( $slug ) { return '_f1wms_rows_' . sanitize_key( $slug ); }
	public static function meta_key_raw( $slug ) { return '_f1wms_raw_' . sanitize_key( $slug ); }

	public static function get_races() {
		$posts = get_posts( array(
			'post_type'      => 'f1_race',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array( 'id' => (int)$p->ID, 'title' => get_the_title( $p->ID ) );
		}
		return $out;
	}

	public static function get_weekend_type( $race_id ) {
		$wt = (string)get_post_meta( $race_id, '_f1cal_weekend_type', true );
		return in_array( $wt, array( 'sprint', 'normal' ), true ) ? $wt : 'normal';
	}

	public static function get_flag_url( $race_id ) {
		$file = (string)get_post_meta( $race_id, '_f1cal_flag_file', true );
		$file = sanitize_file_name( $file );
		if ( $file === '' ) return '';
		if ( function_exists( 'f1cal_validate_flag_file' ) && function_exists( 'f1cal_flag_url' ) ) {
			$valid = f1cal_validate_flag_file( $file );
			if ( $valid !== '' ) return (string)f1cal_flag_url( $valid );
		}
		$u = wp_upload_dir();
		$base = $u['baseurl'] ?? '';
		return rtrim( $base, '/' ) . '/flags/' . rawurlencode( $file );
	}

	public static function get_sessions_for_race( $race_id ) {
		$wt = self::get_weekend_type( $race_id );
		$has = function( $k ) use ( $race_id ) { return trim( (string)get_post_meta( $race_id, $k, true ) ) !== ''; };
		$sessions = array();

		if ( $wt === 'normal' ) {
			if ( $has( '_f1cal_fp1_date' ) ) $sessions['fp1'] = array( 'label' => '1. Training', 'type_badge' => 'Training' );
			if ( $has( '_f1cal_fp2_date' ) ) $sessions['fp2'] = array( 'label' => '2. Training', 'type_badge' => 'Training' );
			if ( $has( '_f1cal_fp3_date' ) ) $sessions['fp3'] = array( 'label' => '3. Training', 'type_badge' => 'Training' );
			if ( $has( '_f1cal_quali_date' ) ) $sessions['quali'] = array( 'label' => 'Qualifying', 'type_badge' => 'Qualifying' );
			$sessions['grid'] = array( 'label' => 'Startaufstellung', 'type_badge' => 'Start' );
			if ( $has( '_f1cal_race_date' ) ) $sessions['race'] = array( 'label' => 'Rennen', 'type_badge' => 'Rennen' );
		}
		if ( $wt === 'sprint' ) {
			if ( $has( '_f1cal_fp1_date' ) ) $sessions['fp1'] = array( 'label' => '1. Training', 'type_badge' => 'Training' );
			if ( $has( '_f1cal_sq_date' ) ) $sessions['sq'] = array( 'label' => 'Sprint-Qualifying', 'type_badge' => 'Sprint' );
			$sessions['sprint_grid'] = array( 'label' => 'Sprint-Startaufstellung', 'type_badge' => 'Sprint' );
			if ( $has( '_f1cal_sprint_date' ) ) $sessions['sprint'] = array( 'label' => 'Sprint', 'type_badge' => 'Sprint' );
			if ( $has( '_f1cal_quali_date' ) ) $sessions['quali'] = array( 'label' => 'Qualifying', 'type_badge' => 'Qualifying' );
			$sessions['grid'] = array( 'label' => 'Startaufstellung', 'type_badge' => 'Start' );
			if ( $has( '_f1cal_race_date' ) ) $sessions['race'] = array( 'label' => 'Rennen', 'type_badge' => 'Rennen' );
		}
		return $sessions;
	}

	public static function session_schema( $slug ) {
		$slug = sanitize_key( $slug );
		$common = array(
			array( 'key'=>'pos',    'label'=>'Pos.' ),
			array( 'key'=>'driver', 'label'=>'Fahrer' ),
			array( 'key'=>'team',   'label'=>'Team' ),
			array( 'key'=>'laps',   'label'=>'Runden' ),
			array( 'key'=>'time',   'label'=>'Rundenzeit' ),
			array( 'key'=>'pts',    'label'=>'Pkt.' ),
		);
		$schema = array(
			'slug' => $slug, 'label' => $slug, 'points_mode' => 'none',
			'columns' => array( array('key'=>'pos','label'=>'Pos.'), array('key'=>'driver','label'=>'Driver'), array('key'=>'team','label'=>'Team') )
		);

		if ( in_array( $slug, array( 'fp1','fp2','fp3' ), true ) ) {
			$schema['label'] = 'Freies Training'; $schema['columns'] = $common;
			return $schema;
		}
		if ( in_array( $slug, array( 'quali','sq' ), true ) ) {
			$schema['label'] = ($slug === 'sq') ? 'Sprint-Qualifying' : 'Qualifying';
			$schema['columns'] = array(
				array('key'=>'pos','label'=>'Pos.'), array('key'=>'driver','label'=>'Fahrer'), array('key'=>'team','label'=>'Team'),
				array('key'=>'q1','label'=>'Q1'), array('key'=>'q2','label'=>'Q2'), array('key'=>'q3','label'=>'Q3'), array('key'=>'laps','label'=>'Runden'),
			);
			return $schema;
		}
		if ( in_array( $slug, array( 'grid','sprint_grid' ), true ) ) {
			$schema['label'] = ($slug === 'sprint_grid') ? 'Sprint-Startaufstellung' : 'Startaufstellung';
			$schema['columns'] = array(
				array('key'=>'pos','label'=>'Pos.'), array('key'=>'driver','label'=>'Fahrer'), array('key'=>'team','label'=>'Team'), array('key'=>'time','label'=>'Rundenzeit'),
			);
			return $schema;
		}
		if ( $slug === 'sprint' ) {
			$schema['label'] = 'Sprint'; $schema['columns'] = $common; $schema['points_mode'] = 'sprint';
			return $schema;
		}
		if ( $slug === 'race' ) {
			$schema['label'] = 'Rennen'; $schema['columns'] = $common; $schema['points_mode'] = 'race';
			return $schema;
		}
		return $schema;
	}

	public static function points_for_pos( $mode, $pos ) {
		$pos = (int)$pos; if ( $pos <= 0 ) return 0;
		if ( $mode === 'sprint' ) {
			$map = array( 1=>8, 2=>7, 3=>6, 4=>5, 5=>4, 6=>3, 7=>2, 8=>1 );
			return $map[$pos] ?? 0;
		}
		if ( $mode === 'race' ) {
			$map = array( 1=>25, 2=>18, 3=>15, 4=>12, 5=>10, 6=>8, 7=>6, 8=>4, 9=>2, 10=>1 );
			return $map[$pos] ?? 0;
		}
		return 0;
	}

	/* --- Parser --- */

	public static function decode_u_escapes( $s ) {
		$s = (string)$s;
		if ( $s === '' ) return $s;
		if ( stripos( $s, 'u00' ) === false && strpos( $s, '\\u' ) === false ) return $s;
		$s = preg_replace_callback( '~\\\\u([0-9a-fA-F]{4})~', function( $m ){ return html_entity_decode( '&#x'.$m[1].';', ENT_NOQUOTES, 'UTF-8' ); }, $s );
		$s = preg_replace_callback( '~u([0-9a-fA-F]{4})~', function( $m ){ return html_entity_decode( '&#x'.$m[1].';', ENT_NOQUOTES, 'UTF-8' ); }, $s );
		return $s;
	}

	public static function normalize_pos( $pos ) {
		$pos = strtoupper( trim( (string)$pos ) );
		$pos = str_replace( array('.',','), '', $pos );
		if ( preg_match( '~^\d+~', $pos, $m ) ) return (string)((int)$m[0]);
		$allowed = array( 'NC','DNF','DNS','DQ','DSQ','EX','EXCLUDED','RET','R' );
		if ( in_array( $pos, $allowed, true ) ) return ($pos === 'EXCLUDED') ? 'EX' : $pos;
		if ( $pos === 'DISQ' || $pos === 'DISQUALIFIED' ) return 'DSQ';
		$pos = preg_replace( '~\s+~', '', $pos );
		return substr( $pos, 0, 8 );
	}

	public static function parse_dataset( $raw, $session_slug ) {
		$schema = self::session_schema( $session_slug );
		$cols = $schema['columns'];
		$mode = $schema['points_mode'];

		$raw = str_replace( array("\r\n","\r"), "\n", (string)$raw );
		$lines = explode( "\n", $raw );
		$clean = array();
		foreach ( $lines as $ln ) {
			$ln = trim( $ln );
			if ( $ln === '' ) continue;
			if ( preg_match( '~^-{3,}$~', $ln ) ) continue;
			if ( preg_match( '~^\-+\s*$~', $ln ) ) continue;
			$clean[] = $ln;
		}

		$start = 0;
		foreach ( $clean as $i => $ln ) {
			if ( stripos( $ln, 'Pos' ) !== false && ( stripos( $ln, 'Driver' ) !== false || stripos( $ln, 'Fahrer' ) !== false ) ) {
				$start = $i + 1; break;
			}
		}

		$rows = array();
		for ( $i = $start; $i < count( $clean ); $i++ ) {
			$ln = $clean[$i];
			if ( ! preg_match( '~^(?:\d+|NC|DNF|DNS|DQ|DSQ|EX|RET|R)\b~i', $ln ) ) continue;
			$parts = ( strpos( $ln, "\t" ) !== false ) ? explode( "\t", $ln ) : preg_split( "~\s{2,}~", $ln );
			if ( ! $parts ) continue;
			$parts = array_map( 'trim', $parts );
			if ( count( $parts ) < 3 ) continue;

			if ( in_array( $session_slug, array( 'quali', 'sq' ), true ) ) {
				// Fix Quali parts logic (condensed for brevity)
				$p = array_pad( $parts, 7, '' );
				// Basic mapping assuming format: Pos, Driver, Team, Q1, Q2, Q3, Laps
				// If advanced parsing needed like legacy f1wms_fix_quali_parts, implementing it here:
				// ...
				// For now simplified direct map, assuming clean copy paste
			}

			$row = array();
			foreach ( $cols as $ci => $c ) {
				$key = $c['key'];
				$row[$key] = isset( $parts[$ci] ) ? (string)$parts[$ci] : '';
			}

			if ( isset( $row['pos'] ) ) $row['pos'] = self::normalize_pos( $row['pos'] );
			foreach ( $row as $k => $v ) {
				if ( ! in_array( $k, array('pos','laps','pts') ) ) {
					$row[$k] = sanitize_text_field( self::decode_u_escapes( $v ) );
				}
			}

			if ( $mode === 'sprint' || $mode === 'race' ) {
				$row['pts'] = self::points_for_pos( $mode, (int)$row['pos'] );
			}

			if ( ! empty( $row['pos'] ) ) {
				$row['_idx'] = count( $rows );
				$rows[] = $row;
			}
		}
		return $rows;
	}

	public static function save_session_results( $race_id, $session_slug, $rows, $raw = null ) {
		$race_id = absint( $race_id );
		if ( ! $race_id ) return false;
		$schema = self::session_schema( $session_slug );
		$mode = $schema['points_mode'];

		$out = array();
		foreach ( $rows as $r ) {
			$row = array();
			foreach ( $schema['columns'] as $c ) {
				$k = $c['key'];
				$val = $r[$k] ?? '';
				if ( $k === 'pos' ) $val = self::normalize_pos( $val );
				if ( $k === 'driver' ) $val = self::canonicalize_driver_name( $val );
				$row[$k] = $val;
			}
			if ( $mode === 'sprint' || $mode === 'race' ) {
				$row['pts'] = self::points_for_pos( $mode, (int)$row['pos'] );
			}
			if ( ! empty( $row['pos'] ) ) $out[] = $row;
		}
		update_post_meta( $race_id, self::meta_key_rows( $session_slug ), wp_json_encode( $out, F1WMS_JSON_FLAGS ) );
		if ( $raw !== null ) update_post_meta( $race_id, self::meta_key_raw( $session_slug ), (string)$raw );
		return true;
	}

	public static function get_session_results( $race_id, $session_slug ) {
		$json = (string)get_post_meta( $race_id, self::meta_key_rows( $session_slug ), true );
		if ( trim( $json ) === '' ) return array();
		$arr = json_decode( $json, true );
		return is_array( $arr ) ? $arr : array();
	}

	public static function get_session_raw( $race_id, $session_slug ) {
		return (string)get_post_meta( $race_id, self::meta_key_raw( $session_slug ), true );
	}

	/* --- Wikidata --- */

	public static function driver_name_key( $name ) {
		$name = sanitize_text_field( (string)$name );
		$plain = function_exists( 'remove_accents' ) ? remove_accents( $name ) : $name;
		$plain = str_replace( array('ß','ẞ'), 'ss', $plain );
		$plain = preg_replace( '~[^a-zA-Z0-9 ]+~', ' ', $plain );
		$plain = strtolower( trim( $plain ) );
		$plain = str_replace( array('ae','oe','ue'), array('a','o','u'), $plain );
		return trim( preg_replace( '~\s+~', ' ', $plain ) );
	}

	public static function get_driver_directory() {
		$ver  = self::DRIVER_DIR_VER;
		$tKey = 'f1wms_driver_directory_' . $ver;
		$oKey = 'f1wms_driver_directory_' . $ver;
		$oTs  = 'f1wms_driver_directory_' . $ver . '_ts';

		$dir = get_transient( $tKey );
		if ( is_array( $dir ) ) return $dir;

		$stored = get_option( $oKey );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			set_transient( $tKey, $stored, 12 * HOUR_IN_SECONDS );
			return $stored;
		}

		// Fetch if not stored or expired
		$labels = self::wikidata_fetch_drivers();
		$map = array();
		foreach ( $labels as $l ) {
			$k = self::driver_name_key( $l );
			if ( $k ) $map[$k] = $l;
		}

		update_option( $oKey, $map, false );
		update_option( $oTs, time(), false );
		set_transient( $tKey, $map, 2 * DAY_IN_SECONDS );
		return $map;
	}

	public static function wikidata_fetch_drivers() {
		$sparql = "SELECT ?item ?itemLabel WHERE { ?item wdt:P31 wd:Q5. ?item wdt:P106 wd:Q10841764. SERVICE wikibase:label { bd:serviceParam wikibase:language \"de,en\". } }";
		$url = 'https://query.wikidata.org/sparql?format=json&query=' . rawurlencode( $sparql );
		$resp = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'Accept' => 'application/sparql-results+json' ) ) );
		if ( is_wp_error( $resp ) ) return array();
		$body = wp_remote_retrieve_body( $resp );
		$json = json_decode( $body, true );
		$out = array();
		if ( ! empty( $json['results']['bindings'] ) ) {
			foreach ( $json['results']['bindings'] as $b ) {
				if ( ! empty( $b['itemLabel']['value'] ) ) $out[] = $b['itemLabel']['value'];
			}
		}
		return array_unique( $out );
	}

	public static function canonicalize_driver_name( $name ) {
		$name = self::decode_u_escapes( $name );
		$dir = self::get_driver_directory();
		$k = self::driver_name_key( $name );
		return isset( $dir[$k] ) ? $dir[$k] : $name;
	}

	/* --- Compute --- */

	public static function compute_standings() {
		$races = array();
		$raw_races = self::get_races();
		foreach ( $raw_races as $r ) {
			$races[] = array( 'id' => $r['id'], 'title' => $r['title'], 'flag_url' => self::get_flag_url( $r['id'] ) );
		}

		$drivers = array();
		$seen = array();

		foreach ( $races as $race ) {
			$rid = $race['id'];
			$sets = array( self::get_session_results( $rid, 'race' ), self::get_session_results( $rid, 'sprint' ) );

			foreach ( $sets as $rows ) {
				foreach ( $rows as $row ) {
					$name = isset( $row['driver'] ) ? self::canonicalize_driver_name( $row['driver'] ) : '';
					if ( ! $name ) continue;
					$pts = (int)( $row['pts'] ?? 0 );

					if ( ! isset( $drivers[$name] ) ) {
						$drivers[$name] = array( 'driver' => $name, 'total' => 0, 'by_race' => array() );
					}
					$drivers[$name]['total'] += $pts;
					$prev = $drivers[$name]['by_race'][$rid] ?? 0;
					$drivers[$name]['by_race'][$rid] = $prev + $pts;
					$seen[$name][$rid] = true;
				}
			}
		}

		// Ensure 0 for participated races with no points if needed, or fill gaps logic
		// Legacy fills with 0 if seen in race.
		foreach ( $seen as $name => $rids ) {
			foreach ( $rids as $rid => $true ) {
				if ( ! isset( $drivers[$name]['by_race'][$rid] ) ) $drivers[$name]['by_race'][$rid] = 0;
			}
		}

		uasort( $drivers, function( $a, $b ) {
			if ( $a['total'] !== $b['total'] ) return $b['total'] <=> $a['total'];
			return strcasecmp( $a['driver'], $b['driver'] );
		} );

		return array( 'races' => $races, 'drivers' => array_values( $drivers ) );
	}

	public static function compute_team_standings_auto() {
		$base = self::compute_standings();
		$races = $base['races'];
		$drivers = $base['drivers'];
		$teams = array();

		// Build Team Map per Race
		$team_map = array(); // [race_id][driver_name] = team_name
		foreach ( $races as $r ) {
			$rid = $r['id'];
			$sessions = self::get_sessions_for_race( $rid );
			foreach ( $sessions as $slug => $cfg ) {
				$rows = self::get_session_results( $rid, $slug );
				foreach ( $rows as $row ) {
					if ( ! empty( $row['driver'] ) && ! empty( $row['team'] ) ) {
						$d = self::canonicalize_driver_name( $row['driver'] );
						$team_map[$rid][$d] = trim( $row['team'] );
					}
				}
			}
		}

		foreach ( $drivers as $d ) {
			$name = $d['driver'];
			foreach ( $d['by_race'] as $rid => $pts ) {
				$t = $team_map[$rid][$name] ?? '';
				if ( ! $t ) continue;
				if ( ! isset( $teams[$t] ) ) $teams[$t] = array( 'team' => $t, 'total' => 0, 'by_race' => array() );
				$teams[$t]['total'] += $pts;
				$prev = $teams[$t]['by_race'][$rid] ?? 0;
				$teams[$t]['by_race'][$rid] = $prev + $pts;
			}
		}

		uasort( $teams, function( $a, $b ) {
			if ( $a['total'] !== $b['total'] ) return $b['total'] <=> $a['total'];
			return strcasecmp( $a['team'], $b['team'] );
		} );

		return array( 'races' => $races, 'teams' => array_values( $teams ) );
	}
}
