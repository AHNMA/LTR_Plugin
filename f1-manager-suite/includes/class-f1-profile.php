<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Profile {

    public function __construct() {
        // Init Hooks (Redirects, Rewrites)
        add_action( 'init', array( $this, 'init_rewrites_and_actions' ) );
        add_action( 'admin_init', array( $this, 'restrict_admin_access' ) );
        add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ), 20 );
        add_filter( 'login_redirect', array( $this, 'custom_login_redirect' ), 10, 3 );

        // Avatar
        add_filter( 'get_avatar_url', array( $this, 'custom_avatar_url' ), 20, 3 );

        // Shortcode
        add_shortcode( 'f1_frontend_profile', array( $this, 'render_frontend_profile' ) );

        // DSGVO Export
        add_action( 'admin_post_f1fp_privacy_export_request', array( $this, 'handle_privacy_export' ) );

        // Profile Save/Delete/Disconnect
        add_action( 'init', array( $this, 'handle_profile_actions' ) );

        // Custom Pages (Privacy Confirm / Reset Password)
        add_action( 'template_redirect', array( $this, 'render_custom_pages' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'login_form_confirmaction', array( $this, 'intercept_confirmaction' ), 0 );

        // Frontend Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        // Only enqueue on profile page to save resources, but for simplicity we assume site-wide or specific check
        // Ideally we check is_page('userprofile') or if shortcode is present.
        // For now, site-wide logic as per original snippet architecture, but can be optimized.
        // We will stick to enqueuing always if user is logged in or if needed?
        // The script is small, so we just enqueue it.
        wp_enqueue_style( 'f1-profile', F1_MANAGER_SUITE_URL . 'assets/css/f1-profile.css', array(), F1_MANAGER_SUITE_VERSION );
        wp_enqueue_script( 'f1-profile', F1_MANAGER_SUITE_URL . 'assets/js/f1-profile.js', array(), F1_MANAGER_SUITE_VERSION, true );
    }

    // --- Core Logic ---

    public function init_rewrites_and_actions() {
        add_rewrite_rule( '^privacy-confirm/?$', 'index.php?f1fp_privacy_confirm=1', 'top' );
        add_rewrite_rule( '^passwort-zuruecksetzen/?$', 'index.php?f1pr_reset=1', 'top' );

        // Flush if needed (should be done on activation, but this is legacy compat)
        if ( ! get_option( 'f1fp_rewrites_flushed' ) ) {
            flush_rewrite_rules();
            update_option( 'f1fp_rewrites_flushed', 1 );
        }
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'f1fp_privacy_confirm';
        $vars[] = 'f1pr_reset';
        return $vars;
    }

    public static function get_profile_url() {
        return home_url( '/userprofile/' );
    }

    public static function is_normal_user( $user_id = 0 ) {
        if ( ! is_user_logged_in() ) return false;
        if ( $user_id <= 0 ) $user_id = get_current_user_id();
        if ( user_can( $user_id, 'manage_options' ) ) return false;
        if ( user_can( $user_id, 'edit_posts' ) ) return false;
        return true;
    }

    public function restrict_admin_access() {
        if ( ! self::is_normal_user() ) return;
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;

        global $pagenow;
        $allowed = array( 'admin-ajax.php', 'admin-post.php' );
        if ( in_array( $pagenow, $allowed, true ) ) return;

        wp_safe_redirect( self::get_profile_url() );
        exit;
    }

    public function hide_admin_bar( $show ) {
        if ( self::is_normal_user() ) return false;
        return $show;
    }

    public function custom_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( is_wp_error( $user ) || ! $user ) return $redirect_to;
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) {
            return $redirect_to;
        }
        return self::get_profile_url();
    }

    // --- Avatar ---

    public function custom_avatar_url( $url, $id_or_email, $args ) {
        $size = isset( $args['size'] ) ? (int)$args['size'] : 96;
        $user_id = 0;

        if ( is_numeric( $id_or_email ) ) $user_id = (int)$id_or_email;
        elseif ( is_object( $id_or_email ) && isset( $id_or_email->ID ) ) $user_id = $id_or_email->ID;
        elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $u = get_user_by( 'email', $id_or_email );
            if ( $u ) $user_id = $u->ID;
        }

        if ( $user_id > 0 ) {
            $custom = self::get_custom_avatar_url( $user_id, $size );
            if ( $custom !== '' ) return $custom;
        }

        return self::placeholder_avatar_data_uri( $size );
    }

    public static function get_custom_avatar_url( $user_id, $size = 120 ) {
        $att_id = (int) get_user_meta( $user_id, '_f1fp_avatar_id', true );
        if ( $att_id <= 0 ) return '';
        $u = wp_get_attachment_image_url( $att_id, array( $size, $size ) );
        return $u ? $u : '';
    }

    public static function placeholder_avatar_data_uri( $size = 120 ) {
        $size = max( 20, (int)$size );
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 100 100"><rect width="100" height="100" fill="#FFFFFF"/><rect x="0" y="0" width="100" height="8" fill="#E00078"/><circle cx="50" cy="41" r="16" fill="#202020"/><path d="M18 92c7-22 57-22 64 0" fill="#202020"/></svg>';
        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( $svg );
    }

    // --- Profile Actions ---

    public function handle_profile_actions() {
        if ( empty( $_POST['f1fp_action'] ) ) return;
        if ( ! is_user_logged_in() ) return;

        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );
        $action = sanitize_key( $_POST['f1fp_action'] );

        if ( $action === 'save_profile' ) $this->save_profile( $user_id, $user );
        if ( $action === 'delete_profile' ) $this->delete_profile( $user_id );
        if ( $action === 'disconnect_google' ) $this->disconnect_google( $user_id );
        if ( $action === 'send_reset_link' ) $this->send_reset_link( $user_id, $user );
        // Password set is handled in separate flow or here? Original code has it in init too.
        if ( $action === 'set_new_password' ) $this->handle_password_reset_post();
    }

    private function save_profile( $user_id, $user ) {
        if ( ! wp_verify_nonce( $_POST['f1fp_nonce'] ?? '', 'f1fp_save_profile' ) ) {
            $this->set_flash( $user_id, 'error', 'Sicherheitsprüfung fehlgeschlagen.' );
            wp_safe_redirect( self::get_profile_url() );
            exit;
        }

        $errors = array();

        $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
        $display_name = sanitize_text_field( $_POST['display_name'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $url = esc_url_raw( $_POST['user_url'] ?? '' );

        $fb = esc_url_raw( $_POST['f1_social_fb'] ?? '' );
        $x = esc_url_raw( $_POST['f1_social_x'] ?? '' );
        $ig = esc_url_raw( $_POST['f1_social_ig'] ?? '' );

        $new_email = sanitize_email( $_POST['user_email'] ?? $user->user_email );

        if ( $new_email !== $user->user_email ) {
            if ( ! is_email( $new_email ) ) $errors[] = 'Ungültige E-Mail.';
            elseif ( email_exists( $new_email ) ) $errors[] = 'E-Mail bereits vergeben.';
        }

        if ( empty( $display_name ) ) $display_name = $user->user_login;

        // Avatar Handling
        if ( ! empty( $_POST['f1fp_avatar_remove'] ) && $_POST['f1fp_avatar_remove'] === '1' ) {
            delete_user_meta( $user_id, '_f1fp_avatar_id' );
        } elseif ( ! empty( $_FILES['f1fp_avatar']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $uploaded = wp_handle_upload( $_FILES['f1fp_avatar'], array( 'test_form' => false ) );
            if ( isset( $uploaded['file'] ) ) {
                $file = $uploaded['file'];
                $attachment = array(
                    'post_mime_type' => $uploaded['type'],
                    'post_title' => 'Avatar ' . $user_id,
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                $attach_id = wp_insert_attachment( $attachment, $file );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
                wp_update_attachment_metadata( $attach_id, $attach_data );
                update_user_meta( $user_id, '_f1fp_avatar_id', $attach_id );
            }
        }

        if ( ! empty( $errors ) ) {
            $this->set_flash( $user_id, 'error', $errors );
            wp_safe_redirect( self::get_profile_url() );
            exit;
        }

        wp_update_user( array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
            'description' => $description,
            'user_url' => $url,
            'user_email' => $new_email
        ) );

        update_user_meta( $user_id, '_f1usr_fb', $fb );
        update_user_meta( $user_id, '_f1usr_x', $x );
        update_user_meta( $user_id, '_f1usr_ig', $ig );

        $this->set_flash( $user_id, 'success', 'Profil gespeichert.' );
        wp_safe_redirect( self::get_profile_url() );
        exit;
    }

    private function delete_profile( $user_id ) {
        if ( ! wp_verify_nonce( $_POST['f1fp_delete_nonce'] ?? '', 'f1fp_delete_profile' ) ) {
            wp_safe_redirect( self::get_profile_url() ); exit;
        }
        if ( empty( $_POST['f1fp_delete_confirm'] ) ) {
            $this->set_flash( $user_id, 'error', 'Bitte Bestätigung anhaken.' );
            wp_safe_redirect( self::get_profile_url() ); exit;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    private function disconnect_google( $user_id ) {
        if ( wp_verify_nonce( $_POST['f1fp_disconnect_nonce'] ?? '', 'f1fp_disconnect_google' ) ) {
            delete_user_meta( $user_id, 'bp_google_id' );
            $this->set_flash( $user_id, 'success', 'Google getrennt.' );
        }
        wp_safe_redirect( self::get_profile_url() );
        exit;
    }

    private function send_reset_link( $user_id, $user ) {
        if ( wp_verify_nonce( $_POST['f1fp_reset_nonce'] ?? '', 'f1fp_send_reset_link' ) ) {
            $res = retrieve_password( $user->user_login );
            if ( is_wp_error( $res ) ) {
                $this->set_flash( $user_id, 'error', 'Fehler beim Senden.' );
            } else {
                $this->set_flash( $user_id, 'success', 'Link gesendet.' );
            }
        }
        wp_safe_redirect( self::get_profile_url() );
        exit;
    }

    public function handle_privacy_export() {
        if ( ! is_user_logged_in() ) return;
        $user_id = get_current_user_id();

        if ( ! wp_verify_nonce( $_POST['f1fp_privacy_nonce'] ?? '', 'f1fp_privacy_export' ) ) return;
        if ( empty( $_POST['f1fp_export_confirm'] ) ) return;

        $user = get_userdata( $user_id );
        $request_id = wp_create_user_request( $user->user_email, 'export_personal_data' );

        if ( ! is_wp_error( $request_id ) ) {
            wp_send_user_request( $request_id );
            $this->set_flash( $user_id, 'success', 'Bestätigungs-Mail gesendet.' );
        } else {
            $this->set_flash( $user_id, 'error', 'Fehler beim Export-Request.' );
        }

        wp_safe_redirect( self::get_profile_url() );
        exit;
    }

    // --- Helpers ---

    private function set_flash( $user_id, $type, $msg ) {
        set_transient( 'f1fp_flash_' . $user_id, array( 'type' => $type, 'messages' => (array)$msg ), 60 );
    }

    private function get_flash( $user_id ) {
        $f = get_transient( 'f1fp_flash_' . $user_id );
        if ( $f ) delete_transient( 'f1fp_flash_' . $user_id );
        return $f;
    }

    // --- Render Shortcode ---

    public function render_frontend_profile( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="f1fp-wrap"><div class="f1fp-card"><div class="f1fp-body">Bitte einloggen.</div></div></div>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $flash = $this->get_flash( $user_id );

        $fb = get_user_meta( $user_id, '_f1usr_fb', true );
        $x = get_user_meta( $user_id, '_f1usr_x', true );
        $ig = get_user_meta( $user_id, '_f1usr_ig', true );
        $has_google = get_user_meta( $user_id, 'bp_google_id', true );
        $avatar_url = self::get_custom_avatar_url( $user_id, 240 );
        $initials = f1fp_user_initials( $user->display_name );

        ob_start();
        ?>
        <div class="f1fp-wrap">
            <div class="f1fp-container">
                <div class="f1fp-card">
                    <div class="f1fp-head"><h2 class="f1fp-head__title">Profil-Daten</h2></div>
                    <div class="f1fp-body">
                        <?php if ( $flash ) : ?>
                            <div class="f1fp-alert <?php echo $flash['type'] === 'error' ? 'f1fp-alert--error' : ''; ?>">
                                <?php foreach ( $flash['messages'] as $m ) echo '<div>' . esc_html( $m ) . '</div>'; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Avatar & Top Info -->
                        <div class="f1fp-top">
                            <div>
                                <form id="f1fp-avatar-form" method="post" action="" enctype="multipart/form-data">
                                    <?php wp_nonce_field( 'f1fp_save_profile', 'f1fp_nonce' ); ?>
                                    <input type="hidden" name="f1fp_action" value="save_profile">
                                    <input id="f1fp_avatar_input" name="f1fp_avatar" type="file" style="display:none;">
                                    <input id="f1fp_avatar_remove" name="f1fp_avatar_remove" type="hidden" value="0">
                                    <div class="f1fp-avatar-wrap">
                                        <?php if ( $avatar_url ) : ?><button type="button" class="f1fp-avatar-x" id="f1fp-avatar-x">×</button><?php endif; ?>
                                        <button type="button" class="f1fp-avatar-click" id="f1fp-avatar-click">
                                            <div class="f1fp-avatar">
                                                <?php if ( $avatar_url ) : ?><img src="<?php echo esc_url( $avatar_url ); ?>"><?php else : ?><span class="f1fp-avatar__initials"><?php echo esc_html( $initials ); ?></span><?php endif; ?>
                                            </div>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div>
                                <p class="f1fp-name"><?php echo esc_html( $user->display_name ); ?></p>
                                <p class="f1fp-meta"><?php echo esc_html( $user->user_email ); ?></p>
                            </div>
                        </div>

                        <!-- Main Form -->
                        <form id="f1fp-main-form" class="f1fp-form" method="post" action="">
                            <?php wp_nonce_field( 'f1fp_save_profile', 'f1fp_nonce' ); ?>
                            <input type="hidden" name="f1fp_action" value="save_profile">

                            <div class="f1fp-section">
                                <p class="f1fp-section-title">Persönliche Daten</p>
                                <div class="f1fp-fields">
                                    <div class="f1fp-field"><label class="f1fp-label">Vorname</label><input class="f1fp-input" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>"></div>
                                    <div class="f1fp-field"><label class="f1fp-label">Nachname</label><input class="f1fp-input" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>"></div>
                                    <div class="f1fp-field"><label class="f1fp-label">Anzeigename</label><input class="f1fp-input" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>"></div>
                                    <div class="f1fp-field"><label class="f1fp-label">Website</label><input class="f1fp-input" name="user_url" value="<?php echo esc_attr( $user->user_url ); ?>"></div>
                                </div>
                            </div>

                            <div class="f1fp-section">
                                <p class="f1fp-section-title">Social Links</p>
                                <div class="f1fp-fields">
                                    <div class="f1fp-field"><label class="f1fp-label">Facebook</label><input class="f1fp-input" name="f1_social_fb" value="<?php echo esc_attr( $fb ); ?>"></div>
                                    <div class="f1fp-field"><label class="f1fp-label">X</label><input class="f1fp-input" name="f1_social_x" value="<?php echo esc_attr( $x ); ?>"></div>
                                    <div class="f1fp-field"><label class="f1fp-label">Instagram</label><input class="f1fp-input" name="f1_social_ig" value="<?php echo esc_attr( $ig ); ?>"></div>
                                </div>
                            </div>

                            <div class="f1fp-section">
                                <p class="f1fp-section-title">Sicherheit</p>
                                <div class="f1fp-fields">
                                    <div class="f1fp-field"><label class="f1fp-label">E-Mail</label><input class="f1fp-input" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>"></div>
                                    <div class="f1fp-pass-col">
                                        <button type="submit" form="f1fp-reset-link-form" class="f1fp-btn f1fp-btn--ghost">Reset-Link senden</button>
                                    </div>
                                </div>
                            </div>

                            <div class="f1fp-actions-bar">
                                <div class="f1fp-actions-left">
                                    <button type="button" id="f1fp-delete-toggle" class="f1fp-btn f1fp-btn--danger">Profil löschen</button>
                                    <button type="button" id="f1fp-export-toggle" class="f1fp-btn f1fp-btn--orange">Datenexport</button>
                                </div>
                                <div class="f1fp-actions-right">
                                    <button class="f1fp-btn f1fp-btn--primary" type="submit">Speichern</button>
                                </div>
                            </div>
                        </form>

                        <!-- Hidden Forms -->
                        <form id="f1fp-reset-link-form" method="post" style="display:none;">
                            <?php wp_nonce_field( 'f1fp_send_reset_link', 'f1fp_reset_nonce' ); ?>
                            <input type="hidden" name="f1fp_action" value="send_reset_link">
                        </form>

                        <div id="f1fp-delete-wrap" hidden>
                            <form class="f1fp-form" method="post" action="">
                                <?php wp_nonce_field( 'f1fp_delete_profile', 'f1fp_delete_nonce' ); ?>
                                <input type="hidden" name="f1fp_action" value="delete_profile">
                                <div class="f1fp-section f1fp-section--danger">
                                    <p class="f1fp-text-muted">Aktion ist endgültig.</p>
                                    <div class="f1fp-inline-action">
                                        <label class="f1fp-checkrow"><input type="checkbox" name="f1fp_delete_confirm" value="1"> Ich möchte löschen.</label>
                                        <button class="f1fp-btn f1fp-btn--danger" type="submit">Bestätigen</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div id="f1fp-export-wrap" hidden>
                            <form class="f1fp-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'f1fp_privacy_export', 'f1fp_privacy_nonce' ); ?>
                                <input type="hidden" name="action" value="f1fp_privacy_export_request">
                                <div class="f1fp-section">
                                    <div class="f1fp-inline-action">
                                        <label class="f1fp-checkrow"><input type="checkbox" name="f1fp_export_confirm" value="1"> Datenexport anfordern.</label>
                                        <button class="f1fp-btn f1fp-btn--primary" type="submit">Senden</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // --- Custom Pages ---

    public function intercept_confirmaction() {
        if ( ! isset( $_GET['request_id'], $_GET['confirm_key'] ) ) return;

        $request_id = absint( $_GET['request_id'] );
        $key = sanitize_text_field( $_GET['confirm_key'] );

        $result = wp_validate_user_request_key( $request_id, $key );

        if ( ! is_wp_error( $result ) ) {
            do_action( 'user_request_action_confirmed', $request_id );
            $title = 'Bestätigung erfolgreich';
            $msg = 'Danke für die Bestätigung.';
        } else {
            $title = 'Fehler';
            $msg = $result->get_error_message();
        }

        // Redirect to custom page with token data (simulated here via transient)
        $token = wp_generate_password( 20, false );
        set_transient( 'f1fp_privacy_confirm_' . $token, array( 'title' => $title, 'html' => $msg ), 300 );

        wp_safe_redirect( add_query_arg( 'token', $token, home_url( '/privacy-confirm/' ) ) );
        exit;
    }

    public function handle_password_reset_post() {
        if ( is_user_logged_in() ) return;
        if ( ! wp_verify_nonce( $_POST['f1pr_nonce'], 'f1pr_set_new_password' ) ) return;

        $login = $_POST['login'];
        $key = $_POST['key'];
        $pass1 = $_POST['pass1'];
        $pass2 = $_POST['pass2'];

        if ( $pass1 !== $pass2 ) {
            // Error handling needs improvement in a real scenario, basic redirect for now
            wp_safe_redirect( home_url( '/passwort-zuruecksetzen/' ) );
            exit;
        }

        $user = check_password_reset_key( $key, $login );
        if ( ! is_wp_error( $user ) ) {
            reset_password( $user, $pass1 );
            wp_safe_redirect( add_query_arg( 'pw', 'changed', home_url( '/passwort-zuruecksetzen/' ) ) );
            exit;
        }
    }

    public function render_custom_pages() {
        if ( get_query_var( 'f1fp_privacy_confirm' ) ) {
            $token = $_GET['token'] ?? '';
            $data = get_transient( 'f1fp_privacy_confirm_' . $token );
            $title = $data['title'] ?? 'Privacy';
            $html = $data['html'] ?? 'Link abgelaufen.';

            // Output simple HTML
            echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html($title) . '</title></head><body>';
            echo '<h1>' . esc_html($title) . '</h1>';
            echo '<div>' . wp_kses_post($html) . '</div>';
            echo '</body></html>';
            exit;
        }

        if ( get_query_var( 'f1pr_reset' ) ) {
            // Password reset form logic (simplified)
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Passwort Reset</title></head><body>';
            echo '<form method="post"><input type="hidden" name="f1fp_action" value="set_new_password">';
            echo '<input type="password" name="pass1" placeholder="New Password">';
            echo '<input type="password" name="pass2" placeholder="Confirm">';
            echo '<input type="hidden" name="login" value="' . esc_attr($_GET['login'] ?? '') . '">';
            echo '<input type="hidden" name="key" value="' . esc_attr($_GET['key'] ?? '') . '">';
            wp_nonce_field('f1pr_set_new_password', 'f1pr_nonce');
            echo '<button type="submit">Save</button></form>';
            echo '</body></html>';
            exit;
        }
    }
}
