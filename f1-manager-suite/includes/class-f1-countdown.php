<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Countdown {

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_save' ) );

        // Frontend
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_head', array( $this, 'output_anti_flicker_css' ), 1 );

        // Shortcode
        add_shortcode( 'f1_session_countdown', array( $this, 'render_shortcode' ) );
    }

    /* =========================================================
       ADMIN
       ========================================================= */

    public function register_admin_menu() {
        add_menu_page(
            'F1 Countdown',
            'Countdown',
            'manage_options',
            'f1-countdown',
            array( $this, 'render_admin_page' ),
            'dashicons-clock',
            33
        );
    }

    public function render_admin_page() {
        require_once F1_MANAGER_SUITE_PATH . 'includes/admin/countdown-admin.php';
    }

    public function handle_save() {
        if ( ! is_admin() ) return;
        if ( ! isset( $_POST['f1_countdown_save'] ) ) return;

        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );
        if ( ! check_admin_referer( 'f1_countdown_save_action', 'f1_countdown_nonce' ) ) wp_die( 'Nonce Fehler.' );

        update_option( 'f1_cnt_manual_active', ! empty( $_POST['f1_cnt_manual_active'] ) ? 1 : 0 );
        update_option( 'f1_cnt_manual_label', sanitize_text_field( $_POST['f1_cnt_manual_label'] ?? '' ) );
        update_option( 'f1_cnt_manual_date', sanitize_text_field( $_POST['f1_cnt_manual_date'] ?? '' ) );
        update_option( 'f1_cnt_manual_time', sanitize_text_field( $_POST['f1_cnt_manual_time'] ?? '' ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'f1-countdown', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* =========================================================
       ASSETS & HEAD
       ========================================================= */

    public function output_anti_flicker_css() {
        if ( is_admin() ) return;
        echo '<style id="f1hdr-prehide-video">.search-watch.bottom-bar-right .custom-menu-link.aft-custom-fa-icon{ visibility:hidden !important; }</style>';
    }

    public function enqueue_assets() {
        if ( is_admin() ) return;

        wp_enqueue_style( 'f1-countdown', F1_MANAGER_SUITE_URL . 'assets/css/f1-countdown.css', array(), '1.0.0' );
        wp_enqueue_script( 'f1-countdown', F1_MANAGER_SUITE_URL . 'assets/js/f1-countdown.js', array(), '1.0.0', true );

        wp_localize_script( 'f1-countdown', 'f1_countdown_cfg', array(
            'html' => $this->get_widget_html(),
        ) );
    }

    public function render_shortcode( $atts ) {
        return $this->get_widget_html();
    }

    /* =========================================================
       LOGIC
       ========================================================= */

    public function get_next_event() {
        // 1. Manual Override
        $manual = get_option( 'f1_cnt_manual_active' );
        if ( $manual ) {
            $label = get_option( 'f1_cnt_manual_label' );
            $date  = get_option( 'f1_cnt_manual_date' );
            $time  = get_option( 'f1_cnt_manual_time' );

            if ( $label && $date ) {
                $tz = wp_timezone();
                try {
                    $dt = new DateTime( $date . ' ' . ( $time ? $time : '00:00' ), $tz );
                    $ts = $dt->getTimestamp();
                    if ( $ts > time() ) {
                        return array(
                            'ts'          => (int)$ts,
                            'iso'         => (string)$dt->format( 'c' ),
                            'label_long'  => $label,
                            'label_short' => self::short_session_label( $label ),
                            'flag_url'    => '', // No flag for manual
                        );
                    }
                } catch ( Exception $e ) {}
            }
        }

        // 2. Automatic CPT Lookup
        $tz = wp_timezone();
        $now = new DateTime( 'now', $tz );
        $nowTs = $now->getTimestamp();

        $races = get_posts( array(
            'post_type'      => 'f1_race',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ) );

        if ( empty( $races ) ) return null;

        $priority = array(); $fallback = array();
        foreach ( $races as $race ) {
            $s = get_post_meta( $race->ID, '_f1cal_status', true );
            if ( $s === 'next' || $s === 'live' ) $priority[] = $race->ID; else $fallback[] = $race->ID;
        }
        $ordered = array_merge( $priority, $fallback );
        $best = null;

        foreach ( $ordered as $pid ) {
            $wt = get_post_meta( $pid, '_f1cal_weekend_type', true );
            $wt = ( $wt === 'sprint' ) ? 'sprint' : 'normal';

            $sessions = array();
            $keys = array(
                'fp1' => '1. Freies Training', 'fp2' => '2. Freies Training', 'fp3' => '3. Freies Training',
                'sq' => 'Sprint Qualifying', 'sprint' => 'Sprint', 'quali' => 'Das Qualifying', 'race' => 'Das Rennen'
            );

            if ( $wt === 'sprint' ) {
                $check = array( 'fp1', 'sq', 'sprint', 'quali', 'race' );
            } else {
                $check = array( 'fp1', 'fp2', 'fp3', 'quali', 'race' );
            }

            foreach ( $check as $slug ) {
                $d = get_post_meta( $pid, "_f1cal_{$slug}_date", true );
                $t = get_post_meta( $pid, "_f1cal_{$slug}_time", true );
                if ( ! $d || ! $t ) continue;
                $sessions[] = array( 'label' => $keys[$slug], 'date' => $d, 'time' => $t );
            }

            foreach ( $sessions as $s ) {
                if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $s['date'] ) ) continue;
                try {
                    $dt = new DateTime( $s['date'] . ' ' . $s['time'] . ':00', $tz );
                } catch ( Exception $e ) { continue; }

                $ts = $dt->getTimestamp();
                if ( $ts <= $nowTs ) continue;

                if ( $best === null || $ts < $best['ts'] ) {
                    $flag = self::get_flag_url( $pid );
                    $best = array(
                        'ts'          => (int)$ts,
                        'iso'         => (string)$dt->format( 'c' ),
                        'label_long'  => $s['label'],
                        'label_short' => self::short_session_label( $s['label'] ),
                        'flag_url'    => $flag,
                        'pid'         => $pid,
                    );
                }
            }
        }
        return $best;
    }

    public function get_widget_html() {
        $next = $this->get_next_event();
        $cal_url = home_url( '/kalender/' );
        $cal_url = self::force_https_url( $cal_url );

        if ( ! $next ) {
            return '<div class="custom-menu-link f1hdr-next" data-f1hdr="1"><a href="' . esc_url( $cal_url ) . '" aria-label="Rennkalender"><span class="f1hdr-next__seg f1hdr-next__seg--left"><span class="f1hdr-next__leftwrap"><span class="f1hdr-next__session">—</span></span></span><span class="f1hdr-next__seg f1hdr-next__seg--right"><span class="f1hdr-next__count">SAISONPAUSE</span></span></a></div>';
        }

        $label = $next['label_short'];
        $iso = $next['iso'];
        $flag = $next['flag_url'];
        $flag_html = $flag ? '<img class="f1hdr-next__flag" src="' . esc_url( $flag ) . '" alt="" loading="lazy">' : '';
        $a11y = 'Nächste Session: ' . $next['label_long'];

        return '<div class="custom-menu-link f1hdr-next" data-f1hdr="1">' .
            '<a href="' . esc_url( $cal_url ) . '" aria-label="' . esc_attr( $a11y ) . '">' .
                '<span class="f1hdr-next__seg f1hdr-next__seg--left">' .
                    '<span class="f1hdr-next__leftwrap">' .
                        '<span class="f1hdr-next__session">' . esc_html( $label ) . '</span>' .
                        $flag_html .
                    '</span>' .
                '</span>' .
                '<span class="f1hdr-next__seg f1hdr-next__seg--right">' .
                    '<span class="f1hdr-next__count" data-target-iso="' . esc_attr( $iso ) . '">00T 00S 00M 00S</span>' .
                '</span>' .
            '</a>' .
        '</div>';
    }

    /* =========================================================
       HELPERS
       ========================================================= */

    public static function force_https_url( $url ) {
        $url = (string)$url;
        if ( $url === '' ) return '';
        if ( preg_match( '~^https?://~i', $url ) ) return set_url_scheme( $url, 'https' );
        return $url;
    }

    public static function get_flag_url( $post_id ) {
        $file = get_post_meta( $post_id, '_f1cal_flag_file', true );
        if ( $file ) {
            $u = wp_upload_dir();
            if ( isset( $u['basedir'] ) ) {
                $path = rtrim( $u['basedir'], '/\\' ) . '/flags/' . sanitize_file_name( $file );
                if ( file_exists( $path ) ) return self::force_https_url( rtrim( $u['baseurl'], '/' ) . '/flags/' . rawurlencode( $file ) );
            }
        }
        $id = (int) get_post_meta( $post_id, '_f1cal_flag_id', true );
        if ( $id ) return self::force_https_url( wp_get_attachment_image_url( $id, 'thumbnail' ) );
        return '';
    }

    public static function short_session_label( $label ) {
        $l = strtolower( trim( $label ) );
        if ( strpos( $l, '1.' ) !== false && strpos( $l, 'training' ) !== false ) return 'FP1';
        if ( strpos( $l, '2.' ) !== false && strpos( $l, 'training' ) !== false ) return 'FP2';
        if ( strpos( $l, '3.' ) !== false && strpos( $l, 'training' ) !== false ) return 'FP3';
        if ( strpos( $l, 'sprint qualifying' ) !== false ) return 'SQ';
        if ( $l === 'sprint' ) return 'SPRINT';
        if ( strpos( $l, 'qualifying' ) !== false ) return 'QUALI';
        if ( strpos( $l, 'rennen' ) !== false ) return 'RENNEN';
        return mb_substr( strtoupper( $label ), 0, 10 );
    }
}
