<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Profile {

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Init Hooks
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'template_redirect', array( $this, 'template_redirect' ) );

        // Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Shortcodes
        add_shortcode( 'f1_frontend_profile', array( $this, 'render_shortcode' ) );

        // Filters
        add_filter( 'show_admin_bar', array( $this, 'filter_show_admin_bar' ), 20 );
        add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 10, 3 );
        add_filter( 'get_avatar_url', array( $this, 'filter_get_avatar_url' ), 20, 3 );

        // Password Reset
        add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 10, 2 );
        add_filter( 'retrieve_password_message', array( $this, 'filter_retrieve_password_message' ), 999, 4 );

        // Privacy Confirm Intercept
        add_action( 'login_form_confirmaction', array( $this, 'intercept_login_form_confirmaction' ), 0 );

        // Admin Post (Privacy Export)
        add_action( 'admin_post_f1fp_privacy_export_request', array( $this, 'handle_privacy_export_request' ) );

        // Query Vars
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
    }

    /* =========================================================
       INIT Logic
       ========================================================= */

    public function add_query_vars( $vars ) {
        $vars[] = 'f1fp_privacy_confirm';
        $vars[] = 'f1pr_reset';
        return $vars;
    }

    public function init() {
        // Rewrites
        add_rewrite_rule( '^privacy-confirm/?$', 'index.php?f1fp_privacy_confirm=1', 'top' );
        add_rewrite_rule( '^passwort-zuruecksetzen/?$', 'index.php?f1pr_reset=1', 'top' );

        // Flush once
        if ( ! get_option( 'f1fp_rewrites_flushed_v1' ) ) {
            flush_rewrite_rules( false );
            update_option( 'f1fp_rewrites_flushed_v1', 1, true );
        }

        // Handle POST Actions
        $this->handle_post_actions();

        // Handle Password Reset Redirects
        $this->handle_login_page_redirects();
    }

    public function admin_init() {
        if ( ! self::is_normal_user() ) return;
        if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return;
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;

        global $pagenow;
        $allowed = array( 'admin-ajax.php', 'admin-post.php' );
        if ( in_array( $pagenow, $allowed, true ) ) return;

        wp_safe_redirect( self::profile_url() );
        exit;
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'f1-profile', F1_MANAGER_SUITE_URL . 'assets/css/f1-profile.css', array(), '1.0.0' );
        wp_enqueue_script( 'f1-profile', F1_MANAGER_SUITE_URL . 'assets/js/f1-profile.js', array(), '1.0.0', true );
    }

    /* =========================================================
       HANDLERS
       ========================================================= */

    private function handle_post_actions() {
        if ( empty( $_POST['f1fp_action'] ) && empty( $_POST['f1pr_action'] ) ) return;

        // Password Reset Set New
        if ( ! empty( $_POST['f1pr_action'] ) && $_POST['f1pr_action'] === 'set_new_password' ) {
            $this->handle_set_new_password();
            return;
        }

        if ( ! is_user_logged_in() ) return;
        $user_id = get_current_user_id();
        $action  = sanitize_key( $_POST['f1fp_action'] );

        if ( $action === 'disconnect_google' ) $this->handle_disconnect_google( $user_id );
        if ( $action === 'send_reset_link' )   $this->handle_send_reset_link( $user_id );
        if ( $action === 'delete_profile' )    $this->handle_delete_profile( $user_id );
        if ( $action === 'save_profile' )      $this->handle_save_profile( $user_id );
    }

    public function handle_privacy_export_request() {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( wp_get_referer() ?: home_url( '/' ) ) );
            exit;
        }
        $user_id = get_current_user_id();

        if ( get_transient( self::lock_key( $user_id, 'privacy_export' ) ) ) {
            self::set_flash( $user_id, 'error', 'Bitte warte kurz – du hast gerade bereits einen Datenexport angefordert.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }
        set_transient( self::lock_key( $user_id, 'privacy_export' ), 1, 30 );

        if ( empty( $_POST['f1fp_privacy_nonce'] ) || ! wp_verify_nonce( $_POST['f1fp_privacy_nonce'], 'f1fp_privacy_export' ) ) {
            self::set_flash( $user_id, 'error', 'Sicherheitsprüfung fehlgeschlagen.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }

        if ( empty( $_POST['f1fp_export_confirm'] ) || $_POST['f1fp_export_confirm'] !== '1' ) {
            self::set_flash( $user_id, 'error', 'Bitte bestätige die Datenexport-Anfrage per Checkbox.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }

        $user = get_userdata( $user_id );
        if ( ! $user || empty( $user->user_email ) ) {
            self::set_flash( $user_id, 'error', 'Keine gültige E-Mail-Adresse.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }

        $request_id = wp_create_user_request( $user->user_email, 'export_personal_data' );
        if ( is_wp_error( $request_id ) ) {
            self::set_flash( $user_id, 'error', 'Fehler: ' . $request_id->get_error_message() );
        } else {
            $sent = wp_send_user_request( $request_id );
            if ( is_wp_error( $sent ) ) {
                self::set_flash( $user_id, 'error', 'Mail Fehler: ' . $sent->get_error_message() );
            } else {
                self::set_flash( $user_id, 'success', 'Bestätigungs-Mail gesendet.' );
            }
        }
        wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
        exit;
    }

    private function handle_disconnect_google( $user_id ) {
        if ( ! wp_verify_nonce( $_POST['f1fp_disconnect_nonce'] ?? '', 'f1fp_disconnect_google' ) ) {
            self::set_flash( $user_id, 'error', 'Nonce Fehler.' );
        } else {
            delete_user_meta( $user_id, 'bp_google_id' );
            self::set_flash( $user_id, 'success', array( 'Verbindung getrennt.', 'Nutze "Passwort-Reset" für Login ohne Google.' ) );
        }
        wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
        exit;
    }

    private function handle_send_reset_link( $user_id ) {
        if ( ! wp_verify_nonce( $_POST['f1fp_reset_nonce'] ?? '', 'f1fp_send_reset_link' ) ) {
            self::set_flash( $user_id, 'error', 'Nonce Fehler.' );
        } else {
            $user = get_userdata( $user_id );
            $res = retrieve_password( $user->user_login );
            if ( is_wp_error( $res ) ) {
                self::set_flash( $user_id, 'error', 'Fehler: ' . $res->get_error_message() );
            } else {
                self::set_flash( $user_id, 'success', 'Reset-Link gesendet.' );
            }
        }
        wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
        exit;
    }

    private function handle_delete_profile( $user_id ) {
        if ( get_transient( self::lock_key( $user_id, 'delete_profile' ) ) ) {
            self::set_flash( $user_id, 'error', 'Warte kurz...' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }
        set_transient( self::lock_key( $user_id, 'delete_profile' ), 1, 15 );

        if ( ! wp_verify_nonce( $_POST['f1fp_delete_nonce'] ?? '', 'f1fp_delete_profile' ) ) {
            self::set_flash( $user_id, 'error', 'Nonce Fehler.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }

        if ( empty( $_POST['f1fp_delete_confirm'] ) ) {
            self::set_flash( $user_id, 'error', 'Bitte Checkbox bestätigen.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_logout();
        wp_delete_user( $user_id );
        wp_safe_redirect( add_query_arg( 'account_deleted', '1', home_url( '/' ) ) );
        exit;
    }

    private function handle_save_profile( $user_id ) {
        if ( get_transient( self::lock_key( $user_id, 'save_profile' ) ) ) {
            self::set_flash( $user_id, 'error', 'Speichern läuft bereits.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }
        set_transient( self::lock_key( $user_id, 'save_profile' ), 1, 10 );

        if ( ! wp_verify_nonce( $_POST['f1fp_nonce'] ?? '', 'f1fp_save_profile' ) ) {
            self::set_flash( $user_id, 'error', 'Nonce Fehler.' );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }

        $user = get_userdata( $user_id );
        $errors = array();

        $first   = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last    = sanitize_text_field( $_POST['last_name'] ?? '' );
        $display = sanitize_text_field( $_POST['display_name'] ?? '' );
        $url     = self::clean_url( $_POST['user_url'] ?? '' );
        $bio     = sanitize_textarea_field( $_POST['description'] ?? '' );

        $fb      = self::clean_url( $_POST['f1_social_fb'] ?? '' );
        $x       = self::clean_url( $_POST['f1_social_x'] ?? '' );
        $ig      = self::clean_url( $_POST['f1_social_ig'] ?? '' );

        $email = sanitize_email( $_POST['user_email'] ?? $user->user_email );
        $email_changed = ( strtolower( $email ) !== strtolower( $user->user_email ) );

        if ( $email_changed ) {
            if ( ! is_email( $email ) ) $errors[] = 'Ungültige E-Mail.';
            elseif ( email_exists( $email ) ) $errors[] = 'E-Mail bereits vergeben.';
        }

        if ( ! $display ) {
            $fallback = trim( "$first $last" );
            $display = $fallback ?: $user->user_login;
        }

        // Avatar
        if ( ! empty( $_POST['f1fp_avatar_remove'] ) ) {
            $old = self::get_custom_avatar_id( $user_id );
            if ( $old ) {
                $is_av = get_post_meta( $old, '_f1fp_is_user_avatar', true );
                if ( $is_av ) wp_delete_attachment( $old, true );
            }
            delete_user_meta( $user_id, self::avatar_meta_key() );
        } elseif ( ! empty( $_FILES['f1fp_avatar']['name'] ) ) {
            if ( $_FILES['f1fp_avatar']['size'] > 2 * 1024 * 1024 ) {
                $errors[] = 'Bild zu groß (max 2MB).';
            } else {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $up = wp_handle_upload( $_FILES['f1fp_avatar'], array( 'test_form' => false ) );
                if ( ! empty( $up['error'] ) ) {
                    $errors[] = 'Upload Fehler: ' . $up['error'];
                } else {
                    $att = array(
                        'post_mime_type' => $up['type'],
                        'post_title'     => 'avatar-' . $user_id . '-' . time(),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    );
                    $aid = wp_insert_attachment( $att, $up['file'] );
                    wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $up['file'] ) );

                    $old = self::get_custom_avatar_id( $user_id );
                    if ( $old && $old != $aid ) {
                        if ( get_post_meta( $old, '_f1fp_is_user_avatar', true ) ) wp_delete_attachment( $old, true );
                    }
                    update_user_meta( $user_id, self::avatar_meta_key(), $aid );
                    update_post_meta( $aid, '_f1fp_is_user_avatar', '1' );
                }
            }
        }

        if ( $errors ) {
            self::set_flash( $user_id, 'error', $errors );
            wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
            exit;
        }

        $args = array(
            'ID' => $user_id,
            'first_name' => $first, 'last_name' => $last, 'display_name' => $display,
            'user_url' => $url, 'description' => $bio
        );
        if ( $email_changed ) $args['user_email'] = $email;

        $r = wp_update_user( $args );
        if ( is_wp_error( $r ) ) {
            self::set_flash( $user_id, 'error', $r->get_error_message() );
        } else {
            update_user_meta( $user_id, '_f1usr_fb', $fb );
            update_user_meta( $user_id, '_f1usr_x', $x );
            update_user_meta( $user_id, '_f1usr_ig', $ig );
            self::set_flash( $user_id, 'success', 'Gespeichert.' );
        }
        wp_safe_redirect( add_query_arg( 'f1fp', '1', self::profile_url() ) );
        exit;
    }

    /* --- Password Reset Logic --- */

    private function handle_login_page_redirects() {
        if ( ! isset( $GLOBALS['pagenow'] ) || $GLOBALS['pagenow'] !== 'wp-login.php' ) return;
        $action = $_REQUEST['action'] ?? '';
        if ( ! in_array( $action, array( 'lostpassword', 'rp', 'resetpass' ), true ) ) return;

        if ( is_user_logged_in() && in_array( $action, array( 'rp', 'resetpass' ) ) ) {
            self::set_reset_flash( 'error', 'Bitte erst ausloggen.' );
            wp_safe_redirect( self::reset_url() );
            exit;
        }

        $url = self::reset_url();
        if ( ! empty( $_GET['key'] ) ) $url = add_query_arg( 'key', $_GET['key'], $url );
        if ( ! empty( $_GET['login'] ) ) $url = add_query_arg( 'login', $_GET['login'], $url );
        if ( $action === 'lostpassword' ) $url = add_query_arg( 'view', 'request', $url );
        else $url = add_query_arg( 'action', 'rp', $url );

        wp_safe_redirect( $url );
        exit;
    }

    private function handle_set_new_password() {
        if ( is_user_logged_in() ) {
            self::set_reset_flash( 'error', 'Bitte erst ausloggen.' );
            wp_safe_redirect( self::reset_url() );
            exit;
        }
        if ( ! wp_verify_nonce( $_POST['f1pr_nonce'] ?? '', 'f1pr_set_new_password' ) ) {
            self::set_reset_flash( 'error', 'Nonce Fehler.' );
            wp_safe_redirect( self::reset_url() );
            exit;
        }

        $login = $_POST['login'] ?? '';
        $key   = $_POST['key'] ?? '';
        $p1    = $_POST['pass1'] ?? '';
        $p2    = $_POST['pass2'] ?? '';

        if ( ! $login || ! $key ) {
            self::set_reset_flash( 'error', 'Link ungültig.' );
            wp_safe_redirect( self::reset_url() );
            exit;
        }
        if ( $p1 !== $p2 || strlen( $p1 ) < 8 ) {
            self::set_reset_flash( 'error', 'Passwörter ungültig/ungleich.' );
            wp_safe_redirect( self::build_reset_link( rawurldecode( $login ), rawurldecode( $key ) ) );
            exit;
        }

        $u = check_password_reset_key( rawurldecode( $key ), rawurldecode( $login ) );
        if ( is_wp_error( $u ) || ! $u ) {
            self::set_reset_flash( 'error', 'Link abgelaufen.' );
            wp_safe_redirect( add_query_arg( 'view', 'request', self::reset_url() ) );
            exit;
        }

        reset_password( $u, $p1 );
        self::set_reset_flash( 'success', 'Passwort geändert.' );
        wp_safe_redirect( add_query_arg( 'pw', 'changed', self::reset_url() ) );
        exit;
    }

    /* =========================================================
       TEMPLATES
       ========================================================= */

    public function template_redirect() {
        if ( get_query_var( 'f1fp_privacy_confirm' ) ) {
            $this->render_privacy_confirm();
            exit;
        }
        if ( get_query_var( 'f1pr_reset' ) ) {
            $this->render_password_reset();
            exit;
        }
    }

    private function render_privacy_confirm() {
        nocache_headers();
        $token = $_GET['token'] ?? '';
        $data = $token ? get_transient( self::privacy_confirm_transient_key( $token ) ) : null;
        if ( $token ) delete_transient( self::privacy_confirm_transient_key( $token ) );

        $type  = $data['type'] ?? 'error';
        $title = $data['title'] ?? 'Bestätigung';
        $html  = $data['html'] ?? '<p>Link ungültig.</p>';

        require F1_MANAGER_SUITE_PATH . 'includes/frontend/privacy-confirm-view.php';
    }

    private function render_password_reset() {
        nocache_headers();
        $flash = self::get_reset_flash();
        $pw_changed = ( isset( $_GET['pw'] ) && $_GET['pw'] === 'changed' );
        $action = $_GET['action'] ?? '';

        $has_reset_params = ( ! empty( $_GET['login'] ) && ! empty( $_GET['key'] ) && in_array( $action, array( 'rp', 'resetpass' ) ) );
        $q_login = $_GET['login'] ?? '';
        $q_key   = $_GET['key'] ?? '';
        $block_logged_in_reset = ( is_user_logged_in() && $has_reset_params );
        $logout_url = wp_logout_url( home_url( $_SERVER['REQUEST_URI'] ) );
        $title = $has_reset_params ? 'Neues Passwort setzen' : 'Passwort zurücksetzen';

        require F1_MANAGER_SUITE_PATH . 'includes/frontend/password-reset-view.php';
    }

    public function render_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="f1fp-wrap"><div class="f1fp-card"><div class="f1fp-body">Bitte einloggen.</div></div></div>';
        }

        $user_id = get_current_user_id();
        $user    = wp_get_current_user();
        $flash   = self::get_flash( $user_id );

        $fb = get_user_meta( $user_id, '_f1usr_fb', true );
        $x  = get_user_meta( $user_id, '_f1usr_x', true );
        $ig = get_user_meta( $user_id, '_f1usr_ig', true );
        $has_google = get_user_meta( $user_id, 'bp_google_id', true );

        $avatar_url = self::get_custom_avatar_url( $user_id, 240 );
        $initials   = self::user_initials( $user->display_name );
        $has_custom_avatar = ( self::get_custom_avatar_id( $user_id ) > 0 );

        global $wp_roles;
        $roles = (array)$user->roles;
        $role_names = array();
        foreach ( $roles as $r ) $role_names[] = translate_user_role( $wp_roles->roles[$r]['name'] ?? $r );
        $role_label = implode( ', ', $role_names );

        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/profile-view.php';
        return ob_get_clean();
    }

    /* =========================================================
       FILTERS & HELPERS
       ========================================================= */

    public function filter_show_admin_bar( $show ) {
        return self::is_normal_user() ? false : $show;
    }

    public function filter_login_redirect( $redirect_to, $req, $user ) {
        if ( is_wp_error( $user ) || ! $user ) return $redirect_to;
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) return $redirect_to;
        return self::profile_url();
    }

    public function filter_get_avatar_url( $url, $id_or_email, $args ) {
        $size = $args['size'] ?? 96;
        $user_id = 0;
        if ( is_numeric( $id_or_email ) ) $user_id = (int)$id_or_email;
        elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) $user_id = $id_or_email->user_id;
        elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $u = get_user_by( 'email', $id_or_email );
            if ( $u ) $user_id = $u->ID;
        }
        if ( $user_id > 0 ) {
            $c = self::get_custom_avatar_url( $user_id, $size );
            if ( $c ) return $c;
        }
        return self::placeholder_avatar_data_uri( $size );
    }

    public function filter_lostpassword_url( $url, $redirect ) {
        $u = self::reset_url();
        if ( $redirect ) $u = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $u );
        return add_query_arg( 'view', 'request', $u );
    }

    public function filter_retrieve_password_message( $message, $key, $login, $user_data ) {
        $link = self::build_reset_link( $login, $key );
        $html_link = str_replace( '&', '&amp;', $link );
        $pattern = '~https?://[^\s"\'>]+wp-login\.php\?[^\s"\'>]*\baction=(?:rp|resetpass)\b[^\s"\'>]*~i';

        $msg = preg_replace_callback( $pattern, function( $m ) use ( $link, $html_link ) {
            return ( strpos( $m[0], '&amp;' ) !== false ) ? $html_link : $link;
        }, $message );

        if ( strpos( $msg, $link ) === false && strpos( $msg, $html_link ) === false ) {
            $msg .= "\n\n" . $link;
        }
        return $msg;
    }

    public function intercept_login_form_confirmaction() {
        if ( ! function_exists( 'wp_validate_user_request_key' ) ) require_once ABSPATH . 'wp-admin/includes/user.php';

        $rid = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;
        $key = isset( $_GET['confirm_key'] ) ? $_GET['confirm_key'] : '';

        $fail = function( $msg ) {
            $t = wp_generate_password( 20, false );
            set_transient( self::privacy_confirm_transient_key( $t ), array( 'type'=>'error', 'html'=>esc_html( $msg ) ), 300 );
            wp_safe_redirect( add_query_arg( 'token', $t, self::privacy_confirm_url() ) );
            exit;
        };

        if ( ! $rid || ! $key ) $fail( 'Link ungültig.' );
        $res = wp_validate_user_request_key( $rid, $key );
        if ( is_wp_error( $res ) ) $fail( $res->get_error_message() );

        do_action( 'user_request_action_confirmed', $rid );
        $html = function_exists( '_wp_privacy_account_request_confirmed_message' ) ? _wp_privacy_account_request_confirmed_message( $rid ) : 'Danke.';

        $t = wp_generate_password( 20, false );
        set_transient( self::privacy_confirm_transient_key( $t ), array( 'type'=>'success', 'title'=>'Bestätigung', 'html'=>$html ), 300 );
        wp_safe_redirect( add_query_arg( 'token', $t, self::privacy_confirm_url() ) );
        exit;
    }

    /* --- Static Helpers --- */

    public static function is_normal_user( $uid = 0 ) {
        if ( ! is_user_logged_in() ) return false;
        if ( ! $uid ) $uid = get_current_user_id();
        if ( user_can( $uid, 'manage_options' ) || user_can( $uid, 'edit_posts' ) ) return false;
        return true;
    }
    public static function profile_url() { return home_url( '/userprofile/' ); }
    public static function reset_url() { return home_url( '/passwort-zuruecksetzen/' ); }
    public static function privacy_confirm_url() { return home_url( '/privacy-confirm/' ); }

    public static function clean_url( $u ) {
        $u = trim( $u );
        if ( preg_match( '~^/[^\s]*$~', $u ) ) return $u;
        return esc_url_raw( $u );
    }
    public static function flash_key( $uid ) { return 'f1fp_flash_' . (int)$uid; }
    public static function lock_key( $uid, $act ) { return 'f1fp_lock_' . $act . '_' . (int)$uid; }
    public static function set_flash( $uid, $type, $msg ) { set_transient( self::flash_key( $uid ), array( 'type'=>$type, 'messages'=>(array)$msg ), 60 ); }
    public static function get_flash( $uid ) { $f = get_transient( self::flash_key( $uid ) ); delete_transient( self::flash_key( $uid ) ); return $f; }

    public static function set_reset_flash( $type, $msg ) { set_transient( 'f1pr_flash', array( 'type'=>$type, 'messages'=>(array)$msg ), 60 ); }
    public static function get_reset_flash() { $f = get_transient( 'f1pr_flash' ); delete_transient( 'f1pr_flash' ); return $f; }

    public static function avatar_meta_key() { return '_f1fp_avatar_id'; }
    public static function get_custom_avatar_id( $uid ) { return (int)get_user_meta( $uid, self::avatar_meta_key(), true ); }
    public static function get_custom_avatar_url( $uid, $s = 120 ) {
        $aid = self::get_custom_avatar_id( $uid );
        if ( ! $aid ) return '';
        return wp_get_attachment_image_url( $aid, array( $s, $s ) ) ?: '';
    }
    public static function user_initials( $name ) {
        $parts = preg_split( '~\s+~', trim( $name ) );
        $l = '';
        foreach ( $parts as $p ) { if ( $p ) $l .= mb_substr( $p, 0, 1 ); if ( mb_strlen( $l ) >= 2 ) break; }
        return mb_strtoupper( $l ) ?: 'U';
    }
    public static function placeholder_avatar_data_uri( $size ) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 100 100"><rect width="100" height="100" fill="#FFFFFF"/><rect x="0" y="0" width="100" height="8" fill="#E00078"/><circle cx="50" cy="41" r="16" fill="#202020"/><path d="M18 92c7-22 57-22 64 0" fill="#202020"/></svg>';
        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( $svg );
    }
    public static function privacy_confirm_transient_key( $t ) { return 'f1fp_privacy_confirm_' . preg_replace( '~[^a-zA-Z0-9_\-]~', '', $t ); }
    public static function build_reset_link( $login, $key ) {
        return add_query_arg( array( 'action'=>'rp', 'login'=>rawurlencode( $login ), 'key'=>rawurlencode( $key ) ), self::reset_url() );
    }
}
