<?php
// Frontend Driver Profile View
if ( ! defined( 'ABSPATH' ) ) exit;

// Expects $pid to be set
$m = f1drv_get_meta($pid);
$name = get_the_title($pid);
// ...
?>
<div class="f1drv-wrap">
    <div class="f1drv-card">
        <div class="f1drv-head">
            <div class="f1drv-head__label">Fahrerprofil</div>
        </div>
        <div class="f1drv-body">
            <!-- Media, Content, Stats -->
            <h1><?php echo esc_html($name); ?></h1>
            <div class="f1drv-bio">
                <?php echo wpautop($m['bio']); ?>
            </div>

            <!-- Gutenberg Content -->
            <div class="f1drv-editor">
                <?php echo $content; // Passed from filter ?>
            </div>
        </div>
    </div>
</div>
