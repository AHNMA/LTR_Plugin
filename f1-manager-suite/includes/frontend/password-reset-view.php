<!doctype html>
<html lang="<?php echo esc_attr( substr( get_locale(), 0, 2 ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="stylesheet" href="<?php echo plugins_url( '../../assets/css/f1-profile.css', __FILE__ ); ?>">

	<?php if ( $pw_changed ) : ?>
		<script>
			setTimeout(function(){
				window.location.href = <?php echo json_encode( home_url( '/' ) ); ?>;
			}, 3000);
		</script>
	<?php endif; ?>
</head>

<body>
<div class="f1pr-wrap">
	<div class="f1pr-card">
		<div class="f1pr-head">
			<h1 class="f1pr-head__title"><?php echo esc_html( $title ); ?></h1>
		</div>

		<div class="f1pr-body">

			<?php if ( ! empty( $flash ) && is_array( $flash ) ) : ?>
				<div class="f1pr-alert <?php echo ( ! empty( $flash['type'] ) && $flash['type'] === 'error' ) ? 'f1pr-alert--error' : ''; ?>">
					<?php
					$msgs = isset( $flash['messages'] ) && is_array( $flash['messages'] ) ? $flash['messages'] : array();
					if ( count( $msgs ) <= 1 ) {
						echo esc_html( $msgs ? $msgs[0] : '' );
					} else {
						echo '<div><b>Bitte prüfen:</b></div><ul>';
						foreach ( $msgs as $m ) echo '<li>' . esc_html( $m ) . '</li>';
						echo '</ul>';
					}
					?>
				</div>
			<?php endif; ?>

			<?php if ( $block_logged_in_reset ) : ?>

				<div class="f1pr-alert f1pr-alert--error">
					Du bist bereits eingeloggt. Aus Sicherheitsgründen ist das Zurücksetzen per E-Mail-Link jetzt deaktiviert.
					Bitte logge dich aus und öffne den Link danach erneut.
				</div>

				<div class="f1pr-actions">
					<a class="f1pr-btn f1pr-btn--primary" href="<?php echo esc_url( $logout_url ); ?>">Logout &amp; Link erneut öffnen</a>
					<a class="f1pr-btn f1pr-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a>
				</div>

			<?php elseif ( $has_reset_params ) : ?>

				<p class="f1pr-muted">Bitte gib dein neues Passwort ein.</p>

				<form class="f1pr-form" method="post" action="">
					<?php wp_nonce_field( 'f1pr_set_new_password', 'f1pr_nonce' ); ?>
					<input type="hidden" name="f1pr_action" value="set_new_password" />
					<input type="hidden" name="login" value="<?php echo esc_attr( $q_login ); ?>" />
					<input type="hidden" name="key" value="<?php echo esc_attr( $q_key ); ?>" />

					<div class="f1pr-row">
						<label class="f1pr-label" for="f1pr_pass1">Neues Passwort</label>
						<input class="f1pr-input" id="f1pr_pass1" name="pass1" type="password" autocomplete="new-password" />
					</div>

					<div class="f1pr-row">
						<label class="f1pr-label" for="f1pr_pass2">Neues Passwort (Wdh.)</label>
						<input class="f1pr-input" id="f1pr_pass2" name="pass2" type="password" autocomplete="new-password" />
					</div>

					<div class="f1pr-actions">
						<button class="f1pr-btn f1pr-btn--primary" type="submit">Passwort speichern</button>
						<a class="f1pr-btn f1pr-btn--ghost" href="<?php echo esc_url( wp_login_url() ); ?>">Zum Login</a>
					</div>
				</form>

			<?php else : ?>

				<div class="f1pr-alert f1pr-alert--error">
					Diese Seite dient nur zum Setzen eines neuen Passworts über einen Link aus der E-Mail.
					Bitte nutze „Passwort vergessen?“ auf der Login-Seite, um einen Reset-Link zu erhalten.
				</div>

				<div class="f1pr-actions">
					<a class="f1pr-btn f1pr-btn--primary" href="<?php echo esc_url( wp_login_url() ); ?>">Zum Login</a>
					<a class="f1pr-btn f1pr-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a>
				</div>

			<?php endif; ?>

		</div>
	</div>
</div>
</body>
</html>
