<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Theme_Tweaks {

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Enqueue Assets (CSS/JS)
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // External Links (Footer)
        add_action( 'wp_footer', array( $this, 'external_links_fix' ) );
    }

    public function enqueue_scripts() {
        if ( is_admin() ) return;

        // 1. JS
        wp_enqueue_script(
            'f1-theme-tweaks-js',
            F1_MANAGER_SUITE_URL . 'assets/js/f1-theme-tweaks.js',
            array(),
            '1.0.0',
            true
        );

        // 2. CSS (Inline via Dummy)
        // Register a dummy handle to attach inline styles to
        wp_register_style( 'f1-theme-tweaks-css', false );
        wp_enqueue_style( 'f1-theme-tweaks-css' );

        $css = '
        /* --------------------------------------------
           BURGER: Dracula darf das Weiß nicht ummappen
           -------------------------------------------- */
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham,
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham::before,
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham::after{
          --dracula-background-ffffff: #ffffff !important;
          --dracula-border-ffffff: #ffffff !important;
          filter: none !important;
          -webkit-filter: none !important;
          opacity: 1 !important;
        }

        /* CLOSED: Burger (mittlere Linie = i.ham selbst) */
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham:not(.exit){
          background-color: #ffffff !important;
        }
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham:not(.exit)::before,
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham:not(.exit)::after{
          background-color: #ffffff !important;
        }

        /* OPEN: X (mittlere Linie muss weg) */
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham.exit{
          background-color: transparent !important;
          box-shadow: none !important;
        }
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham.exit::before,
        html[data-dracula-scheme="dark"] body #main-navigation-bar i.ham.exit::after{
          background-color: #ffffff !important;
        }

        /* --------------------------------------------
           OFFCANVAS MENU: Fallback Hintergrund
           -------------------------------------------- */
        html[data-dracula-scheme="dark"] body #main-navigation-bar ul#menu-hauptmenue.menu-mobile{
          background-color: #181a1b !important;
        }

        /* --------------------------------------------
           MENU ITEMS: SOLID Hintergründe im Darkmode (Fallback)
           -------------------------------------------- */
        html[data-dracula-scheme="dark"] body #main-navigation-bar ul#menu-hauptmenue.menu-mobile li.menu-item,
        html[data-dracula-scheme="dark"] body #main-navigation-bar ul#menu-hauptmenue.menu-mobile li.menu-item > a{
          background-color: #181a1b !important;
          background-image: none !important;
        }

        /* =====================================================
           ✅ WM-Ticker darf NICHT "durch" Burger/Menu klicken
           ===================================================== */
        html.bp-nav-open .f1wmt-banner-exclusive-posts-wrapper,
        html.bp-nav-open .f1wmt-banner-exclusive-posts-wrapper *,
        html.bp-nav-arming .f1wmt-banner-exclusive-posts-wrapper,
        html.bp-nav-arming .f1wmt-banner-exclusive-posts-wrapper *{
          pointer-events: none !important;
        }
        ';

        wp_add_inline_style( 'f1-theme-tweaks-css', $css );
    }

    public function external_links_fix() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const link = document.querySelector('.custom-menu-link.aft-custom-fa-icon a');
            if (link) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            }
        });
        </script>
        <?php
    }
}
