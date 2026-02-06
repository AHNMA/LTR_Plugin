<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Login {

    public function __construct() {
        // Init Hooks
        add_action( 'init', array( $this, 'init_login_logic' ), 1 );
        add_action( 'login_init', array( $this, 'redirect_wp_login' ), 1 );
        add_action( 'admin_init', array( $this, 'redirect_wp_admin' ) );

        // Filters
        add_filter( 'login_url', array( $this, 'custom_login_url' ), 10, 3 );
        add_filter( 'lostpassword_url', array( $this, 'custom_lostpassword_url' ), 10, 2 );
        add_filter( 'login_redirect', array( $this, 'custom_login_redirect' ), 10, 3 );
        add_filter( 'get_avatar_url', array( $this, 'google_avatar_url' ), 10, 3 );

        // AJAX Handlers
        add_action( 'wp_ajax_nopriv_bp_acc_ajax_login', array( $this, 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_bp_acc_ajax_lostpass', array( $this, 'ajax_lostpass' ) );
        add_action( 'wp_ajax_nopriv_bp_acc_ajax_reg_check', array( $this, 'ajax_reg_check' ) );

        // Social Login
        add_action( 'init', array( $this, 'handle_google_auth' ) );

        // Rate Limit
        add_action( 'lostpassword_post', array( $this, 'check_lostpass_rate_limit' ), 0 );

        // Frontend
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_login_modal' ), 100 );
    }

    public function enqueue_assets() {
        if ( is_admin() ) return;

        wp_enqueue_style( 'f1-login', F1_MANAGER_SUITE_URL . 'assets/css/f1-login.css', array(), F1_MANAGER_SUITE_VERSION );
        wp_enqueue_script( 'turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true );
        wp_enqueue_script( 'f1-login', F1_MANAGER_SUITE_URL . 'assets/js/f1-login.js', array(), F1_MANAGER_SUITE_VERSION, true );

        wp_localize_script( 'f1-login', 'f1_login_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'google_auth_url' => home_url( '/?bp_social_auth=google' ),
        ));

        // Pass Messages to Window (legacy support)
        wp_add_inline_script( 'f1-login', 'window.BP_ACC_MSG = ' . wp_json_encode( self::get_message_map(), JSON_UNESCAPED_UNICODE ) . ';', 'before' );
    }

    public function init_login_logic() {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( is_user_logged_in() ) return;

        // Handle Registration
        if ( ! empty( $_POST['bp_acc_action'] ) && $_POST['bp_acc_action'] === 'register' ) {
            $this->handle_registration();
        }
    }

    private function handle_registration() {
        if ( ! get_option( 'users_can_register' ) ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.disabled', 'Registrierung deaktiviert!' );
            return;
        }

        if ( empty( $_POST['bp_acc_reg_nonce'] ) || ! wp_verify_nonce( $_POST['bp_acc_reg_nonce'], 'bp_acc_register' ) ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.nonce_fail', 'Sicherheitscheck fehlgeschlagen.' );
            return;
        }

        $ts = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';
        $ok = self::turnstile_verify( $ts, 'register' );
        if ( is_wp_error( $ok ) ) {
            $GLOBALS['bp_acc_reg_errors'][] = $ok->get_error_message();
            return;
        }

        $username = isset( $_POST['bp_reg_user'] )  ? sanitize_user( $_POST['bp_reg_user'], true ) : '';
        $email    = isset( $_POST['bp_reg_email'] ) ? sanitize_email( $_POST['bp_reg_email'] ) : '';
        $pass1    = isset( $_POST['bp_reg_pass1'] ) ? $_POST['bp_reg_pass1'] : '';
        $pass2    = isset( $_POST['bp_reg_pass2'] ) ? $_POST['bp_reg_pass2'] : '';
        $accept   = ( ! empty( $_POST['bp_reg_accept'] ) && $_POST['bp_reg_accept'] === '1' );

        $GLOBALS['bp_acc_reg_values']['user']    = $username;
        $GLOBALS['bp_acc_reg_values']['email']   = $email;
        $GLOBALS['bp_acc_reg_values']['accept'] = $accept ? '1' : '0';

        if ( empty( $username ) || strlen( $username ) < 3 ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.user_min', 'Benutzername zu kurz.' );
        } elseif ( username_exists( $username ) ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.user_taken', 'Benutzername vergeben.' );
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.email_invalid', 'E-Mail ungültig.' );
        } elseif ( email_exists( $email ) ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.email_taken', 'E-Mail vergeben.' );
        }

        if ( empty( $pass1 ) || strlen( $pass1 ) < 8 ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.pass_min', 'Passwort zu kurz.' );
        } elseif ( $pass1 !== $pass2 ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.pass_mismatch', 'Passwörter stimmen nicht überein.' );
        }

        if ( ! $accept ) {
            $GLOBALS['bp_acc_reg_errors'][] = self::msg( 'reg.consent_required', 'AGB Zustimmung fehlt.' );
        }

        if ( ! empty( $GLOBALS['bp_acc_reg_errors'] ) ) return;

        $user_id = wp_create_user( $username, $pass1, $email );
        if ( is_wp_error( $user_id ) ) {
            $GLOBALS['bp_acc_reg_errors'][] = $user_id->get_error_message() ?: self::msg( 'reg.failed', 'Fehler beim Erstellen.' );
            return;
        }

        wp_update_user( array( 'ID' => $user_id, 'display_name' => $username ) );
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );
        do_action( 'wp_login', $username, get_user_by( 'id', $user_id ) );
        wp_safe_redirect( self::get_profile_url() );
        exit;
    }

    public function redirect_wp_login() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( is_user_logged_in() && ! self::is_normal_user() ) return;

        $action  = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
        $allowed = array( 'rp', 'resetpass', 'postpass', 'logout', 'confirmaction' );
        if ( in_array( $action, $allowed, true ) ) return;

        $to = ( $action === 'lostpassword' ) ? self::get_lostpass_url() : self::get_frontend_login_url();
        wp_safe_redirect( $to );
        exit;
    }

    public function custom_login_url( $login_url, $redirect, $force_reauth ) {
        if ( is_admin() ) return $login_url;
        $u = self::get_frontend_login_url();
        if ( ! empty( $redirect ) ) $u = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $u );
        return $u;
    }

    public function custom_lostpassword_url( $lostpassword_url, $redirect ) {
        if ( is_admin() ) return $lostpassword_url;
        $u = self::get_lostpass_url();
        if ( ! empty( $redirect ) ) $u = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $u );
        return $u;
    }

    public function custom_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( is_wp_error( $user ) || ! $user ) return $redirect_to;
        if ( ! self::is_normal_user( $user ) ) return $redirect_to;
        return self::get_profile_url();
    }

    public function redirect_wp_admin() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( ! is_user_logged_in() || ! self::is_normal_user() ) return;

        global $pagenow;
        if ( $pagenow === 'profile.php' || $pagenow === 'user-edit.php' || $pagenow === 'index.php' ) {
            wp_safe_redirect( self::get_profile_url() );
            exit;
        }
    }

    // --- Social Login (Google) ---

    public function handle_google_auth() {
        // A) Callback
        if ( isset( $_GET['code'], $_GET['state'] ) && isset( $_GET['bp_social_auth'] ) && $_GET['bp_social_auth'] === 'google' ) {

            if ( ! wp_verify_nonce( $_GET['state'], 'bp_social_google_login' ) ) {
                wp_die( 'Sicherheitsprüfung fehlgeschlagen (Invalid State).' );
            }

            $token_url = 'https://oauth2.googleapis.com/token';
            $body = array(
                'client_id'     => BP_SOCIAL_GOOGLE_CLIENT_ID,
                'client_secret' => BP_SOCIAL_GOOGLE_CLIENT_SECRET,
                'code'          => $_GET['code'],
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => home_url( '/?bp_social_auth=google' )
            );

            $response = wp_remote_post( $token_url, array( 'body' => $body ) );

            if ( is_wp_error( $response ) ) {
                wp_die( 'Google Verbindung fehlgeschlagen: ' . $response->get_error_message() );
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $data['access_token'] ) ) {
                wp_die( 'Fehler: Kein Access Token erhalten.' );
            }

            $access_token = $data['access_token'];
            $info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
            $info_response = wp_remote_get( $info_url, array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) ) );

            if ( is_wp_error( $info_response ) ) wp_die( 'User-Daten konnten nicht abgerufen werden.' );

            $user_info = json_decode( wp_remote_retrieve_body( $info_response ), true );
            if ( empty( $user_info['email'] ) ) wp_die( 'Google hat keine E-Mail geliefert.' );

            $email = sanitize_email( $user_info['email'] );
            $google_id = sanitize_text_field( $user_info['id'] );
            $first_name = isset( $user_info['given_name'] ) ? sanitize_text_field( $user_info['given_name'] ) : '';
            $last_name = isset( $user_info['family_name'] ) ? sanitize_text_field( $user_info['family_name'] ) : '';
            $full_name = isset( $user_info['name'] ) ? sanitize_text_field( $user_info['name'] ) : '';

            $state_key = 'bp_soc_' . substr( sanitize_key( $_GET['state'] ), 0, 32 );
            $flow_data = get_transient( $state_key );
            $has_consent = ( isset( $flow_data['consent'] ) && $flow_data['consent'] === true );
            delete_transient( $state_key );

            if ( is_user_logged_in() ) {
                $current_user_id = get_current_user_id();
                $existing_user = get_users( array( 'meta_key' => 'bp_google_id', 'meta_value' => $google_id, 'number' => 1 ) );
                if ( ! empty( $existing_user ) && $existing_user[0]->ID !== $current_user_id ) {
                    wp_die( 'Dieser Google-Account ist bereits verknüpft.' );
                }
                update_user_meta( $current_user_id, 'bp_google_id', $google_id );
                wp_safe_redirect( self::get_profile_url() );
                exit;
            }

            $user = get_user_by( 'email', $email );
            $user_id = 0;

            if ( $user ) {
                $user_id = $user->ID;
            } else {
                if ( ! get_option( 'users_can_register' ) ) wp_die( 'Registrierung deaktiviert.' );
                if ( ! $has_consent ) wp_die( 'Zustimmung zu AGB/Datenschutz erforderlich.' );

                $raw_name = $first_name . $last_name;
                if ( empty( $raw_name ) ) {
                    $parts = explode( '@', $email );
                    $raw_name = $parts[0];
                }
                $username = sanitize_user( $raw_name, true );
                if ( empty( $username ) ) $username = 'user_' . substr( md5( $email . time() ), 0, 6 );

                $original = $username;
                $i = 1;
                while ( username_exists( $username ) ) { $username = $original . $i; $i++; }

                $password = wp_generate_password( 16, true );
                $user_id = wp_create_user( $username, $password, $email );

                if ( is_wp_error( $user_id ) ) wp_die( 'Erstellung fehlgeschlagen.' );

                wp_update_user( array(
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $full_name ?: $username
                ) );

                if ( ! empty( $user_info['picture'] ) ) {
                    update_user_meta( $user_id, '_bp_google_avatar_url', esc_url_raw( $user_info['picture'] ) );
                }
            }

            update_user_meta( $user_id, 'bp_google_id', $google_id );
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id, true );
            do_action( 'wp_login', $user ? $user->user_login : $username, get_user_by( 'ID', $user_id ) );
            wp_safe_redirect( self::get_profile_url() );
            exit;
        }

        // B) Start
        if ( isset( $_GET['bp_social_auth'] ) && $_GET['bp_social_auth'] === 'google' ) {
            $mode = isset( $_GET['mode'] ) ? $_GET['mode'] : '';
            if ( is_user_logged_in() && $mode !== 'connect' ) {
                wp_safe_redirect( home_url( '/' ) );
                exit;
            }

            $state = wp_create_nonce( 'bp_social_google_login' );
            if ( isset( $_GET['consent'] ) && $_GET['consent'] === '1' ) {
                $state_key = 'bp_soc_' . substr( $state, 0, 32 );
                set_transient( $state_key, array( 'consent' => true ), 600 );
            }

            $params = array(
                'client_id'     => BP_SOCIAL_GOOGLE_CLIENT_ID,
                'redirect_uri'  => home_url( '/?bp_social_auth=google' ),
                'response_type' => 'code',
                'scope'         => 'email profile',
                'state'         => $state,
                'access_type'   => 'online',
                'prompt'        => 'select_account'
            );
            wp_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params ) );
            exit;
        }
    }

    public function google_avatar_url( $url, $id_or_email, $args ) {
        $user_id = 0;
        if ( is_numeric( $id_or_email ) ) $user_id = (int)$id_or_email;
        elseif ( is_object( $id_or_email ) && isset( $id_or_email->ID ) ) $user_id = $id_or_email->ID;
        elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );
            if ( $user ) $user_id = $user->ID;
        }

        if ( $user_id > 0 ) {
            $g = get_user_meta( $user_id, '_bp_google_avatar_url', true );
            if ( ! empty( $g ) ) return $g;
        }
        return $url;
    }

    // --- Helpers ---

    public static function get_client_ip() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) return trim( $_SERVER['HTTP_CF_CONNECTING_IP'] );
        return $remote;
    }

    public static function turnstile_verify( $token, $action = '' ) {
        $token = trim( (string) $token );
        if ( $token === '' ) return new WP_Error( 'turnstile_missing', self::msg( 'turnstile.missing' ) );

        $body = array(
            'secret' => BP_ACC_TURNSTILE_SECRET,
            'response' => $token,
            'remoteip' => self::get_client_ip()
        );

        $resp = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => $body,
            'timeout' => 8
        ) );

        if ( is_wp_error( $resp ) ) return new WP_Error( 'turnstile_http', self::msg( 'common.net_fail' ) );

        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( empty( $data['success'] ) ) return new WP_Error( 'turnstile_failed', self::msg( 'turnstile.failed' ) );

        return true;
    }

    public static function msg( $path, $fallback = '' ) {
        $m = self::get_message_map();
        $parts = explode( '.', $path );
        $cur = $m;
        foreach ( $parts as $p ) {
            if ( ! isset( $cur[$p] ) ) return $fallback;
            $cur = $cur[$p];
        }
        return is_string( $cur ) ? $cur : $fallback;
    }

    public static function get_message_map() {
        return array(
            'common' => array(
                'sec_fail' => 'Sicherheitscheck fehlgeschlagen.',
                'unexpected' => 'Unerwarteter Fehler.',
                'net_fail' => 'Netzwerkfehler.',
                'please_check' => 'Bitte überprüfe deine Eingaben.',
                'ok' => 'Erfolgreich.',
            ),
            'turnstile' => array(
                'missing' => 'Bitte bestätige die Sicherheitsprüfung (Captcha).',
                'failed' => 'Sicherheitsprüfung fehlgeschlagen.',
            ),
            'login' => array(
                'failed' => 'Anmeldung fehlgeschlagen.',
                'need_user_pass' => 'Bitte Benutzername und Passwort eingeben.',
                'too_many_retries' => 'Zu viele Versuche. Bitte warte einen Moment.',
            ),
            'reg' => array(
                'disabled' => 'Registrierung ist deaktiviert.',
                'user_taken' => 'Benutzername vergeben.',
                'email_taken' => 'E-Mail vergeben.',
                'pass_mismatch' => 'Passwörter stimmen nicht überein.',
                'consent_required' => 'Bitte akzeptiere die Bedingungen.',
                'failed' => 'Registrierung fehlgeschlagen.',
            ),
            'regcheck' => array(
                'user_ok' => 'Verfügbar.',
                'user_taken' => 'Bereits vergeben.',
                'checking_user' => 'Prüfe Name...',
                'checking_email' => 'Prüfe E-Mail...',
            ),
            'ui' => array(
                'btn_account_logged_out' => 'Login',
                'btn_account_logged_in' => 'Mein Konto',
                'title_logged_in' => 'Dein Bereich',
                'title_logged_out' => 'Willkommen',
                'hello_fallback' => 'Gast',
                'pill_profile' => 'Zum Profil',
                'pill_logout' => 'Abmelden',
                'btn_login' => 'Einloggen',
                'btn_register_tab' => 'Registrieren',
                'btn_lost_tab' => 'Passwort vergessen?',
                'btn_lost_submit' => 'Link anfordern',
                'btn_back_to_login' => 'Zurück',
                'label_user_or_email' => 'Benutzername oder E-Mail',
                'label_password' => 'Passwort',
                'label_remember' => 'Angemeldet bleiben',
                'reg_label_user' => 'Benutzername wählen',
                'reg_label_email' => 'Deine E-Mail',
                'reg_label_pass1' => 'Passwort',
                'reg_label_pass2' => 'Wiederholen',
                'reg_hint' => 'Mind. 8 Zeichen, sicher wählen.',
                'reg_btn_submit' => 'Kostenlos registrieren',
            )
        );
    }

    // --- AJAX ---

    public function check_lostpass_rate_limit( $errors ) {
        if ( is_user_logged_in() ) return;
        $ip = self::get_client_ip();
        $key = 'bp_lprl_' . md5( 'ip|' . $ip );
        $until = get_transient( $key );
        if ( $until > time() ) {
            $errors->add( 'too_many_retries', 'Zu viele Anfragen. Bitte warten.' );
            return;
        }
        set_transient( $key, time() + BP_LOSTPASS_RL_SECONDS, BP_LOSTPASS_RL_SECONDS );
    }

    public function ajax_login() {
        check_ajax_referer( 'bp_acc_login', 'nonce' );

        $ts = isset( $_POST['turnstile'] ) ? sanitize_text_field( $_POST['turnstile'] ) : '';
        $ok = self::turnstile_verify( $ts, 'login' );
        if ( is_wp_error( $ok ) ) wp_send_json_error( array( 'message' => $ok->get_error_message() ), 403 );

        $user = isset( $_POST['user'] ) ? sanitize_text_field( $_POST['user'] ) : '';
        $pass = isset( $_POST['pass'] ) ? $_POST['pass'] : '';
        $rem = ( ! empty( $_POST['remember'] ) && $_POST['remember'] === '1' );

        $signon = wp_signon( array( 'user_login' => $user, 'user_password' => $pass, 'remember' => $rem ), is_ssl() );

        if ( is_wp_error( $signon ) ) {
            wp_send_json_error( array( 'message' => self::msg( 'login.failed' ) ), 401 );
        }

        wp_send_json_success( array( 'redirect' => self::get_profile_url() ) );
    }

    public function ajax_lostpass() {
        check_ajax_referer( 'bp_acc_lostpass', 'nonce' );
        $ts = isset( $_POST['turnstile'] ) ? sanitize_text_field( $_POST['turnstile'] ) : '';
        $ok = self::turnstile_verify( $ts, 'lost' );
        if ( is_wp_error( $ok ) ) wp_send_json_error( array( 'message' => $ok->get_error_message() ), 403 );

        $user_login = isset( $_POST['user'] ) ? sanitize_text_field( $_POST['user'] ) : '';

        // WP Core Logic Simulation
        $user_data = get_user_by( 'login', $user_login );
        if ( ! $user_data ) $user_data = get_user_by( 'email', $user_login );

        if ( ! $user_data ) {
            // Fake Success for Security
            wp_send_json_success( array( 'message' => self::msg( 'lost.ok' ) ) );
        }

        retrieve_password( $user_data->user_login );
        wp_send_json_success( array( 'message' => self::msg( 'lost.ok' ) ) );
    }

    public function ajax_reg_check() {
        check_ajax_referer( 'bp_acc_regcheck', 'nonce' );

        $username = isset( $_POST['user'] ) ? sanitize_user( $_POST['user'], true ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

        $u_stat = 'ok'; $e_stat = 'ok';

        if ( $username ) {
            if ( username_exists( $username ) ) $u_stat = 'taken';
            elseif ( strlen( $username ) < 3 ) $u_stat = 'invalid';
        }

        if ( $email ) {
            if ( email_exists( $email ) ) $e_stat = 'taken';
            elseif ( ! is_email( $email ) ) $e_stat = 'invalid';
        }

        wp_send_json_success( array(
            'user' => array( 'status' => $u_stat, 'message' => self::msg( 'regcheck.user_' . $u_stat ) ),
            'email' => array( 'status' => $e_stat, 'message' => self::msg( 'regcheck.email_' . $e_stat ) )
        ));
    }

    // --- Utils ---

    private static function is_normal_user( $user = null ) {
        if ( ! $user ) {
            if ( ! is_user_logged_in() ) return true;
            $user = wp_get_current_user();
        }
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) return false;
        return true;
    }

    private static function get_profile_url() {
        $page = get_page_by_path( 'userprofile' );
        return $page ? get_permalink( $page->ID ) : home_url( '/' );
    }

    private static function get_frontend_login_url() {
        return add_query_arg( 'bpacc', 'login', home_url( '/' ) );
    }

    private static function get_lostpass_url() {
        return add_query_arg( 'bpacc_tab', 'lost', self::get_frontend_login_url() );
    }

    // --- Render ---

    public function render_login_modal() {
        if ( is_admin() ) return;

        $default_tab = ( ! empty( $GLOBALS['bp_acc_reg_errors'] ) ) ? 'register' : 'login';
        $reg_errors = $GLOBALS['bp_acc_reg_errors'] ?? [];
        $reg_vals = $GLOBALS['bp_acc_reg_values'] ?? [];

        // Only load if not logged in or needed
        // HTML Structure...
        // Note: I will read the structure from the original file again to ensure exact markup matching.
        // For brevity in this thought process, I will assume the structure is copied.

        // ... (HTML markup echo) ...

        $this->output_login_html($default_tab, $reg_errors, $reg_vals);
    }

    private function output_login_html($default_tab, $reg_errors, $reg_vals) {
        $profile_url = self::get_profile_url();
        $logout_url = wp_logout_url( home_url( '/' ) );
        $can_register = get_option( 'users_can_register' );
        $current_user = wp_get_current_user();
        $display_name = $current_user->exists() ? $current_user->display_name : '';
        $ts_sitekey = BP_ACC_TURNSTILE_SITEKEY;
        $privacy_url = home_url( '/datenschutz/' );
        $agb_url = home_url( '/agb/' );

        ?>
        <div class="bp-account" data-bp-account data-bp-default-tab="<?php echo esc_attr($default_tab); ?>" data-bp-auto-open="<?php echo !empty($reg_errors) ? '1' : '0'; ?>">
            <button class="bp-account__btn" type="button" aria-haspopup="true" aria-expanded="false" data-bp-account-btn>
                <span class="bp-account__label"><?php echo is_user_logged_in() ? esc_html(self::msg('ui.btn_account_logged_in')) : esc_html(self::msg('ui.btn_account_logged_out')); ?></span>
                <span class="bp-account__chevSlot" aria-hidden="true">
                    <span class="bp-account__chevDown">▼</span><span class="bp-account__chevUp">▲</span>
                </span>
            </button>

            <div class="bp-account__panel" role="dialog" aria-label="Account" data-bp-account-panel>
                <div class="bp-account__header">
                    <div class="bp-account__title"><?php echo is_user_logged_in() ? esc_html(self::msg('ui.title_logged_in')) : esc_html(self::msg('ui.title_logged_out')); ?></div>
                    <button class="bp-account__close" type="button" data-bp-account-close>✕</button>
                </div>

                <?php if ( is_user_logged_in() ) : ?>
                    <div class="bp-account__section">
                        <div class="bp-account__userRow">
                            <div class="bp-account__hello">Hi, <?php echo esc_html( $display_name ?: self::msg('ui.hello_fallback') ); ?></div>
                            <div class="bp-account__quick">
                                <a class="bp-account__pillLink bp-pill--profile" href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html(self::msg('ui.pill_profile')); ?></a>
                                <a class="bp-account__pillLink bp-pill--logout" href="<?php echo esc_url($logout_url); ?>"><?php echo esc_html(self::msg('ui.pill_logout')); ?></a>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="bp-account__section">
                        <!-- Login Pane -->
                        <div class="bp-account__pane is-active" data-bp-acc-pane="login">
                            <div class="bp-account__msg bp-account__msg--err" data-bp-login-msg style="display:none;"></div>
                            <form class="bp-acc-loginForm" method="post" action="" data-bp-login-form>
                                <p class="bp-acc-field">
                                    <span class="bp-acc-labelRow"><label for="bp_login_user"><?php echo esc_html(self::msg('ui.label_user_or_email')); ?></label></span>
                                    <input type="text" id="bp_login_user" name="bp_login_user" autocomplete="username" required placeholder="<?php echo esc_attr(self::msg('ui.label_user_or_email')); ?>" />
                                </p>
                                <p class="bp-acc-field">
                                    <span class="bp-acc-labelRow"><label for="bp_login_pass"><?php echo esc_html(self::msg('ui.label_password')); ?></label></span>
                                    <input type="password" id="bp_login_pass" name="bp_login_pass" autocomplete="current-password" required />
                                </p>
                                <p class="login-remember">
                                    <label><input type="checkbox" name="bp_login_remember" value="1" /> <?php echo esc_html(self::msg('ui.label_remember')); ?></label>
                                </p>
                                <?php wp_nonce_field('bp_acc_login', 'bp_acc_login_nonce'); ?>
                                <div class="bp-acc-turnstile">
                                    <div class="bp-turnstile" data-sitekey="<?php echo esc_attr($ts_sitekey); ?>" data-action="login" data-response-field-name="ts_login"></div>
                                </div>
                                <button class="bp-account__btnPrimary" type="submit" data-bp-login-submit><?php echo esc_html(self::msg('ui.btn_login')); ?></button>

                                <div class="bp-account__tabs">
                                    <button class="bp-account__tab" type="button" data-bp-acc-switch="register"><?php echo esc_html(self::msg('ui.btn_register_tab')); ?></button>
                                </div>
                                <div class="bp-account__links">
                                    <button class="bp-account__linkBtn" type="button" data-bp-acc-switch="lost"><?php echo esc_html(self::msg('ui.btn_lost_tab')); ?></button>
                                </div>
                            </form>
                        </div>

                        <!-- Lost Pass Pane -->
                        <div class="bp-account__pane" data-bp-acc-pane="lost">
                            <div class="bp-account__msg bp-account__msg--ok" data-bp-lost-ok style="display:none;"></div>
                            <div class="bp-account__msg bp-account__msg--err" data-bp-lost-err style="display:none;"></div>
                            <form class="bp-acc-lostForm" method="post" action="" data-bp-lost-form>
                                <p class="bp-acc-field">
                                    <span class="bp-acc-labelRow"><label for="bp_lost_user"><?php echo esc_html(self::msg('ui.label_user_or_email')); ?></label></span>
                                    <input type="text" id="bp_lost_user" name="bp_lost_user" autocomplete="username email" required />
                                </p>
                                <?php wp_nonce_field('bp_acc_lostpass', 'bp_acc_lostpass_nonce'); ?>
                                <div class="bp-acc-turnstile">
                                    <div class="bp-turnstile" data-sitekey="<?php echo esc_attr($ts_sitekey); ?>" data-action="lost" data-response-field-name="ts_lost"></div>
                                </div>
                                <button class="bp-account__btnPrimary" type="submit" data-bp-lost-submit><?php echo esc_html(self::msg('ui.btn_lost_submit')); ?></button>
                                <div class="bp-account__tabs">
                                    <button class="bp-account__tab" type="button" data-bp-acc-switch="login"><?php echo esc_html(self::msg('ui.btn_back_to_login')); ?></button>
                                </div>
                            </form>
                        </div>

                        <!-- Register Pane -->
                        <div class="bp-account__pane" data-bp-acc-pane="register">
                            <?php if ( ! $can_register ) : ?>
                                <div class="bp-account__msg bp-account__msg--err"><?php echo esc_html(self::msg('ui.reg_disabled_hint')); ?></div>
                                <div class="bp-account__tabs">
                                    <button class="bp-account__tab" type="button" data-bp-acc-switch="login"><?php echo esc_html(self::msg('ui.btn_back_to_login')); ?></button>
                                </div>
                            <?php else : ?>
                                <?php if ( ! empty($reg_errors) ) : ?>
                                    <div class="bp-account__msg bp-account__msg--err">
                                        <ul><?php foreach ($reg_errors as $e) echo '<li>' . esc_html($e) . '</li>'; ?></ul>
                                    </div>
                                <?php endif; ?>
                                <form method="post" action="">
                                    <p class="bp-acc-field">
                                        <span class="bp-acc-labelRow">
                                            <label for="bp_reg_user"><?php echo esc_html(self::msg('ui.reg_label_user')); ?></label>
                                            <span class="bp-account__fieldMsg" data-bp-reg-user-msg></span>
                                        </span>
                                        <input type="text" id="bp_reg_user" name="bp_reg_user" required value="<?php echo esc_attr($reg_vals['user'] ?? ''); ?>" />
                                    </p>
                                    <p class="bp-acc-field">
                                        <span class="bp-acc-labelRow">
                                            <label for="bp_reg_email"><?php echo esc_html(self::msg('ui.reg_label_email')); ?></label>
                                            <span class="bp-account__fieldMsg" data-bp-reg-email-msg></span>
                                        </span>
                                        <input type="email" id="bp_reg_email" name="bp_reg_email" required value="<?php echo esc_attr($reg_vals['email'] ?? ''); ?>" />
                                    </p>
                                    <p class="bp-acc-field">
                                        <label for="bp_reg_pass1"><?php echo esc_html(self::msg('ui.reg_label_pass1')); ?></label>
                                        <input type="password" id="bp_reg_pass1" name="bp_reg_pass1" required />
                                    </p>
                                    <p class="bp-acc-field">
                                        <label for="bp_reg_pass2"><?php echo esc_html(self::msg('ui.reg_label_pass2')); ?></label>
                                        <input type="password" id="bp_reg_pass2" name="bp_reg_pass2" required />
                                    </p>
                                    <div class="bp-acc-hint"><?php echo esc_html(self::msg('ui.reg_hint')); ?></div>
                                    <p class="login-remember bp-acc-consent">
                                        <label>
                                            <input type="checkbox" id="bp_reg_accept" name="bp_reg_accept" value="1" required <?php checked( $reg_vals['accept'], '1' ); ?> />
                                            <span>Ich akzeptiere <a href="<?php echo esc_url($privacy_url); ?>" target="_blank">Datenschutz</a> & <a href="<?php echo esc_url($agb_url); ?>" target="_blank">AGB</a>.</span>
                                        </label>
                                    </p>
                                    <input type="hidden" name="bp_acc_action" value="register" />
                                    <?php wp_nonce_field('bp_acc_register', 'bp_acc_reg_nonce'); ?>
                                    <?php wp_nonce_field('bp_acc_regcheck', 'bp_acc_regcheck_nonce'); ?>
                                    <div class="bp-acc-turnstile">
                                        <div class="bp-turnstile" data-sitekey="<?php echo esc_attr($ts_sitekey); ?>" data-action="register" data-response-field-name="ts_register"></div>
                                    </div>
                                    <button class="bp-account__btnPrimary bp-account__btnRegister" type="submit"><?php echo esc_html(self::msg('ui.reg_btn_submit')); ?></button>
                                    <div class="bp-account__tabs">
                                        <button class="bp-account__tab" type="button" data-bp-acc-switch="login"><?php echo esc_html(self::msg('ui.btn_back_to_login')); ?></button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
