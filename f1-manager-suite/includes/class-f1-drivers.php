<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Drivers {

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ), 0 );
        add_action( 'admin_init', array( $this, 'add_capabilities' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // AJAX
        add_action( 'wp_ajax_f1drv_save', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_f1drv_delete', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_f1drv_move', array( $this, 'ajax_move' ) );

        // Frontend
        add_filter( 'the_content', array( $this, 'filter_content' ), 12 );
        add_action( 'wp_head', array( $this, 'frontend_styles' ), 99 );
    }

    public function register_cpt() {
        register_post_type( 'f1_driver', array(
            'labels' => array( 'name' => 'F1 Fahrer', 'singular_name' => 'Fahrer' ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => array( 'slug' => 'fahrer', 'with_front' => false ),
            'supports' => array( 'title', 'editor', 'page-attributes' ),
        ) );
    }

    public function add_capabilities() {
        $roles = array( 'administrator', 'editor', 'author' );
        foreach ( $roles as $r ) {
            $role = get_role( $r );
            if ( $role && ! $role->has_cap( 'manage_f1_driver_profiles' ) ) {
                $role->add_cap( 'manage_f1_driver_profiles' );
            }
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            'Fahrer',
            'Fahrer',
            'manage_f1_driver_profiles',
            'f1drv-admin-panel',
            array( $this, 'render_admin_page' ),
            'dashicons-plus-alt',
            28
        );
    }

    public function render_admin_page() {
        require_once F1_MANAGER_SUITE_PATH . 'includes/admin/drivers-admin.php';
    }

    public function ajax_save() { /* ... */ }
    public function ajax_delete() { /* ... */ }
    public function ajax_move() { /* ... */ }

    public function frontend_styles() {
        if ( ! is_singular( 'f1_driver' ) ) return;
        echo '<style>.single-f1_driver .aft-view-count { display:none !important; }</style>';
    }

    public function filter_content( $content ) {
        if ( ! is_singular( 'f1_driver' ) || ! in_the_loop() || ! is_main_query() ) return $content;

        $pid = get_the_ID();
        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/driver-profile-view.php';
        return ob_get_clean();
    }
}
