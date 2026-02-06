<?php
// WM Stand View
if ( ! defined( 'ABSPATH' ) ) exit;

// Expects $data (races, drivers/teams)
?>
<div class="f1wms-card">
    <div class="f1wms-shell">
        <div class="f1wms-scroll" data-f1wms-scroll>
            <table class="f1wms-table f1wms-standings">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>PTS</th>
                        <!-- Race Columns -->
                         <?php foreach ($data['races'] as $r): ?>
                             <th><img src="<?php echo esc_url($r['flag_url']); ?>" width="20"></th>
                         <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data[$mode] as $i => $row): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td class="f1wms-name"><?php echo esc_html($mode === 'teams' ? $row['team'] : $row['driver']); ?></td>
                            <td class="f1wms-pts"><?php echo $row['total']; ?></td>
                             <?php foreach ($data['races'] as $r):
                                 $rid = $r['id'];
                                 $val = $row['by_race'][$rid] ?? '-';
                             ?>
                                 <td><?php echo $val; ?></td>
                             <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
