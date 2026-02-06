<?php
// Frontend Calendar View
if ( ! defined( 'ABSPATH' ) ) exit;

$races = get_posts(array(
    'post_type' => 'f1_race',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'orderby' => 'menu_order',
    'order' => 'ASC',
));

// Logic to render cards...
echo f1cal_render_cards($races);
?>
