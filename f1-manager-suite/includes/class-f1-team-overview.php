<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class F1_Team_Overview {

	public function __construct() {
		add_shortcode( 'f1_team_overview', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'f1-team-overview', plugin_dir_url( __FILE__ ) . '../assets/css/f1-team-overview.css', array(), '1.0.0' );
	}

	public function render_shortcode() {
		if ( ! post_type_exists( 'f1_team' ) || ! post_type_exists( 'f1_driver' ) ) {
			return '<div style="padding:12px; border:1px solid rgba(0,0,0,.15); background:#fff;"><b>F1 Team-Übersicht:</b> CPTs f1_team / f1_driver nicht gefunden.</div>';
		}

		$teams = array();
		// 1) Try f1team_get_ordered_ids if available (from F1_Teams module)
		if ( function_exists( 'f1team_get_ordered_ids' ) ) {
			$ids = f1team_get_ordered_ids();
			if ( ! empty( $ids ) ) {
				$teams = get_posts( array(
					'post_type'      => 'f1_team',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'post__in'       => $ids,
					'orderby'        => 'post__in',
					'no_found_rows'  => true,
				) );
			}
		}

		// 2) Fallback
		if ( ! $teams ) {
			$teams = get_posts( array(
				'post_type'      => 'f1_team',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'  => true,
			) );
		}

		if ( ! $teams ) {
			return '<div style="padding:12px; border:1px solid rgba(0,0,0,.15); background:#fff;"><b>F1 Team-Übersicht:</b> Keine Teams gefunden.</div>';
		}

		$data = array();

		foreach ( $teams as $t ) {
			$tid = $t->ID;
			$tm = self::get_team_meta( $tid );
			$color = self::get_team_color( $tid );

			$row = array(
				'id'      => $tid,
				'name'    => get_the_title( $tid ),
				'link'    => get_permalink( $tid ),
				'accent'  => $color ? $color : '#E00078',
				'head'    => '#202020',
				'logo'    => self::get_teamlogo_url( $tm['teamlogo'] ?? '' ),
				'car'     => self::get_carimg_url( $tm['carimg'] ?? '' ),
				'socials' => self::render_socials( $tm['fb'] ?? '', $tm['x'] ?? '', $tm['ig'] ?? '' ),
				'meta_rows' => array(),
				'drivers' => array(),
			);

			if ( ! empty( $tm['nationality'] ) ) $row['meta_rows'][] = array( 'Nationalität', $tm['nationality'] );
			if ( ! empty( $tm['entry_year'] ) )  $row['meta_rows'][] = array( 'Eintrittsjahr', $tm['entry_year'] );
			if ( ! empty( $tm['chassis'] ) )     $row['meta_rows'][] = array( 'Chassis', $tm['chassis'] );
			if ( ! empty( $tm['powerunit'] ) )   $row['meta_rows'][] = array( 'Power Unit', $tm['powerunit'] );

			// Drivers
			$drvs = get_posts( array(
				'post_type'      => 'f1_driver',
				'posts_per_page' => 2,
				'post_status'    => 'publish',
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => '_f1drv_team_id', 'value' => (string)$tid, 'compare' => '=' ),
					array(
						'relation' => 'OR',
						array( 'key' => '_f1drv_team_inactive', 'compare' => 'NOT EXISTS' ),
						array( 'key' => '_f1drv_team_inactive', 'value' => '1', 'compare' => '!=' ),
					),
				),
				'no_found_rows'  => true,
			) );

			if ( ! $drvs ) continue; // Skip teams without active drivers

			foreach ( $drvs as $d ) {
				$did = $d->ID;
				$dm = self::get_driver_meta( $did );

				$d_data = array(
					'id'     => $did,
					'name'   => get_the_title( $did ),
					'link'   => get_permalink( $did ),
					'img'    => self::get_driver_img_url( $dm['img'] ?? '' ),
					'flag'   => self::get_flag_url( $dm['flag'] ?? '' ),
					'socials_std' => self::render_socials( $dm['fb'] ?? '', $dm['x'] ?? '', $dm['ig'] ?? '' ),
					'socials_head' => self::render_socials( $dm['fb'] ?? '', $dm['x'] ?? '', $dm['ig'] ?? '', 'f1ov-driver-social--head' ),
					'socials_below' => self::render_socials( $dm['fb'] ?? '', $dm['x'] ?? '', $dm['ig'] ?? '', 'f1ov-driver-social--belowname' ),
					'meta_rows' => array(),
				);

				if ( ! empty( $dm['birthplace'] ) ) $d_data['meta_rows'][] = array( 'Geburtsort', $dm['birthplace'] );
				if ( ! empty( $dm['birthdate'] ) )  $d_data['meta_rows'][] = array( 'Geburtstag', $dm['birthdate'] );
				if ( ! empty( $dm['height'] ) )     $d_data['meta_rows'][] = array( 'Größe', $dm['height'] );
				if ( ! empty( $dm['weight'] ) )     $d_data['meta_rows'][] = array( 'Gewicht', $dm['weight'] );

				$row['drivers'][] = $d_data;
			}

			$data[] = $row;
		}

		if ( empty( $data ) ) {
			return '<div style="padding:12px; border:1px solid rgba(0,0,0,.15); background:#fff;"><b>F1 Team-Übersicht:</b> Keine Teams mit aktiven Fahrern.</div>';
		}

		ob_start();
		require plugin_dir_path( __FILE__ ) . 'frontend/team-overview-view.php';
		return ob_get_clean();
	}

	/* =========================================================
	   HELPERS
	   ========================================================= */

	private static function clean_link( $url ) {
		$url = trim( (string)$url );
		if ( $url === '' ) return '';
		if ( preg_match( '~^/[^\s]*$~', $url ) ) return $url;
		return esc_url( $url ) ?: '';
	}

	private static function render_socials( $fb, $x, $ig, $extra_class = '' ) {
		$fb = self::clean_link( $fb );
		$x  = self::clean_link( $x );
		$ig = self::clean_link( $ig );
		$extra_class = trim( (string)$extra_class );

		if ( ! $fb && ! $x && ! $ig ) return '';

		$out = '<div class="f1ov__social' . ( $extra_class ? ' ' . esc_attr( $extra_class ) : '' ) . '">';
		if ( $fb ) $out .= '<a data-social="facebook" href="' . esc_url( $fb ) . '" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i></a>';
		if ( $x )  $out .= '<a data-social="x" href="' . esc_url( $x ) . '" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i></a>';
		if ( $ig ) $out .= '<a data-social="instagram" href="' . esc_url( $ig ) . '" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i></a>';
		$out .= '</div>';
		return $out;
	}

	private static function get_team_color( $id ) {
		$keys = array( '_f1team_color', '_f1team_teamcolor', '_f1team_team_colour', '_f1_teamcolor', 'teamcolor' );
		foreach ( $keys as $k ) {
			$v = trim( (string)get_post_meta( $id, $k, true ) );
			if ( $v ) return $v;
		}
		return '';
	}

	private static function get_team_meta( $id ) {
		return array(
			'teamlogo'    => (string)get_post_meta( $id, '_f1team_teamlogo', true ),
			'carimg'      => (string)get_post_meta( $id, '_f1team_carimg', true ),
			'flag'        => (string)get_post_meta( $id, '_f1team_flag', true ),
			'nationality' => (string)get_post_meta( $id, '_f1team_nationality', true ),
			'entry_year'  => (string)get_post_meta( $id, '_f1team_entry_year', true ),
			'teamchief'   => (string)get_post_meta( $id, '_f1team_teamchief', true ),
			'base'        => (string)get_post_meta( $id, '_f1team_base', true ),
			'chassis'     => (string)get_post_meta( $id, '_f1team_chassis', true ),
			'powerunit'   => (string)get_post_meta( $id, '_f1team_powerunit', true ),
			'teamcolor'   => (string)get_post_meta( $id, '_f1team_color', true ),
			'fb'          => (string)get_post_meta( $id, '_f1team_fb', true ),
			'x'           => (string)get_post_meta( $id, '_f1team_x', true ),
			'ig'          => (string)get_post_meta( $id, '_f1team_ig', true ),
			'bio'         => (string)get_post_meta( $id, '_f1team_bio', true ),
		);
	}

	private static function get_driver_meta( $id ) {
		return array(
			'img'        => (string)get_post_meta( $id, '_f1drv_img', true ),
			'flag'       => (string)get_post_meta( $id, '_f1drv_flag', true ),
			'nationality'=> (string)get_post_meta( $id, '_f1drv_nationality', true ),
			'birthplace' => (string)get_post_meta( $id, '_f1drv_birthplace', true ),
			'birthdate'  => (string)get_post_meta( $id, '_f1drv_birthdate', true ),
			'height'     => (string)get_post_meta( $id, '_f1drv_height', true ),
			'weight'     => (string)get_post_meta( $id, '_f1drv_weight', true ),
			'fb'         => (string)get_post_meta( $id, '_f1drv_fb', true ),
			'x'          => (string)get_post_meta( $id, '_f1drv_x', true ),
			'ig'         => (string)get_post_meta( $id, '_f1drv_ig', true ),
		);
	}

	private static function get_driver_img_url( $file ) {
		$file = sanitize_file_name( $file );
		if ( ! $file || ! preg_match( '~\.png$~i', $file ) ) return '';
		return rtrim( content_url( 'uploads' ), '/' ) . '/driver/440px/' . $file;
	}

	private static function get_flag_url( $code ) {
		$code = preg_replace( '~[^a-z]~', '', strtolower( trim( (string)$code ) ) );
		if ( strlen( $code ) !== 2 ) return '';
		return rtrim( content_url( 'uploads' ), '/' ) . '/flags/' . $code . '.png';
	}

	private static function get_teamlogo_url( $file ) {
		$file = sanitize_file_name( $file );
		if ( ! $file || ! preg_match( '~\.png$~i', $file ) ) return '';
		return rtrim( content_url( 'uploads' ), '/' ) . '/teams/logo/' . $file;
	}

	private static function get_carimg_url( $file ) {
		$file = sanitize_file_name( $file );
		if ( ! $file || ! preg_match( '~\.png$~i', $file ) ) return '';
		return rtrim( content_url( 'uploads' ), '/' ) . '/teams/car/' . $file;
	}
}
