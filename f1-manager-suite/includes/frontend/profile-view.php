<div class="f1fp-wrap">
<div class="f1fp-container">
	<div class="f1fp-card">
	<div class="f1fp-head">
		<h2 class="f1fp-head__title">Profil-Daten</h2>
	</div>
	<div class="f1fp-body">

		<?php if (!empty($flash) && is_array($flash)): ?>
		<div class="f1fp-alert <?php echo (!empty($flash['type']) && $flash['type'] === 'error') ? 'f1fp-alert--error' : ''; ?>">
			<?php
			$msgs = isset($flash['messages']) && is_array($flash['messages']) ? $flash['messages'] : array();
			if (count($msgs) <= 1) {
				echo esc_html($msgs ? $msgs[0] : '');
			} else {
				echo '<div><b>Bitte prüfen:</b></div><ul>';
				foreach ($msgs as $m) {
				echo '<li>' . esc_html($m) . '</li>';
				}
				echo '</ul>';
			}
			?>
		</div>
		<?php endif; ?>

		<div class="f1fp-top">
		<div>
			<div class="f1fp-avatar-stack">
			<form id="f1fp-avatar-form" method="post" action="" enctype="multipart/form-data" style="margin:0;">
				<?php wp_nonce_field('f1fp_save_profile', 'f1fp_nonce'); ?>
				<input type="hidden" name="f1fp_action" value="save_profile" />
				<input id="f1fp_avatar_input" name="f1fp_avatar" type="file" accept="image/jpeg,image/png,image/webp" style="display:none;" />
				<input id="f1fp_avatar_remove" name="f1fp_avatar_remove" type="hidden" value="0" />
				<div class="f1fp-avatar-wrap">
				<?php if ($has_custom_avatar): ?>
					<button type="button" class="f1fp-avatar-x" id="f1fp-avatar-x" aria-label="Profilbild löschen">×</button>
				<?php endif; ?>
				<button type="button" class="f1fp-avatar-click" id="f1fp-avatar-click" aria-label="Profilbild hochladen oder ändern">
					<div class="f1fp-avatar" aria-hidden="true">
					<?php if ($avatar_url !== ''): ?>
						<img decoding="async" loading="lazy" src="<?php echo esc_url($avatar_url); ?>" alt="">
					<?php else: ?>
						<span class="f1fp-avatar__initials"><?php echo esc_html($initials); ?></span>
					<?php endif; ?>
					<span class="f1fp-avatar-overlay" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3v10" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round"/><path d="M8.5 6.5L12 3l3.5 3.5" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 14v4a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-4" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</span>
					</div>
				</button>
				</div>
			</form>
			<div class="f1fp-social-under-avatar">
				<div class="f1fp-social" aria-label="Social Links">
				<?php if ($fb !== ''): ?><a data-social="facebook" href="<?php echo esc_url($fb); ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><?php endif; ?>
				<?php if ($x  !== ''): ?><a data-social="x" href="<?php echo esc_url($x); ?>" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a><?php endif; ?>
				<?php if ($ig !== ''): ?><a data-social="instagram" href="<?php echo esc_url($ig); ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><?php endif; ?>
				</div>
			</div>
			</div>
		</div>
		<div style="min-width:0;">
			<p class="f1fp-name"><?php echo esc_html($user->display_name); ?></p>
			<p class="f1fp-meta"><?php echo esc_html($user->user_email); ?></p>
			<div class="f1fp-row f1fp-row--stack">
			<span class="f1fp-badge">Rolle: <?php echo esc_html($role_label); ?></span>
			<span class="f1fp-badge">ID: <?php echo (int)$user_id; ?></span>
			</div>
		</div>
		</div>

		<form id="f1fp-main-form" class="f1fp-form f1fp-js-disable-on-submit" method="post" action="">
		<?php wp_nonce_field('f1fp_save_profile', 'f1fp_nonce'); ?>
		<input type="hidden" name="f1fp_action" value="save_profile" />

		<div class="f1fp-section">
			<p class="f1fp-section-title">Persönliche Daten</p>
			<div class="f1fp-fields">
			<div class="f1fp-field"><label class="f1fp-label" for="f1fp_first">Vorname</label><input class="f1fp-input" id="f1fp_first" name="first_name" type="text" value="<?php echo esc_attr($user->first_name); ?>" /></div>
			<div class="f1fp-field"><label class="f1fp-label" for="f1fp_last">Nachname</label><input class="f1fp-input" id="f1fp_last" name="last_name" type="text" value="<?php echo esc_attr($user->last_name); ?>" /></div>
			<div class="f1fp-field"><label class="f1fp-label" for="f1fp_display">Anzeigename</label><input class="f1fp-input" id="f1fp_display" name="display_name" type="text" value="<?php echo esc_attr($user->display_name); ?>" /></div>
			<div class="f1fp-field"><label class="f1fp-label" for="f1fp_url">Website</label><input class="f1fp-input" id="f1fp_url" name="user_url" type="url" value="<?php echo esc_attr($user->user_url); ?>" placeholder="https://..." /></div>
			<div class="f1fp-field" style="grid-column:1/-1;"><label class="f1fp-label" for="f1fp_bio">Bio</label><textarea class="f1fp-textarea" id="f1fp_bio" name="description"><?php echo esc_textarea($user->description); ?></textarea></div>
			</div>
		</div>

		<div class="f1fp-section">
			<p class="f1fp-section-title">Social Links</p>
			<div class="f1fp-fields">
			<div class="f1fp-field"><label class="f1fp-label" for="f1fp_fb">Facebook</label><input class="f1fp-input" id="f1fp_fb" name="f1_social_fb" type="url" value="<?php echo esc_attr($fb); ?>" placeholder="https://facebook.com/..." /></div>
			<div class="f1fp-field"><label class="f1fp-label" for="f1fp_x">X (Twitter)</label><input class="f1fp-input" id="f1fp_x" name="f1_social_x" type="url" value="<?php echo esc_attr($x); ?>" placeholder="https://x.com/..." /></div>
			<div class="f1fp-field"><label class="f1fp-label" for="f1fp_ig">Instagram</label><input class="f1fp-input" id="f1fp_ig" name="f1_social_ig" type="url" value="<?php echo esc_attr($ig); ?>" placeholder="https://instagram.com/..." /></div>
			</div>
		</div>

		<div class="f1fp-section">
			<p class="f1fp-section-title">Sicherheit</p>

			<div class="f1fp-fields">
			<div class="f1fp-field" style="grid-column: 1 / -1;">
				<div style="display:flex; flex-wrap:wrap; gap:18px; align-items:flex-end;">

					<div style="flex: 1 1 300px; min-width:0;">
						<label class="f1fp-label" for="f1fp_email">E-Mail</label>
						<input class="f1fp-input" id="f1fp_email" name="user_email" type="email" value="<?php echo esc_attr($user->user_email); ?>" />
					</div>

					<div class="f1fp-pass-col">
						<label class="f1fp-label">Passwort</label>
						<button type="submit" form="f1fp-reset-link-form" class="f1fp-btn f1fp-btn--ghost"
								style="border:1px solid rgba(0,0,0,.15); justify-content:center; padding:10px 14px; min-width:140px;">
							Reset-Link senden
						</button>
					</div>

				</div>
			</div>
			</div>
		</div>

		<div class="f1fp-section">
			<p class="f1fp-section-title">Login-Verknüpfungen</p>
			<div class="f1fp-fields" style="grid-template-columns:1fr;">
			<div class="f1fp-field">
				<label class="f1fp-label">Google</label>
				<?php if ($has_google): ?>
				<div class="f1fp-connected-box">
					<div style="display:flex; align-items:center; gap:8px;">
					<svg style="width:18px; height:18px;" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path></svg>
					<span style="font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;">Verbunden</span>
					</div>
					<button type="submit" form="f1fp-disconnect-google-form" class="f1fp-btn f1fp-btn--ghost" style="padding:6px 10px; font-size:10px; border:1px solid rgba(0,0,0,.15); border-left-width:1px;">Trennen</button>
				</div>
				<?php else: ?>
				<a href="<?php echo esc_url(home_url('/?bp_social_auth=google&mode=connect')); ?>" class="f1fp-connect-btn">
					<svg viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path></svg>
					Google verbinden
				</a>
				<?php endif; ?>
			</div>
			</div>
		</div>
		</form>

		<?php if ($has_google): ?>
		<form id="f1fp-disconnect-google-form" method="post" action="" style="display:none;">
			<?php wp_nonce_field('f1fp_disconnect_google', 'f1fp_disconnect_nonce'); ?>
			<input type="hidden" name="f1fp_action" value="disconnect_google" />
		</form>
		<?php endif; ?>

		<form id="f1fp-reset-link-form" method="post" action="" style="display:none;">
			<?php wp_nonce_field('f1fp_send_reset_link', 'f1fp_reset_nonce'); ?>
			<input type="hidden" name="f1fp_action" value="send_reset_link" />
		</form>

		<div class="f1fp-actions-bar">
		<div class="f1fp-actions-left">
			<button id="f1fp-delete-toggle" class="f1fp-btn f1fp-btn--danger" type="button" aria-controls="f1fp-delete-wrap" aria-expanded="false" data-label-open="Profil löschen" data-label-close="Löschen abbrechen">Profil löschen</button>
			<button id="f1fp-export-toggle" class="f1fp-btn f1fp-btn--orange" type="button" aria-controls="f1fp-export-wrap" aria-expanded="false" data-label-open="Datenexport" data-label-close="Datenexport abbrechen">Datenexport</button>
		</div>
		<div class="f1fp-actions-right">
			<button class="f1fp-btn f1fp-btn--primary" type="submit" form="f1fp-main-form">Speichern</button>
		</div>
		</div>

		<div id="f1fp-delete-wrap" hidden>
		<form class="f1fp-form f1fp-js-disable-on-submit" method="post" action="" style="margin-top:20px;">
			<?php wp_nonce_field('f1fp_delete_profile', 'f1fp_delete_nonce'); ?>
			<input type="hidden" name="f1fp_action" value="delete_profile" />
			<div class="f1fp-section f1fp-section--danger">
			<p class="f1fp-section-title" style="color:#b00020; border-color:rgba(176,0,32,.2);">PROFIL LÖSCHEN</p>
			<p class="f1fp-text-muted">Diese Aktion ist endgültig und kann nicht rückgängig gemacht werden. Alle Daten werden gelöscht.</p>

			<div class="f1fp-inline-action">
				<label class="f1fp-checkrow">
					<input type="checkbox" name="f1fp_delete_confirm" value="1" />
					Ich möchte mein Profil wirklich dauerhaft löschen.
				</label>
				<button class="f1fp-btn f1fp-btn--danger" type="submit" onclick="return confirm('Profil wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">Löschung bestätigen</button>
			</div>

			</div>
		</form>
		</div>

		<div id="f1fp-export-wrap" hidden>
		<form class="f1fp-form f1fp-js-disable-on-submit" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
			<?php wp_nonce_field('f1fp_privacy_export', 'f1fp_privacy_nonce'); ?>
			<input type="hidden" name="action" value="f1fp_privacy_export_request">
			<div class="f1fp-section">
			<p class="f1fp-section-title">DATENEXPORT</p>
			<p class="f1fp-text-muted">Du bekommst eine E-Mail mit einem Bestätigungslink. Nach der Bestätigung startet WordPress den Export deiner personenbezogenen Daten.</p>

			<div class="f1fp-inline-action">
				<label class="f1fp-checkrow">
					<input type="checkbox" name="f1fp_export_confirm" value="1" />
					Ich möchte einen Datenexport anfordern und die Bestätigungs-Mail erhalten.
				</label>
				<button class="f1fp-btn f1fp-btn--primary" type="submit">Bestätigungs-Mail senden</button>
			</div>

			</div>
		</form>
		</div>

	</div>
	</div>
</div>
</div>
