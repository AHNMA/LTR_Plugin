<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Placeholder logic
$is_empty = empty($items);
if ($is_empty) {
	for ($i=1; $i<=50; $i++) {
		$items[] = array(
			'user_id'      => 0,
			'display_name' => '—',
			'avatar'       => '',
			'points'       => 0,
			'wins'         => 0,
			'_placeholder' => true,
		);
	}
}

$colsClass = $show_wins ? 'f1tips-lb-cols-4' : 'f1tips-lb-cols-3';
?>
<div class="f1tips-lb-wrap <?php echo esc_attr($colsClass); ?>">
	<div class="f1tips-lb-card">
		<div class="f1tips-lb-head">
			<div class="f1tips-lb-title"><?php echo esc_html($title); ?></div>
			<div class="f1tips-lb-sub"><?php echo 'Saison '.esc_html($year).' · Liga gesamt'; ?></div>
		</div>

		<?php if ($is_empty): ?>
			<div class="f1tips-lb-info">
				Es sind noch keine Tipper oder Ergebnisse vorhanden. Sobald es Daten gibt, erscheint hier automatisch das echte Leaderboard.
			</div>
		<?php endif; ?>

		<div class="f1tips-lb-body">
			<div class="f1tips-lb-list-head">
				<div>#</div>
				<div>Spieler</div>
				<div>Punkte</div>
				<?php if ($show_wins): ?><div>Siege</div><?php endif; ?>
			</div>

			<?php foreach ($items as $i => $it):
				$isPh = !empty($it['_placeholder']);
				$rowClass = $isPh ? 'f1tips-lb-row is-empty' : 'f1tips-lb-row';
			?>
				<div class="<?php echo esc_attr($rowClass); ?>">
					<div><strong><?php echo (int)($i+1); ?></strong></div>

					<div>
						<div class="f1tips-lb-user">
							<?php if (!$isPh && !empty($it['avatar'])): ?>
								<img src="<?php echo esc_url($it['avatar']); ?>" alt="">
							<?php else: ?>
								<span class="f1tips-lb-ava-ph" aria-hidden="true"></span>
							<?php endif; ?>

							<div style="min-width:0;">
								<div class="f1tips-lb-name"><?php echo esc_html($it['display_name']); ?></div>
								<div class="f1tips-lb-muted">
									<?php echo $isPh ? '—' : ('#'.(int)$it['user_id']); ?>
								</div>
							</div>
						</div>
					</div>

					<div><span class="f1tips-lb-points"><?php echo (int)$it['points']; ?></span></div>

					<?php if ($show_wins): ?>
						<div class="f1tips-lb-muted"><?php echo (int)$it['wins']; ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
