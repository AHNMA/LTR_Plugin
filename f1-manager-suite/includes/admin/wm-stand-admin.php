<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( F1WMS_CAPABILITY ) ) {
	wp_die( 'Keine Berechtigung.' );
}

$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'results';

// Tab 1 Data
$races = F1_WM_Stand::get_races();
$race_id = isset( $_GET['race_id'] ) ? absint( $_GET['race_id'] ) : 0;
if ( ! $race_id && ! empty( $races[0]['id'] ) ) {
	$race_id = (int)$races[0]['id'];
}
$sessions = $race_id ? F1_WM_Stand::get_sessions_for_race( $race_id ) : array();
$notice = isset( $_GET['f1wms_notice'] ) ? sanitize_text_field( $_GET['f1wms_notice'] ) : '';

$dir_ver = F1_WM_Stand::DRIVER_DIR_VER;
$dir_ts = (int)get_option( 'f1wms_driver_directory_' . $dir_ver . '_ts', 0 );
$dir_ts_label = $dir_ts > 0 ? date_i18n( 'd.m.Y H:i', $dir_ts ) : 'noch nie';
$dir_count = is_array( get_option( 'f1wms_driver_directory_' . $dir_ver ) ) ? count( get_option( 'f1wms_driver_directory_' . $dir_ver ) ) : 0;

// Tab 2 Data
$converter_output = isset( $converter_output ) ? $converter_output : '';
$converter_info   = isset( $converter_info ) ? $converter_info : '';
$input_val        = isset( $_POST['f1sc_input'] ) ? wp_unslash( $_POST['f1sc_input'] ) : '';
?>
<div class="wrap">
	<h1>WM-Stand</h1>

	<h2 class="nav-tab-wrapper">
		<a href="?page=f1-wm-stand&tab=results" class="nav-tab <?php echo $tab !== 'converter' ? 'nav-tab-active' : ''; ?>">Session Ergebnisse</a>
		<a href="?page=f1-wm-stand&tab=converter" class="nav-tab <?php echo $tab === 'converter' ? 'nav-tab-active' : ''; ?>">Format Konverter</a>
	</h2>

	<style>
		.f1wms-admin{ --canvas: #EEEEEE; --card: #ffffff; --head: #202020; --accent: #E00078; --text: #111111; --muted: rgba(17,17,17,.65); --line: rgba(0,0,0,.08); color: var(--text); font-size: 13px; line-height: 1.45; }
		.f1wms-admin *{ box-sizing: border-box; }
		.f1wms-admin .canvas{ background: var(--canvas); padding: 14px 20px; width: 100%; }
		.f1wms-admin .card{ background: var(--card); border: 1px solid var(--line); box-shadow: 0 8px 24px rgba(0,0,0,.06); margin-bottom: 14px; width: 100%; overflow: hidden; }
		.f1wms-admin .card .head{ background: var(--head); color: #fff; padding: 10px 12px; display:flex; align-items:center; justify-content:space-between; }
		.f1wms-admin .card .head .title{ font-weight: 800; font-size: 13px; }
		.f1wms-admin .card .body{ padding: 12px; }
		.f1wms-admin .badge{ background: rgba(224,0,120,.10); color: var(--accent); border: 1px solid rgba(224,0,120,.22); padding: 3px 7px; font-weight: 800; font-size: 11px; }
		.f1wms-admin .grid{ display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
		@media (max-width: 1100px){ .f1wms-admin .grid{ grid-template-columns: 1fr; } }
		.f1wms-admin label{ font-weight: 800; display:block; margin-bottom:6px; font-size: 12px; }
		.f1wms-admin input, .f1wms-admin select, .f1wms-admin textarea{ width: 100%; border: 1px solid var(--line); padding: 8px 10px; background: #fff; font-size: 13px; }
		.f1wms-admin textarea{ min-height: 160px; font-family: monospace; }
		.f1wms-admin .btn{ display:inline-flex; align-items:center; justify-content:center; padding: 8px 10px; border: 1px solid rgba(0,0,0,.12); background: #fff; font-weight: 900; font-size: 12px; cursor: pointer; min-width: 140px; }
		.f1wms-admin .btn-primary{ background: var(--accent); color: #fff; border-color: transparent; }
		.f1wms-admin .btn-danger{ border-color: rgba(216,58,58,.35); color: #a00; }
		.f1wms-admin table{ width:100%; border-collapse: collapse; border: 1px solid var(--line); table-layout: fixed; }
		.f1wms-admin th{ text-align:left; background: var(--head); color:#fff; padding: 9px 10px; font-weight:900; font-size: 12px; }
		.f1wms-admin td{ padding: 9px 10px; border-top: 1px solid var(--line); font-size: 13px; }
		.f1wms-admin .muted{ color: var(--muted); }
		.f1wms-admin .actions{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top: 10px; }
		.f1wms-admin .callout{ padding: 10px 12px; background: rgba(224,0,120,.08); border: 1px solid rgba(224,0,120,.30); font-weight: 800; margin-bottom: 12px; }
		.f1wms-admin .kv{ display:grid; grid-template-columns: 220px 1fr; gap: 10px; border: 1px solid var(--line); padding: 10px; background:#fff; }
	</style>

	<div class="f1wms-admin">
		<div class="canvas">

			<?php if ( $tab !== 'converter' ) : ?>

				<?php if ( $notice ) : ?>
					<div class="callout"><?php echo esc_html( $notice ); ?></div>
				<?php endif; ?>

				<div class="card">
					<div class="head"><div class="title">WM-Stand Tools</div><span class="badge">Achtung: irreversibel</span></div>
					<div class="body">
						<p class="muted" style="margin:0 0 10px 0;">Damit kannst du den kompletten WM-Stand löschen (alle Sessions aller Rennen).</p>
						<form method="post" onsubmit="return confirm('Wirklich alles löschen?');" style="margin:0;">
							<?php wp_nonce_field( 'f1wms_admin_save', 'f1wms_nonce' ); ?>
							<input type="hidden" name="f1wms_action" value="reset_all">
							<button class="btn btn-danger" type="submit">WM-Stand komplett zurücksetzen</button>
						</form>
					</div>
				</div>

				<div class="card">
					<div class="head"><div class="title">Namens-Normalisierung (Wikidata)</div><span class="badge"><?php echo esc_html( $dir_count ); ?> Einträge</span></div>
					<div class="body">
						<div class="kv">
							<div><b>Cache Status</b></div>
							<div>Letzter Refresh: <?php echo esc_html( $dir_ts_label ); ?></div>
							<div><b>Aktion</b></div>
							<div>
								<form method="post" style="margin:0;">
									<?php wp_nonce_field( 'f1wms_dir_refresh', 'f1wms_dir_nonce' ); ?>
									<input type="hidden" name="f1wms_dir_action" value="refresh_driver_directory">
									<input type="hidden" name="race_id" value="<?php echo (int)$race_id; ?>">
									<button class="btn btn-primary" type="submit">Fahrer-Referenz aktualisieren</button>
								</form>
							</div>
						</div>
					</div>
				</div>

				<div class="card">
					<div class="head"><div class="title">Rennen auswählen</div></div>
					<div class="body">
						<?php if ( empty( $races ) ) : ?>
							<p>Keine Rennen im F1 Kalender gefunden.</p>
						<?php else : ?>
							<form method="get" style="margin:0;">
								<input type="hidden" name="page" value="f1-wm-stand">
								<select name="race_id" onchange="this.form.submit()" style="max-width:520px;">
									<?php foreach ( $races as $r ) : ?>
										<option value="<?php echo (int)$r['id']; ?>" <?php selected( $race_id, (int)$r['id'] ); ?>><?php echo esc_html( $r['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</form>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $race_id && ! empty( $sessions ) ) : ?>
					<?php foreach ( $sessions as $slug => $cfg ) :
						$schema = F1_WM_Stand::session_schema( $slug );
						$cols = $schema['columns'];
						$raw = F1_WM_Stand::get_session_raw( $race_id, $slug );
						$rows = F1_WM_Stand::get_session_results( $race_id, $slug );
					?>
						<div class="card">
							<div class="head"><div class="title"><?php echo esc_html( $cfg['label'] ); ?></div><span class="badge"><?php echo esc_html( $cfg['type_badge'] ); ?></span></div>
							<div class="body">
								<div class="grid">
									<div>
										<form method="post">
											<?php wp_nonce_field( 'f1wms_admin_save', 'f1wms_nonce' ); ?>
											<input type="hidden" name="f1wms_action" value="import">
											<input type="hidden" name="race_id" value="<?php echo (int)$race_id; ?>">
											<input type="hidden" name="session_slug" value="<?php echo esc_attr( $slug ); ?>">
											<label>Datensatz (Tab-getrennt)</label>
											<textarea name="f1wms_raw" placeholder="Copy-Paste aus Excel/Wikipedia..."><?php echo esc_textarea( $raw ); ?></textarea>
											<div class="actions"><button class="btn btn-primary" type="submit">Datensatz übernehmen</button></div>
										</form>
									</div>
									<div>
										<form method="post" class="f1wms-manual-form">
											<?php wp_nonce_field( 'f1wms_admin_save', 'f1wms_nonce' ); ?>
											<input type="hidden" name="f1wms_action" value="save_manual">
											<input type="hidden" name="race_id" value="<?php echo (int)$race_id; ?>">
											<input type="hidden" name="session_slug" value="<?php echo esc_attr( $slug ); ?>">
											<label>Manuelle Tabelle</label>
											<div style="overflow:auto; max-height: 520px; border: 1px solid var(--line);">
												<table>
													<thead>
														<tr>
															<?php foreach ( $cols as $c ) echo '<th>' . esc_html( $c['label'] ) . '</th>'; ?>
															<th>Del</th>
														</tr>
													</thead>
													<tbody data-next-index="<?php echo (int)max( 1, count( $rows ) + 1 ); ?>">
														<?php if ( empty( $rows ) ) $rows = array( array() ); ?>
														<?php foreach ( $rows as $i => $r ) : ?>
															<tr>
																<?php foreach ( $cols as $c ) :
																	$k = $c['key'];
																	$v = $r[$k] ?? '';
																	$type = ( $k === 'laps' || $k === 'pts' ) ? 'number' : 'text';
																?>
																	<td><input type="<?php echo $type; ?>" name="f1wms_rows[<?php echo $i; ?>][<?php echo $k; ?>]" value="<?php echo esc_attr( $v ); ?>"></td>
																<?php endforeach; ?>
																<td style="text-align:center;"><input type="checkbox" name="f1wms_delete_rows[]" value="<?php echo $i; ?>"></td>
															</tr>
														<?php endforeach; ?>

														<!-- Template Row -->
														<tr class="f1wms-row-template" style="display:none;">
															<?php foreach ( $cols as $c ) :
																$k = $c['key']; $type = ( $k === 'laps' || $k === 'pts' ) ? 'number' : 'text';
															?>
																<td><input type="<?php echo $type; ?>" data-name="f1wms_rows[__i__][<?php echo $k; ?>]" value=""></td>
															<?php endforeach; ?>
															<td style="text-align:center;"><input type="checkbox" data-name="f1wms_delete_rows[]" value=""></td>
														</tr>
													</tbody>
												</table>
											</div>
											<div class="actions">
												<button class="btn" type="button" data-f1wms-add-row>+ Zeile</button>
												<button class="btn btn-primary" type="submit">Manuell speichern</button>
											</div>
										</form>
									</div>
								</div>
								<div class="actions" style="margin-top:12px; justify-content:flex-end;">
									<form method="post" onsubmit="return confirm('Session löschen?');" style="margin:0;">
										<?php wp_nonce_field( 'f1wms_admin_save', 'f1wms_nonce' ); ?>
										<input type="hidden" name="f1wms_action" value="delete_session">
										<input type="hidden" name="race_id" value="<?php echo (int)$race_id; ?>">
										<input type="hidden" name="session_slug" value="<?php echo esc_attr( $slug ); ?>">
										<button class="btn btn-danger" type="submit">Session löschen</button>
									</form>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

			<?php else : ?>

				<!-- Converter Tab -->
				<div class="card">
					<div class="head"><div class="title">HTML zu TSV Converter</div><span class="badge">Tool</span></div>
					<div class="body">
						<p style="max-width:900px; margin-bottom:14px;">
							Kopiere eine HTML-Tabelle (z.B. von formula1.com) in das Feld. Der Converter erkennt das Format und erstellt ein TSV für den Import.
						</p>

						<?php if ( $converter_info ) : ?>
							<div class="callout" style="border-left:4px solid var(--accent);"><?php echo esc_html( $converter_info ); ?></div>
						<?php endif; ?>

						<form method="post">
							<?php wp_nonce_field( 'f1sc_convert', 'f1sc_nonce' ); ?>

							<label>Input (HTML oder Text)</label>
							<textarea name="f1sc_input" style="height:200px;"><?php echo esc_textarea( $input_val ); ?></textarea>

							<div class="actions" style="margin-bottom:20px;">
								<button type="submit" class="btn btn-primary" name="f1sc_do_convert" value="1">Konvertieren</button>
							</div>

							<label>Output (TSV)</label>
							<textarea id="f1sc_output" readonly style="height:200px; background:#f9f9f9;"><?php echo esc_textarea( $converter_output ); ?></textarea>

							<div class="actions">
								<button type="button" class="btn" id="f1sc_copy">Output kopieren</button>
							</div>
						</form>
					</div>
				</div>

				<div class="card">
					<div class="head"><div class="title">Unterstützte Formate</div></div>
					<div class="body">
						<ul style="margin:0; padding-left:20px;">
							<li><b>FP1/FP2/FP3</b> (Pos., No., Driver, Team, Time / Gap, Laps)</li>
							<li><b>Qualifying</b> (Pos., No., Driver, Team, Q1, Q2, Q3, Laps)</li>
							<li><b>Grid</b> (Pos., No., Driver, Team, Time)</li>
							<li><b>Race/Sprint</b> (Pos., No., Driver, Team, Laps, Time / Retired, Pts)</li>
						</ul>
					</div>
				</div>

				<script>
				(function(){
					const btn = document.getElementById('f1sc_copy');
					const out = document.getElementById('f1sc_output');
					if(btn && out){
						btn.addEventListener('click', function(){
							out.select();
							try {
								document.execCommand('copy');
								btn.textContent = 'Kopiert!';
								setTimeout(()=>btn.textContent='Output kopieren', 1500);
							} catch(e){ alert('Konnte nicht kopieren.'); }
						});
					}
				})();
				</script>

			<?php endif; ?>

		</div>
	</div>

	<script>
	(function(){
		function addRow(btn){
			var form = btn.closest('form');
			if (!form) return;
			var tbody = form.querySelector('tbody');
			var tpl = tbody.querySelector('.f1wms-row-template');
			if (!tbody || !tpl) return;
			var idx = parseInt(tbody.getAttribute('data-next-index') || '0', 10);
			var tr = tpl.cloneNode(true);
			tr.style.display = '';
			tr.classList.remove('f1wms-row-template');
			tr.querySelectorAll('[data-name]').forEach(function(el){
				var nm = el.getAttribute('data-name').replace('__i__', String(idx));
				el.setAttribute('name', nm);
				if (el.type === 'checkbox') el.value = String(idx);
			});
			tbody.appendChild(tr);
			tbody.setAttribute('data-next-index', String(idx+1));
		}
		document.querySelectorAll('[data-f1wms-add-row]').forEach(function(btn){
			btn.addEventListener('click', function(){ addRow(btn); });
		});
	})();
	</script>
</div>
