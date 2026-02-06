<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$links = get_option( 'f1_footer_links', array() );
if ( ! is_array( $links ) ) $links = array();
?>
<div class="wrap">
	<h1>F1 Footer Links</h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'f1_footer_save_action', 'f1_footer_nonce' ); ?>
		<input type="hidden" name="f1_footer_save" value="1">

		<style>
			.f1-footer-admin-table { width: 100%; max-width: 800px; border-collapse: collapse; margin-bottom: 20px; }
			.f1-footer-admin-table th, .f1-footer-admin-table td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
			.f1-footer-admin-table th { background: #f9f9f9; }
			.f1-footer-admin-table input[type="text"], .f1-footer-admin-table input[type="url"] { width: 100%; }
			.f1-footer-btn-remove { color: #a00; text-decoration: underline; cursor: pointer; border:none; background:transparent; }
		</style>

		<table class="f1-footer-admin-table">
			<thead>
				<tr>
					<th style="width: 40%;">Label</th>
					<th style="width: 50%;">URL</th>
					<th style="width: 10%;">Aktion</th>
				</tr>
			</thead>
			<tbody id="f1-footer-rows">
				<?php
				if ( empty( $links ) ) {
					// Dummy row if empty
					$links = array( array('label' => '', 'url' => '') );
				}
				foreach ( $links as $index => $link ) :
				?>
					<tr>
						<td><input type="text" name="links[<?php echo $index; ?>][label]" value="<?php echo esc_attr( $link['label'] ); ?>" placeholder="z.B. Impressum"></td>
						<td><input type="url" name="links[<?php echo $index; ?>][url]" value="<?php echo esc_attr( $link['url'] ); ?>" placeholder="https://..."></td>
						<td><button type="button" class="f1-footer-btn-remove">Löschen</button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div style="margin-bottom: 20px;">
			<button type="button" class="button" id="f1-footer-add-row">+ Zeile hinzufügen</button>
		</div>

		<button type="submit" class="button button-primary">Änderungen speichern</button>
	</form>

	<script>
	(function(){
		var tbody = document.getElementById('f1-footer-rows');
		var addBtn = document.getElementById('f1-footer-add-row');

		function bindEvents(row) {
			var btn = row.querySelector('.f1-footer-btn-remove');
			if(btn) {
				btn.addEventListener('click', function(){
					row.remove();
				});
			}
		}

		// Initial bind
		Array.from(tbody.querySelectorAll('tr')).forEach(bindEvents);

		addBtn.addEventListener('click', function(){
			var idx = tbody.querySelectorAll('tr').length;
			var tr = document.createElement('tr');
			tr.innerHTML = `
				<td><input type="text" name="links[${Date.now()}][label]" placeholder="Label"></td>
				<td><input type="url" name="links[${Date.now()}][url]" placeholder="https://..."></td>
				<td><button type="button" class="f1-footer-btn-remove">Löschen</button></td>
			`;
			tbody.appendChild(tr);
			bindEvents(tr);
		});
	})();
	</script>
</div>
