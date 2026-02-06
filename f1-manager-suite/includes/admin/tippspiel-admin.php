<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( F1TIPS_CAPABILITY ) ) {
	wp_die( 'Keine Berechtigung.' );
}

global $wpdb;
$t = F1_Tippspiel::tables();

$season_id = F1_Tippspiel::get_active_season_id();
$year      = F1_Tippspiel::get_year_for_season_id($season_id);
F1_Tippspiel::ensure_default_rules_by_season($season_id);
$league_id = F1_Tippspiel::single_league_id($season_id);

$tab = sanitize_key($_GET['tab'] ?? 'rounds');
if (!in_array($tab, array('rounds','rules','bonus','results','backups'), true)) $tab = 'rounds';

$backup_json_out = '';

$drivers = get_posts(array(
	'post_type' => 'f1_driver',
	'post_status' => 'publish',
	'posts_per_page' => -1,
	'orderby' => 'title',
	'order' => 'ASC',
));
$drivers = array_values(array_filter($drivers, function($p){
	return F1_Tippspiel::driver_is_available($p->ID);
}));

$teams_q = new WP_Query(array(
	'post_type'      => 'f1_team',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => array('menu_order' => 'ASC', 'ID' => 'ASC'),
	'no_found_rows'  => true,
));
$teams = array();
foreach ((array)$teams_q->posts as $p) {
	if (is_object($p) && !empty($p->ID) && F1_Tippspiel::team_is_available($p->ID)) $teams[] = $p;
}

$rounds = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM {$t['rounds']} WHERE season_id=%d ORDER BY id ASC",
	$season_id
), ARRAY_A);

$rq = F1_Tippspiel::get_rules($season_id, 'quali');
$rr = F1_Tippspiel::get_rules($season_id, 'race');

$recentRounds = $wpdb->get_results($wpdb->prepare(
	"SELECT * FROM {$t['rounds']} WHERE season_id=%d ORDER BY id DESC LIMIT 30",
	$season_id
), ARRAY_A);

/* --- POST HANDLER --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	if (isset($_POST['f1tips_sync_rounds']) && check_admin_referer('f1tips_sync_rounds')) {
		$q = new WP_Query(array(
			'post_type' => 'f1_race',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'no_found_rows' => true,
		));
		foreach ($q->posts as $race_id) F1_Tippspiel::ensure_round($season_id, (int)$race_id);
		echo '<div class="notice notice-success"><p>Runden wurden synchronisiert.</p></div>';
		$rounds = $wpdb->get_results($wpdb->get_var($wpdb->prepare(
			"SELECT * FROM {$t['rounds']} WHERE season_id=%d ORDER BY id ASC",
			$season_id
		)));
	}

	if (isset($_POST['f1tips_save_session_states']) && check_admin_referer('f1tips_save_session_states')) {
		$states = isset($_POST['states']) && is_array($_POST['states']) ? $_POST['states'] : array();
		$topMap = array('quali'=>4,'sq'=>4,'sprint'=>8,'race'=>8);
		$okCount = 0; $errCount = 0; $triggerRebuild = false;

		foreach ($states as $race_post_id => $sessionStates) {
			$race_post_id = (int)$race_post_id;
			if ($race_post_id <= 0 || !is_array($sessionStates)) continue;

			foreach ($sessionStates as $slug => $state) {
				$slug  = sanitize_key($slug);
				$state = sanitize_key($state);
				$ts = F1_Tippspiel::get_race_session_start_ts($race_post_id, $slug);
				if (!$ts) continue;

				$prevState = F1_Tippspiel::get_session_state($race_post_id, $slug);

				if ($state === 'ended') {
					$topN = isset($topMap[$slug]) ? (int)$topMap[$slug] : 0;
					if (!$topN || !F1_Tippspiel::wm_has_result($race_post_id, $slug, $topN)) {
						$errCount++;
						continue;
					}
				}

				$ok = F1_Tippspiel::set_session_state($race_post_id, $slug, $state);
				if ($ok) {
					$okCount++;
					if ($prevState !== $state && ($prevState === 'ended' || $state === 'ended')) {
						$triggerRebuild = true;
					}
				} else {
					$errCount++;
				}
			}
		}
		if ($okCount > 0) echo '<div class="notice notice-success"><p>Session-Status gespeichert ('.$okCount.' Änderungen).</p></div>';
		if ($errCount > 0) echo '<div class="notice notice-warning"><p>Einige Änderungen wurden übersprungen.</p></div>';

		if ($triggerRebuild) {
			F1_Tippspiel::rebuild_scores_cache($season_id, $league_id);
			echo '<div class="notice notice-success"><p>Leaderboard Cache automatisch neu berechnet.</p></div>';
		}
	}

	if (isset($_POST['f1tips_save_rules']) && check_admin_referer('f1tips_save_rules')) {
		$sets = array(
			'quali' => array(
				'exact' => array_map('intval', (array)($_POST['quali_exact'] ?? array())),
				'wrong_mode' => sanitize_key($_POST['quali_wrong_mode'] ?? 'absolute'),
				'wrong_points' => (int)($_POST['quali_wrong_points'] ?? 1),
				'rel_penalty' => (int)($_POST['quali_rel_penalty'] ?? 1),
			),
			'race' => array(
				'exact' => array_map('intval', (array)($_POST['race_exact'] ?? array())),
				'wrong_mode' => sanitize_key($_POST['race_wrong_mode'] ?? 'absolute'),
				'wrong_points' => (int)($_POST['race_wrong_points'] ?? 1),
				'rel_penalty' => (int)($_POST['race_rel_penalty'] ?? 1),
			),
		);

		foreach ($sets as $type => $cfg) {
			$cfg['exact'] = array_values(array_filter($cfg['exact'], function($v){ return $v >= 0; }));
			if ($type === 'quali') $cfg['exact'] = array_slice(array_pad($cfg['exact'], 4, 0), 0, 4);
			if ($type === 'race')  $cfg['exact'] = array_slice(array_pad($cfg['exact'], 8, 0), 0, 8);

			$now = F1_Tippspiel::now_mysql();
			$exists = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$t['rules']} WHERE season_id=%d AND session_type=%s",
				$season_id, $type
			));

			$row = array(
				'season_id' => $season_id,
				'session_type' => $type,
				'exact_points_json' => wp_json_encode($cfg['exact']),
				'wrong_mode' => in_array($cfg['wrong_mode'], array('absolute','relative'), true) ? $cfg['wrong_mode'] : 'absolute',
				'wrong_points' => (int)$cfg['wrong_points'],
				'rel_penalty' => (int)$cfg['rel_penalty'],
				'updated_at' => $now,
			);
			if ($exists) $wpdb->update($t['rules'], $row, array('id'=>$exists));
			else { $row['created_at']=$now; $wpdb->insert($t['rules'], $row); }
		}
		echo '<div class="notice notice-success"><p>Punkteregeln gespeichert.</p></div>';
		$rq = F1_Tippspiel::get_rules($season_id, 'quali');
		$rr = F1_Tippspiel::get_rules($season_id, 'race');
	}

	if (isset($_POST['f1tips_bonus_preset_action']) && check_admin_referer('f1tips_bonus_preset_action')) {
		$preset_key = sanitize_key($_POST['preset_key'] ?? '');
		$do = sanitize_key($_POST['do'] ?? '');
		$defs = F1_Tippspiel::bonus_preset_definitions();

		if (!isset($defs[$preset_key])) {
			echo '<div class="notice notice-error"><p>Ungültige Bonus-Aktion.</p></div>';
		} else {
			$prevQ = F1_Tippspiel::bonus_get_preset_question($season_id, $league_id, $preset_key);
			$qid = $prevQ ? (int)($prevQ['id'] ?? 0) : 0;

			$prev_scored = (is_array($prevQ) ? F1_Tippspiel::bonus_is_scored_row($prevQ) : false);
			$prev_points = (int)($prevQ['points'] ?? 0);
			$prev_correct = trim((string)($prevQ['correct_value'] ?? ''));
			$points = (int)($_POST['points'] ?? 10);
			if ($points < 0) $points = 0;

			$qt = (string)$defs[$preset_key]['question_text'];
			$type = (string)$defs[$preset_key]['question_type'];
			$needRebuild = false;

			if ($do === 'activate') {
				if ($qid > 0) {
					$wpdb->update($t['bonus_q'], array(
						'points' => $points, 'status' => 'open', 'closes_at' => null, 'correct_value' => null, 'revealed_at' => null, 'league_id' => $league_id,
					), array('id'=>$qid, 'season_id'=>$season_id), array('%d','%s','%s','%s','%s','%d'), array('%d','%d'));
				} else {
					$wpdb->insert($t['bonus_q'], array(
						'season_id' => $season_id, 'league_id' => $league_id, 'question_text' => $qt, 'question_type' => $type,
						'points' => $points, 'options_json' => null, 'closes_at' => null, 'status' => 'open', 'correct_value' => null,
						'revealed_at' => null, 'created_by' => get_current_user_id(), 'created_at' => F1_Tippspiel::now_mysql(),
					), array('%d','%d','%s','%s','%d','%s','%s','%s','%s','%s','%d','%s'));
				}
				if ($prev_scored) $needRebuild = true;
				echo '<div class="notice notice-success"><p>Bonusfrage aktiviert.</p></div>';
			}
			elseif ($do === 'lock') {
				if ($qid <= 0) {
					echo '<div class="notice notice-error"><p>Kann nicht sperren: Bonusfrage ist nicht aktiviert.</p></div>';
				} else {
					$wpdb->update($t['bonus_q'], array(
						'points' => $points, 'status' => 'closed', 'closes_at' => null,
					), array('id'=>$qid, 'season_id'=>$season_id), array('%d','%s','%s'), array('%d','%d'));
					if ($prev_scored) $needRebuild = true;
					echo '<div class="notice notice-success"><p>Bonusfrage gesperrt.</p></div>';
				}
			}
			elseif ($do === 'reveal') {
				if ($qid <= 0) {
					echo '<div class="notice notice-error"><p>Kann nicht auflösen: Bonusfrage ist nicht aktiviert.</p></div>';
				} else {
					$correct_value = '';
					if ($type === 'driver') {
						$correct_driver_id = (int)($_POST['correct_driver_id'] ?? 0);
						if ($correct_driver_id <= 0 || !F1_Tippspiel::driver_is_available($correct_driver_id)) {
							echo '<div class="notice notice-error"><p>Auflösen nicht möglich: Bitte einen gültigen Fahrer auswählen.</p></div>';
						} else {
							$correct_value = (string)$correct_driver_id;
						}
					} else {
						$correct_team_id = (int)($_POST['correct_team_id'] ?? 0);
						if ($correct_team_id <= 0 || !F1_Tippspiel::team_is_available($correct_team_id)) {
							echo '<div class="notice notice-error"><p>Auflösen nicht möglich: Bitte ein gültiges Team auswählen.</p></div>';
						} else {
							$correct_value = (string)$correct_team_id;
						}
					}

					if ($correct_value !== '') {
						$wpdb->update($t['bonus_q'], array(
							'points' => $points, 'status' => 'revealed', 'closes_at' => null,
							'correct_value' => $correct_value, 'revealed_at' => F1_Tippspiel::now_mysql(),
						), array('id'=>$qid, 'season_id'=>$season_id), array('%d','%s','%s','%s','%s'), array('%d','%d'));

						$needRebuild = true;
						if ($prev_scored) {
							if ($points !== $prev_points) $needRebuild = true;
							if ($correct_value !== $prev_correct) $needRebuild = true;
						}
						echo '<div class="notice notice-success"><p>Bonusfrage aufgelöst.</p></div>';
					}
				}
			}
			elseif ($do === 'delete') {
				if ($qid <= 0) {
					echo '<div class="notice notice-error"><p>Kann nicht löschen: Bonusfrage ist nicht aktiviert.</p></div>';
				} else {
					F1_Tippspiel::bonus_delete_question($qid);
					if ($prev_scored) $needRebuild = true;
					echo '<div class="notice notice-success"><p>Bonusfrage gelöscht.</p></div>';
				}
			} else {
				echo '<div class="notice notice-error"><p>Unbekannte Bonus-Aktion.</p></div>';
			}

			if ($needRebuild) {
				F1_Tippspiel::rebuild_scores_cache($season_id, $league_id);
				echo '<div class="notice notice-success"><p>Leaderboard Cache automatisch neu berechnet.</p></div>';
			}
		}
	}

	if (isset($_POST['f1tips_save_result']) && check_admin_referer('f1tips_save_result')) {
		$round_id = (int)($_POST['round_id'] ?? 0);
		$session = sanitize_key($_POST['session_slug'] ?? '');
		$topN = (int)($_POST['topN'] ?? 0);
		$ids = array_map('intval', (array)($_POST['driver_ids'] ?? array()));
		$ids = array_slice(array_values($ids), 0, $topN);

		foreach ($ids as $did) {
			if (!F1_Tippspiel::driver_is_available($did)) {
				echo '<div class="notice notice-error"><p>Ungültiger Fahrer im Ergebnis.</p></div>';
				return;
			}
		}

		if ($round_id && $session && $topN && count($ids)===$topN && F1_Tippspiel::validate_tip_unique($ids)) {
			F1_Tippspiel::upsert_result_override($round_id, $session, $ids, 'manual');
			echo '<div class="notice notice-success"><p>Ergebnis gespeichert (Override).</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>Ungültiges Ergebnis.</p></div>';
		}
	}

	if (isset($_POST['f1tips_rebuild_cache']) && check_admin_referer('f1tips_rebuild_cache')) {
		F1_Tippspiel::rebuild_scores_cache($season_id, $league_id);
		echo '<div class="notice notice-success"><p>Leaderboard Cache neu berechnet.</p></div>';
	}

	if (isset($_POST['f1tips_backup_export']) && check_admin_referer('f1tips_backup_export')) {
		$payload = F1_Tippspiel::backup_export_payload_single($season_id, $league_id);
		if ($payload) {
			$backup_json_out = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			echo '<div class="notice notice-success"><p>Backup erstellt.</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>Backup konnte nicht erstellt werden.</p></div>';
		}
	}

	if (isset($_POST['f1tips_backup_import']) && check_admin_referer('f1tips_backup_import')) {
		$json = (string)($_POST['backup_json'] ?? '');
		$mode = sanitize_key($_POST['import_mode'] ?? 'merge');
		$payload = json_decode($json, true);
		if (!is_array($payload)) {
			echo '<div class="notice notice-error"><p>Ungültiges JSON.</p></div>';
		} else {
			$res = F1_Tippspiel::backup_import_payload_single($season_id, $league_id, $payload, $mode);
			if (!empty($res['ok'])) {
				echo '<div class="notice notice-success"><p>'.esc_html((string)$res['message']).' (Importierte Tipps: '.(int)($res['imported_tips'] ?? 0).')</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>'.esc_html((string)($res['message'] ?? 'Import fehlgeschlagen.')).'</p></div>';
			}
		}
	}

	if (isset($_POST['f1tips_reset']) && check_admin_referer('f1tips_reset')) {
		$mode = sanitize_key($_POST['reset_mode'] ?? 'tips');
		$res = F1_Tippspiel::reset_single_season($season_id, $league_id, $mode);
		if (!empty($res['ok'])) {
			echo '<div class="notice notice-success"><p>Reset erledigt.</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>Reset fehlgeschlagen.</p></div>';
		}
	}
}

/* --- ADMIN CSS --- */
$is_our_page = (is_admin() && isset($_GET['page']) && $_GET['page'] === 'f1-tippspiel');
?>
<style>
	<?php if ($is_our_page): ?>
	.toplevel_page_f1-tippspiel .wrap{ max-width:none !important; width:100% !important; }
	.toplevel_page_f1-tippspiel #wpbody-content{ max-width:none !important; width:100% !important; }
	.toplevel_page_f1-tippspiel .card{
		max-width:none !important;
		width:100% !important;
		margin-left:0 !important;
		margin-right:0 !important;
	}
	<?php endif; ?>

	.f1tips-admin{
		--canvas: <?php echo esc_attr(F1TIPS_CANVAS); ?>;
		--card: #ffffff;
		--head: <?php echo esc_attr(F1TIPS_HEAD); ?>;
		--accent: <?php echo esc_attr(F1TIPS_ACCENT); ?>;
		--text: #111111;
		--muted: rgba(17,17,17,.65);
		--line: rgba(0,0,0,.08);

		color: var(--text);
		font-size: 13px;
		line-height: 1.45;
		-webkit-font-smoothing: antialiased;
		-moz-osx-font-smoothing: grayscale;
		text-rendering: geometricPrecision;
	}
	.f1tips-admin *{ box-sizing: border-box; }

	.f1tips-admin .canvas{
		background: var(--canvas);
		padding: 14px 20px;
		margin: 0;
		width: 100%;
		max-width: none;
	}

	.f1tips-admin .card{
		background: var(--card);
		border: 1px solid var(--line);
		box-shadow: 0 8px 24px rgba(0,0,0,.06);
		overflow: hidden;
		margin-bottom: 14px;
		max-width: none;
		width: 100%;
	}
	.f1tips-admin .card .head{
		background: var(--head);
		color: #fff;
		padding: 10px 12px;
		display:flex; align-items:center; justify-content:space-between;
		gap: 10px;
	}
	.f1tips-admin .card .head .title{
		font-weight: 800;
		letter-spacing:.2px;
		font-size: 13px;
	}
	.f1tips-admin .card .body{ padding: 12px; }

	.f1tips-admin .badge{
		display:inline-flex; align-items:center; gap:6px;
		background: rgba(224,0,120,.10);
		color: var(--accent);
		border: 1px solid rgba(224,0,120,.22);
		padding: 3px 7px;
		font-weight: 800;
		font-size: 11px;
		white-space: nowrap;
	}

	.f1tips-admin .grid{ display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
	.f1tips-admin label{ font-weight: 800; display:block; margin-bottom:6px; font-size: 12px; }

	.f1tips-admin input[type="text"],
	.f1tips-admin input[type="number"],
	.f1tips-admin select,
	.f1tips-admin textarea{
		width: 100%;
		border: 1px solid var(--line);
		padding: 8px 10px;
		background: #fff;
		outline: none;
		font-size: 13px;
		line-height: 1.2;
	}

	.f1tips-admin .btn{
		display:inline-flex; align-items:center; justify-content:center;
		padding: 8px 10px;
		border: 1px solid rgba(0,0,0,.12);
		background: #fff;
		font-weight: 900;
		font-size: 12px;
		cursor: pointer;
		text-decoration:none;
		min-width: 140px;
		white-space: nowrap;
	}
	.f1tips-admin .btn-primary{
		background: var(--accent);
		color: #fff;
		border-color: rgba(0,0,0,.0);
		min-width: 200px;
	}
	.f1tips-admin .btn-danger{
		border-color: rgba(216,58,58,.35);
		color: #a00;
		background: #fff;
	}
	.f1tips-admin .btn:hover{ filter: brightness(0.98); }

	.f1tips-admin table{
		width:100%;
		border-collapse: collapse;
		border: 1px solid var(--line);
		table-layout: fixed;
	}
	.f1tips-admin thead th{
		text-align:left;
		background: var(--head);
		color:#fff;
		padding: 9px 10px;
		font-weight:900;
		font-size: 12px;
		vertical-align: top;
		overflow:hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.f1tips-admin tbody td{
		padding: 9px 10px;
		border-top: 1px solid var(--line);
		vertical-align: top;
		font-size: 13px;
		overflow:hidden;
		text-overflow: ellipsis;
	}

	.f1tips-admin .muted{ color: var(--muted); }
	.f1tips-admin .tabs{ display:flex; gap:8px; flex-wrap:wrap; margin: 0 0 12px; }
	.f1tips-admin .tab{
		display:inline-flex; align-items:center; justify-content:center;
		padding: 8px 10px;
		border: 1px solid rgba(0,0,0,.12);
		background:#fff;
		font-weight: 900;
		font-size: 12px;
		text-decoration:none;
		color: #111;
		min-width: 180px;
		white-space: nowrap;
	}
	.f1tips-admin .tab.active{
		background: rgba(224,0,120,.10);
		border-color: rgba(224,0,120,.22);
		color: var(--accent);
	}
	.f1tips-admin .hr{ border:none; border-top:1px solid rgba(0,0,0,.08); margin:14px 0; }
	.f1tips-admin .actions-inline{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
	.f1tips-admin .copy-input{
		width:100%;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
	}

	.f1tips-admin .sessgrid{
		display:grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 8px;
	}
	.f1tips-admin .sessbox{
		padding: 8px 10px;
		background: #fff;
		border: 1px solid var(--line);
	}
	.f1tips-admin .sessmeta{
		display:flex;
		align-items:center;
		justify-content:space-between;
		gap: 8px;
		margin-bottom: 6px;
	}
	.f1tips-admin .sessname{
		font-weight: 900;
		color: rgba(17,17,17,.75);
	}
	.f1tips-admin .wm-badge{
		display:inline-flex;
		align-items:center;
		justify-content:center;
		padding: 2px 8px;
		border: 1px solid rgba(0,0,0,.10);
		background: rgba(0,0,0,.04);
		font-weight: 900;
		font-size: 11px;
		white-space: nowrap;
	}
	.f1tips-admin .wm-badge.ok{
		background: rgba(224,0,120,.10);
		border-color: rgba(224,0,120,.22);
		color: var(--accent);
	}
	.f1tips-admin .wm-badge.miss{ color: rgba(17,17,17,.60); }
	.f1tips-admin .pill{
		display:inline-flex; align-items:center; justify-content:center;
		padding: 2px 8px;
		border: 1px solid rgba(0,0,0,.10);
		background:#fff;
		font-weight:900;
		font-size:11px;
		white-space:nowrap;
	}
	.f1tips-admin .sessselect select{ width: 100%; }
</style>

<div class="wrap"><h1>F1 Tippspiel <span class="badge">Aktive Saison <?php echo esc_html($year); ?></span> <span class="badge">Liga: gesamt</span></h1></div>
<div class="f1tips-admin"><div class="canvas">

<div class="card"><div class="head"><div class="title">Navigation</div><div class="badge">One-Page Admin</div></div><div class="body">
<div class="tabs">
<?php
$tabs = array(
	'rounds'   => 'Tipprunden & Sessions',
	'rules'    => 'Punkteregeln',
	'bonus'    => 'Bonusfragen',
	'results'  => 'Ergebnisse & Cache',
	'backups'  => 'Backups',
);
foreach ($tabs as $k => $label) {
	$url = add_query_arg(array('page'=>'f1-tippspiel','tab'=>$k), admin_url('admin.php'));
	echo '<a class="tab '.($tab===$k?'active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>';
}
?>
</div></div></div>

<?php if ($tab === 'rounds'): ?>
<div class="card"><div class="head"><div class="title">Tipprunden & Sessions (Bulk Save)</div><div class="badge">Kalender: CPT f1_race</div></div><div class="body">
	<div class="actions-inline" style="margin:0 0 12px;">
		<form method="post" style="margin:0;">
			<?php wp_nonce_field('f1tips_sync_rounds'); ?>
			<input type="hidden" name="f1tips_sync_rounds" value="1">
			<button class="btn btn-primary" type="submit">Runden aus Kalender synchronisieren</button>
		</form>
		<div class="muted">Erstellt DB-Runden für alle veröffentlichten <code>f1_race</code> Posts.</div>
	</div>

	<?php if ($rounds):
		$session_labels = array('quali'=>'Quali','sq'=>'SQ','sprint'=>'Sprint','race'=>'Race');
		$label_state = function($s) {
			if ($s === 'open') return 'Offen';
			if ($s === 'closed') return 'Geschlossen';
			if ($s === 'ended') return 'Beendet';
			return $s;
		};
		?>
		<form method="post" style="margin:0;">
		<?php wp_nonce_field('f1tips_save_session_states'); ?>
		<input type="hidden" name="f1tips_save_session_states" value="1">
		<table><thead><tr>
			<th style="width:70px;">#</th>
			<th style="width:260px;">GP</th>
			<th style="width:120px;">Race Post</th>
			<th style="width:240px;">Kalender-Zeiten</th>
			<th>Session Status (Bulk)</th>
		</tr></thead><tbody>
		<?php
		$topMap = array('quali'=>4,'sq'=>4,'sprint'=>8,'race'=>8);
		foreach ($rounds as $r) {
			$race_id = (int)$r['race_post_id'];
			$name = esc_html($r['name']);
			$dead = array();
			foreach ($session_labels as $slug=>$lab) {
				$ts = F1_Tippspiel::get_race_session_start_ts($race_id, $slug);
				if ($ts) $dead[] = $lab.': '.esc_html(wp_date('d.m.Y H:i', $ts));
			}
			$ui = '<div class="sessgrid">';
			foreach ($session_labels as $slug=>$lab) {
				$ts = F1_Tippspiel::get_race_session_start_ts($race_id, $slug);
				if (!$ts) {
					$ui .= '<div class="sessbox muted" style="border-style:dashed;">—</div>';
					continue;
				}
				$state = F1_Tippspiel::get_session_state($race_id, $slug);
				$topN  = isset($topMap[$slug]) ? (int)$topMap[$slug] : 0;
				$wmOk  = ($topN > 0) ? F1_Tippspiel::wm_has_result($race_id, $slug, $topN) : false;
				$wmClass = $wmOk ? 'ok' : 'miss';
				$wmText  = $wmOk ? 'WM OK' : 'WM fehlt';

				$ui .= '<div class="sessbox">';
				$ui .= '<div class="sessmeta">';
				$ui .=    '<div class="sessname">'.esc_html($lab).'</div>';
				$ui .=    '<span class="pill">'.esc_html($label_state($state)).'</span>';
				$ui .=    '<span class="wm-badge '.$wmClass.'">'.esc_html($wmText).'</span>';
				$ui .= '</div>';
				$ui .= '<div class="sessselect">';
				$ui .= '<select name="states['.(int)$race_id.']['.esc_attr($slug).']">';
				$ui .= '<option value="open" '.selected($state,'open',false).'>Offen</option>';
				$ui .= '<option value="closed" '.selected($state,'closed',false).'>Geschlossen</option>';
				if ($wmOk || $state === 'ended') {
					$ui .= '<option value="ended" '.selected($state,'ended',false).'>Beendet</option>';
				}
				$ui .= '</select>';
				$ui .= '</div></div>';
			}
			$ui .= '</div>';
			echo '<tr>';
			echo '<td>'.(int)$r['id'].'</td>';
			echo '<td><strong>'.$name.'</strong></td>';
			echo '<td><a href="'.esc_url(get_edit_post_link($race_id)).'">#'.$race_id.'</a></td>';
			echo '<td class="muted">'.($dead ? implode('<br>', $dead) : '—').'</td>';
			echo '<td>'.$ui.'</td>';
			echo '</tr>';
		}
		?>
		</tbody></table>
		<div class="actions-inline" style="margin-top:12px;">
			<button class="btn btn-primary" type="submit">Alle Änderungen speichern</button>
			<div class="muted">Du kannst mehrere Sessions / mehrere GPs ändern und dann einmal speichern.</div>
		</div>
		</form>
	<?php endif; ?>
</div></div>
<?php endif; ?>

<?php if ($tab === 'rules'): ?>
<div class="card"><div class="head"><div class="title">Punkteregeln</div><div class="badge">Saison <?php echo esc_html($year); ?></div></div><div class="body">
	<form method="post" style="margin:0;">
	<?php wp_nonce_field('f1tips_save_rules'); ?>
	<input type="hidden" name="f1tips_save_rules" value="1">
	<div class="grid">
		<div>
			<div class="muted" style="margin-bottom:10px;"><strong>Qualifying / Sprint Qualifying</strong> — Top 4</div>
			<label>Exakt-Punkte (Pos 1–4)</label>
			<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px">
			<?php for ($i=0;$i<4;$i++) { $v = (int)($rq['exact'][$i] ?? 0); echo '<input type="number" name="quali_exact[]" value="'.esc_attr($v).'" min="0">'; } ?>
			</div><div style="height:10px"></div>
			<label>Falsche Position Modus</label>
			<select name="quali_wrong_mode">
				<option value="absolute" <?php selected($rq['wrong_mode'],'absolute'); ?>>Absolut (fixe Punkte)</option>
				<option value="relative" <?php selected($rq['wrong_mode'],'relative'); ?>>Relativ (Abzug pro Position)</option>
			</select><div style="height:10px"></div>
			<label>Absolut: Punkte für richtigen Tipp an falscher Position</label>
			<input type="number" name="quali_wrong_points" value="<?php echo esc_attr((int)$rq['wrong_points']); ?>" min="0">
			<div style="height:10px"></div>
			<label>Relativ: Punkteabzug pro falscher Position</label>
			<input type="number" name="quali_rel_penalty" value="<?php echo esc_attr((int)$rq['rel_penalty']); ?>" min="0">
		</div>
		<div>
			<div class="muted" style="margin-bottom:10px;"><strong>Rennen / Sprint</strong> — Top 8</div>
			<label>Exakt-Punkte (Pos 1–8)</label>
			<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px">
			<?php for ($i=0;$i<8;$i++) { $v = (int)($rr['exact'][$i] ?? 0); echo '<input type="number" name="race_exact[]" value="'.esc_attr($v).'" min="0">'; } ?>
			</div><div style="height:10px"></div>
			<label>Falsche Position Modus</label>
			<select name="race_wrong_mode">
				<option value="absolute" <?php selected($rr['wrong_mode'],'absolute'); ?>>Absolut (fixe Punkte)</option>
				<option value="relative" <?php selected($rr['wrong_mode'],'relative'); ?>>Relativ (Abzug pro Position)</option>
			</select><div style="height:10px"></div>
			<label>Absolut: Punkte für richtigen Tipp an falscher Position</label>
			<input type="number" name="race_wrong_points" value="<?php echo esc_attr((int)$rr['wrong_points']); ?>" min="0">
			<div style="height:10px"></div>
			<label>Relativ: Punkteabzug pro falscher Position</label>
			<input type="number" name="race_rel_penalty" value="<?php echo esc_attr((int)$rr['rel_penalty']); ?>" min="0">
		</div>
	</div>
	<div style="height:12px"></div>
	<button class="btn btn-primary" type="submit">Regeln speichern</button>
	</form>
</div></div>
<?php endif; ?>

<?php if ($tab === 'bonus'): ?>
	<?php
	$defs = F1_Tippspiel::bonus_preset_definitions();
	$q_driver = F1_Tippspiel::bonus_get_preset_question($season_id, $league_id, 'driver_wc');
	$q_team   = F1_Tippspiel::bonus_get_preset_question($season_id, $league_id, 'team_wc');

	$render_preset = function($preset_key, $row) use ($defs, $drivers, $teams) {
		$def = $defs[$preset_key];
		$exists = is_array($row) && !empty($row['id']);
		$qid = $exists ? (int)$row['id'] : 0;
		$status = $exists ? sanitize_key($row['status'] ?? 'open') : 'inactive';
		$points = $exists ? (int)($row['points'] ?? 10) : 10;
		$correct = $exists ? (string)($row['correct_value'] ?? '') : '';
		$revealed_at = $exists ? (string)($row['revealed_at'] ?? '') : '';

		echo '<div class="card"><div class="head"><div class="title">'.esc_html($def['label']).'</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
		echo '<span class="badge">'.esc_html($exists ? $status : 'nicht aktiv').'</span>';
		echo '<span class="badge">'.esc_html($exists ? ('ID '.$qid) : '—').'</span>';
		if ($exists && $status === 'revealed' && $revealed_at) echo '<span class="badge">revealed_at: '.esc_html($revealed_at).'</span>';
		echo '</div></div><div class="body">';

		echo '<form method="post" class="grid" style="margin:0; align-items:end;">';
		wp_nonce_field('f1tips_bonus_preset_action');
		echo '<input type="hidden" name="f1tips_bonus_preset_action" value="1">';
		echo '<input type="hidden" name="preset_key" value="'.esc_attr($preset_key).'">';

		echo '<div><label>Punkte</label><input type="number" name="points" value="'.esc_attr((int)$points).'" min="0"></div>';

		if ($def['question_type'] === 'driver') {
			echo '<div><label>Correct (Fahrer)</label><select name="correct_driver_id">';
			echo '<option value="">Fahrer wählen…</option>';
			foreach ((array)$drivers as $d) {
				$sel = ($correct !== '' && (int)$correct === (int)$d->ID) ? ' selected' : '';
				echo '<option value="'.(int)$d->ID.'"'.$sel.'>'.esc_html((string)$d->post_title).'</option>';
			}
			echo '</select></div>';
		} else {
			$selectedTeamId = 0;
			if ($correct !== '') {
				if (is_numeric($correct)) $selectedTeamId = (int)$correct;
				else $selectedTeamId = (int) F1_Tippspiel::match_team_to_post_id($correct);
			}
			echo '<div><label>Correct (Team)</label><select name="correct_team_id">';
			echo '<option value="">Team wählen…</option>';
			foreach ((array)$teams as $tm) {
				$tid = (int)$tm->ID;
				$sel = ($selectedTeamId > 0 && $selectedTeamId === $tid) ? ' selected' : '';
				echo '<option value="'.$tid.'"'.$sel.'>'.esc_html((string)$tm->post_title).'</option>';
			}
			echo '</select></div>';
		}

		echo '<div><label>Aktionen</label><div class="actions-inline">';
		echo '<button class="btn btn-primary" type="submit" name="do" value="activate">Aktivieren</button>';
		echo '<button class="btn" type="submit" name="do" value="lock" '.(!$exists ? 'disabled' : '').'>Sperren</button>';
		echo '<button class="btn" type="submit" name="do" value="reveal" '.(!$exists ? 'disabled' : '').'>Auflösen</button>';
		echo '<button class="btn btn-danger" type="submit" name="do" value="delete" '.(!$exists ? 'disabled' : '').' onclick="return confirm(\'Bonusfrage wirklich löschen? (inkl. aller Antworten)\');">Löschen</button>';
		echo '</div></div>';
		echo '</form></div></div>';
	};

	$render_preset('driver_wc', $q_driver);
	$render_preset('team_wc', $q_team);
	?>
<?php endif; ?>

<?php if ($tab === 'results'): ?>
<div class="card"><div class="head"><div class="title">Ergebnisse & Cache</div><div class="badge">Saison <?php echo esc_html($year); ?></div></div><div class="body">
	<div style="height:12px"></div>
	<form method="post" class="grid" style="align-items:end; margin:0;">
	<?php wp_nonce_field('f1tips_save_result'); ?>
	<input type="hidden" name="f1tips_save_result" value="1">
	<div><label>Runde</label><select name="round_id">
	<?php foreach ($recentRounds as $r) echo '<option value="'.(int)$r['id'].'">#'.(int)$r['id'].' – '.esc_html((string)$r['name']).'</option>'; ?>
	</select></div>
	<div><label>Session</label><select name="session_slug"><option value="quali">Quali (Top 4)</option><option value="sq">Sprint Quali (Top 4)</option><option value="sprint">Sprint (Top 8)</option><option value="race">Race (Top 8)</option></select></div>
	<div><label>TopN</label><select name="topN"><option value="4">4</option><option value="8" selected>8</option></select></div>
	<div style="grid-column:1/-1"><label>Plätze (in Reihenfolge)</label>
	<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px">
	<?php for ($i=1;$i<=8;$i++) {
		echo '<select name="driver_ids[]">';
		echo '<option value="">P'.$i.' – Fahrer wählen</option>';
		foreach ($drivers as $d) echo '<option value="'.(int)$d->ID.'">'.esc_html((string)$d->post_title).'</option>';
		echo '</select>';
	} ?>
	</div></div>
	<div><button class="btn btn-primary" type="submit">Override speichern</button></div>
	</form>
	<div class="hr"></div>
	<form method="post" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap; margin:0;">
	<?php wp_nonce_field('f1tips_rebuild_cache'); ?>
	<input type="hidden" name="f1tips_rebuild_cache" value="1">
	<button class="btn btn-primary" type="submit">Leaderboard Cache neu berechnen</button>
	<div class="muted">Gilt immer für Liga „gesamt“ der aktiven Saison.</div>
	</form>
</div></div>
<?php endif; ?>

<?php if ($tab === 'backups'): ?>
<div class="card"><div class="head"><div class="title">Backups</div><div class="badge">Single-League Export/Import</div></div><div class="body">
	<div class="hr"></div>
	<div class="card" style="margin:0 0 14px;"><div class="head"><div class="title">Export</div></div><div class="body">
	<form method="post" style="margin:0;">
	<?php wp_nonce_field('f1tips_backup_export'); ?>
	<input type="hidden" name="f1tips_backup_export" value="1">
	<button class="btn btn-primary" type="submit">Backup erstellen</button>
	<div style="height:10px"></div>
	<label>Backup JSON</label>
	<textarea id="f1tips-backup-export" rows="14" placeholder="..."><?php echo esc_textarea((string)$backup_json_out); ?></textarea>
	<div class="actions-inline" style="margin-top:8px;">
	<button class="btn" type="button" data-copy-from="#f1tips-backup-export">JSON kopieren</button>
	<button class="btn" type="button" data-download-from="#f1tips-backup-export" data-filename="f1tips-backup-<?php echo $year; ?>.json">Als Datei speichern</button>
	</div>
	</form></div></div>

	<div class="card" style="margin:0 0 14px;"><div class="head"><div class="title">Import</div></div><div class="body">
	<form method="post" style="margin:0;">
	<?php wp_nonce_field('f1tips_backup_import'); ?>
	<input type="hidden" name="f1tips_backup_import" value="1">
	<div class="grid" style="align-items:end;">
	<div><label>Import-Modus</label><select name="import_mode"><option value="merge">Merge</option><option value="overwrite">Overwrite</option></select></div>
	<div class="muted" style="align-self:center;">Overwrite leert Mitglieder/Tips/Cache der Liga „gesamt“ und importiert neu.</div>
	<div style="grid-column:1/-1;"><label>Backup JSON einfügen</label>
	<textarea name="backup_json" rows="14" placeholder="..."></textarea></div>
	</div>
	<div style="height:10px"></div>
	<button class="btn btn-primary" type="submit">Import starten</button>
	</form></div></div>

	<div class="card" style="margin:0;"><div class="head"><div class="title">Reset</div><div class="badge">Vorsicht</div></div><div class="body">
	<form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:0;">
	<?php wp_nonce_field('f1tips_reset'); ?>
	<input type="hidden" name="f1tips_reset" value="1">
	<button class="btn btn-danger" type="submit" name="reset_mode" value="tips" onclick="return confirm('Wirklich ALLE Tipps, Bonus-Antworten, Ergebnis-Overrides und den Cache löschen?');">Tipps resetten</button>
	<button class="btn btn-danger" type="submit" name="reset_mode" value="all" onclick="return confirm('WIRKLICH ALLES löschen (Tipps + Bonus + Members + Cache + Bonusfragen)?');">Alles resetten</button>
	</form></div></div>
</div></div>
<?php endif; ?>

<script>
(function(){
	function copyText(text){
		if (!text) return;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).catch(function(){}); return;
		}
		var ta = document.createElement("textarea");
		ta.value = text; ta.style.position = "fixed"; ta.style.left = "-9999px";
		document.body.appendChild(ta); ta.focus(); ta.select();
		try { document.execCommand("copy"); } catch(e) {}
		document.body.removeChild(ta);
	}
	document.addEventListener("click", function(e){
		var btn2 = e.target.closest("[data-copy-from]");
		if (btn2) {
			var sel = btn2.getAttribute("data-copy-from");
			var el = document.querySelector(sel);
			if (el) copyText(el.value || el.textContent || "");
		}
		var btn3 = e.target.closest("[data-download-from]");
		if (btn3) {
			var sel3 = btn3.getAttribute("data-download-from");
			var el3 = document.querySelector(sel3);
			var content = el3 ? (el3.value || el3.textContent || "") : "";
			var filename = btn3.getAttribute("data-filename") || "backup.json";
			try {
				var blob = new Blob([content], {type: "application/json;charset=utf-8"});
				var url = URL.createObjectURL(blob);
				var a = document.createElement("a");
				a.href = url; a.download = filename;
				document.body.appendChild(a); a.click();
				document.body.removeChild(a); URL.revokeObjectURL(url);
			} catch(e) {}
		}
	});
})();
</script>
</div></div>
