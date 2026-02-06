<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$manual = get_option( 'f1_cnt_manual_active' );
$label  = get_option( 'f1_cnt_manual_label' );
$date   = get_option( 'f1_cnt_manual_date' );
$time   = get_option( 'f1_cnt_manual_time' );
?>
<div class="wrap">
	<h1>F1 Session Countdown</h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'f1_countdown_save_action', 'f1_countdown_nonce' ); ?>
		<input type="hidden" name="f1_countdown_save" value="1">

		<table class="form-table">
			<tr>
				<th scope="row">Modus</th>
				<td>
					<label for="f1_cnt_manual_active">
						<input name="f1_cnt_manual_active" type="checkbox" id="f1_cnt_manual_active" value="1" <?php checked( $manual, 1 ); ?>>
						Manuellen Countdown aktivieren
					</label>
					<p class="description">Ist dies deaktiviert, wird automatisch die nächste Session aus dem F1 Kalender (CPT) angezeigt.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="f1_cnt_manual_label">Label</label></th>
				<td>
					<input name="f1_cnt_manual_label" type="text" id="f1_cnt_manual_label" value="<?php echo esc_attr( $label ); ?>" class="regular-text" placeholder="z.B. Saisonstart 2026">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="f1_cnt_manual_date">Datum</label></th>
				<td>
					<input name="f1_cnt_manual_date" type="date" id="f1_cnt_manual_date" value="<?php echo esc_attr( $date ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="f1_cnt_manual_time">Uhrzeit</label></th>
				<td>
					<input name="f1_cnt_manual_time" type="time" id="f1_cnt_manual_time" value="<?php echo esc_attr( $time ); ?>" class="regular-text">
					<p class="description">WP Zeitzone wird verwendet.</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
