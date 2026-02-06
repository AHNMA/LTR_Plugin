<?php
// Admin UI for WM-Stand
if ( ! defined( 'ABSPATH' ) ) exit;

// ... (Content from f1wms_render_admin_page)
?>
<div class="wrap f1wms-wrap-admin">
    <h1>WM-Stand – Session Ergebnisse</h1>

    <!-- Notices -->
    <?php if ( ! empty( $_GET['f1wms_notice'] ) ) : ?>
        <div class="notice notice-info"><p><?php echo esc_html( $_GET['f1wms_notice'] ); ?></p></div>
    <?php endif; ?>

    <div class="f1wms-admin">
        <div class="card">
            <h3>Tools</h3>
            <form method="post" onsubmit="return confirm('Reset?');">
                <?php wp_nonce_field('f1wms_admin_save', 'f1wms_nonce'); ?>
                <input type="hidden" name="f1wms_action" value="reset_all">
                <button class="button button-link-delete">Reset All</button>
            </form>

            <hr>

            <form method="post">
                <?php wp_nonce_field('f1wms_dir_refresh', 'f1wms_dir_nonce'); ?>
                <input type="hidden" name="f1wms_dir_action" value="refresh_driver_directory">
                <button class="button">Fahrer-Referenz aktualisieren (Wikidata)</button>
            </form>
        </div>

        <!-- Race Selector & Session Editors -->
        <!-- Logic to list races and sessions goes here -->
        <!-- ... -->
    </div>
</div>
