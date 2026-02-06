<?php
// Admin UI for Teams
if ( ! defined( 'ABSPATH' ) ) exit;

$teams = get_posts(array(
    'post_type'      => 'f1_team',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
));

$nonce = wp_create_nonce('f1team_nonce');
$media = f1team_get_media_options();
?>
<div class="wrap f1team-wrap-admin">
    <h1 style="margin-bottom:6px;">F1 Teams – Admin Panel</h1>
    <div class="f1team-admin" id="f1teamPanel">
        <div class="f1team-admin__surface">
            <h3>Teams verwalten</h3>
            <ul class="f1team-admin__list" id="f1teamList">
                <?php $n = 1; foreach ($teams as $t) :
                    $pid = (int)$t->ID;
                    $m = f1team_get_meta($pid);
                    $title = get_the_title($pid);
                ?>
                <li class="f1team-admin__item"
                    data-id="<?php echo esc_attr((string)$pid); ?>"
                    data-name="<?php echo esc_attr($title); ?>"
                    data-slug="<?php echo esc_attr($m['slug']); ?>"
                    data-teamcolor="<?php echo esc_attr($m['teamcolor']); ?>"
                    data-bio="<?php echo esc_attr($m['bio']); ?>"
                    /* ... other data attrs ... */
                >
                    <div class="f1team-admin__nr"><?php echo esc_html($n.'.'); ?></div>
                    <div class="f1team-admin__meta">
                        <div class="f1team-admin__title"><?php echo esc_html($title); ?></div>
                    </div>
                </li>
                <?php $n++; endforeach; ?>
            </ul>

            <input type="hidden" id="f1team_post_id" value="0">
            <p><label>Name</label><input type="text" id="f1team_name" class="widefat"></p>
            <p><label>Slug</label><input type="text" id="f1team_slug" class="widefat"></p>
            <p><label>Farbe</label><input type="color" id="f1team_teamcolor_picker"><input type="text" id="f1team_teamcolor"></p>
            <p><label>Bio</label><textarea id="f1team_bio" class="widefat" rows="5"></textarea></p>

            <div class="f1team-admin__btns">
                <button class="button button-primary" id="f1team_save" type="button">Speichern</button>
                <button class="button button-link-delete" id="f1team_delete" type="button">Löschen</button>
            </div>
            <div id="f1team_msg"></div>
        </div>
    </div>
</div>
<script>
    // JS Logic for F1 Teams Admin
    // ... (Content from Teamseiten.php JS)
</script>
