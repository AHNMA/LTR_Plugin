<?php
// Leaderboard View
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="f1tips-lb-wrap">
    <div class="f1tips-lb-card">
        <div class="f1tips-lb-head">
            <div class="f1tips-lb-title"><?php echo esc_html($title); ?></div>
        </div>
        <div class="f1tips-lb-body">
            <!-- Render rows -->
            <?php foreach ($items as $i => $it): ?>
                <div class="f1tips-lb-row">
                    <div><?php echo $i+1; ?></div>
                    <div><?php echo esc_html($it['display_name']); ?></div>
                    <div><?php echo $it['points']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
