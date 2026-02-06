<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class F1_Teams {

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ), 0 );
        add_action( 'admin_init', array( $this, 'add_capabilities' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // AJAX
        add_action( 'wp_ajax_f1team_save', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_f1team_delete', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_f1team_move', array( $this, 'ajax_move' ) );

        // Frontend
        add_filter( 'the_content', array( $this, 'filter_content' ), 12 );
        add_action( 'wp_head', array( $this, 'frontend_styles' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_block_styles' ), 20 );
    }

    public function register_cpt() {
        register_post_type( 'f1_team', array(
            'labels' => array( 'name' => 'F1 Teams', 'singular_name' => 'Team' ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // Hidden, we use custom admin page
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => array( 'slug' => 'teams', 'with_front' => false ),
            'supports' => array( 'title', 'editor', 'page-attributes' ),
        ) );
    }

    public function add_capabilities() {
        $roles = array( 'administrator', 'editor', 'author' );
        foreach ( $roles as $r ) {
            $role = get_role( $r );
            if ( $role && ! $role->has_cap( 'manage_f1_team_profiles' ) ) {
                $role->add_cap( 'manage_f1_team_profiles' );
            }
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            'Teams',
            'Teams',
            'manage_f1_team_profiles',
            'f1team-admin-panel',
            array( $this, 'render_admin_page' ),
            'dashicons-plus-alt',
            27
        );
    }

    public function render_admin_page() {
        // ... (Logic from f1team_render_admin_panel_page)
        // For brevity, assume inclusion of view file
        require_once F1_MANAGER_SUITE_PATH . 'includes/admin/teams-admin.php';
    }

    // --- AJAX Handlers ---
    public function ajax_save() {
        // ... (Logic from wp_ajax_f1team_save)
        // Must verify nonce 'f1team_nonce'
    }

    public function ajax_delete() {
        // ... (Logic from wp_ajax_f1team_delete)
    }

    public function ajax_move() {
        // ... (Logic from wp_ajax_f1team_move)
    }

    // --- Frontend ---
    public function enqueue_block_styles() {
        if ( is_singular( 'f1_team' ) ) wp_enqueue_style( 'wp-block-library' );
    }

    public function frontend_styles() {
        if ( ! is_singular( 'f1_team' ) ) return;
        // Output custom CSS for hiding standard theme elements
        echo '<style>.single-f1_team .aft-view-count, .single-f1_team .nav-previous { display:none !important; }</style>';
    }

    public function filter_content( $content ) {
        if ( ! is_singular( 'f1_team' ) || ! in_the_loop() || ! is_main_query() ) return $content;

        $pid = get_the_ID();
        // Render Profile
        ob_start();
        // Ideally: separate view file
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/team-profile-view.php';
        return ob_get_clean();
    }
}
