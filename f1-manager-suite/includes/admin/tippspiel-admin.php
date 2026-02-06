<?php
// Admin UI for Tippspiel
if ( ! defined( 'ABSPATH' ) ) exit;

// This file contains the content of f1tips_render_admin_all()
// Variables $season_id, $year, $league_id are expected to be passed or retrieved here.

$season_id = F1_Tippspiel::get_active_season_id();
$year = F1_Tippspiel::get_year_for_season_id($season_id);
F1_Tippspiel::ensure_default_rules_by_season($season_id);
$league_id = F1_Tippspiel::single_league_id($season_id);

$tab = sanitize_key($_GET['tab'] ?? 'rounds');
?>
<div class="wrap">
    <h1>F1 Tippspiel <span class="badge">Saison <?php echo esc_html($year); ?></span></h1>
    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=f1-tippspiel&tab=rounds" class="nav-tab <?php echo $tab === 'rounds' ? 'nav-tab-active' : ''; ?>">Runden</a>
        <a href="?page=f1-tippspiel&tab=rules" class="nav-tab <?php echo $tab === 'rules' ? 'nav-tab-active' : ''; ?>">Regeln</a>
        <a href="?page=f1-tippspiel&tab=bonus" class="nav-tab <?php echo $tab === 'bonus' ? 'nav-tab-active' : ''; ?>">Bonus</a>
        <a href="?page=f1-tippspiel&tab=results" class="nav-tab <?php echo $tab === 'results' ? 'nav-tab-active' : ''; ?>">Ergebnisse</a>
        <a href="?page=f1-tippspiel&tab=backups" class="nav-tab <?php echo $tab === 'backups' ? 'nav-tab-active' : ''; ?>">Backups</a>
    </h2>

    <div class="f1tips-admin-content">
        <?php if ($tab === 'rounds'): ?>
            <!-- Rounds UI -->
            <p>Runden Verwaltung...</p>
        <?php elseif ($tab === 'rules'): ?>
             <!-- Rules UI -->
             <p>Regel Verwaltung...</p>
        <?php elseif ($tab === 'bonus'): ?>
             <!-- Bonus UI -->
             <p>Bonusfragen...</p>
        <?php elseif ($tab === 'results'): ?>
             <!-- Results UI -->
             <p>Ergebnisse & Cache...</p>
        <?php elseif ($tab === 'backups'): ?>
             <!-- Backup UI -->
             <p>Backups...</p>
        <?php endif; ?>
    </div>
</div>
