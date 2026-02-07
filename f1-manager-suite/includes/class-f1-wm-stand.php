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

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', array( $this, 'add_capabilities' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        add_shortcode( 'f1_wm_stand', array( $this, 'shortcode_driver_standings' ) );
        add_shortcode( 'f1_team_wm_stand', array( $this, 'shortcode_team_standings' ) );

        // Calculator Shortcodes
        add_shortcode( 'f1_wm_rechnerisch_fahrer', array( $this, 'shortcode_calculator_drivers' ) );
        add_shortcode( 'f1_wm_rechnerisch_teams', array( $this, 'shortcode_calculator_teams' ) );
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
        global $post;
        $has_shortcode = is_a( $post, 'WP_Post' ) && (
            has_shortcode( $post->post_content, 'f1_wm_stand' ) ||
            has_shortcode( $post->post_content, 'f1_team_wm_stand' ) ||
            has_shortcode( $post->post_content, 'f1_wm_rechnerisch_fahrer' ) ||
            has_shortcode( $post->post_content, 'f1_wm_rechnerisch_teams' )
        );
        if ( ! $has_shortcode ) return;

        wp_enqueue_style( 'f1-wm-stand', F1_MANAGER_SUITE_URL . 'assets/css/f1-wm-stand.css', array(), '1.0.0' );
        wp_enqueue_script( 'f1-wm-stand', F1_MANAGER_SUITE_URL . 'assets/js/f1-wm-stand.js', array(), '1.0.0', true );

        // Modal HTML for frontend assets (using footer action since this method runs on enqueue)
        add_action('wp_footer', function() {
            echo '<div class="f1wms-swipe-modal" id="f1wmsSwipeModal" aria-hidden="true"><div class="f1wms-swipe-modal__box" role="dialog" aria-modal="true" aria-label="Hinweis: Wischen"><div class="f1wms-swipe-modal__title">Wischen</div><div class="f1wms-swipe-modal__anim" aria-hidden="true"><div class="f1wms-swipe-track"></div><div class="f1wms-swipe-finger"></div></div></div></div>';
        });
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
        // Handle Converter Logic directly before render if posted
        $converter_output = '';
        $converter_info   = '';

        if ( isset( $_POST['f1sc_do_convert'] ) && check_admin_referer( 'f1sc_convert', 'f1sc_nonce' ) ) {
            $input = isset( $_POST['f1sc_input'] ) ? wp_unslash( $_POST['f1sc_input'] ) : '';
            $res = self::converter_convert_any_to_bulk_tsv( $input );
            $converter_output = $res['tsv'];
            $converter_info   = $res['info'];
        }

        require_once F1_MANAGER_SUITE_PATH . 'includes/admin/wm-stand-admin.php';
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

    public function shortcode_calculator_drivers() {
        return $this->render_calculator( 'drivers' );
    }

    public function shortcode_calculator_teams() {
        return $this->render_calculator( 'teams' );
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
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/wm-stand-view.php';
        return ob_get_clean();
    }

    public function render_calculator( $mode ) {
        // Ensure assets are loaded
        $this->enqueue_frontend_assets();

        $data = ( $mode === 'teams' ) ? self::compute_team_standings_auto() : self::compute_standings();
        $raw_rows = ( $mode === 'teams' ) ? $data['teams'] : $data['drivers'];

        // Simplify rows structure for calculator
        $rows = array();
        foreach ( $raw_rows as $r ) {
            $rows[] = array(
                'key' => ( $mode === 'teams' ) ? $r['team'] : $r['driver'],
                'label' => ( $mode === 'teams' ) ? $r['team'] : $r['driver'],
                'points' => (int)$r['total']
            );
        }

        $leader_pts = 0;
        foreach ( $rows as $r ) $leader_pts = max( $leader_pts, $r['points'] );

        $rem = self::calc_get_remaining_points_total( $mode );
        $remaining_total = (int)$rem['remaining_total'];

        $contenders = array();
        foreach ( $rows as $r ) {
            $p = (int)$r['points'];
            if ( ( $p + $remaining_total ) >= $leader_pts ) {
                $r['behind'] = max( 0, $leader_pts - $p );
                $r['max_final'] = $p + $remaining_total;
                $contenders[] = $r;
            }
        }

        // Sort
        usort( $contenders, function( $a, $b ) {
            if ( $a['points'] !== $b['points'] ) return $b['points'] <=> $a['points'];
            return strcasecmp( $a['label'], $b['label'] );
        } );

        $title = ( $mode === 'teams' ) ? 'Weltmeisterchancen' : 'Weltmeisterchancen';

        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/wm-rechner-view.php';
        return ob_get_clean();
    }

    /* =========================================================
       CALCULATOR HELPER
       ========================================================= */

    public static function calc_session_has_results( $race_id, $slug ) {
        $rows = self::get_session_results( $race_id, $slug );
        return ! empty( $rows );
    }

    public static function calc_get_remaining_points_total( $mode ) {
        $races = self::get_races();
        if ( empty( $races ) ) return array( 'remaining_total' => 0, 'remaining_races' => 0 );

        $total = 0;
        $events = 0;

        $pts_race   = ( $mode === 'teams' ) ? 43 : 25; // 25+18 vs 25
        $pts_sprint = ( $mode === 'teams' ) ? 15 : 8;  // 8+7 vs 8

        foreach ( $races as $r ) {
            $rid = (int)$r['id'];
            $wt = self::get_weekend_type( $rid );
            $sessions = self::get_sessions_for_race( $rid );

            $added = 0;
            $has_race = isset( $sessions['race'] );
            $has_sprint = isset( $sessions['sprint'] );

            // Fallback logic from legacy if meta incomplete
            if ( ! $has_race && ! $has_sprint ) {
                $has_race = true;
                if ( $wt === 'sprint' ) $has_sprint = true;
            }

            if ( $has_sprint && ! self::calc_session_has_results( $rid, 'sprint' ) ) {
                $total += $pts_sprint;
                $added += $pts_sprint;
            }
            if ( $has_race && ! self::calc_session_has_results( $rid, 'race' ) ) {
                $total += $pts_race;
                $added += $pts_race;
            }

            if ( $added > 0 ) $events++;
        }
        return array( 'remaining_total' => $total, 'remaining_races' => $events );
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

    /* =========================================================
       CONVERTER LOGIC
       ========================================================= */

    public static function converter_convert_any_to_bulk_tsv( $rawInput ) {
        $rawInput = trim( (string)$rawInput );
        if ( $rawInput === '' ) return array( 'tsv' => '', 'info' => 'Kein Input.' );

        $parsed = self::converter_parse_input( $rawInput );
        $rows   = $parsed['rows'] ?? array();
        $kind   = $parsed['kind'] ?? 'unknown';
        $hdrs   = $parsed['headers'] ?? array();

        if ( ! $rows ) return array( 'tsv' => '', 'info' => 'Kein Tabellen-Inhalt erkannt.' );

        // Dedupe
        $unique = array();
        $cleanRows = array();
        $idx = 0;

        foreach ( $rows as $r ) {
            $pos = trim( (string)($r['pos'] ?? '') );
            $no  = trim( (string)($r['no'] ?? '') );

            if ( ! preg_match( '~^\d+$~', $no ) ) continue;
            $posOk = (bool) preg_match( '~^\d+$~', $pos ) || (bool) preg_match( '~^(NC|DNF|DNS|DQ|DSQ|EX|EXCLUDED|RET|R)$~i', $pos );
            if ( ! $posOk ) continue;

            $r['_idx'] = $idx++;
            $k = $pos . '-' . $no;
            if ( isset( $unique[$k] ) ) continue;
            $unique[$k] = true;
            $cleanRows[] = $r;
        }

        usort( $cleanRows, function( $a, $b ) {
            $pa = $a['pos'] ?? '';
            $pb = $b['pos'] ?? '';
            $aNum = preg_match( '~^\d+$~', $pa );
            $bNum = preg_match( '~^\d+$~', $pb );
            if ( $aNum && $bNum ) return ((int)$pa) <=> ((int)$pb);
            if ( $aNum && ! $bNum ) return -1;
            if ( ! $aNum && $bNum ) return 1;
            return ($a['_idx'] ?? 0) <=> ($b['_idx'] ?? 0);
        } );

        $out = array();
        if ( $kind === 'qualifying' ) {
            $out[] = "Pos.\tDriver\tTeam\tQ1\tQ2\tQ3\tLaps";
        } elseif ( $kind === 'grid' ) {
            $out[] = "Pos.\tDriver\tTeam\tTime";
        } else {
            $out[] = "Pos.\tDriver\tTeam\tLaps\tTime / Retired\tPts.";
        }

        foreach ( $cleanRows as $r ) {
            $pos = $r['pos'] ?? '';
            $drv = self::converter_clean_driver_name( $r['driver'] ?? '' );
            $team= $r['team'] ?? '';

            if ( $kind === 'practice' ) {
                $laps = $r['laps'] ?? '';
                $time = $r['time_gap'] ?? '';
                $pts = '';
            } elseif ( $kind === 'qualifying' ) {
                $laps = $r['laps'] ?? '';
                $q1 = $r['q1'] ?? ''; $q2 = $r['q2'] ?? ''; $q3 = $r['q3'] ?? '';
                $time = self::converter_best_quali_time( $q1, $q2, $q3 );
                $pts = '';
            } elseif ( $kind === 'grid' ) {
                $laps = '0';
                $time = $r['time'] ?? '';
                $pts = '';
            } elseif ( $kind === 'race' ) {
                $laps = $r['laps'] ?? '';
                $time = $r['time_retired'] ?? '';
                $pts = $r['pts'] ?? '';
            } else {
                $laps = $r['laps'] ?? '';
                $time = $r['time_gap'] ?? ( $r['time_retired'] ?? ( $r['time'] ?? '' ) );
                $pts = $r['pts'] ?? '';
                if ( $laps === '' && $time !== '' ) $laps = '0';
            }

            if ( $kind === 'qualifying' ) {
                $out[] = "$pos\t$drv\t$team\t$q1\t$q2\t$q3\t$laps";
            } elseif ( $kind === 'grid' ) {
                $out[] = "$pos\t$drv\t$team\t$time";
            } else {
                $out[] = "$pos\t$drv\t$team\t$laps\t$time\t$pts";
            }
        }

        $info = 'Erkanntes Format: ' . self::converter_kind_label( $kind );
        if ( ! empty( $hdrs ) ) $info .= ' (Headers: ' . implode( ', ', $hdrs ) . ')';
        $info .= ' | Zeilen erkannt: ' . count( $cleanRows );

        return array( 'tsv' => implode( "\n", $out ), 'info' => $info );
    }

    private static function converter_kind_label( $kind ) {
        if ( $kind === 'practice' ) return 'FP (Time/Gap + Laps)';
        if ( $kind === 'qualifying' ) return 'Qualifying (Q1/Q2/Q3 + Laps)';
        if ( $kind === 'grid' ) return 'Startaufstellung (Time)';
        if ( $kind === 'race' ) return 'Rennen/Sprint (Laps + Time/Retired + Pts)';
        return 'Unbekannt';
    }

    private static function converter_best_quali_time( $q1, $q2, $q3 ) {
        $q1 = self::converter_norm( $q1 ); $q2 = self::converter_norm( $q2 ); $q3 = self::converter_norm( $q3 );
        if ( $q3 !== '' && $q3 !== '-' ) return $q3;
        if ( $q2 !== '' && $q2 !== '-' ) return $q2;
        if ( $q1 !== '' && $q1 !== '-' ) return $q1;
        return '';
    }

    private static function converter_parse_input( $raw ) {
        if ( stripos( $raw, '<table' ) !== false ) return self::converter_parse_html_table( $raw );
        if ( strpos( $raw, "\t" ) !== false ) return self::converter_parse_tsv( $raw );
        return self::converter_parse_plain_text_best_effort( $raw );
    }

    private static function converter_parse_html_table( $html ) {
        $wrap = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( $wrap, LIBXML_NOWARNING | LIBXML_NOERROR );
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName( 'table' );
        if ( ! $tables || $tables->length === 0 ) return array( 'kind' => 'unknown', 'headers' => array(), 'rows' => array() );

        $allRows = array();
        $detectedKind = 'unknown';
        $detectedHeaders = array();

        for ( $ti = 0; $ti < $tables->length; $ti++ ) {
            $table = $tables->item( $ti );
            $headers = array();
            $thNodes = $table->getElementsByTagName( 'th' );
            foreach ( $thNodes as $th ) $headers[] = self::converter_norm( $th->textContent );

            $kind = self::converter_detect_kind_from_headers( $headers );
            if ( $detectedKind === 'unknown' && $kind !== 'unknown' ) {
                $detectedKind = $kind;
                $detectedHeaders = $headers;
            }

            $trNodes = $table->getElementsByTagName( 'tr' );
            foreach ( $trNodes as $tr ) {
                $tds = $tr->getElementsByTagName( 'td' );
                if ( ! $tds->length ) continue;
                $cells = array();
                foreach ( $tds as $td ) $cells[] = self::converter_norm( $td->textContent );

                $row = self::converter_map_cells_to_row( $cells, $kind );
                if ( $row ) $allRows[] = $row;
            }
        }

        if ( $detectedKind === 'unknown' ) $detectedKind = self::converter_guess_kind_from_rows( $allRows );

        if ( $detectedKind !== 'unknown' ) {
            foreach ( $allRows as &$row ) {
                if ( isset( $row['_raw_cells'] ) ) {
                    $r2 = self::converter_map_cells_to_row( $row['_raw_cells'], $detectedKind );
                    if ( $r2 ) $row = $r2;
                }
            }
        }
        return array( 'kind' => $detectedKind, 'headers' => $detectedHeaders, 'rows' => $allRows );
    }

    private static function converter_parse_tsv( $tsv ) {
        $tsv = str_replace( "\r", "", (string)$tsv );
        $lines = array_filter( explode( "\n", $tsv ) );
        if ( ! $lines ) return array( 'kind'=>'unknown', 'headers'=>array(), 'rows'=>array() );

        $headers = array_map( 'trim', explode( "\t", array_shift( $lines ) ) );
        $kind = self::converter_detect_kind_from_headers( $headers );
        $rows = array();
        foreach ( $lines as $l ) {
            $parts = array_map( 'trim', explode( "\t", $l ) );
            if ( count( $parts ) < 4 ) continue;
            if ( ! preg_match( '~^\d+$~', $parts[0] ) ) continue;
            $row = self::converter_map_cells_to_row( $parts, $kind );
            if ( $row ) $rows[] = $row;
        }
        return array( 'kind' => $kind, 'headers' => $headers, 'rows' => $rows );
    }

    private static function converter_parse_plain_text_best_effort( $txt ) {
        $tokens = preg_split( '~\s+~u', trim( str_replace( "\r", "", $txt ) ) );
        $tokens = array_values( array_filter( $tokens ) );
        $rows = array();
        $i = 0;
        while ( $i < count( $tokens ) ) {
            $t0 = $tokens[$i] ?? '';
            $t1 = $tokens[$i+1] ?? '';
            if ( ! preg_match( '~^\d+$~', $t0 ) || ! preg_match( '~^\d+$~', $t1 ) ) { $i++; continue; }

            $pos = $t0; $no = $t1; $i += 2;
            $chunk = array();
            while ( $i < count( $tokens ) ) {
                $a = $tokens[$i] ?? '';
                $b = $tokens[$i+1] ?? '';
                if ( preg_match( '~^\d+$~', $a ) && preg_match( '~^\d+$~', $b ) ) break;
                $chunk[] = $a; $i++;
            }
            if ( ! $chunk ) continue;

            $pts = '';
            if ( preg_match( '~^\d+$~', end( $chunk ) ) ) $pts = array_pop( $chunk );

            $laps = ''; $lapsIdx = -1;
            for ( $j=count( $chunk )-1; $j>=0; $j-- ) {
                if ( preg_match( '~^\d+$~', $chunk[$j] ) ) { $laps = $chunk[$j]; $lapsIdx = $j; break; }
            }

            $pre = $chunk;
            $time = '';
            if ( $lapsIdx >= 0 ) {
                $pre = array_slice( $chunk, 0, $lapsIdx );
                $time = implode( ' ', array_slice( $chunk, $lapsIdx+1 ) );
            }

            $split = self::converter_split_driver_team( $pre );
            $rows[] = array(
                'pos' => $pos, 'no' => $no, 'driver' => $split['driver'], 'team' => $split['team'],
                'laps' => $laps, 'time_gap' => $time, 'time_retired' => $time, 'pts' => $pts,
                '_raw_cells' => array_merge( array( $pos, $no, $split['driver'], $split['team'] ), $chunk )
            );
        }
        $kind = self::converter_guess_kind_from_rows( $rows );
        return array( 'kind' => $kind, 'headers' => array(), 'rows' => $rows );
    }

    private static function converter_detect_kind_from_headers( $headers ) {
        $h = array_map( function( $x ) { return strtolower( self::converter_norm( $x ) ); }, $headers );
        if ( in_array( 'laps', $h ) && ( in_array( 'time / retired', $h ) || in_array( 'time/retired', $h ) ) && ( in_array( 'pts.', $h ) || in_array( 'pts', $h ) ) ) return 'race';
        if ( in_array( 'q1', $h ) && in_array( 'q2', $h ) && in_array( 'q3', $h ) ) return 'qualifying';
        if ( ( in_array( 'time / gap', $h ) || in_array( 'time/gap', $h ) ) && in_array( 'laps', $h ) ) return 'practice';
        if ( in_array( 'time', $h ) && in_array( 'driver', $h ) && ! in_array( 'laps', $h ) ) return 'grid';
        return 'unknown';
    }

    private static function converter_guess_kind_from_rows( $rows ) {
        foreach ( $rows as $r ) {
            if ( empty( $r['_raw_cells'] ) ) continue;
            $n = count( $r['_raw_cells'] );
            if ( $n === 6 ) return 'practice';
            if ( $n === 5 ) return 'grid';
            if ( $n === 8 ) return 'qualifying';
            if ( $n === 7 ) return 'race';
        }
        return 'unknown';
    }

    private static function converter_map_cells_to_row( $cells, $kind ) {
        $cells = array_map( array( __CLASS__, 'converter_norm' ), $cells );
        if ( count( $cells ) < 4 ) return null;
        $rc = $cells;

        if ( $kind === 'unknown' ) {
            $n = count( $cells );
            if ( $n === 6 ) $kind = 'practice';
            elseif ( $n === 5 ) $kind = 'grid';
            elseif ( $n === 8 ) $kind = 'qualifying';
            elseif ( $n === 7 ) $kind = 'race';
        }

        $base = array( 'pos' => $cells[0], 'no' => $cells[1], 'driver' => self::converter_clean_driver_name( $cells[2] ), 'team' => $cells[3], '_raw_cells' => $rc );

        if ( $kind === 'practice' && count( $cells ) >= 6 ) return array_merge( $base, array( 'time_gap' => $cells[4], 'laps' => $cells[5] ) );
        if ( $kind === 'qualifying' && count( $cells ) >= 8 ) return array_merge( $base, array( 'q1' => $cells[4], 'q2' => $cells[5], 'q3' => $cells[6], 'laps' => $cells[7] ) );
        if ( $kind === 'grid' && count( $cells ) >= 5 ) return array_merge( $base, array( 'time' => $cells[4] ) );
        if ( $kind === 'race' && count( $cells ) >= 7 ) return array_merge( $base, array( 'laps' => $cells[4], 'time_retired' => $cells[5], 'pts' => $cells[6] ) );

        return $base;
    }

    private static function converter_norm( $s ) {
        $s = str_replace( "\xC2\xA0", ' ', (string)$s );
        $s = wp_strip_all_tags( $s );
        $s = preg_replace( '~\s+~u', ' ', $s );
        return trim( $s );
    }

    private static function converter_clean_driver_name( $name ) {
        $name = self::converter_norm( $name );
        if ( preg_match( '~\s~u', $name ) ) {
            $name = preg_replace( '~\s+[A-Z]{3}$~u', '', $name );
            $name = preg_replace( '~([\p{L}])([A-Z]{3})$~u', '$1', $name );
        }
        return trim( preg_replace( '~\s+~u', ' ', $name ) );
    }

    private static function converter_split_driver_team( $tokens ) {
        $toks = array_values( array_filter( array_map( array( __CLASS__, 'converter_norm' ), $tokens ) ) );
        if ( ! $toks ) return array( 'driver' => '', 'team' => '' );

        $known = array( 'Red Bull Racing', 'McLaren', 'Ferrari', 'Mercedes', 'Aston Martin', 'Alpine', 'Haas F1 Team', 'Williams', 'Racing Bulls', 'Kick Sauber', 'Sauber', 'RB', 'Visa Cash App RB', 'Visa Cash App RB F1 Team' );
        $lower = array_map( 'strtolower', $toks );

        foreach ( $known as $t ) {
            $parts = array_map( 'strtolower', preg_split( '~\s+~', trim( $t ) ) );
            if ( count( $parts ) > count( $lower ) ) continue;
            $ok = true;
            for ( $j=0; $j<count( $parts ); $j++ ) {
                if ( $lower[ count( $lower ) - count( $parts ) + $j ] !== $parts[$j] ) { $ok = false; break; }
            }
            if ( $ok ) {
                $team = implode( ' ', array_slice( $toks, count( $toks ) - count( $parts ) ) );
                $driver = implode( ' ', array_slice( $toks, 0, count( $toks ) - count( $parts ) ) );
                return array( 'driver' => self::converter_clean_driver_name( $driver ), 'team' => $team );
            }
        }

        if ( count( $toks ) >= 2 ) {
            $team = array_pop( $toks );
            return array( 'driver' => self::converter_clean_driver_name( implode( ' ', $toks ) ), 'team' => $team );
        }
        return array( 'driver' => self::converter_clean_driver_name( implode( ' ', $toks ) ), 'team' => '' );
    }
}
