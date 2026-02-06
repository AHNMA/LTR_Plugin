<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Helper for View
if ( ! function_exists( 'f1wms_view_decode' ) ) {
	function f1wms_view_decode( $s ) { return F1_WM_Stand::decode_u_escapes( $s ); }
}

if ( ! empty( $race_id ) ) {
	/* =========================================
	   DETAIL VIEW (RACE / SESSION)
	   ========================================= */
	$back_url = remove_query_arg( array( 'race', 'session' ), $page_url );
	$current_label = $sessions[$session]['label'] ?? 'Session';
	$dd_id = 'f1wms-dd-' . $race_id . '-' . wp_rand( 1000, 9999 );
	?>
	<div class="f1wms-card">
		<div class="f1wms-toolbar">
			<div class="f1wms-headleft">
				<div class="f1wms-title"><?php echo esc_html( f1wms_view_decode( $race_title ) ); ?></div>
				<div class="f1wms-sessionwrap f1wms-dd" data-f1wms-dd>
					<button type="button" class="f1wms-dd__btn" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?php echo esc_attr( $dd_id ); ?>">
						<span class="f1wms-dd__btnlabel"><?php echo esc_html( $current_label ); ?></span>
						<span class="f1wms-dd__chev" aria-hidden="true">▾</span>
					</button>
					<div class="f1wms-dd__menu" id="<?php echo esc_attr( $dd_id ); ?>" role="listbox" hidden>
						<?php foreach ( $sessions as $slug => $cfg ) :
							$u = add_query_arg( array( 'race' => $race_id, 'session' => $slug ), $page_url );
							$is_sel = ( $slug === $session );
						?>
							<button type="button" class="f1wms-dd__opt <?php echo $is_sel ? 'is-selected is-active' : ''; ?>" data-url="<?php echo esc_url( $u ); ?>">
								<?php echo esc_html( $cfg['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<select class="f1wms-sessionselect" onchange="if(this.value)window.location.href=this.value;">
						<?php foreach ( $sessions as $slug => $cfg ) :
							$u = add_query_arg( array( 'race' => $race_id, 'session' => $slug ), $page_url );
						?>
							<option value="<?php echo esc_url( $u ); ?>" <?php selected( $slug, $session ); ?>><?php echo esc_html( $cfg['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<a class="f1wms-btn" href="<?php echo esc_url( $back_url ); ?>">← Zurück</a>
		</div>

		<?php if ( empty( $session_rows ) ) : ?>
			<div class="f1wms-empty">Für diese Session sind noch keine Daten hinterlegt.</div>
		<?php else :
			$cols = $schema['columns'];
			// Filter visible cols
			$visible = array();
			foreach ( $cols as $c ) {
				$k = $c['key'];
				$must = in_array( $k, array( 'pos', 'driver', 'team' ), true );
				$has = false;
				if ( ! $must ) {
					foreach ( $session_rows as $r ) {
						if ( isset( $r[$k] ) && trim( (string)$r[$k] ) !== '' ) { $has = true; break; }
					}
				}
				if ( $must || $has ) $visible[] = $c;
			}
		?>
			<div class="f1wms-scroll" tabindex="0" role="region">
				<table class="f1wms-table f1wms-session-table">
					<thead>
						<tr>
							<?php foreach ( $visible as $c ) echo '<th>' . esc_html( $c['label'] ) . '</th>'; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $session_rows as $r ) : ?>
							<tr>
								<?php foreach ( $visible as $c ) :
									$k = $c['key'];
									$val = $r[$k] ?? '';
									if ( is_string( $val ) ) $val = f1wms_view_decode( $val );
									if ( $k === 'driver' ) $val = F1_WM_Stand::canonicalize_driver_name( $val );
								?>
									<td><?php echo esc_html( $val ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

<?php } else {
	/* =========================================
	   STANDINGS VIEW
	   ========================================= */
	if ( empty( $races ) ) {
		echo '<div class="f1wms-empty">Noch keine WM-Daten vorhanden.</div>';
		return;
	}
	$race_count = max( 1, count( $races ) );
	?>
	<div class="f1wms-card">
		<div class="f1wms-shell">
			<div class="f1wms-scroll" data-f1wms-scroll tabindex="0" role="region">
				<table class="f1wms-table f1wms-standings" style="--raceCount: <?php echo $race_count; ?>;">
					<colgroup>
						<col class="f1wms-col-pos"><col class="f1wms-col-name"><col class="f1wms-col-pts">
						<?php foreach ( $races as $r ) echo '<col class="f1wms-col-race">'; ?>
						<col class="f1wms-col-end">
					</colgroup>
					<thead>
						<tr>
							<th>#</th><th><?php echo esc_html( $name_label ); ?></th><th>PTS</th>
							<?php foreach ( $races as $race ) :
								$rid = $race['id'];
								$u = add_query_arg( array( 'race' => $rid ), $page_url );
								$flag = $race['flag_url'];
							?>
								<th>
									<a class="f1wms-flaglink" href="<?php echo esc_url( $u ); ?>" title="<?php echo esc_attr( $race['title'] ); ?>">
										<?php if ( $flag ) : ?><img class="f1wms-flag" src="<?php echo esc_url( $flag ); ?>"><?php else : ?><span class="f1wms-dot">•</span><?php endif; ?>
									</a>
								</th>
							<?php endforeach; ?>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $i => $row ) :
							$pos = $i + 1;
							$total = $row['total'] ?? 0;
							$by_race = $row['by_race'] ?? array();
							$name = ( $mode === 'teams' ) ? ( $row['team'] ?? '' ) : ( $row['driver'] ?? '' );
							$name = f1wms_view_decode( $name );
						?>
							<tr>
								<td><?php echo $pos; ?></td>
								<td class="f1wms-name"><?php echo esc_html( $name ); ?></td>
								<td class="f1wms-pts"><b><?php echo $total; ?></b></td>
								<?php foreach ( $races as $race ) :
									$rid = $race['id'];
									$pt = $by_race[$rid] ?? null;
								?>
									<td><?php echo ( $pt === null ) ? '-' : $pt; ?></td>
								<?php endforeach; ?>
								<td></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Mobile Card View -->
			<div class="f1wms-mobile">
				<?php foreach ( $rows as $i => $row ) :
					$pos = $i + 1;
					$total = $row['total'] ?? 0;
					$by_race = $row['by_race'] ?? array();
					$name = ( $mode === 'teams' ) ? ( $row['team'] ?? '' ) : ( $row['driver'] ?? '' );
					$name = f1wms_view_decode( $name );
				?>
					<details class="f1wms-mrow">
						<summary>
							<span class="f1wms-mpos"><?php echo $pos; ?></span>
							<span class="f1wms-mname"><span><?php echo esc_html( $name ); ?></span></span>
							<span class="f1wms-mpts"><?php echo $total; ?> P</span>
							<span class="f1wms-mchev"><svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M12 5v14M5 12h14"></path></svg></span>
						</summary>
						<div class="f1wms-mbody">
							<div class="f1wms-mgrid">
								<?php foreach ( $races as $race ) :
									$rid = $race['id'];
									$u = add_query_arg( array( 'race' => $rid ), $page_url );
									$pt = $by_race[$rid] ?? null;
									$val = ( $pt === null ) ? '-' : $pt;
									$flag = $race['flag_url'];
								?>
									<a class="f1wms-mevent" href="<?php echo esc_url( $u ); ?>">
										<?php if ( $flag ) : ?><img class="f1wms-flag" src="<?php echo esc_url( $flag ); ?>"><?php else : ?><span class="f1wms-dot">•</span><?php endif; ?>
										<span class="f1wms-mevent__pts"><?php echo $val; ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php } ?>
