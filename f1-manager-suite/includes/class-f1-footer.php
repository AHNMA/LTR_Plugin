<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Footer {

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
        // We need to hook handle_save on admin_init or admin_post
        add_action( 'admin_init', array( $this, 'handle_save' ) );

        // Frontend
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        // Shortcode
        add_shortcode( 'f1_footer', array( $this, 'render_shortcode' ) );
    }

    public function register_admin_menu() {
        add_menu_page(
            'F1 Footer Links',
            'Footer',
            'manage_options',
            'f1-footer',
            array( $this, 'render_admin_page' ),
            'dashicons-editor-insertmore',
            31
        );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        require_once F1_MANAGER_SUITE_PATH . 'includes/admin/footer-admin.php';
    }

    public function handle_save() {
        if ( ! is_admin() ) return;
        // Check if our submit button was clicked
        if ( ! isset( $_POST['f1_footer_save'] ) ) return;

        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );
        if ( ! check_admin_referer( 'f1_footer_save_action', 'f1_footer_nonce' ) ) wp_die( 'Nonce Fehler.' );

        $raw = isset( $_POST['links'] ) ? (array) $_POST['links'] : array();
        $clean = array();

        foreach ( $raw as $row ) {
            $label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
            $url   = isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '';
            if ( empty( $label ) && empty( $url ) ) continue;

            $clean[] = array(
                'label' => $label,
                'url'   => $url,
            );
        }

        update_option( 'f1_footer_links', $clean, false );

        wp_safe_redirect( add_query_arg( array( 'page' => 'f1-footer', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function enqueue_assets() {
        // Only load if shortcode is present? Or always?
        // The prompt asked: "Stelle sicher, dass Assets nur geladen werden, wenn die entsprechenden Shortcodes auch auf der Seite vorhanden sind."
        // However, checking for shortcode presence globally can be tricky (need to check post content).
        // For footer, it might be in a widget or global footer.
        // If it's used via shortcode `[f1_footer]`, we can try to detect it.
        // But if it's in the footer template via do_shortcode, `has_shortcode` on post object won't work.
        // For now, let's keep it simple or check `is_singular()` and `has_shortcode()`.

        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'f1_footer' ) ) {
             wp_enqueue_style( 'f1-footer', F1_MANAGER_SUITE_URL . 'assets/css/f1-footer.css', array(), '1.0.0' );
        }
        // If it is used in a widget or theme file, this might break styling.
        // Given the requirement "Stelle sicher, dass Assets nur geladen werden, wenn die entsprechenden Shortcodes auch auf der Seite vorhanden sind.",
        // I will stick to the post content check for now. If the user uses it in a global footer widget, they might need to enqueue it manually or I should accept that limitation.
        // Actually, for a footer link, it's small CSS.
        // But I will follow the instruction.
    }

    public function render_shortcode( $atts ) {
        // Enqueue if not already (for non-singular pages or widgets where wp_enqueue_scripts logic might miss)
        wp_enqueue_style( 'f1-footer', F1_MANAGER_SUITE_URL . 'assets/css/f1-footer.css', array(), '1.0.0' );

        $links = get_option( 'f1_footer_links', array() );
        if ( empty( $links ) || ! is_array( $links ) ) return '';

        $out = '<span class="f1-footer-links" aria-label="Rechtliche Links">';
        $count = count( $links );
        $i = 0;

        foreach ( $links as $l ) {
            $label = isset( $l['label'] ) ? $l['label'] : '';
            $url   = isset( $l['url'] ) ? $l['url'] : '';

            if ( ! $label ) continue;

            $out .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';

            if ( $i < $count - 1 ) {
                $out .= '<span class="sep">|</span>';
            }
            $i++;
        }
        $out .= '</span>';

        return $out;
    }
}
