<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Logo_Switcher {

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_shortcode( 'f1_logo', array( $this, 'render_shortcode' ) );
    }

    public function register_admin_menu() {
        add_menu_page(
            'F1 Logo Switcher',
            'Logo Switcher',
            'manage_options',
            'f1-logo',
            array( $this, 'render_admin_page' ),
            'dashicons-format-image',
            32
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        require_once F1_MANAGER_SUITE_PATH . 'includes/admin/logo-admin.php';
    }

    public function handle_save() {
        if ( ! is_admin() ) return;
        if ( ! isset( $_POST['f1_logo_save'] ) ) return;

        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );
        if ( ! check_admin_referer( 'f1_logo_save_action', 'f1_logo_nonce' ) ) wp_die( 'Nonce Fehler.' );

        if ( isset( $_POST['f1_logo_light_url'] ) ) {
            update_option( 'f1_logo_light_url', esc_url_raw( $_POST['f1_logo_light_url'] ) );
        }
        if ( isset( $_POST['f1_logo_dark_url'] ) ) {
            update_option( 'f1_logo_dark_url', esc_url_raw( $_POST['f1_logo_dark_url'] ) );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'f1-logo', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'f1_logo' ) ) {
            return;
        }

        // CSS
        wp_enqueue_style( 'f1-logo-switcher', F1_MANAGER_SUITE_URL . 'assets/css/f1-logo-switcher.css', array(), '1.0.0' );

        // JS (in HEAD for fast swap)
        wp_enqueue_script( 'f1-logo-switcher', F1_MANAGER_SUITE_URL . 'assets/js/f1-logo-switcher.js', array(), '1.0.0', false );

        $light = get_option( 'f1_logo_light_url', '' );
        $dark  = get_option( 'f1_logo_dark_url', '' );

        wp_localize_script( 'f1-logo-switcher', 'f1_logo_cfg', array(
            'light' => $light,
            'dark'  => $dark,
        ) );
    }

    public function render_shortcode( $atts ) {
        // Enqueue assets just in case (e.g. non-singular context)
        wp_enqueue_style( 'f1-logo-switcher', F1_MANAGER_SUITE_URL . 'assets/css/f1-logo-switcher.css', array(), '1.0.0' );
        wp_enqueue_script( 'f1-logo-switcher', F1_MANAGER_SUITE_URL . 'assets/js/f1-logo-switcher.js', array(), '1.0.0', false );

        $light = get_option( 'f1_logo_light_url', '' );
        $dark  = get_option( 'f1_logo_dark_url', '' );

        wp_localize_script( 'f1-logo-switcher', 'f1_logo_cfg', array(
            'light' => $light,
            'dark'  => $dark,
        ) );

        // Fallback if empty options
        if ( ! $light ) $light = home_url( '/wp-content/logos/ltr_logo_black.svg' );

        return sprintf(
            '<a href="%s" class="custom-logo-link" rel="home" aria-current="page"><img src="%s" class="custom-logo f1-logo-target" alt="%s"></a>',
            esc_url( home_url( '/' ) ),
            esc_url( $light ),
            esc_attr( get_bloginfo( 'name', 'display' ) )
        );
    }
}
