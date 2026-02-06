<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure F1_WM_Stand class is available for helpers
if ( ! class_exists( 'F1_WM_Stand' ) ) return;

$view_mode = ( $mode === 'teams' ) ? 'f1wms-mode-teams' : 'f1wms-mode-drivers';
?>
<div class="f1wms f1wms-champ <?php echo esc_attr( $view_mode ); ?>">
	<div class="f1wms-card">

		<div class="f1wms-toolbar">
			<div style="min-width:0;">
				<h1 class="f1wms-title entry-title"><?php echo esc_html( $title ); ?></h1>
			</div>
		</div>

		<div class="f1wms-shell">
			<?php if ( empty( $contenders ) ) : ?>
				<div class="f1wms-empty">Niemand hat rechnerisch noch eine Chance (oder es sind keine Punkte mehr zu vergeben).</div>
			<?php else : ?>

				<div class="f1wms-scroll" tabindex="0" role="region" aria-label="Tabelle horizontal scrollen">
					<table class="f1wms-table f1wms-champ-table">
						<colgroup>
							<col class="f1wms-col-pos">
							<col class="f1wms-col-name">
							<col>
							<col>
							<col>
							<col>
						</colgroup>
						<thead>
							<tr>
								<th>#</th>
								<th><?php echo ( $mode === 'teams' ) ? 'Team' : 'Fahrer'; ?></th>
								<th class="f1wms-num">Aktuell</th>
								<th class="f1wms-num">Noch möglich</th>
								<th class="f1wms-num">Max. Endstand</th>
								<th class="f1wms-num">Rückstand auf P1</th>
							</tr>
						</thead>
						<tbody>
							<?php $i = 0; foreach ( $contenders as $r ) : $i++; ?>
								<tr>
									<td><?php echo (int)$i; ?></td>
									<td class="f1wms-name">
										<?php
											if ( $mode === 'teams' ) {
												$team_name = F1_WM_Stand::decode_u_escapes( (string)$r['label'] );
												// Team Link Helper if available (assuming F1_WM_Stand has no direct link helper, checking legacy)
												// Legacy used f1wms_champ_team_html. We should implement simple logic or rely on F1_Team_Overview helper if accessible?
												// For now: plain text or simple link if F1_Team_Overview exists.
												// Actually, F1_WM_Stand renders links in standard view. We can reuse F1_WM_Stand::canonicalize_driver_name logic but for teams it's direct.
												// Let's keep it simple: just text for now as in legacy if link helper missing, or implement basic link.
												// Legacy: f1wms_front_team_link.
												// We will output text. If link logic is needed, we'd need to port F1_Team_Overview logic here or duplicate.
												// Wait, F1_WM_Stand standard view DOES have links. But they are generated in frontend/wm-stand-view.php using F1_WM_Stand::canonicalize_driver_name.
												// But for Teams?
												// The F1_WM_Stand class doesn't have public link helpers.
												// Let's stick to text for robustness, or try to emulate the link if easy.
												// The standard view uses F1_Team_Overview logic via `f1wms_front_team_link` legacy function if available.
												// Here we will just output name to be safe.
												echo esc_html( $team_name );
											} else {
												$canon = (string)$r['key'];
												$label = F1_WM_Stand::decode_u_escapes( (string)$r['label'] );
												// Same here. Just output label.
												echo esc_html( $label );
											}
										?>
									</td>

									<td class="f1wms-num"><?php echo (int)$r['points']; ?> P</td>
									<td class="f1wms-num"><?php echo (int)$remaining_total; ?> P</td>
									<td class="f1wms-num"><?php echo (int)$r['max_final']; ?> P</td>
									<td class="f1wms-num"><?php echo (int)$r['behind']; ?> P</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="f1wms-mobile" aria-label="Rechnerische WM-Chancen – mobile Ansicht">
					<?php $i = 0; foreach ( $contenders as $r ) : $i++; ?>
						<?php
							$display_label = F1_WM_Stand::decode_u_escapes( (string)$r['label'] );
						?>
						<details class="f1wms-mcard">
							<summary>
								<span class="f1wms-mpos"><?php echo (int)$i; ?></span>
								<span class="f1wms-mname"><?php echo esc_html( $display_label ); ?></span>
								<span class="f1wms-mmeta"><?php echo (int)$r['points']; ?> P</span>
								<span class="f1wms-mchev" aria-hidden="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
										<path d="M12 5v14M5 12h14"></path>
									</svg>
								</span>
							</summary>

							<div class="f1wms-mbody">
								<div class="f1wms-mgrid">
									<div class="f1wms-mitem">
										<div class="f1wms-mlabel">Aktuell</div>
										<div class="f1wms-mvalue"><?php echo (int)$r['points']; ?> P</div>
									</div>
									<div class="f1wms-mitem">
										<div class="f1wms-mlabel">Rückstand auf P1</div>
										<div class="f1wms-mvalue"><?php echo (int)$r['behind']; ?> P</div>
									</div>

									<div class="f1wms-mitem">
										<div class="f1wms-mlabel">Noch möglich</div>
										<div class="f1wms-mvalue"><?php echo (int)$remaining_total; ?> P</div>
									</div>
									<div class="f1wms-mitem">
										<div class="f1wms-mlabel">Max. Endstand</div>
										<div class="f1wms-mvalue"><?php echo (int)$r['max_final']; ?> P</div>
									</div>
								</div>
							</div>
						</details>
					<?php endforeach; ?>
				</div>

			<?php endif; ?>
		</div>

	</div>
</div>
