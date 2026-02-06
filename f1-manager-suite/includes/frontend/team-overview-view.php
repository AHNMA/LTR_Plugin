<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="f1ov-wrap">
	<?php foreach ( $data as $team ) : ?>
		<div class="f1ov-team" style="--accent: <?php echo esc_attr( $team['accent'] ); ?>; --head: <?php echo esc_attr( $team['head'] ); ?>;">

			<div class="f1ov-head">
				<div class="f1ov-head__inner">
					<div class="f1ov-head-left">
						<div class="f1ov-teamlogo" aria-hidden="true">
							<?php if ( $team['logo'] ) : ?>
								<img decoding="async" loading="lazy" src="<?php echo esc_url( $team['logo'] ); ?>" alt="<?php echo esc_attr( $team['name'] ); ?> Logo">
							<?php else : ?>
								<span style="font-weight:900; font-size:12px; opacity:.8; color:#202020; background:#fff; padding:2px 6px;">—</span>
							<?php endif; ?>
						</div>
						<div class="f1ov-teamname"><?php echo esc_html( $team['name'] ); ?></div>
					</div>
					<?php echo $team['socials']; ?>
				</div>
			</div>

			<div class="f1ov-body">
				<div class="f1ov-drivers">
					<?php foreach ( $team['drivers'] as $d ) : ?>
						<div class="f1ov-driver">
							<a class="f1ov-cardlink" href="<?php echo esc_url( $d['link'] ); ?>" aria-label="<?php echo esc_attr( $d['name'] ); ?>"></a>

							<div class="f1ov-driver-media">
								<div class="f1ov-avatar">
									<?php if ( $d['img'] ) : ?>
										<img decoding="async" loading="lazy" src="<?php echo esc_url( $d['img'] ); ?>" alt="<?php echo esc_attr( $d['name'] ); ?>">
									<?php else : ?>
										<div class="f1ov-avatar--empty">Kein Bild</div>
									<?php endif; ?>
								</div>
								<?php echo $d['socials_std']; ?>
							</div>

							<div class="f1ov-driver-content">
								<div class="f1ov-driverhead">
									<div class="f1ov-driverhead-left">
										<h3 class="f1ov-drivername">
											<?php echo esc_html( $d['name'] ); ?>
											<?php if ( $d['flag'] ) : ?>
												<span class="f1ov-flag"><img decoding="async" loading="lazy" src="<?php echo esc_url( $d['flag'] ); ?>" alt=""></span>
											<?php endif; ?>
										</h3>
									</div>
									<?php echo $d['socials_head']; ?>
								</div>

								<?php echo $d['socials_below']; ?>

								<?php if ( ! empty( $d['meta_rows'] ) ) : ?>
									<ul class="f1ov-driver-meta">
										<?php foreach ( $d['meta_rows'] as $r ) : ?>
											<li>
												<span class="f1ov-label"><?php echo esc_html( $r[0] ); ?></span>
												<span class="f1ov-val"><?php echo esc_html( $r[1] ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="f1ov-teaminfo">
					<a class="f1ov-cardlink" href="<?php echo esc_url( $team['link'] ); ?>" aria-label="<?php echo esc_attr( $team['name'] ); ?>"></a>

					<div>
						<?php if ( $team['car'] ) : ?>
							<div class="f1ov-car">
								<img decoding="async" loading="lazy" src="<?php echo esc_url( $team['car'] ); ?>" alt="<?php echo esc_attr( $team['name'] ); ?> Auto">
							</div>
						<?php endif; ?>
					</div>

					<div>
						<?php if ( ! empty( $team['meta_rows'] ) ) : ?>
							<ul class="f1ov-team-meta">
								<?php foreach ( $team['meta_rows'] as $r ) : ?>
									<li>
										<span class="f1ov-label"><?php echo esc_html( $r[0] ); ?></span>
										<span class="f1ov-val"><?php echo esc_html( $r[1] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
