<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Teams {

    const CAPABILITY = 'manage_f1_team_profiles';
    const NONCE_ACTION = 'f1team_nonce';

    public function __construct() {
        // Init
        add_action( 'init', array( $this, 'register_cpt' ), 0 );
        add_action( 'admin_init', array( $this, 'add_capabilities' ) );
        add_action( 'admin_init', array( $this, 'flush_rewrites' ) );

        // Admin Menu
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // AJAX
        add_action( 'wp_ajax_f1team_save', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_f1team_delete', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_f1team_move', array( $this, 'ajax_move' ) );

        // Frontend
        add_filter( 'the_content', array( $this, 'render_frontend_profile' ), 12 );
        add_action( 'wp_head', array( $this, 'frontend_css_fix' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_styles' ), 20 );
    }

    /* =========================
       1) CPT & Init
       ========================= */

    public function register_cpt() {
        register_post_type( 'f1_team', array(
            'labels' => array(
                'name'            => 'F1 Teams',
                'singular_name' => 'Team',
            ),
            'public'              => true,
            'publicly_queryable'  => true,
            'show_in_rest'        => true, // Gutenberg
            'show_ui'             => true,
            'show_in_menu'        => false,
            'has_archive'         => false,
            'rewrite'             => array( 'slug' => 'teams', 'with_front' => false ),
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
        if ( get_option( 'f1team_rewrite_flushed' ) === '1' ) return;
        flush_rewrite_rules( false );
        update_option( 'f1team_rewrite_flushed', '1', false );
    }

    public function user_can_manage() {
        return current_user_can( self::CAPABILITY );
    }

    public function enqueue_block_styles() {
        if ( ! is_singular( 'f1_team' ) ) return;
        wp_enqueue_style( 'wp-block-library' );
    }

    /* =========================
       2) Helper Methods
       ========================= */

    private function paths() {
        $base_dir = rtrim( WP_CONTENT_DIR, '/' ) . '/uploads';
        $base_url = rtrim( content_url( 'uploads' ), '/' );

        return array(
            'teamlogo_dir' => $base_dir . '/teams/',
            'teamlogo_url' => $base_url . '/teams/',
            'car_dir'      => $base_dir . '/cars/',
            'car_url'      => $base_url . '/cars/',
            'flags_dir'    => $base_dir . '/flags/',
            'flags_url'    => $base_url . '/flags/',
        );
    }

    public function meta_keys() {
        return array(
            'slug'        => '_f1team_slug',
            'teamlogo'    => '_f1team_teamlogo',
            'carimg'      => '_f1team_carimg',
            'flag'        => '_f1team_flag',
            'nationality' => '_f1team_nationality',
            'entry_year'  => '_f1team_entry_year',
            'teamchief'   => '_f1team_teamchief',
            'base'        => '_f1team_base',
            'chassis'     => '_f1team_chassis',
            'powerunit'   => '_f1team_powerunit',
            'fb'          => '_f1team_fb',
            'x'           => '_f1team_x',
            'ig'          => '_f1team_ig',
            'teamcolor'   => '_f1team_color',
            'bio'         => '_f1team_bio',
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

    private function clean_hex_color( $hex ) {
        $hex = trim( (string) $hex );
        if ( $hex === '' ) return '';
        if ( $hex[0] !== '#' ) $hex = '#' . $hex;
        if ( ! preg_match( '~^#[0-9a-fA-F]{6}$~', $hex ) ) return '';
        return strtoupper( $hex );
    }

    private function clean_bio( $bio ) {
        $bio = (string) $bio;
        $bio = wp_kses_post( $bio );
        return trim( $bio );
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

        $m['teamlogo']  = $this->clean_filename_png( $m['teamlogo'] );
        $m['carimg']    = $this->clean_filename_png( $m['carimg'] );
        $m['flag']      = $this->clean_flag_code( $m['flag'] );
        $m['fb']        = $this->clean_link( $m['fb'] );
        $m['x']         = $this->clean_link( $m['x'] );
        $m['ig']        = $this->clean_link( $m['ig'] );
        $m['slug']      = $this->clean_slug( $m['slug'] );
        $m['teamcolor'] = $this->clean_hex_color( $m['teamcolor'] );
        $m['bio']       = $this->clean_bio( $m['bio'] );

        return $m;
    }

    /* Media Scanning */
    private function label_from_filename( $filename ) {
        $name = preg_replace( '~\.png$~i', '', (string) $filename );
        $name = preg_replace( '~_car_210px$~i', '', $name );
        $name = preg_replace( '~_210px$~i', '', $name );
        $name = preg_replace( '~_150px$~i', '', $name );
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
            'teamlogos' => $this->scan_png_folder( $p['teamlogo_dir'], $p['teamlogo_url'], 'generic' ),
            'cars'      => $this->scan_png_folder( $p['car_dir'],      $p['car_url'],      'generic' ),
            'flags'     => $this->scan_png_folder( $p['flags_dir'],    $p['flags_url'],    'flags' ),
        );
    }

    /* Menu Order Helpers */
    private function get_ordered_ids() {
        $q = new WP_Query( array(
            'post_type'      => 'f1_team',
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
            'post_type'      => 'f1_team',
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
    public function teamlogo_url( $filename ) {
        $filename = $this->clean_filename_png( $filename );
        if ( $filename === '' ) return '';
        $p = $this->paths();
        return rtrim( $p['teamlogo_url'], '/' ) . '/' . $filename;
    }

    public function carimg_url( $filename ) {
        $filename = $this->clean_filename_png( $filename );
        if ( $filename === '' ) return '';
        $p = $this->paths();
        return rtrim( $p['car_url'], '/' ) . '/' . $filename;
    }

    public function flag_url_from_code( $code ) {
        $code = $this->clean_flag_code( $code );
        if ( $code === '' ) return '';
        $p = $this->paths();
        return rtrim( $p['flags_url'], '/' ) . '/' . $code . '.png';
    }

    /* Frontend Logic */
    private function hex_to_rgb( $hex ) {
        $hex = trim( (string) $hex );
        if ( $hex === '' ) return '224,0,120';

        $hex = ltrim( $hex, '#' );

        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
            return '224,0,120';
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        return $r . ',' . $g . ',' . $b;
    }

    private function get_team_color( $post_id ) {
        $m = $this->get_meta( (int) $post_id );
        $c = isset( $m['teamcolor'] ) ? trim( (string) $m['teamcolor'] ) : '';
        return $c !== '' ? $c : '#E00078';
    }

    private function get_prev_next_by_menu_order( $current_id ) {
        $current_id = (int) $current_id;
        if ( $current_id <= 0 ) return array( 'prev' => 0, 'next' => 0 );

        static $cache_ids = null;
        if ( $cache_ids === null ) {
            $cache_ids = $this->get_ordered_ids();
        }

        if ( empty( $cache_ids ) ) return array( 'prev' => 0, 'next' => 0 );

        $idx = array_search( $current_id, $cache_ids, true );
        if ( $idx === false ) return array( 'prev' => 0, 'next' => 0 );

        $prev_id = ( $idx > 0 ) ? (int) $cache_ids[$idx - 1] : 0;
        $next_id = ( $idx < ( count( $cache_ids ) - 1 ) ) ? (int) $cache_ids[$idx + 1] : 0;

        return array( 'prev' => $prev_id, 'next' => $next_id );
    }

    private function render_socials( $fb, $x, $ig, $extra_class = '' ) {
        $extra_class = trim( (string) $extra_class );

        $out = '<div class="f1team__social' . ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' ) . '">';
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

        $teamlogo = isset( $_POST['teamlogo'] ) ? sanitize_text_field( wp_unslash( $_POST['teamlogo'] ) ) : '';
        $carimg   = isset( $_POST['carimg'] )   ? sanitize_text_field( wp_unslash( $_POST['carimg'] ) )   : '';
        $flag     = isset( $_POST['flag'] )     ? sanitize_text_field( wp_unslash( $_POST['flag'] ) )     : '';

        $nationality = isset( $_POST['nationality'] ) ? sanitize_text_field( wp_unslash( $_POST['nationality'] ) ) : '';
        $entry_year  = isset( $_POST['entry_year'] )  ? sanitize_text_field( wp_unslash( $_POST['entry_year'] ) )  : '';
        $teamchief   = isset( $_POST['teamchief'] )   ? sanitize_text_field( wp_unslash( $_POST['teamchief'] ) )   : '';
        $base        = isset( $_POST['base'] )        ? sanitize_text_field( wp_unslash( $_POST['base'] ) )        : '';
        $chassis     = isset( $_POST['chassis'] )     ? sanitize_text_field( wp_unslash( $_POST['chassis'] ) )     : '';
        $powerunit   = isset( $_POST['powerunit'] )   ? sanitize_text_field( wp_unslash( $_POST['powerunit'] ) )   : '';

        $fb = isset( $_POST['fb'] ) ? wp_unslash( $_POST['fb'] ) : '';
        $x  = isset( $_POST['x'] )  ? wp_unslash( $_POST['x'] )  : '';
        $ig = isset( $_POST['ig'] ) ? wp_unslash( $_POST['ig'] ) : '';

        $teamcolor = isset( $_POST['teamcolor'] ) ? sanitize_text_field( wp_unslash( $_POST['teamcolor'] ) ) : '';
        $bio = isset( $_POST['bio'] ) ? wp_unslash( $_POST['bio'] ) : '';

        $slug     = $this->clean_slug( $slug );
        $teamlogo = $this->clean_filename_png( $teamlogo );
        $carimg   = $this->clean_filename_png( $carimg );
        $flag     = $this->clean_flag_code( $flag );

        $fb = $this->clean_link( $fb );
        $x  = $this->clean_link( $x );
        $ig = $this->clean_link( $ig );

        $teamcolor = $this->clean_hex_color( $teamcolor );
        $bio = $this->clean_bio( $bio );

        if ( $name === '' ) wp_send_json_error( array( 'message' => 'Name fehlt.' ) );

        if ( $teamlogo !== '' && ! $this->file_exists_in( $paths['teamlogo_dir'], $teamlogo ) ) {
            wp_send_json_error( array( 'message' => 'Team-Logo nicht gefunden in /uploads/teams/.' ) );
        }
        if ( $carimg !== '' && ! $this->file_exists_in( $paths['car_dir'], $carimg ) ) {
            wp_send_json_error( array( 'message' => 'Auto-Bild nicht gefunden in /uploads/cars/.' ) );
        }
        if ( $flag !== '' && ! $this->flag_exists( $paths['flags_dir'], $flag ) ) {
            wp_send_json_error( array( 'message' => 'Flagge nicht gefunden in /uploads/flags/ (erwartet: ' . $flag . '.png).' ) );
        }

        if ( $post_id > 0 ) {
            $upd = array( 'ID' => $post_id, 'post_title' => $name );
            if ( $slug !== '' ) $upd['post_name'] = $slug;
            wp_update_post( $upd );
        } else {
            $ins = array(
                'post_type'   => 'f1_team',
                'post_status' => 'publish',
                'post_title'  => $name,
                'menu_order'  => $this->get_next_menu_order(),
            );
            if ( $slug !== '' ) $ins['post_name'] = $slug;

            $post_id = (int) wp_insert_post( $ins, true );
            if ( ! $post_id || is_wp_error( $post_id ) ) {
                wp_send_json_error( array( 'message' => 'Konnte Team nicht erstellen.' ) );
            }
        }

        update_post_meta( $post_id, $k['slug'], $slug );
        update_post_meta( $post_id, $k['teamlogo'], $teamlogo );
        update_post_meta( $post_id, $k['carimg'],   $carimg );
        update_post_meta( $post_id, $k['flag'],     $flag );
        update_post_meta( $post_id, $k['nationality'], $nationality );
        update_post_meta( $post_id, $k['entry_year'],  $entry_year );
        update_post_meta( $post_id, $k['teamchief'],   $teamchief );
        update_post_meta( $post_id, $k['base'],        $base );
        update_post_meta( $post_id, $k['chassis'],     $chassis );
        update_post_meta( $post_id, $k['powerunit'],   $powerunit );
        update_post_meta( $post_id, $k['fb'], $fb );
        update_post_meta( $post_id, $k['x'],  $x );
        update_post_meta( $post_id, $k['ig'], $ig );
        update_post_meta( $post_id, $k['teamcolor'], $teamcolor );
        update_post_meta( $post_id, $k['bio'], $bio );

        wp_send_json_success( array(
            'message'   => 'Gespeichert.',
            'post_id'   => $post_id,
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

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts}
                 SET menu_order = menu_order - 1
                 WHERE post_type = %s AND post_status = %s AND menu_order > %d",
                'f1_team', 'publish', $deleted_order
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
            'Teams',
            'Teams',
            self::CAPABILITY,
            'f1team-admin-panel',
            array( $this, 'render_admin_panel' ),
            'dashicons-plus-alt',
            27
        );
        remove_submenu_page( 'f1team-admin-panel', 'f1team-admin-panel' );
    }

    public function render_admin_panel() {
        if ( ! $this->user_can_manage() ) wp_die( 'Keine Berechtigung.' );

        $teams = get_posts( array(
            'post_type'      => 'f1_team',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ) );

        $nonce = wp_create_nonce( self::NONCE_ACTION );
        $media = $this->get_media_options();

        require F1_MANAGER_SUITE_PATH . 'includes/admin/teams-admin.php';
    }

    /* =========================
       5) Frontend
       ========================= */

    public function frontend_css_fix() {
        if ( ! is_singular( 'f1_team' ) ) return;
        ?>
        <style>
          .single-f1_team .aft-view-count,
          .single-f1_team .aft-social-share{
            display:none !important;
          }

          .single-f1_team .nav-previous,
          .single-f1_team .nav-next,
          .single-f1_team .post-navigation{
            display:none !important;
          }

          .single-f1_team .aft-post-excerpt-and-meta.color-pad,
          .single-f1_team .aft-post-excerpt-and-meta .color-pad,
          .single-f1_team .aft-post-excerpt-and-meta{
            display:none !important;
          }

          .single-f1_team .read-img.pos-rel{
            display:none !important;
          }

          .single-f1_team .entry-content{
            margin: 5px 0px 0px 0px !important;
          }
        </style>
        <?php
    }

    public function render_frontend_profile( $content ) {
        if ( ! is_singular( 'f1_team' ) ) return $content;
        if ( ! in_the_loop() || ! is_main_query() ) return $content;

        $post_id = get_the_ID();
        if ( ! $post_id ) return $content;

        $m = $this->get_meta( $post_id );
        $name = get_the_title( $post_id );

        $logo_url = $this->teamlogo_url( $m['teamlogo'] );
        $car_url  = $this->carimg_url( $m['carimg'] );
        $flag_url = $this->flag_url_from_code( $m['flag'] );

        $team_color = isset( $m['teamcolor'] ) ? trim( (string) $m['teamcolor'] ) : '';
        if ( $team_color === '' ) $team_color = '#E00078';

        $accent = $team_color;
        $head   = '#202020';

        $socials_left  = $this->render_socials( $m['fb'], $m['x'], $m['ig'] );
        $socials_stack = $this->render_socials( $m['fb'], $m['x'], $m['ig'], 'f1team__social--stack' );

        $rows = array();
        if ( $m['nationality'] !== '' ) $rows[] = array( 'Nationalität', $m['nationality'] );
        if ( $m['entry_year']  !== '' ) $rows[] = array( 'Eintrittsjahr', $m['entry_year'] );
        if ( $m['teamchief']   !== '' ) $rows[] = array( 'Teamchef', $m['teamchief'] );
        if ( $m['base']        !== '' ) $rows[] = array( 'Basis', $m['base'] );
        if ( $m['chassis']     !== '' ) $rows[] = array( 'Chassis', $m['chassis'] );
        if ( $m['powerunit']   !== '' ) $rows[] = array( 'Power Unit', $m['powerunit'] );

        $bio = $m['bio'];

        $fallback = home_url( '/teams-fahrer/' );
        $back_url = wp_validate_redirect( wp_get_referer(), $fallback );

        $editor_content_html = trim( (string) $content );

        $adj = $this->get_prev_next_by_menu_order( $post_id );

        $prev_post = ( ! empty( $adj['prev'] ) ) ? get_post( (int) $adj['prev'] ) : null;
        $next_post = ( ! empty( $adj['next'] ) ) ? get_post( (int) $adj['next'] ) : null;

        if ( $prev_post && ( $prev_post->post_type !== 'f1_team' || $prev_post->post_status !== 'publish' ) ) $prev_post = null;
        if ( $next_post && ( $next_post->post_type !== 'f1_team' || $next_post->post_status !== 'publish' ) ) $next_post = null;

        $prev_color = ( $prev_post && ! empty( $prev_post->ID ) ) ? $this->get_team_color( $prev_post->ID ) : '';
        $next_color = ( $next_post && ! empty( $next_post->ID ) ) ? $this->get_team_color( $next_post->ID ) : '';

        $prev_rgb = ( $prev_color !== '' ) ? $this->hex_to_rgb( $prev_color ) : '224,0,120';
        $next_rgb = ( $next_color !== '' ) ? $this->hex_to_rgb( $next_color ) : '224,0,120';

        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/team-profile-view.php';
        return ob_get_clean();
    }
}
