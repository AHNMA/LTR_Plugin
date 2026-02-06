<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Drivers {

    const CAPABILITY = 'manage_f1_driver_profiles';
    const NONCE_ACTION = 'f1drv_nonce';

    public function __construct() {
        // Init
        add_action( 'init', array( $this, 'register_cpt' ), 0 );
        add_action( 'admin_init', array( $this, 'add_capabilities' ) );
        add_action( 'admin_init', array( $this, 'flush_rewrites' ) );

        // Admin Menu
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // AJAX
        add_action( 'wp_ajax_f1drv_save', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_f1drv_delete', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_f1drv_move', array( $this, 'ajax_move' ) );

        // Frontend
        add_filter( 'the_content', array( $this, 'render_frontend_profile' ), 12 );
        add_action( 'wp_head', array( $this, 'frontend_css_fix' ), 99 );
    }

    /* =========================
       1) CPT & Init
       ========================= */

    public function register_cpt() {
        register_post_type( 'f1_driver', array(
            'labels' => array(
                'name'          => 'F1 Fahrer',
                'singular_name' => 'Fahrer',
            ),
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // Eigener Admin-Panel
            'show_in_rest'        => true,  // Gutenberg Support
            'has_archive'         => false,
            'rewrite'             => array( 'slug' => 'fahrer', 'with_front' => false ),
            'supports'            => array( 'title', 'editor', 'page-attributes' ),
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

    public function flush_rewrites() {
        if ( ! $this->user_can_manage() ) return;
        if ( get_option( 'f1drv_rewrite_flushed' ) === '1' ) return;
        flush_rewrite_rules( false );
        update_option( 'f1drv_rewrite_flushed', '1', false );
    }

    public function user_can_manage() {
        return current_user_can( self::CAPABILITY );
    }

    /* =========================
       2) Helper Methods
       ========================= */

    private function paths() {
        $base_dir = rtrim( WP_CONTENT_DIR, '/' ) . '/uploads';
        $base_url = rtrim( content_url( 'uploads' ), '/' );

        return array(
            'driver440_dir' => $base_dir . '/driver/440px/',
            'driver440_url' => $base_url . '/driver/440px/',
            'flags_dir'     => $base_dir . '/flags/',
            'flags_url'     => $base_url . '/flags/',
        );
    }

    private function get_team_options() {
        $teams = get_posts( array(
            'post_type'      => 'f1_team',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );

        $out = array();
        foreach ( $teams as $tid ) {
            $tid = (int) $tid;
            $out[] = array(
                'id'    => $tid,
                'title' => get_the_title( $tid ),
                'link'  => get_permalink( $tid ),
            );
        }
        return $out;
    }

    public function meta_keys() {
        return array(
            'slug'          => '_f1drv_slug',
            'img'           => '_f1drv_img',
            'flag'          => '_f1drv_flag',
            'team_id'       => '_f1drv_team_id',
            'team_inactive' => '_f1drv_team_inactive',
            'nationality'   => '_f1drv_nationality',
            'birthplace'    => '_f1drv_birthplace',
            'birthdate'     => '_f1drv_birthdate',
            'height'        => '_f1drv_height',
            'weight'        => '_f1drv_weight',
            'marital'       => '_f1drv_marital',
            'fb'            => '_f1drv_fb',
            'x'             => '_f1drv_x',
            'ig'            => '_f1drv_ig',
            'bio'           => '_f1drv_bio',
        );
    }

    /* Cleaners */
    private function clean_flag_code( $code ) {
        $code = strtolower( trim( (string) $code ) );
        $code = preg_replace( '~[^a-z]~', '', $code );
        if ( strlen( $code ) !== 2 ) return '';
        return $code;
    }

    private function clean_filename_png( $fn ) {
        $fn = sanitize_file_name( (string) $fn );
        if ( $fn === '' ) return '';
        if ( ! preg_match( '~\.png$~i', $fn ) ) return '';
        if ( strpos( $fn, '/' ) !== false || strpos( $fn, '\\' ) !== false ) return '';
        return $fn;
    }

    private function clean_link( $url ) {
        $url = trim( (string) $url );
        if ( $url === '' ) return '';
        if ( preg_match( '~^/[^\s]*$~', $url ) ) return sanitize_text_field( $url );
        $clean = esc_url_raw( $url );
        return $clean ? $clean : '';
    }

    private function clean_slug( $slug ) {
        $slug = trim( (string) $slug );
        if ( $slug === '' ) return '';
        return sanitize_title( $slug );
    }

    private function clean_bio( $bio ) {
        $bio = (string) $bio;
        $bio = wp_kses_post( $bio );
        return trim( $bio );
    }

    private function clean_int_id( $v ) {
        $v = (int) $v;
        return ( $v > 0 ) ? $v : 0;
    }

    private function clean_bool01( $v ) {
        if ( is_bool( $v ) ) return $v ? '1' : '0';
        $v = strtolower( trim( (string) $v ) );
        return in_array( $v, array( '1', 'true', 'on', 'yes' ), true ) ? '1' : '0';
    }

    /* File Checks */
    private function file_exists_in( $dir, $filename ) {
        $filename = $this->clean_filename_png( $filename );
        if ( $filename === '' ) return false;
        $full = rtrim( $dir, '/' ) . '/' . $filename;
        return file_exists( $full );
    }

    private function flag_exists( $dir, $code ) {
        $code = $this->clean_flag_code( $code );
        if ( $code === '' ) return false;
        $full = rtrim( $dir, '/' ) . '/' . $code . '.png';
        return file_exists( $full );
    }

    /* Meta Getter */
    public function get_meta( $post_id ) {
        $post_id = (int) $post_id;
        $k = $this->meta_keys();
        $m = array();

        foreach ( $k as $key => $meta_key ) {
            $m[$key] = (string) get_post_meta( $post_id, $meta_key, true );
        }

        $m['img']     = $this->clean_filename_png( $m['img'] );
        $m['flag']    = $this->clean_flag_code( $m['flag'] );
        $m['fb']      = $this->clean_link( $m['fb'] );
        $m['x']       = $this->clean_link( $m['x'] );
        $m['ig']      = $this->clean_link( $m['ig'] );
        $m['slug']    = $this->clean_slug( $m['slug'] );
        $m['bio']     = $this->clean_bio( $m['bio'] );
        $m['team_id'] = (string) $this->clean_int_id( $m['team_id'] );
        $m['team_inactive'] = $this->clean_bool01( $m['team_inactive'] );

        return $m;
    }

    /* Media Scanning */
    private function label_from_filename( $filename ) {
        $name = preg_replace( '~\.png$~i', '', (string) $filename );
        $name = preg_replace( '~_440px$~i', '', $name );
        $name = str_replace( array( '-', '_' ), ' ', $name );
        $name = trim( $name );
        if ( $name === '' ) return '—';
        return mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' );
    }

    private function scan_png_folder( $dir, $url_base, $mode = 'generic' ) {
        $out = array();
        if ( ! is_dir( $dir ) ) return $out;

        $files = glob( rtrim( $dir, '/' ) . '/*.png' );
        if ( ! $files ) $files = array();

        foreach ( $files as $fp ) {
            $bn = basename( $fp );
            if ( ! preg_match( '~\.png$~i', $bn ) ) continue;

            if ( $mode === 'flags' ) {
                $code = strtolower( preg_replace( '~\.png$~i', '', $bn ) );
                $code = preg_replace( '~[^a-z]~', '', $code );
                if ( strlen( $code ) !== 2 ) continue;

                $out[] = array(
                    'value' => $code,
                    'label' => strtoupper( $code ),
                    'url'   => rtrim( $url_base, '/' ) . '/' . $bn,
                    'file'  => $bn,
                    'code'  => strtoupper( $code ),
                );
            } else {
                $clean = $this->clean_filename_png( $bn );
                if ( $clean === '' ) continue;

                $out[] = array(
                    'value' => $clean,
                    'label' => $this->label_from_filename( $clean ),
                    'url'   => rtrim( $url_base, '/' ) . '/' . $clean,
                    'file'  => $clean,
                );
            }
        }

        usort( $out, function( $a, $b ){
            return strcmp( (string) $a['label'], (string) $b['label'] );
        } );

        return $out;
    }

    private function get_media_options() {
        $p = $this->paths();
        return array(
            'drivers440' => $this->scan_png_folder( $p['driver440_dir'], $p['driver440_url'], 'generic' ),
            'flags'      => $this->scan_png_folder( $p['flags_dir'],     $p['flags_url'],     'flags' ),
        );
    }

    /* Menu Order Helpers */
    private function get_ordered_ids() {
        $q = new WP_Query( array(
            'post_type'      => 'f1_driver',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
        $ids = $q->posts ? $q->posts : array();
        return array_map( 'intval', $ids );
    }

    private function get_next_menu_order() {
        $q = new WP_Query( array(
            'post_type'      => 'f1_driver',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'orderby'        => array( 'menu_order' => 'DESC', 'ID' => 'DESC' ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );

        if ( ! empty( $q->posts[0] ) ) {
            $max_id = (int) $q->posts[0];
            return ( (int) get_post_field( 'menu_order', $max_id ) ) + 1;
        }
        return 0;
    }

    private function reindex_menu_order( $ids = null ) {
        if ( $ids === null ) $ids = $this->get_ordered_ids();
        $i = 0;
        foreach ( $ids as $pid ) {
            wp_update_post( array( 'ID' => (int) $pid, 'menu_order' => $i ), true );
            $i++;
        }
    }

    /* URL Builders */
    public function driver_img_url( $filename ) {
        $filename = $this->clean_filename_png( $filename );
        if ( $filename === '' ) return '';
        $p = $this->paths();
        return rtrim( $p['driver440_url'], '/' ) . '/' . $filename;
    }

    public function flag_url_from_code( $code ) {
        $code = $this->clean_flag_code( $code );
        if ( $code === '' ) return '';
        $p = $this->paths();
        return rtrim( $p['flags_url'], '/' ) . '/' . $code . '.png';
    }

    /* Frontend Logic */
    private function team_color_from_team_id( $team_id ) {
        $team_id = (int) $team_id;
        if ( $team_id <= 0 ) return '';

        $candidates = array(
            '_f1team_teamcolor',
            '_f1team_color',
            '_f1_teamcolor',
            'teamcolor',
        );

        foreach ( $candidates as $mk ) {
            $v = get_post_meta( $team_id, $mk, true );
            $v = trim( (string) $v );
            if ( $v !== '' ) return $v;
        }

        return '';
    }

    private function accent_from_driver_id( $driver_id ) {
        $driver_id = (int) $driver_id;
        if ( $driver_id <= 0 ) return '#E00078';

        $m = $this->get_meta( $driver_id );

        $inactive = ( isset( $m['team_inactive'] ) && (string) $m['team_inactive'] === '1' );
        if ( $inactive ) return '#e72b99';

        $team_id = isset( $m['team_id'] ) ? (int) $m['team_id'] : 0;
        $team_color = ( $team_id > 0 ) ? $this->team_color_from_team_id( $team_id ) : '';
        return ( $team_color !== '' ) ? $team_color : '#E00078';
    }

    private function get_ordered_driver_ids_frontend() {
        return $this->get_ordered_ids();
    }

    private function is_driver_inactive_frontend( $driver_id ) {
        $driver_id = (int) $driver_id;
        if ( $driver_id <= 0 ) return false;

        static $cache = array();
        if ( array_key_exists( $driver_id, $cache ) ) return (bool) $cache[$driver_id];

        $m = $this->get_meta( $driver_id );
        $inactive = ( is_array( $m ) && isset( $m['team_inactive'] ) && (string) $m['team_inactive'] === '1' );

        $cache[$driver_id] = $inactive ? 1 : 0;
        return $inactive;
    }

    private function get_prev_next_by_menu_order( $current_id ) {
        $current_id = (int) $current_id;
        if ( $current_id <= 0 ) return array( 'prev' => 0, 'next' => 0 );

        static $cache_ids = null;
        if ( $cache_ids === null ) {
            $cache_ids = $this->get_ordered_driver_ids_frontend();
        }

        if ( empty( $cache_ids ) ) return array( 'prev' => 0, 'next' => 0 );

        $idx = array_search( $current_id, $cache_ids, true );
        if ( $idx === false ) return array( 'prev' => 0, 'next' => 0 );

        // Prev: rückwärts laufen bis aktiver Fahrer gefunden
        $prev_id = 0;
        for ( $i = $idx - 1; $i >= 0; $i-- ) {
            $cand = (int) $cache_ids[$i];
            if ( $cand <= 0 ) continue;

            if ( ! $this->is_driver_inactive_frontend( $cand ) ) {
                $prev_id = $cand;
                break;
            }
        }

        // Next: vorwärts laufen bis aktiver Fahrer gefunden
        $next_id = 0;
        $max = count( $cache_ids ) - 1;
        for ( $i = $idx + 1; $i <= $max; $i++ ) {
            $cand = (int) $cache_ids[$i];
            if ( $cand <= 0 ) continue;

            if ( ! $this->is_driver_inactive_frontend( $cand ) ) {
                $next_id = $cand;
                break;
            }
        }

        return array( 'prev' => $prev_id, 'next' => $next_id );
    }

    private function render_socials( $fb, $x, $ig, $extra_class = '' ) {
        $extra_class = trim( (string) $extra_class );

        $out = '<div class="f1drv__social' . ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' ) . '">';
        $has = false;

        if ( $fb !== '' ) { $has = true; $out .= '<a data-social="facebook" href="' . esc_url( $fb ) . '" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i></a>'; }
        if ( $x  !== '' ) { $has = true; $out .= '<a data-social="x" href="' . esc_url( $x ) . '" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i></a>'; }
        if ( $ig !== '' ) { $has = true; $out .= '<a data-social="instagram" href="' . esc_url( $ig ) . '" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i></a>'; }

        $out .= '</div>';
        return $has ? $out : '';
    }

    /* =========================
       3) AJAX Handlers
       ========================= */

    public function ajax_save() {
        if ( ! $this->user_can_manage() ) wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $paths = $this->paths();
        $k     = $this->meta_keys();

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

        $img  = isset( $_POST['img'] ) ? sanitize_text_field( wp_unslash( $_POST['img'] ) ) : '';
        $flag = isset( $_POST['flag'] ) ? sanitize_text_field( wp_unslash( $_POST['flag'] ) ) : '';

        $team_id = isset( $_POST['team_id'] ) ? (int) $_POST['team_id'] : 0;
        $team_inactive = isset( $_POST['team_inactive'] ) ? wp_unslash( $_POST['team_inactive'] ) : '0';

        $nationality = isset( $_POST['nationality'] ) ? sanitize_text_field( wp_unslash( $_POST['nationality'] ) ) : '';

        $birthplace = isset( $_POST['birthplace'] ) ? sanitize_text_field( wp_unslash( $_POST['birthplace'] ) ) : '';
        $birthdate  = isset( $_POST['birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['birthdate'] ) ) : '';

        $height  = isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( $_POST['height'] ) ) : '';
        $weight  = isset( $_POST['weight'] ) ? sanitize_text_field( wp_unslash( $_POST['weight'] ) ) : '';
        $marital = isset( $_POST['marital'] ) ? sanitize_text_field( wp_unslash( $_POST['marital'] ) ) : '';

        $fb = isset( $_POST['fb'] ) ? wp_unslash( $_POST['fb'] ) : '';
        $x  = isset( $_POST['x'] )  ? wp_unslash( $_POST['x'] )  : '';
        $ig = isset( $_POST['ig'] ) ? wp_unslash( $_POST['ig'] ) : '';

        $bio = isset( $_POST['bio'] ) ? wp_unslash( $_POST['bio'] ) : '';

        $slug = $this->clean_slug( $slug );
        $img  = $this->clean_filename_png( $img );
        $flag = $this->clean_flag_code( $flag );

        $team_id = $this->clean_int_id( $team_id );
        $team_inactive = $this->clean_bool01( $team_inactive );

        $fb = $this->clean_link( $fb );
        $x  = $this->clean_link( $x );
        $ig = $this->clean_link( $ig );

        $bio = $this->clean_bio( $bio );

        if ( $name === '' ) wp_send_json_error( array( 'message' => 'Name fehlt.' ) );

        if ( $img !== '' && ! $this->file_exists_in( $paths['driver440_dir'], $img ) ) {
            wp_send_json_error( array( 'message' => 'Fahrerbild nicht gefunden in /uploads/driver/440px/.' ) );
        }
        if ( $flag !== '' && ! $this->flag_exists( $paths['flags_dir'], $flag ) ) {
            wp_send_json_error( array( 'message' => 'Flagge nicht gefunden in /uploads/flags/ (erwartet: ' . $flag . '.png).' ) );
        }

        if ( $team_id > 0 ) {
            $t = get_post( $team_id );
            if ( ! $t || $t->post_type !== 'f1_team' || $t->post_status !== 'publish' ) {
                wp_send_json_error( array( 'message' => 'Team ist ungültig oder nicht veröffentlicht.' ) );
            }
        }

        if ( $post_id > 0 ) {
            $upd = array( 'ID' => $post_id, 'post_title' => $name );
            if ( $slug !== '' ) $upd['post_name'] = $slug;
            wp_update_post( $upd );
        } else {
            $ins = array(
                'post_type'   => 'f1_driver',
                'post_status' => 'publish',
                'post_title'  => $name,
                'menu_order'  => $this->get_next_menu_order(),
            );
            if ( $slug !== '' ) $ins['post_name'] = $slug;

            $post_id = (int) wp_insert_post( $ins, true );
            if ( ! $post_id || is_wp_error( $post_id ) ) {
                wp_send_json_error( array( 'message' => 'Konnte Fahrer nicht erstellen.' ) );
            }
        }

        update_post_meta( $post_id, $k['slug'], $slug );
        update_post_meta( $post_id, $k['img'], $img );
        update_post_meta( $post_id, $k['flag'], $flag );
        update_post_meta( $post_id, $k['team_id'], (string) $team_id );
        update_post_meta( $post_id, $k['team_inactive'], (string) $team_inactive );
        update_post_meta( $post_id, $k['nationality'], $nationality );
        update_post_meta( $post_id, $k['birthplace'], $birthplace );
        update_post_meta( $post_id, $k['birthdate'],  $birthdate );
        update_post_meta( $post_id, $k['height'], $height );
        update_post_meta( $post_id, $k['weight'], $weight );
        update_post_meta( $post_id, $k['marital'], $marital );
        update_post_meta( $post_id, $k['fb'], $fb );
        update_post_meta( $post_id, $k['x'],  $x );
        update_post_meta( $post_id, $k['ig'], $ig );
        update_post_meta( $post_id, $k['bio'], $bio );

        wp_send_json_success( array(
            'message' => 'Gespeichert.',
            'post_id' => $post_id,
            'permalink' => get_permalink( $post_id ),
        ) );
    }

    public function ajax_delete() {
        if ( ! $this->user_can_manage() ) wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        global $wpdb;

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( $post_id <= 0 ) wp_send_json_error( array( 'message' => 'Ungültige ID.' ) );

        $deleted_order = (int) get_post_field( 'menu_order', $post_id );

        wp_delete_post( $post_id, true );

        // Lücken schließen
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts}
                 SET menu_order = menu_order - 1
                 WHERE post_type = %s AND post_status = %s AND menu_order > %d",
                'f1_driver', 'publish', $deleted_order
            )
        );

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
        if ( $idx === false ) wp_send_json_error( array( 'message' => 'Eintrag nicht in Liste gefunden.' ) );

        $target = ( $dir === 'up' ) ? $idx - 1 : $idx + 1;
        if ( $target < 0 || $target >= count( $ids ) ) {
            wp_send_json_success( array( 'message' => 'Schon am Rand – keine Verschiebung möglich.' ) );
        }

        $a = (int) $ids[$idx];
        $b = (int) $ids[$target];

        $oa = (int) get_post_field( 'menu_order', $a );
        $ob = (int) get_post_field( 'menu_order', $b );

        wp_update_post( array( 'ID' => $a, 'menu_order' => $ob ), true );
        wp_update_post( array( 'ID' => $b, 'menu_order' => $oa ), true );

        wp_send_json_success( array( 'message' => 'Verschoben.' ) );
    }

    /* =========================
       4) Admin Panel
       ========================= */

    public function register_admin_menu() {
        add_menu_page(
            'Fahrer',
            'Fahrer',
            self::CAPABILITY,
            'f1drv-admin-panel',
            array( $this, 'render_admin_panel' ),
            'dashicons-plus-alt',
            28
        );
        remove_submenu_page( 'f1drv-admin-panel', 'f1drv-admin-panel' );
    }

    public function render_admin_panel() {
        if ( ! $this->user_can_manage() ) wp_die( 'Keine Berechtigung.' );

        $drivers = get_posts( array(
            'post_type'      => 'f1_driver',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ) );

        $teams = $this->get_team_options();
        $nonce = wp_create_nonce( self::NONCE_ACTION );
        $media = $this->get_media_options();

        require F1_MANAGER_SUITE_PATH . 'includes/admin/drivers-admin.php';
    }

    /* =========================
       5) Frontend
       ========================= */

    public function frontend_css_fix() {
        if ( ! is_singular( 'f1_driver' ) ) return;
        ?>
        <style>
          .single-f1_driver .aft-view-count,
          .single-f1_driver .aft-social-share,
          .single-f1_driver .nav-previous,
          .single-f1_driver .nav-next{
            display:none !important;
          }

          .single-f1_driver .aft-post-excerpt-and-meta.color-pad,
          .single-f1_driver .aft-post-excerpt-and-meta .color-pad,
          .single-f1_driver .aft-post-excerpt-and-meta{
            display:none !important;
          }

          .single-f1_driver .read-img.pos-rel{
            display:none !important;
          }

          .single-f1_driver .post-navigation{
            display:none !important;
          }

          .single-f1_driver .entry-content{
            margin: 5px 0px 0px 0px !important;
          }
        </style>
        <?php
    }

    public function render_frontend_profile( $content ) {
        if ( ! is_singular( 'f1_driver' ) ) return $content;
        if ( ! in_the_loop() || ! is_main_query() ) return $content;

        $post_id = get_the_ID();
        if ( ! $post_id ) return $content;

        $m = $this->get_meta( $post_id );
        $name = get_the_title( $post_id );
        $img_url  = $this->driver_img_url( $m['img'] );
        $flag_url = $this->flag_url_from_code( $m['flag'] );
        $flag_code = $m['flag'];

        $editor_content_html = trim( (string) $content );

        $team_id   = isset( $m['team_id'] ) ? (int) $m['team_id'] : 0;
        $inactive  = ( isset( $m['team_inactive'] ) && (string) $m['team_inactive'] === '1' );

        $team_id_effective = $inactive ? 0 : $team_id;

        $team_name = '';
        if ( $team_id_effective > 0 && get_post_status( $team_id_effective ) === 'publish' ) {
            $team_name = get_the_title( $team_id_effective );
        }

        if ( $inactive ) {
            $accent = '#e72b99';
        } else {
            $team_color = ( $team_id_effective > 0 ) ? $this->team_color_from_team_id( $team_id_effective ) : '';
            $accent = ( $team_color !== '' ) ? $team_color : '#E00078';
        }
        $head = '#202020';

        $socials_left  = $this->render_socials( $m['fb'], $m['x'], $m['ig'] );
        $socials_stack = $this->render_socials( $m['fb'], $m['x'], $m['ig'], 'f1drv__social--stack' );

        $rows = array();
        if ( $team_name !== '' ) {
            $rows[] = array( 'Team', $team_name );
            $rows[] = array( '__spacer__', '' );
        }
        if ( $m['nationality'] !== '' ) $rows[] = array( 'Nationalität', $m['nationality'] );
        if ( $m['birthplace']  !== '' ) $rows[] = array( 'Geburtsort', $m['birthplace'] );
        if ( $m['birthdate']   !== '' ) $rows[] = array( 'Geburtsdatum', $m['birthdate'] );
        if ( $m['marital']     !== '' ) $rows[] = array( 'Familienstand', $m['marital'] );
        if ( $m['height']      !== '' ) $rows[] = array( 'Größe', $m['height'] );
        if ( $m['weight']      !== '' ) $rows[] = array( 'Gewicht', $m['weight'] );

        $bio = $m['bio'];

        $fallback = home_url( '/teams-fahrer/' );
        $back_url = wp_validate_redirect( wp_get_referer(), $fallback );

        // Prev/Next
        $adj = $this->get_prev_next_by_menu_order( $post_id );
        $prev_post = ( ! empty( $adj['prev'] ) ) ? get_post( (int) $adj['prev'] ) : null;
        $next_post = ( ! empty( $adj['next'] ) ) ? get_post( (int) $adj['next'] ) : null;

        if ( $prev_post && ( $prev_post->post_type !== 'f1_driver' || $prev_post->post_status !== 'publish' ) ) $prev_post = null;
        if ( $next_post && ( $next_post->post_type !== 'f1_driver' || $next_post->post_status !== 'publish' ) ) $next_post = null;

        $prev_color = ( $prev_post && ! empty( $prev_post->ID ) ) ? $this->accent_from_driver_id( $prev_post->ID ) : '';
        $next_color = ( $next_post && ! empty( $next_post->ID ) ) ? $this->accent_from_driver_id( $next_post->ID ) : '';

        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/driver-profile-view.php';
        return ob_get_clean();
    }
}
