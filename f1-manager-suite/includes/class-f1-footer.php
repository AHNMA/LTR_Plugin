<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class F1_Footer {

	public function __construct() {
		// Admin
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );

		// Frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
		require_once plugin_dir_path( __FILE__ ) . 'admin/footer-admin.php';
	}

	public function handle_save() {
		if ( ! is_admin() ) return;
		if ( ! isset( $_POST['f1_footer_save'] ) ) return;

		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung.' );
		if ( ! check_admin_referer( 'f1_footer_save_action', 'f1_footer_nonce' ) ) wp_die( 'Nonce Fehler.' );

		$raw = isset( $_POST['links'] ) ? (array)$_POST['links'] : array();
		$clean = array();

		foreach ( $raw as $row ) {
			if ( empty( $row['label'] ) && empty( $row['url'] ) ) continue;

			$clean[] = array(
				'label' => sanitize_text_field( $row['label'] ),
				'url'   => esc_url_raw( $row['url'] ),
			);
		}

		update_option( 'f1_footer_links', $clean, false );

		wp_safe_redirect( add_query_arg( array( 'page' => 'f1-footer', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'f1-footer', plugin_dir_url( __FILE__ ) . '../assets/css/f1-footer.css', array(), '1.0.0' );
	}

	public function render_shortcode( $atts ) {
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
