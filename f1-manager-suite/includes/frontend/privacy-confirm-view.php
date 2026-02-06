<!doctype html>
<html lang="<?php echo esc_attr( substr( get_locale(), 0, 2 ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="stylesheet" href="<?php echo plugins_url( '../../assets/css/f1-profile.css', __FILE__ ); ?>">
</head>
<body>
	<div class="f1fp-wrap">
		<div class="f1fp-card">
			<div class="f1fp-head">
				<h1 class="f1fp-head__title"><?php echo esc_html( $title ); ?></h1>
			</div>

			<div class="f1fp-body">
				<div class="f1fp-alert <?php echo ( $type === 'error' ) ? 'f1fp-alert--error' : ''; ?>">
					<?php echo wp_kses_post( $html ); ?>
				</div>

				<p class="f1fp-muted">Du kannst dieses Fenster jetzt schließen oder zurück zu deinem Profil.</p>

				<div class="f1fp-actions">
					<a class="f1fp-btn f1fp-btn--primary" href="<?php echo esc_url( F1_Profile::profile_url() ); ?>">Zum Profil</a>
					<a class="f1fp-btn f1fp-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
