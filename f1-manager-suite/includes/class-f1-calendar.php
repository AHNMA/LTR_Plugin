<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Calendar {

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'admin_init', array( $this, 'add_capabilities' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // AJAX
        add_action( 'wp_ajax_f1cal_save', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_f1cal_delete', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_f1cal_move', array( $this, 'ajax_move' ) );

        // Shortcode
        add_shortcode( 'f1_calendar', array( $this, 'render_shortcode' ) );
    }

    public function register_cpt() {
        register_post_type( 'f1_race', array(
            'labels' => array( 'name' => 'F1 Kalender', 'singular_name' => 'F1 Rennen' ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => array( 'title', 'page-attributes' ),
        ) );
    }

    public function add_capabilities() {
        $roles = array( 'administrator', 'editor', 'author' );
        foreach ( $roles as $r ) {
            $role = get_role( $r );
            if ( $role && ! $role->has_cap( 'manage_f1_calendar' ) ) {
                $role->add_cap( 'manage_f1_calendar' );
            }
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            'Rennkalender',
            'Rennkalender',
            'manage_f1_calendar',
            'f1cal_editor',
            array( $this, 'render_admin_page' ),
            'dashicons-plus-alt',
            26
        );
    }

    public function render_admin_page() {
        // The admin page essentially renders the frontend shortcode + admin JS
        echo '<div class="wrap">';
        echo do_shortcode( '[f1_calendar]' );
        echo '</div>';
    }

    public function ajax_save() { /* ... */ }
    public function ajax_delete() { /* ... */ }
    public function ajax_move() { /* ... */ }

    public function render_shortcode( $atts ) {
        // Logic from f1_calendar shortcode
        // ...
        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/calendar-view.php';
        return ob_get_clean();
    }
}
