<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Manager_Calendar {

    const CAPABILITY = 'manage_f1_calendar';
    const NONCE_ACTION = 'f1cal_nonce';

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'admin_init', array( $this, 'add_capabilities' ) );
        add_action( 'admin_init', array( $this, 'admin_redirects' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // AJAX
        add_action( 'wp_ajax_f1cal_save', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_f1cal_delete', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_f1cal_move', array( $this, 'ajax_move' ) );
        add_action( 'wp_ajax_f1cal_list_flags', array( $this, 'ajax_list_flags' ) );

        // Shortcode
        add_shortcode( 'f1_calendar', array( $this, 'render_shortcode' ) );
    }

    public function register_cpt() {
        register_post_type( 'f1_race', array(
            'labels' => array(
                'name' => 'F1 Kalender',
                'singular_name' => 'F1 Rennen',
            ),
            'public' => false,
            'show_ui' => false, // Wir nutzen unsere eigene Admin-Page
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'menu_icon' => 'dashicons-plus-alt',
            'menu_position' => 27,
            'supports' => array( 'title', 'page-attributes' ),
        ) );
    }

    public function add_capabilities() {
        if ( ! is_admin() ) return;
        $roles = array( 'administrator', 'editor', 'author' );
        foreach ( $roles as $role_name ) {
            $role = get_role( $role_name );
            if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
                $role->add_cap( self::CAPABILITY );
            }
        }
    }

    public function admin_redirects() {
        if ( ! is_admin() ) return;
        if ( ! $this->user_can_manage() ) return;
        global $pagenow;
        $pt = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
        if ( $pt === 'f1_race' && ( $pagenow === 'edit.php' || $pagenow === 'post-new.php' ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=f1cal_editor' ) );
            exit;
        }
        if ( $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
            $pid = (int) $_GET['post'];
            if ( $pid > 0 && get_post_type( $pid ) === 'f1_race' ) {
                wp_safe_redirect( admin_url( 'admin.php?page=f1cal_editor' ) );
                exit;
            }
        }
    }

    public function register_admin_menu() {
        if ( ! $this->user_can_manage() ) return;
        add_menu_page(
            'Rennkalender',
            'Rennkalender',
            self::CAPABILITY,
            'f1cal_editor',
            array( $this, 'render_admin_page' ),
            'dashicons-plus-alt',
            26
        );
    }

    public function render_admin_page() {
        if ( ! $this->user_can_manage() ) {
            echo '<div class="wrap"><h1>F1 Kalender</h1><p>Keine Berechtigung.</p></div>';
            return;
        }
        // In the legacy code, the admin page just shows the shortcode, but hides the frontend table/cards via CSS.
        // The shortcode logic handles rendering the admin panel if is_admin().
        echo '<div class="wrap">';
        echo '<style>.f1site-cal__card, .f1cal-cardswrap{ display:none !important; }</style>';
        echo do_shortcode( '[f1_calendar]' );
        echo '</div>';
    }

    private function user_can_manage() {
        if ( current_user_can( self::CAPABILITY ) ) return true;
        if ( current_user_can( 'manage_options' ) ) return true;
        return false;
    }

    /* ======================================================================
       HELPER METHODS
       ====================================================================== */

    public function meta_keys() {
        return array(
            'gp'           => '_f1cal_gp',
            'circuit'      => '_f1cal_circuit',
            'status'       => '_f1cal_status',
            'weekend_type' => '_f1cal_weekend_type',
            'flag_file'    => '_f1cal_flag_file',
            'flag_id'      => '_f1cal_flag_id',
            'fp1_date'     => '_f1cal_fp1_date',
            'fp1_time'     => '_f1cal_fp1_time',
            'fp2_date'     => '_f1cal_fp2_date',
            'fp2_time'     => '_f1cal_fp2_time',
            'fp3_date'     => '_f1cal_fp3_date',
            'fp3_time'     => '_f1cal_fp3_time',
            'quali_date'   => '_f1cal_quali_date',
            'quali_time'   => '_f1cal_quali_time',
            'race_date'    => '_f1cal_race_date',
            'race_time'    => '_f1cal_race_time',
            'sq_date'      => '_f1cal_sq_date',
            'sq_time'      => '_f1cal_sq_time',
            'sprint_date'  => '_f1cal_sprint_date',
            'sprint_time'  => '_f1cal_sprint_time',
        );
    }

    public function sanitize_weekend_type( $t ) {
        return ( $t === 'sprint' ) ? 'sprint' : 'normal';
    }

    public function sanitize_status( $status ) {
        $allowed = array( 'none', 'next', 'live', 'cancelled', 'completed' );
        return in_array( $status, $allowed, true ) ? $status : 'none';
    }

    private function scalar( $v ) {
        if ( is_array( $v ) ) {
            $first = reset( $v );
            return is_scalar( $first ) ? (string) $first : '';
        }
        if ( is_object( $v ) ) {
            return ( method_exists( $v, '__toString' ) ) ? (string) $v : '';
        }
        return is_scalar( $v ) ? (string) $v : '';
    }

    private function meta_str( $post_id, $meta_key ) {
        return $this->scalar( get_post_meta( (int) $post_id, (string) $meta_key, true ) );
    }

    private function force_https_url( $url ) {
        $url = (string) $url;
        if ( $url === '' ) return '';
        if ( preg_match( '~^https?://~i', $url ) ) {
            return set_url_scheme( $url, 'https' );
        }
        return $url;
    }

    private function get_flag_url_by_id( $attachment_id ) {
        $attachment_id = (int) $attachment_id;
        if ( $attachment_id <= 0 ) return '';
        $url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
        $url = $url ? (string) $url : '';
        return $this->force_https_url( $url );
    }

    private function get_flag_url_by_file( $file ) {
        $file = (string) $file;
        if ( $file === '' ) return '';

        $u = wp_upload_dir();
        $baseurl = isset( $u['baseurl'] ) ? (string) $u['baseurl'] : '';
        if ( $baseurl === '' ) return '';

        $baseurl = rtrim( $baseurl, '/' );
        $url = $baseurl . '/flags/' . rawurlencode( $file );
        return $this->force_https_url( $url );
    }

    private function validate_flag_file( $file ) {
        $file = sanitize_file_name( (string) $file );
        if ( $file === '' ) return '';

        $u = wp_upload_dir();
        $basedir = isset( $u['basedir'] ) ? (string) $u['basedir'] : '';
        if ( $basedir === '' ) return '';

        $dir = rtrim( $basedir, '/\\' ) . '/flags';
        $dir_real = realpath( $dir );
        if ( ! $dir_real || ! is_dir( $dir_real ) ) return '';

        $path = $dir_real . '/' . $file;
        $path_real = realpath( $path );
        if ( ! $path_real ) return '';

        if ( strpos( $path_real, $dir_real ) !== 0 ) return '';
        if ( ! is_file( $path_real ) ) return '';

        $ext = strtolower( pathinfo( $path_real, PATHINFO_EXTENSION ) );
        $allowed = array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif' );
        if ( ! in_array( $ext, $allowed, true ) ) return '';

        return $file;
    }

    public function is_valid_date( $ymd ) {
        return (bool) preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ymd );
    }

    public function is_valid_time( $hm ) {
        return (bool) preg_match( '~^\d{2}:\d{2}$~', $hm );
    }

    public function format_date_de( $ymd ) {
        if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ymd ) ) return $ymd;
        $parts = explode( '-', $ymd );
        if ( count( $parts ) !== 3 ) return $ymd;
        $y = $parts[0]; $m = $parts[1]; $d = $parts[2];
        return $d . '.' . $m . '.' . $y;
    }

    public function weekday_date_de( $ymd ) {
        if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', $ymd ) ) return $ymd;
        $ts = strtotime( $ymd . ' 12:00:00' );
        if ( ! $ts ) return $this->format_date_de( $ymd );

        $dow = (int) date( 'N', $ts );
        $map = array( 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So' );
        $w = isset( $map[$dow] ) ? $map[$dow] : '';
        return $w . ', ' . $this->format_date_de( $ymd );
    }

    public function get_meta( $post_id ) {
        $post_id = (int) $post_id;
        $k = $this->meta_keys();

        $flag_file = $this->meta_str( $post_id, $k['flag_file'] );
        $flag_file = $this->validate_flag_file( $flag_file );
        $flag_id = (int) get_post_meta( $post_id, $k['flag_id'], true );

        $flag_url = '';
        if ( $flag_file !== '' ) {
            $flag_url = $this->get_flag_url_by_file( $flag_file );
        } else {
            $flag_url = $this->get_flag_url_by_id( $flag_id );
        }
        $flag_url = $this->force_https_url( $flag_url );

        return array(
            'gp'           => $this->meta_str( $post_id, $k['gp'] ),
            'circuit'      => $this->meta_str( $post_id, $k['circuit'] ),
            'status'       => $this->meta_str( $post_id, $k['status'] ),
            'weekend_type' => $this->meta_str( $post_id, $k['weekend_type'] ),
            'flag_file'    => $flag_file,
            'flag_id'      => $flag_id,
            'flag_url'     => $flag_url,
            'fp1_date'     => $this->meta_str( $post_id, $k['fp1_date'] ),
            'fp1_time'     => $this->meta_str( $post_id, $k['fp1_time'] ),
            'fp2_date'     => $this->meta_str( $post_id, $k['fp2_date'] ),
            'fp2_time'     => $this->meta_str( $post_id, $k['fp2_time'] ),
            'fp3_date'     => $this->meta_str( $post_id, $k['fp3_date'] ),
            'fp3_time'     => $this->meta_str( $post_id, $k['fp3_time'] ),
            'quali_date'   => $this->meta_str( $post_id, $k['quali_date'] ),
            'quali_time'   => $this->meta_str( $post_id, $k['quali_time'] ),
            'race_date'    => $this->meta_str( $post_id, $k['race_date'] ),
            'race_time'    => $this->meta_str( $post_id, $k['race_time'] ),
            'sq_date'      => $this->meta_str( $post_id, $k['sq_date'] ),
            'sq_time'      => $this->meta_str( $post_id, $k['sq_time'] ),
            'sprint_date'  => $this->meta_str( $post_id, $k['sprint_date'] ),
            'sprint_time'  => $this->meta_str( $post_id, $k['sprint_time'] ),
        );
    }

    public function render_status_badge( $stat ) {
        $stat = $this->sanitize_status( $stat );

        if ( $stat === 'live' ) {
            return
                '<span class="f1cal-status f1cal-status--live" aria-label="Live">'.
                  '<span class="f1cal-status__icon" aria-hidden="true"><span class="f1cal-live-dot"></span></span>'.
                  '<span class="f1cal-status__text">Live</span>'.
                '</span>';
        }

        if ( $stat === 'next' ) {
            return
                '<span class="f1cal-status f1cal-status--next" aria-label="Next">'.
                  '<span class="f1cal-status__icon" aria-hidden="true">'.
                    '<span class="f1cal-next-arrows" aria-hidden="true">'.
                      '<span class="f1cal-next-chev"></span>'.
                      '<span class="f1cal-next-chev"></span>'.
                      '<span class="f1cal-next-chev"></span>'.
                    '</span>'.
                  '</span>'.
                  '<span class="f1cal-status__text">Next</span>'.
                '</span>';
        }

        if ( $stat === 'cancelled' ) {
            $svg =
                '<svg class="f1cal-cancel-svg" viewBox="0 0 18 16" aria-hidden="true" focusable="false" role="img">'.
                  '<rect x="7.5" y="1.2" width="3" height="9.6" rx="1.2" fill="currentColor"></rect>'.
                  '<rect x="7.5" y="12.3" width="3" height="2.2" rx="1.1" fill="currentColor"></rect>'.
                '</svg>';
            return
                '<span class="f1cal-status f1cal-status--cancelled" aria-label="Abgesagt">'.
                  '<span class="f1cal-status__icon" aria-hidden="true">'.$svg.'</span>'.
                  '<span class="f1cal-status__text">Abgesagt</span>'.
                '</span>';
        }

        if ( $stat === 'completed' ) {
            $svg =
                '<svg class="f1cal-flag-svg" viewBox="0 0 24 20" aria-hidden="true" focusable="false" role="img">'.
                  '<path d="M4 2v16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'.
                  '<path d="M6 3h12l-2 4 2 4H6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>'.
                  '<g transform="translate(7,4)" fill="currentColor">'.
                    '<rect x="0" y="0" width="2" height="2" opacity=".9"/>'.
                    '<rect x="4" y="0" width="2" height="2" opacity=".35"/>'.
                    '<rect x="2" y="2" width="2" height="2" opacity=".35"/>'.
                    '<rect x="6" y="2" width="2" height="2" opacity=".9"/>'.
                    '<rect x="0" y="4" width="2" height="2" opacity=".35"/>'.
                    '<rect x="4" y="4" width="2" height="2" opacity=".9"/>'.
                  '</g>'.
                '</svg>';

            return
                '<span class="f1cal-status f1cal-status--completed" aria-label="Abgeschlossen">'.
                  '<span class="f1cal-status__icon" aria-hidden="true">'.$svg.'</span>'.
                  '<span class="f1cal-status__text">Beendet</span>'.
                '</span>';
        }

        return '—';
    }

    private function add_session( &$bucket, $label, $date, $time ) {
        if ( $date === '' && $time === '' ) return;
        $key = ( $date !== '' && preg_match( '~^\d{4}-\d{2}-\d{2}$~', $date ) ) ? $date : 'tba';
        if ( ! isset( $bucket[$key] ) ) $bucket[$key] = array();
        $bucket[$key][] = array( 'time' => $time, 'label' => $label );
    }

    public function collect_days( $m ) {
        $wt = $this->sanitize_weekend_type( ! empty( $m['weekend_type'] ) ? $m['weekend_type'] : 'normal' );
        $days = array();

        if ( $wt === 'sprint' ) {
            $this->add_session( $days, '1. Freies Training', $m['fp1_date'], $m['fp1_time'] );
            $this->add_session( $days, 'Sprint Qualifying', $m['sq_date'], $m['sq_time'] );
            $this->add_session( $days, 'Sprint', $m['sprint_date'], $m['sprint_time'] );
            $this->add_session( $days, 'Das Qualifying', $m['quali_date'], $m['quali_time'] );
            $this->add_session( $days, 'Das Rennen', $m['race_date'], $m['race_time'] );
        } else {
            $this->add_session( $days, '1. Freies Training', $m['fp1_date'], $m['fp1_time'] );
            $this->add_session( $days, '2. Freies Training', $m['fp2_date'], $m['fp2_time'] );
            $this->add_session( $days, '3. Freies Training', $m['fp3_date'], $m['fp3_time'] );
            $this->add_session( $days, 'Das Qualifying', $m['quali_date'], $m['quali_time'] );
            $this->add_session( $days, 'Das Rennen', $m['race_date'], $m['race_time'] );
        }

        $keys = array_keys( $days );
        usort( $keys, function( $a, $b ){
            if ( $a === 'tba' && $b === 'tba' ) return 0;
            if ( $a === 'tba' ) return 1;
            if ( $b === 'tba' ) return -1;
            return strcmp( $a, $b );
        } );

        foreach ( $keys as $k ) {
            $sessions = $days[$k];
            usort( $sessions, function( $x, $y ){
                $tx = ( ! empty( $x['time'] ) && preg_match( '~^\d{2}:\d{2}$~', $x['time'] ) ) ? $x['time'] : '99:99';
                $ty = ( ! empty( $y['time'] ) && preg_match( '~^\d{2}:\d{2}$~', $y['time'] ) ) ? $y['time'] : '99:99';
                return strcmp( $tx, $ty );
            } );
            $days[$k] = $sessions;
        }
        return array( $keys, $days );
    }

    public function render_sessions_columns( $m ) {
        list( $keys, $days ) = $this->collect_days( $m );
        $count = max( 1, count( $keys ) );
        $cols = min( 6, $count );

        $out = '<div class="f1cal-days" style="--f1cal-cols:'.esc_attr( (string) $cols ).'">';
        foreach ( $keys as $k ) {
            $human = ( $k === 'tba' ) ? 'TBA' : $this->weekday_date_de( $k );
            $out .= '<div class="f1cal-daycol">';
            $out .= '<div class="f1cal-daycol__date">'.esc_html( $human ).'</div>';
            foreach ( $days[$k] as $s ) {
                $t = ( ! empty( $s['time'] ) ) ? $s['time'].' Uhr' : '—';
                $out .= '<div class="f1cal-daycol__line">'
                     .  '<span class="f1cal-daycol__time">'.esc_html( $t ).'</span>'
                     .  '<span class="f1cal-daycol__name">'.esc_html( $s['label'] ).'</span>'
                     .  '</div>';
            }
            $out .= '</div>';
        }
        $out .= '</div>';
        return $out;
    }

    public function render_cards( $races, $nrmap = array() ) {
        if ( empty( $races ) ) {
            return '<div class="f1cal-cards__empty">Keine Einträge</div>';
        }
        $out = '<div class="f1cal-cards">';
        $i = 1;
        foreach ( $races as $race ) {
            $pid = (int) $race->ID;
            $m   = $this->get_meta( $pid );
            $stat = $this->sanitize_status( ! empty( $m['status'] ) ? $m['status'] : 'none' );
            $wt   = $this->sanitize_weekend_type( ! empty( $m['weekend_type'] ) ? $m['weekend_type'] : 'normal' );

            $status_html = $this->render_status_badge( $stat );
            $gp = ( $m['gp'] !== '' ) ? $m['gp'] : get_the_title( $pid );
            $circuit = ( $m['circuit'] !== '' ) ? $m['circuit'] : '—';
            $flag_url = ! empty( $m['flag_url'] ) ? $m['flag_url'] : '';
            $format_label = ( $wt === 'sprint' ) ? 'Sprintformat' : 'Klassisches Format';
            $sessions_html = $this->render_sessions_columns( $m );

            $nr = ( is_array( $nrmap ) && isset( $nrmap[$pid] ) ) ? (int) $nrmap[$pid] : (int) $i;

            $out .= '<article class="f1cal-card is-'.esc_attr( $stat ).'" aria-label="Rennen '.$nr.'">';
            $out .= '  <div class="f1cal-card__top">';
            $out .= '    <div class="f1cal-card__nr"><span class="f1cal-card__nrlabel">NR.</span><span class="f1cal-card__nrval">'.esc_html( $nr ).'</span></div>';
            $out .= '    <div class="f1cal-card__status">'.$status_html.'</div>';
            $out .= '  </div>';

            $out .= '  <div class="f1cal-card__block">';
            $out .= '    <div class="f1cal-card__label">Grand Prix</div>';
            $out .= '    <div class="f1cal-card__value f1cal-card__gp">';
            $out .= '      <span class="f1cal-card__gpname">'.esc_html( $gp ).'</span>';
            if ( $flag_url !== '' ) {
                $out .= '      <img class="f1site-cal__flag f1cal-card__flag" src="'.esc_url( $flag_url ).'" alt="" loading="lazy">';
            }
            $out .= '    </div>';
            $out .= '  </div>';

            $out .= '  <div class="f1cal-card__block">';
            $out .= '    <div class="f1cal-card__label">Strecke</div>';
            $out .= '    <div class="f1cal-card__value">'.esc_html( $circuit ).'</div>';
            $out .= '    <div class="f1cal-card__format">'.esc_html( $format_label ).'</div>';
            $out .= '  </div>';

            $out .= '  <div class="f1cal-card__sessions">';
            $out .= '    <div class="f1cal-card__sessionshead">Sessions (Deutsche Zeit)</div>';
            $out .=      $sessions_html;
            $out .= '  </div>';

            $out .= '</article>';
            $i++;
        }
        $out .= '</div>';
        return $out;
    }

    private function get_ordered_ids() {
        $q = new WP_Query( array(
            'post_type'      => 'f1_race',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
        $ids = $q->posts ? $q->posts : array();
        return array_map( 'intval', $ids );
    }

    private function reindex_menu_order( $ids = null ) {
        if ( $ids === null ) $ids = $this->get_ordered_ids();
        $i = 0;
        foreach ( $ids as $pid ) {
            wp_update_post( array( 'ID' => (int) $pid, 'menu_order' => $i ), true );
            $i++;
        }
    }

    private function enforce_unique_status( $status, $keep_post_id ) {
        $status = $this->sanitize_status( $status );
        $keep_post_id = (int) $keep_post_id;
        $k = $this->meta_keys();

        if ( $status === 'live' ) {
            $q = new WP_Query( array(
                'post_type'      => 'f1_race',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => array(
                    array( 'key' => $k['status'], 'value' => array( 'live', 'next' ), 'compare' => 'IN' )
                )
            ) );
            foreach ( $q->posts as $pid ) {
                $pid = (int) $pid;
                if ( $pid === $keep_post_id ) continue;
                update_post_meta( $pid, $k['status'], 'none' );
            }
            return;
        }

        if ( $status === 'next' ) {
            $q = new WP_Query( array(
                'post_type'      => 'f1_race',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => array(
                    array( 'key' => $k['status'], 'value' => array( 'next', 'live' ), 'compare' => 'IN' )
                )
            ) );
            foreach ( $q->posts as $pid ) {
                $pid = (int) $pid;
                if ( $pid === $keep_post_id ) continue;
                update_post_meta( $pid, $k['status'], 'none' );
            }
            return;
        }
    }

    private function list_flags_from_folder() {
        $u = wp_upload_dir();
        $basedir = isset( $u['basedir'] ) ? (string) $u['basedir'] : '';
        $baseurl = isset( $u['baseurl'] ) ? (string) $u['baseurl'] : '';
        if ( $basedir === '' || $baseurl === '' ) return array();

        $dir = rtrim( $basedir, '/\\' ) . '/flags';
        if ( ! is_dir( $dir ) ) return array();

        $files = glob( $dir . '/*.{png,jpg,jpeg,webp,svg,gif}', GLOB_BRACE );
        if ( ! $files ) return array();

        $baseurl = rtrim( $baseurl, '/' );
        $out = array();

        foreach ( $files as $p ) {
            $bn = basename( $p );
            $ext = strtolower( pathinfo( $bn, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif' ), true ) ) continue;

            $label = preg_replace( '~\.[a-z0-9]+$~i', '', $bn );
            $label = str_replace( array( '_', '-' ), ' ', $label );
            $label = trim( $label );

            $out[] = array(
                'file'  => $bn,
                'url'   => $this->force_https_url( $baseurl . '/flags/' . rawurlencode( $bn ) ),
                'label' => $label,
            );
        }
        usort( $out, function( $a, $b ){
            return strcasecmp( (string) $a['label'], (string) $b['label'] );
        } );
        return $out;
    }

    private function get_media_options() {
        $flags_raw = $this->list_flags_from_folder();
        $flags = array();

        foreach ( $flags_raw as $f ) {
            if ( ! is_array( $f ) ) continue;
            $file = isset( $f['file'] ) ? (string) $f['file'] : '';
            $url  = isset( $f['url'] )  ? (string) $f['url']  : '';
            if ( $file === '' || $url === '' ) continue;

            if ( ! preg_match( '~\.png$~i', $file ) ) continue;
            $code = strtoupper( preg_replace( '~\.png$~i', '', $file ) );
            $label = $code;

            if ( ! preg_match( '~^[A-Z]{2}$~', $code ) ) {
                $label = isset( $f['label'] ) ? (string) $f['label'] : $file;
            }

            $flags[] = array(
                'value' => $file,
                'label' => $label,
                'url'   => $url,
                'file'  => $file,
                'code'  => $code,
            );
        }
        usort( $flags, function( $a, $b ){
            return strcmp( (string) $a['label'], (string) $b['label'] );
        } );
        return array( 'flags' => $flags );
    }

    /* ======================================================================
       AJAX Handlers
       ====================================================================== */

    public function ajax_save() {
        if ( ! $this->user_can_manage() ) wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $gp      = isset( $_POST['gp'] ) ? sanitize_text_field( wp_unslash( $_POST['gp'] ) ) : '';
        $circuit = isset( $_POST['circuit'] ) ? sanitize_text_field( wp_unslash( $_POST['circuit'] ) ) : '';
        $status  = isset( $_POST['status'] ) ? $this->sanitize_status( sanitize_text_field( wp_unslash( $_POST['status'] ) ) ) : 'none';
        $wt      = isset( $_POST['weekend_type'] ) ? $this->sanitize_weekend_type( sanitize_text_field( wp_unslash( $_POST['weekend_type'] ) ) ) : 'normal';

        $flag_file = isset( $_POST['flag_file'] ) ? sanitize_file_name( wp_unslash( $_POST['flag_file'] ) ) : '';
        $flag_file = $this->validate_flag_file( $flag_file );
        $flag_id = isset( $_POST['flag_id'] ) ? (int) $_POST['flag_id'] : 0;

        // Zeit-Felder sammeln
        $fields = ['fp1', 'fp2', 'fp3', 'sq', 'sprint', 'quali', 'race'];
        $dates = [];
        foreach ( $fields as $f ) {
            $dates[$f.'_date'] = isset( $_POST[$f.'_date'] ) ? sanitize_text_field( wp_unslash( $_POST[$f.'_date'] ) ) : '';
            $dates[$f.'_time'] = isset( $_POST[$f.'_time'] ) ? sanitize_text_field( wp_unslash( $_POST[$f.'_time'] ) ) : '';
        }

        if ( $gp === '' ) wp_send_json_error( array( 'message' => 'GP Name fehlt.' ) );
        if ( ! $this->is_valid_date( $dates['race_date'] ) ) wp_send_json_error( array( 'message' => 'Rennen-Datum fehlt/ist ungültig (YYYY-MM-DD).' ) );

        foreach ( $dates as $k => $v ) {
            if ( $v === '' ) continue;
            if ( strpos( $k, '_date' ) !== false && ! $this->is_valid_date( $v ) ) wp_send_json_error( array( 'message' => "$k ungültig." ) );
            if ( strpos( $k, '_time' ) !== false && ! $this->is_valid_time( $v ) ) wp_send_json_error( array( 'message' => "$k muss HH:MM sein." ) );
        }

        $k = $this->meta_keys();

        if ( $post_id > 0 ) {
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $gp ) );
        } else {
            $post_id = (int) wp_insert_post( array(
                'post_type'   => 'f1_race',
                'post_status' => 'publish',
                'post_title'  => $gp,
                'menu_order'  => 999999,
            ) );
            if ( ! $post_id ) wp_send_json_error( array( 'message' => 'Konnte Eintrag nicht erstellen.' ) );
        }

        update_post_meta( $post_id, $k['gp'], $gp );
        update_post_meta( $post_id, $k['circuit'], $circuit );
        update_post_meta( $post_id, $k['status'], $status );
        update_post_meta( $post_id, $k['weekend_type'], $wt );
        update_post_meta( $post_id, $k['flag_file'], $flag_file );
        update_post_meta( $post_id, $k['flag_id'], (int) $flag_id );

        foreach ( $dates as $key => $val ) {
            update_post_meta( $post_id, $k[$key], $val );
        }

        $this->enforce_unique_status( $status, $post_id );
        $this->reindex_menu_order();

        $flag_url = ( $flag_file !== '' ) ? $this->get_flag_url_by_file( $flag_file ) : $this->get_flag_url_by_id( (int) $flag_id );

        wp_send_json_success( array(
            'message'  => 'Gespeichert.',
            'post_id'  => $post_id,
            'flag_url' => $flag_url,
            'flag_file'=> $flag_file,
        ) );
    }

    public function ajax_delete() {
        if ( ! $this->user_can_manage() ) wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( $post_id <= 0 ) wp_send_json_error( array( 'message' => 'Ungültige ID.' ) );
        wp_delete_post( $post_id, true );
        $this->reindex_menu_order();
        wp_send_json_success( array( 'message' => 'Gelöscht.' ) );
    }

    public function ajax_move() {
        if ( ! $this->user_can_manage() ) wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $dir     = isset( $_POST['dir'] ) ? sanitize_text_field( wp_unslash( $_POST['dir'] ) ) : '';

        if ( $post_id <= 0 ) wp_send_json_error( array( 'message' => 'Ungültige ID.' ) );
        if ( ! in_array( $dir, array( 'up', 'down' ), true ) ) wp_send_json_error( array( 'message' => 'Ungültige Richtung.' ) );

        $ids = $this->get_ordered_ids();
        if ( count( $ids ) < 2 ) wp_send_json_success( array( 'message' => 'Keine Verschiebung möglich.' ) );

        $idx = array_search( $post_id, $ids, true );
        if ( $idx === false ) wp_send_json_error( array( 'message' => 'Eintrag nicht gefunden.' ) );

        $target = ( $dir === 'up' ) ? $idx - 1 : $idx + 1;
        if ( $target < 0 || $target >= count( $ids ) ) wp_send_json_success( array( 'message' => 'Limit erreicht.' ) );

        $tmp = $ids[$idx];
        $ids[$idx] = $ids[$target];
        $ids[$target] = $tmp;

        $this->reindex_menu_order( $ids );
        wp_send_json_success( array( 'message' => 'Verschoben.' ) );
    }

    public function ajax_list_flags() {
        if ( ! $this->user_can_manage() ) wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        $flags = $this->list_flags_from_folder();
        wp_send_json_success( array( 'flags' => $flags ) );
    }

    /* ======================================================================
       Admin Panel Rendering
       ====================================================================== */

    public function render_admin_panel( $races, $nonce, $media ) {
        // Backend-Liste: IMMER Original-Reihenfolge (Menu Order)
        if ( is_admin() ) {
            $ordered_ids = $this->get_ordered_ids();
            $by_id = array();
            if ( is_array( $races ) ) {
                foreach ( $races as $p ) {
                    if ( ! is_object( $p ) || empty( $p->ID ) ) continue;
                    $by_id[(int) $p->ID] = $p;
                }
            }
            $out = array();
            foreach ( $ordered_ids as $id ) {
                $id = (int) $id;
                if ( isset( $by_id[$id] ) ) $out[] = $by_id[$id];
            }
            foreach ( $by_id as $id => $p ) {
                $found = false;
                foreach ( $out as $op ) {
                    if ( (int) $op->ID === (int) $id ) { $found = true; break; }
                }
                if ( ! $found ) $out[] = $p;
            }
            $races = $out;
        }

        require F1_MANAGER_SUITE_PATH . 'includes/admin/calendar-admin-panel.php';
    }

    /* ======================================================================
       Shortcode
       ====================================================================== */

    public function render_shortcode() {
        $races = get_posts( array(
            'post_type'      => 'f1_race',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ) );

        // NR Map basierend auf Menu Order
        $nrmap = array();
        if ( ! empty( $races ) ) {
            $n = 1;
            foreach ( $races as $race ) {
                $nrmap[(int) $race->ID] = $n;
                $n++;
            }
        }

        // Frontend Sortierung (Live/Next oben)
        if ( ! empty( $races ) ) {
            usort( $races, function( $a, $b ){
                $k = $this->meta_keys();
                $ka = $this->meta_str( (int) $a->ID, $k['status'] );
                $kb = $this->meta_str( (int) $b->ID, $k['status'] );
                $ka = $this->sanitize_status( $ka );
                $kb = $this->sanitize_status( $kb );

                $prio = array(
                    'live'      => 0,
                    'next'      => 1,
                    'cancelled' => 9,
                    'completed' => 10,
                    'none'      => 50,
                );

                $pa = isset( $prio[$ka] ) ? $prio[$ka] : 50;
                $pb = isset( $prio[$kb] ) ? $prio[$kb] : 50;

                if ( $pa === $pb ) {
                    $moA = isset( $a->menu_order ) ? (int) $a->menu_order : 0;
                    $moB = isset( $b->menu_order ) ? (int) $b->menu_order : 0;
                    if ( $moA === $moB ) return ( (int) $a->ID < (int) $b->ID ) ? -1 : 1;
                    return ( $moA < $moB ) ? -1 : 1;
                }
                return ( $pa < $pb ) ? -1 : 1;
            } );
        }

        $nonce = wp_create_nonce( self::NONCE_ACTION );
        $media = array();
        if ( $this->user_can_manage() ) {
            $media = $this->get_media_options();
        }

        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/calendar-view.php';
        return ob_get_clean();
    }
}
