<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$light = get_option( 'f1_logo_light_url', '' );
$dark  = get_option( 'f1_logo_dark_url', '' );
?>
<div class="wrap">
	<h1>F1 Logo Switcher</h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'f1_logo_save_action', 'f1_logo_nonce' ); ?>
		<input type="hidden" name="f1_logo_save" value="1">

		<table class="form-table">
			<tr>
				<th scope="row"><label for="f1_logo_light_url">Standard Logo (Hell/Schwarz)</label></th>
				<td>
					<input name="f1_logo_light_url" type="url" id="f1_logo_light_url" value="<?php echo esc_attr( $light ); ?>" class="regular-text">
					<p class="description">URL zum Logo für den hellen Modus (oder Default).</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="f1_logo_dark_url">Dark Mode Logo (Dunkel/Weiß)</label></th>
				<td>
					<input name="f1_logo_dark_url" type="url" id="f1_logo_dark_url" value="<?php echo esc_attr( $dark ); ?>" class="regular-text">
					<p class="description">URL zum Logo für den Dracula Dark Mode.</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
