<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="f1tips-wrap">
	<div class="f1tips-canvas" data-toast>

		<select class="f1tips-select" data-team-template style="display:none !important;">
			<option value="">Team wählen</option>
			<?php foreach ((array)$teams as $tm): ?>
				<option value="<?php echo (int)$tm->ID; ?>"><?php echo esc_html((string)$tm->post_title); ?></option>
			<?php endforeach; ?>
		</select>

		<div class="f1tips-view" data-view="main">

			<div class="f1tips-grid-top">

				<div class="f1tips-stack">

					<div class="f1tips-card">
						<div class="f1tips-head">
							<div class="title">Spielerübersicht</div>
						</div>
						<div class="f1tips-body">
							<div class="f1tips-playergrid">
								<div class="kv">
									<div class="k">Profilbild</div>
									<div class="v">
										<img class="f1tips-avatar" data-me-avatar alt="" hidden />
									</div>
								</div>

								<div class="kv"><div class="k">Name</div><div class="v" data-me-name>—</div></div>
								<div class="kv"><div class="k">ID</div><div class="v" data-me-id>—</div></div>
								<div class="kv"><div class="k">Position Gesamt</div><div class="v" data-pos-total>—</div></div>
								<div class="kv"><div class="k">Punkte</div><div class="v"><span data-points-total>—</span></div></div>

								<div class="kv"><div class="k">Siege</div><div class="v"><span data-wins-total>—</span></div></div>
							</div>
						</div>
					</div>

				</div>

				<div class="f1tips-side">

					<div class="f1tips-card">
						<div class="f1tips-head">
							<div class="title">Auswahl Rennwochenende</div>
							<span class="f1tips-mut" style="font-weight:800;">&nbsp;</span>
						</div>
						<div class="f1tips-body">
							<div class="f1tips-weekendbar">
								<select class="f1tips-select" data-race-select data-default-race="<?php echo (int)$default_race_id; ?>">
									<?php foreach ($q->posts as $race_id):
										$race_id = (int)$race_id;
										$gp = (string)get_post_meta($race_id, '_f1cal_gp', true);
										$gp = $gp !== '' ? $gp : get_the_title($race_id);
										$tsRace = F1_Tippspiel::get_race_session_start_ts($race_id, 'race');
										$dateLabel = $tsRace ? (' · '.wp_date('d.m.', $tsRace)) : '';
									?>
										<option value="<?php echo (int)$race_id; ?>"><?php echo esc_html($gp.$dateLabel); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<?php if (!is_user_logged_in()): ?>
								<div style="height:10px"></div>
								<div class="f1tips-note">Du musst eingeloggt sein, um Tipps abzugeben.</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="f1tips-card">
						<div class="f1tips-head">
							<div class="title">Wochenend-Punkte</div>
							<span class="f1tips-mut" style="font-weight:800;">&nbsp;</span>
						</div>
						<div class="f1tips-body">
							<div class="f1tips-weekendpoints" data-weekend-points>
								<span class="wp-item is-empty" data-weekend-item="1">
									<span class="wp-lbl" data-weekend-label="1">—</span>
									<span class="f1tips-points" data-weekend-session="1">—</span>
								</span>

								<span class="wp-item is-empty" data-weekend-item="2">
									<span class="wp-lbl" data-weekend-label="2">—</span>
									<span class="f1tips-points" data-weekend-session="2">—</span>
								</span>

								<span class="wp-item is-empty" data-weekend-item="3">
									<span class="wp-lbl" data-weekend-label="3">—</span>
									<span class="f1tips-points" data-weekend-session="3">—</span>
								</span>

								<span class="wp-item is-empty" data-weekend-item="4">
									<span class="wp-lbl" data-weekend-label="4">—</span>
									<span class="f1tips-points" data-weekend-session="4">—</span>
								</span>

								<span class="wp-item wp-total">
									<span class="wp-lbl">Total</span>
									<span class="f1tips-points" data-weekend-total>—</span>
								</span>
							</div>
						</div>
					</div>

				</div>

			</div>

			<?php foreach ($q->posts as $race_id):
				$race_id = (int)$race_id;
				$gp = (string)get_post_meta($race_id, '_f1cal_gp', true);
				$gp = $gp !== '' ? $gp : get_the_title($race_id);

				$is_sprint_weekend = false;
				$ts_sq     = (int) F1_Tippspiel::get_race_session_start_ts($race_id, 'sq');
				$ts_sprint = (int) F1_Tippspiel::get_race_session_start_ts($race_id, 'sprint');
				$is_sprint_weekend = ($ts_sq > 0 || $ts_sprint > 0);

				$sessions = $is_sprint_weekend
					? array(
						array('slug'=>'sq','label'=>'Sprint-Qualifying','topN'=>4),
						array('slug'=>'sprint','label'=>'Sprint','topN'=>8),
						array('slug'=>'quali','label'=>'Qualifying','topN'=>4),
						array('slug'=>'race','label'=>'Rennen','topN'=>8),
					)
					: array(
						array('slug'=>'quali','label'=>'Qualifying','topN'=>4),
						array('slug'=>'race','label'=>'Rennen','topN'=>8),
					);
			?>
				<div data-race="<?php echo (int)$race_id; ?>" hidden>

					<div class="f1tips-sessions">
						<?php foreach ($sessions as $s):
							$ts = F1_Tippspiel::get_race_session_start_ts($race_id, $s['slug']);
							if (!$ts) continue;

							$state = F1_Tippspiel::get_session_state($race_id, $s['slug']);
							$isLocked = F1_Tippspiel::is_locked($race_id, $s['slug']);

							$badge_state = 'open';
							$badge_label = 'Offen';
							if ($state === 'ended') { $badge_state = 'result'; $badge_label = 'Ergebnis'; }
							else if ($isLocked)     { $badge_state = 'closed'; $badge_label = 'Geschlossen'; }
						?>
							<div class="f1tips-session">
								<div class="f1tips-head">
									<div class="title"><?php echo esc_html($s['label']); ?></div>

									<div class="f1tips-mut" style="font-weight:900;">
										<span class="f1tips-state"
											  data-session-badge="<?php echo esc_attr($s['slug']); ?>"
											  data-state="<?php echo esc_attr($badge_state); ?>">
											<?php echo esc_html($badge_label); ?>
										</span>
										· <?php echo esc_html(wp_date('d.m.Y H:i', $ts)); ?>
									</div>
								</div>

								<div class="f1tips-body">
									<div class="f1tips-picks-grid">
										<?php for ($i=1;$i<=$s['topN'];$i++): ?>
											<div>
												<div class="f1tips-mut" style="font-weight:900; margin:0 0 6px;">P<?php echo $i; ?></div>
												<select class="f1tips-select" data-session="<?php echo esc_attr($s['slug']); ?>" <?php disabled($isLocked); ?>>
													<option value="">Fahrer wählen</option>
													<?php foreach ($drivers as $d): ?>
														<option value="<?php echo (int)$d->ID; ?>"><?php echo esc_html($d->post_title); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										<?php endfor; ?>
									</div>

									<div style="height:10px"></div>

									<div class="f1tips-session-actions">
										<div class="f1tips-session-actions-left">
											<button class="f1tips-btn f1tips-btn-primary"
													data-save="<?php echo esc_attr($s['slug']); ?>"
													data-topn="<?php echo (int)$s['topN']; ?>"
													<?php disabled($isLocked || !is_user_logged_in()); ?>>
												<span class="f1tips-btn-label">Tipp speichern</span>
											</button>

											<button class="f1tips-btn<?php echo ($badge_state === 'open') ? ' f1tips-hidden' : ''; ?>"
													type="button"
													data-open-others="<?php echo esc_attr($s['slug']); ?>"
													data-session-label="<?php echo esc_attr($gp.' · '.$s['label']); ?>">
												<span class="f1tips-btn-label">Was haben andere getippt?</span>
											</button>
										</div>

										<div class="f1tips-session-pointsline"
											 data-my-session-points="<?php echo esc_attr($s['slug']); ?>"
											 hidden>
											Punkte in dieser Session: <span class="f1tips-points">— P</span>
										</div>
									</div>

								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="f1tips-bonus" data-bonus-card hidden>
						<div data-bonus-body>
							<div class="f1tips-mut">Lade Bonusfragen …</div>
						</div>
					</div>

				</div>
			<?php endforeach; ?>

		</div>

		<div class="f1tips-view" data-view="others" hidden>
			<div class="f1tips-card">
				<div class="f1tips-head">
					<div class="title" data-others-title>Andere Tipps</div>
					<button class="f1tips-backlink" type="button" data-others-back>
						<span aria-hidden="true">←</span> <span>Zurück</span>
					</button>
				</div>
				<div class="f1tips-body" data-others-body>
					<div class="f1tips-mut">Lade …</div>
				</div>
			</div>
		</div>

	</div>
</div>
