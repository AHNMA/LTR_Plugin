<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$speed_drivers = (int)get_option( 'f1_ticker_speed_drivers', 80000 );
$speed_teams   = (int)get_option( 'f1_ticker_speed_teams', 80000 );

if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'saved' ) {
	echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>';
}
?>
<div class="wrap">
	<h1>WM Ticker Einstellungen</h1>
	<p>Hier kannst du die Laufgeschwindigkeit des Tickers anpassen.</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'f1wmt_save_options' ); ?>
		<input type="hidden" name="f1wmt_save" value="1">

		<table class="form-table">
			<tr>
				<th scope="row"><label for="speed_drivers">Geschwindigkeit Fahrer (ms)</label></th>
				<td>
					<input name="speed_drivers" type="number" id="speed_drivers" value="<?php echo esc_attr( $speed_drivers ); ?>" class="regular-text" min="5000" step="100">
					<p class="description">Standard: 80000. Je höher der Wert, desto langsamer läuft der Ticker.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="speed_teams">Geschwindigkeit Teams (ms)</label></th>
				<td>
					<input name="speed_teams" type="number" id="speed_teams" value="<?php echo esc_attr( $speed_teams ); ?>" class="regular-text" min="5000" step="100">
					<p class="description">Standard: 80000. Je höher der Wert, desto langsamer läuft der Ticker.</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
