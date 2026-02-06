<?php
// Frontend Team Profile View
if ( ! defined( 'ABSPATH' ) ) exit;

// Expects $pid
$m = f1team_get_meta($pid);
$name = get_the_title($pid);
?>
<div class="f1team-wrap">
    <div class="f1team-card">
        <div class="f1team-head">
            <div class="f1team-head__label">Teamprofil</div>
        </div>
        <div class="f1team-body">
            <h1><?php echo esc_html($name); ?></h1>
            <div class="f1team-bio">
                <?php echo wpautop($m['bio']); ?>
            </div>
            <div class="f1team-editor">
                 <?php echo $content; ?>
            </div>
        </div>
    </div>
</div>
