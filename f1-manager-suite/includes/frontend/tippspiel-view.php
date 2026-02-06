<?php
// Tippspiel Frontend View
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="f1tips-wrap">
    <div class="f1tips-canvas" data-toast>
        <!-- JS App Root -->
        <div class="f1tips-view" data-view="main">
            <!-- Overview, Weekend Selection, Points -->
            <div class="f1tips-grid-top">
                <!-- User Stats -->
                <div class="f1tips-stack">
                     <div class="f1tips-card">
                         <div class="f1tips-head"><div class="title">Spielerübersicht</div></div>
                         <div class="f1tips-body">
                             <!-- populated by JS -->
                             <div class="kv"><div class="k">Name</div><div class="v" data-me-name>—</div></div>
                         </div>
                     </div>
                </div>

                <!-- Race Selector -->
                <div class="f1tips-side">
                    <div class="f1tips-card">
                         <div class="f1tips-head"><div class="title">Rennwochenende</div></div>
                         <div class="f1tips-body">
                             <select class="f1tips-select" data-race-select>
                                 <!-- Options by JS/PHP loop -->
                                 <?php foreach($q->posts as $rid): ?>
                                     <option value="<?php echo $rid; ?>"><?php echo get_the_title($rid); ?></option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Sessions Container (Hidden by default, toggled by JS) -->
             <?php foreach ($q->posts as $race_id): ?>
                <div data-race="<?php echo (int)$race_id; ?>" hidden>
                    <!-- Sessions List -->
                    <div class="f1tips-sessions">
                        <!-- Render Session Cards here -->
                    </div>
                </div>
             <?php endforeach; ?>
        </div>
    </div>
</div>
