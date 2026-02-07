<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
   GLOBAL CONSTANTS
   ========================================================= */

if (!defined('F1TIPS_DB_VER'))     define('F1TIPS_DB_VER', '1.1.0');
if (!defined('F1TIPS_CAPABILITY')) define('F1TIPS_CAPABILITY', 'manage_f1_tippspiel');

// Design Constants
if (!defined('F1TIPS_ACCENT')) define('F1TIPS_ACCENT', '#E00078');
if (!defined('F1TIPS_HEAD'))   define('F1TIPS_HEAD',   '#202020');
if (!defined('F1TIPS_CANVAS')) define('F1TIPS_CANVAS', '#EEEEEE');


class F1_Tippspiel {

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Init - Removed install_db from here
        // add_action( 'init', array( $this, 'init' ) ); // init not needed if only install_db was there

        // Admin
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );

        // Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // REST API
        add_action( 'rest_api_init', array( $this, 'register_api' ) );

        // Shortcodes
        add_shortcode( 'f1_tippspiel', array( $this, 'shortcode_tippspiel' ) );
        add_shortcode( 'f1_leaderboard', array( $this, 'shortcode_leaderboard' ) );
    }

    // Renamed install_db to activate_plugin and made static
    public static function activate_plugin() {
        self::install_db();
    }

    public function admin_init() {
        // Capabilities
        $roles = array('administrator', 'editor', 'author');
        foreach ($roles as $r) {
            $role = get_role($r);
            if ($role && !$role->has_cap(F1TIPS_CAPABILITY)) {
                $role->add_cap(F1TIPS_CAPABILITY);
            }
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            'F1 Tippspiel',
            'F1 Tippspiel',
            F1TIPS_CAPABILITY,
            'f1-tippspiel',
            array( $this, 'render_admin_page' ),
            'dashicons-plus-alt',
            30
        );
    }

    public function render_admin_page() {
        require_once F1_MANAGER_SUITE_PATH . 'includes/admin/tippspiel-admin.php';
    }

    public function enqueue_frontend_assets() {
        global $post;
        $has_shortcode = is_a( $post, 'WP_Post' ) && (
            has_shortcode( $post->post_content, 'f1_tippspiel' ) ||
            has_shortcode( $post->post_content, 'f1_leaderboard' )
        );
        if ( ! $has_shortcode ) return;

        wp_enqueue_style( 'f1-tippspiel', F1_MANAGER_SUITE_URL . 'assets/css/f1-tippspiel.css', array(), '1.0.0' );
        wp_enqueue_script( 'f1-tippspiel', F1_MANAGER_SUITE_URL . 'assets/js/f1-tippspiel.js', array(), '1.0.0', true );

        $season_id = self::get_active_season_id();
        $year      = self::get_year_for_season_id($season_id);
        $league_id = self::single_league_id($season_id);
        $uid       = get_current_user_id();
        $me        = wp_get_current_user();

        $qs = self::bonus_get_questions($season_id, $league_id);
        $openCount = 0;
        foreach ((array)$qs as $q) {
            if (!self::bonus_is_locked_row($q)) $openCount++;
        }

        $ctx = array(
            'rest'        => esc_url_raw( rest_url() ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'year'        => (int)$year,
            'league_id'   => (int)$league_id,
            'logged_in'   => is_user_logged_in(),
            'me_id'       => (int)$uid,
            'me_name'     => $me ? (string)$me->display_name : '',
            'me_avatar'   => $uid ? esc_url_raw( get_avatar_url( $uid, array('size'=>96) ) ) : '',
            'bonus_open_count' => (int)$openCount,
        );

        wp_localize_script( 'f1-tippspiel', 'f1_tippspiel_cfg', $ctx );
    }

    /* =========================================================
       SHORTCODES
       ========================================================= */

    public function shortcode_tippspiel( $atts ) {
        $atts = shortcode_atts(array(
            'year'   => (int)wp_date('Y'),
            'league' => 'gesamt',
        ), $atts);

        $season_id = self::get_active_season_id();
        $year = self::get_year_for_season_id($season_id);
        $league_id = self::single_league_id($season_id);

        // Get Races
        $q = new WP_Query(array(
            'post_type'      => 'f1_race',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ));

        foreach ($q->posts as $race_id) {
            self::ensure_round($season_id, (int)$race_id);
        }

        // Drivers
        $drivers = get_posts(array(
            'post_type'      => 'f1_driver',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        // Filter available
        $drivers = array_values(array_filter($drivers, function($p){
            return is_object($p) && !empty($p->ID) && self::driver_is_available($p->ID);
        }));

        self::sort_posts_by_lastname($drivers);

        // Teams
        $teams = array();
        $tq = new WP_Query(array(
            'post_type'      => 'f1_team',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'ID' => 'ASC'),
            'no_found_rows'  => true,
        ));
        foreach ((array)$tq->posts as $p) {
            if (is_object($p) && !empty($p->ID)) $teams[] = $p;
        }
        self::sort_posts_by_lastname($teams);

        // Default race logic
        $default_race_id = 0;
        foreach ($q->posts as $race_id) {
            $race_id = (int)$race_id;
            $has_open = false;
            foreach (array('quali','sq','sprint','race') as $slug) {
                $ts = self::get_race_session_start_ts($race_id, $slug);
                if (!$ts) continue;
                $state = self::get_session_state($race_id, $slug);
                if ($state === 'open') { $has_open = true; break; }
            }
            if ($has_open) { $default_race_id = $race_id; break; }
        }

        if (!$default_race_id) {
            $best_race_id = 0;
            $best_ts = 0;
            foreach ($q->posts as $race_id) {
                $race_id = (int)$race_id;
                $race_best_ts = 0;
                foreach (array('quali','sq','sprint','race') as $slug) {
                    $ts = self::get_race_session_start_ts($race_id, $slug);
                    if (!$ts) continue;
                    $state = self::get_session_state($race_id, $slug);
                    if ($state === 'ended' && $ts > $race_best_ts) {
                        $race_best_ts = $ts;
                    }
                }
                if ($race_best_ts > $best_ts) {
                    $best_ts = $race_best_ts;
                    $best_race_id = $race_id;
                }
            }
            if ($best_race_id) $default_race_id = $best_race_id;
        }

        if (!$default_race_id && !empty($q->posts)) $default_race_id = (int)$q->posts[0];

        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/tippspiel-view.php';
        return ob_get_clean();
    }

    public function shortcode_leaderboard( $atts ) {
        $atts = shortcode_atts(array(
            'title'     => 'Leaderboard',
            'show_wins' => 1,
        ), $atts);

        $title     = sanitize_text_field((string)$atts['title']);
        $show_wins = (int)$atts['show_wins'] ? 1 : 0;

        $season_id = self::get_active_season_id();
        $league_id = self::single_league_id($season_id);
        $year      = self::get_year_for_season_id($season_id);

        global $wpdb;
        $t = self::tables();

        // Cache check
        $has = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t['scores']} WHERE season_id=%d AND league_id=%d",
            $season_id, $league_id
        ));
        if ($has === 0) {
            self::rebuild_scores_cache($season_id, $league_id);
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, points_total, wins_total
             FROM {$t['scores']}
             WHERE season_id=%d AND league_id=%d",
            $season_id, $league_id
        ), ARRAY_A);

        $items = array();
        foreach ((array)$rows as $r) {
            $uid = (int)$r['user_id'];
            $items[] = array(
                'user_id'      => $uid,
                'display_name' => (string)get_the_author_meta('display_name', $uid),
                'avatar'       => function_exists('f1fp_get_custom_avatar_url')
                                    ? (string)f1fp_get_custom_avatar_url($uid, 64)
                                    : (string)get_avatar_url($uid, array('size'=>64)),
                'points'       => (int)$r['points_total'],
                'wins'         => (int)$r['wins_total'],
            );
        }

        usort($items, function($a,$b){
            if ((int)$b['points'] !== (int)$a['points']) return (int)$b['points'] - (int)$a['points'];
            if ((int)$b['wins'] !== (int)$a['wins']) return (int)$b['wins'] - (int)$a['wins'];
            return strcasecmp((string)$a['display_name'], (string)$b['display_name']);
        });

        ob_start();
        require F1_MANAGER_SUITE_PATH . 'includes/frontend/leaderboard-view.php';
        return ob_get_clean();
    }

    /* =========================================================
       DB & INSTALL
       ========================================================= */

    public static function tables() {
        global $wpdb;
        $p = $wpdb->prefix . 'f1tips_';
        return array(
            'seasons'   => $p.'seasons',
            'leagues'   => $p.'leagues',
            'members'   => $p.'members',
            'rounds'    => $p.'rounds',
            'tips'      => $p.'tips',
            'results'   => $p.'results',
            'rules'     => $p.'rules',
            'bonus_q'   => $p.'bonus_questions',
            'bonus_a'   => $p.'bonus_answers',
            'scores'    => $p.'scores_cache',
        );
    }

    public static function install_db() {
        global $wpdb;
        $installed = get_option('f1tips_db_ver', '');
        if ($installed === F1TIPS_DB_VER) return;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $t = self::tables();
        $sql = array();

        $sql[] = "CREATE TABLE {$t['seasons']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            year INT(4) NOT NULL,
            name VARCHAR(120) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY year (year)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['leagues']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'public',
            join_code VARCHAR(32) DEFAULT NULL,
            owner_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY season_id (season_id),
            UNIQUE KEY season_slug (season_id, slug)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['members']} (
            league_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'member',
            joined_at DATETIME NOT NULL,
            PRIMARY KEY (league_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['rounds']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) UNSIGNED NOT NULL,
            race_post_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(200) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY season_race (season_id, race_post_id),
            KEY season_id (season_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['tips']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id BIGINT(20) UNSIGNED NOT NULL,
            league_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            session_slug VARCHAR(30) NOT NULL,
            tip_json LONGTEXT NOT NULL,
            locked_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_tip (round_id, league_id, user_id, session_slug),
            KEY user_round (user_id, round_id),
            KEY league_round (league_id, round_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['results']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id BIGINT(20) UNSIGNED NOT NULL,
            session_slug VARCHAR(30) NOT NULL,
            result_json LONGTEXT NOT NULL,
            source VARCHAR(40) NOT NULL DEFAULT 'auto',
            imported_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_res (round_id, session_slug),
            KEY round_id (round_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['rules']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) UNSIGNED NULL,
            session_type VARCHAR(20) NOT NULL,
            exact_points_json TEXT NOT NULL,
            wrong_mode VARCHAR(20) NOT NULL DEFAULT 'absolute',
            wrong_points INT NOT NULL DEFAULT 1,
            rel_penalty INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rules (season_id, session_type)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['bonus_q']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season_id BIGINT(20) UNSIGNED NOT NULL,
            league_id BIGINT(20) UNSIGNED NULL,
            question_text VARCHAR(240) NOT NULL,
            question_type VARCHAR(30) NOT NULL DEFAULT 'select',
            points INT NOT NULL DEFAULT 10,
            options_json LONGTEXT DEFAULT NULL,
            closes_at DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            correct_value VARCHAR(240) DEFAULT NULL,
            revealed_at DATETIME DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY season_id (season_id),
            KEY league_id (league_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['bonus_a']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            answer_value VARCHAR(240) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_ans (question_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$t['scores']} (
            league_id BIGINT(20) UNSIGNED NOT NULL,
            season_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            points_total INT NOT NULL DEFAULT 0,
            wins_total INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (league_id, season_id, user_id),
            KEY season_id (season_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        foreach ($sql as $q) dbDelta($q);

        update_option('f1tips_db_ver', F1TIPS_DB_VER);

        $year = (int)wp_date('Y');
        $sid = self::ensure_season($year);
        self::set_active_season_id($sid);
        self::ensure_default_rules_by_season($sid);
        self::ensure_default_league_by_season($sid);
    }

    /* =========================================================
       HELPER METHODS (LOGIC)
       ========================================================= */

    public static function now_mysql() { return current_time('mysql'); }

    public static function get_season_id($year) {
        global $wpdb; $t = self::tables();
        return (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['seasons']} WHERE year=%d", (int)$year));
    }
    public static function get_year_for_season_id($season_id) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        if ($season_id <= 0) return (int)wp_date('Y');
        $y = (int)$wpdb->get_var($wpdb->prepare("SELECT year FROM {$t['seasons']} WHERE id=%d", $season_id));
        return $y > 0 ? $y : (int)wp_date('Y');
    }
    public static function ensure_season($year) {
        global $wpdb; $t = self::tables();
        $year = (int)$year;
        $sid = self::get_season_id($year);
        if ($sid) return $sid;

        $wpdb->insert($t['seasons'], array(
            'year'       => $year,
            'name'       => 'Saison '.$year,
            'status'     => 'active',
            'created_by' => get_current_user_id(),
            'created_at' => self::now_mysql(),
        ), array('%d','%s','%s','%d','%s'));

        return (int)$wpdb->insert_id;
    }
    public static function set_active_season_id($season_id) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        if ($season_id <= 0) return false;
        $wpdb->query("UPDATE {$t['seasons']} SET status='inactive' WHERE status='active'");
        $wpdb->update($t['seasons'], array('status'=>'active'), array('id'=>$season_id), array('%s'), array('%d'));
        return true;
    }
    public static function get_active_season_id() {
        global $wpdb; $t = self::tables();
        $sid = (int)$wpdb->get_var("SELECT id FROM {$t['seasons']} WHERE status='active' ORDER BY year DESC LIMIT 1");
        if ($sid > 0) return $sid;
        $year = (int)wp_date('Y');
        $sid = self::ensure_season($year);
        self::set_active_season_id($sid);
        return (int)$sid;
    }

    public static function ensure_default_league_by_season($season_id) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        if ($season_id <= 0) return 0;
        $slug = 'gesamt';
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['leagues']} WHERE season_id=%d AND slug=%s",
            $season_id, $slug
        ));
        if ($exists) return $exists;
        $wpdb->insert($t['leagues'], array(
            'season_id'     => $season_id,
            'name'          => 'Gesamt',
            'slug'          => $slug,
            'visibility'    => 'public',
            'join_code'     => null,
            'owner_user_id' => 0,
            'created_at'    => self::now_mysql(),
        ), array('%d','%s','%s','%s','%s','%d','%s'));
        return (int)$wpdb->insert_id;
    }
    public static function single_league_id($season_id) {
        $season_id = (int)$season_id;
        $lid = (int)self::ensure_default_league_by_season($season_id);
        return $lid > 0 ? $lid : 0;
    }

    public static function ensure_default_rules_by_season($season_id) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        if ($season_id <= 0) return;
        $defaults = array(
            'quali' => array('exact'=>array(4,3,2,1), 'wrong_mode'=>'absolute', 'wrong_points'=>1, 'rel_penalty'=>1),
            'race'  => array('exact'=>array(8,7,6,5,4,3,2,1), 'wrong_mode'=>'absolute', 'wrong_points'=>1, 'rel_penalty'=>1),
        );
        foreach ($defaults as $type => $cfg) {
            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$t['rules']} WHERE season_id=%d AND session_type=%s",
                $season_id, $type
            ));
            $now = self::now_mysql();
            $row = array(
                'season_id' => $season_id,
                'session_type' => $type,
                'exact_points_json' => wp_json_encode(array_values($cfg['exact'])),
                'wrong_mode' => $cfg['wrong_mode'],
                'wrong_points' => (int)$cfg['wrong_points'],
                'rel_penalty' => (int)$cfg['rel_penalty'],
                'updated_at' => $now,
            );
            if ($exists) $wpdb->update($t['rules'], $row, array('id'=>$exists));
            else { $row['created_at'] = $now; $wpdb->insert($t['rules'], $row); }
        }
    }

    // CALENDAR
    public static function cal_meta_keys() {
        return array(
            'gp'           => '_f1cal_gp',
            'weekend_type' => '_f1cal_weekend_type',
            'quali_date'   => '_f1cal_quali_date',
            'quali_time'   => '_f1cal_quali_time',
            'race_date'    => '_f1cal_race_date',
            'race_time'    => '_f1cal_race_time',
            'sq_date'      => '_f1cal_sq_date',
            'sq_time'      => '_f1cal_sq_time',
            'sprint_date'  => '_f1cal_sprint_date',
            'sprint_time'  => '_f1cal_sprint_time',
        );
    }
    public static function get_race_session_start_ts($race_post_id, $session_slug) {
        $race_post_id = (int)$race_post_id;
        $k = self::cal_meta_keys();
        $map = array(
            'race'   => array($k['race_date'], $k['race_time']),
            'sprint' => array($k['sprint_date'], $k['sprint_time']),
            'quali'  => array($k['quali_date'], $k['quali_time']),
            'sq'     => array($k['sq_date'], $k['sq_time']),
        );
        if (!isset($map[$session_slug])) return 0;
        $d = (string)get_post_meta($race_post_id, $map[$session_slug][0], true);
        $t = (string)get_post_meta($race_post_id, $map[$session_slug][1], true);
        $d = trim($d); $t = trim($t);
        if ($d === '' || $t === '') return 0;
        $tz = wp_timezone();
        try {
            $dt = new DateTime($d.' '.$t, $tz);
            return $dt->getTimestamp();
        } catch (Exception $e) { return 0; }
    }

    public static function session_state_meta_key($session_slug) { return '_f1tips_state_' . sanitize_key($session_slug); }
    public static function get_session_state($race_post_id, $session_slug) {
        $race_post_id = (int)$race_post_id;
        $session_slug = sanitize_key($session_slug);
        $ts = self::get_race_session_start_ts($race_post_id, $session_slug);
        if (!$ts) return 'na';
        $key = self::session_state_meta_key($session_slug);
        $v = (string)get_post_meta($race_post_id, $key, true);
        $v = sanitize_key($v);
        if (!in_array($v, array('open','closed','ended'), true)) $v = 'closed';
        return $v;
    }
    public static function set_session_state($race_post_id, $session_slug, $state) {
        $race_post_id = (int)$race_post_id;
        $session_slug = sanitize_key($session_slug);
        $state = sanitize_key($state);
        $ts = self::get_race_session_start_ts($race_post_id, $session_slug);
        if (!$ts) return false;
        if (!in_array($state, array('open','closed','ended'), true)) $state = 'closed';
        update_post_meta($race_post_id, self::session_state_meta_key($session_slug), $state);
        return true;
    }
    public static function is_locked($race_post_id, $session_slug) {
        $state = self::get_session_state((int)$race_post_id, (string)$session_slug);
        if ($state === 'na') return false;
        return ($state === 'closed' || $state === 'ended');
    }

    // DRIVER
    public static function norm_key($s) {
        $s = (string)$s; $s = wp_strip_all_tags($s); $s = trim($s);
        $s = remove_accents($s); $s = mb_strtolower($s);
        $s = preg_replace('~[^a-z0-9]+~', ' ', $s);
        $s = trim(preg_replace('~\s+~', ' ', $s));
        return $s;
    }
    public static function driver_is_available($driver_post_id) {
        $pid = (int)$driver_post_id;
        if ($pid <= 0) return false;
        if (get_post_type($pid) !== 'f1_driver') return false;
        if (get_post_status($pid) !== 'publish') return false;

        $vPrimary = get_post_meta($pid, '_f1drv_team_inactive', true);
        if ($vPrimary === '1' || $vPrimary === 1 || $vPrimary === true || $vPrimary === 'true' || $vPrimary === 'on' || $vPrimary === 'yes') return false;

        $inactive_like = array('_f1drv_inactive','_f1driver_inactive','f1drv_inactive','inactive','_inactive');
        foreach ($inactive_like as $k) {
            $v = get_post_meta($pid, $k, true);
            if ($v === '1' || $v === 1 || $v === true || $v === 'true' || $v === 'on' || $v === 'yes') return false;
        }
        $active_like = array('_f1drv_active','active');
        foreach ($active_like as $k) {
            $v = get_post_meta($pid, $k, true);
            if ($v === '0' || $v === 0 || $v === false || $v === 'false' || $v === 'off' || $v === 'no') return false;
        }
        $team_keys = array('_f1drv_team_id','_f1drv_team','team_id','team','_team_id');
        foreach ($team_keys as $tk) {
            $tv = get_post_meta($pid, $tk, true);
            if (is_numeric($tv) && (int)$tv > 0) return true;
            if (is_string($tv) && trim($tv) !== '' && trim($tv) !== '0') return true;
            if (is_array($tv) && !empty($tv)) return true;
        }
        return false;
    }
    public static function driver_directory() {
        static $dir = null;
        if (is_array($dir)) return $dir;
        $dir = array();
        $q = new WP_Query(array('post_type' => 'f1_driver','post_status' => 'publish','posts_per_page' => -1,'fields' => 'ids','orderby' => 'title','order' => 'ASC','no_found_rows' => true));
        foreach ($q->posts as $pid) {
            if (!self::driver_is_available($pid)) continue;
            $title = get_the_title($pid);
            $slug_meta = (string)get_post_meta($pid, '_f1drv_slug', true);
            $keys = array();
            $keys[] = self::norm_key($title);
            if ($slug_meta) $keys[] = self::norm_key($slug_meta);
            $parts = preg_split('~\s+~', self::norm_key($title));
            if (!empty($parts)) {
                $keys[] = end($parts);
                if (count($parts) >= 2) $keys[] = $parts[count($parts)-2].' '.end($parts);
            }
            foreach (array_unique(array_filter($keys)) as $k) {
                if (!isset($dir[$k])) $dir[$k] = (int)$pid;
            }
        }
        return $dir;
    }
    public static function match_driver_to_post_id($driver_name) {
        $key = self::norm_key((string)$driver_name);
        if ($key === '') return 0;
        $dir = self::driver_directory();
        if (isset($dir[$key])) return (int)$dir[$key];
        $parts = preg_split('~\s+~', $key);
        if (!empty($parts)) {
            $ln = end($parts);
            if (isset($dir[$ln])) return (int)$dir[$ln];
        }
        return 0;
    }

    // TEAM
    public static function team_is_available($team_post_id) {
        $pid = (int)$team_post_id;
        if ($pid <= 0) return false;
        if (get_post_type($pid) !== 'f1_team') return false;
        if (get_post_status($pid) !== 'publish') return false;
        return true;
    }
    public static function team_directory() {
        static $dir = null;
        if (is_array($dir)) return $dir;
        $dir = array();
        $q = new WP_Query(array('post_type'=>'f1_team','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','orderby'=>array('menu_order'=>'ASC','ID'=>'ASC'),'no_found_rows'=>true));
        foreach ((array)$q->posts as $pid) {
            $pid = (int)$pid;
            if (!self::team_is_available($pid)) continue;
            $title = get_the_title($pid);
            $slug_meta = (string)get_post_meta($pid, '_f1team_slug', true);
            $keys = array();
            $keys[] = self::norm_key($title);
            if ($slug_meta) $keys[] = self::norm_key($slug_meta);
            foreach (array_unique(array_filter($keys)) as $k) {
                if (!isset($dir[$k])) $dir[$k] = $pid;
            }
        }
        return $dir;
    }
    public static function match_team_to_post_id($team_name_or_slug) {
        $key = self::norm_key((string)$team_name_or_slug);
        if ($key === '') return 0;
        $dir = self::team_directory();
        return isset($dir[$key]) ? (int)$dir[$key] : 0;
    }

    // ROUNDS
    public static function round_id_for_race($season_id, $race_post_id) {
        global $wpdb; $t = self::tables();
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['rounds']} WHERE season_id=%d AND race_post_id=%d",
            (int)$season_id, (int)$race_post_id
        ));
    }
    public static function ensure_round($season_id, $race_post_id) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        $race_post_id = (int)$race_post_id;
        $rid = self::round_id_for_race($season_id, $race_post_id);
        if ($rid) return $rid;
        $gp = (string)get_post_meta($race_post_id, '_f1cal_gp', true);
        $name = $gp !== '' ? $gp : get_the_title($race_post_id);
        $wpdb->insert($t['rounds'], array(
            'season_id' => $season_id,
            'race_post_id' => $race_post_id,
            'name' => $name,
            'status' => 'open',
            'created_by' => get_current_user_id(),
            'created_at' => self::now_mysql(),
        ), array('%d','%d','%s','%s','%d','%s'));
        return (int)$wpdb->insert_id;
    }

    // RESULTS
    public static function get_auto_result_from_wm_meta($race_post_id, $session_slug, $topN) {
        $key = '_f1wms_rows_' . sanitize_key($session_slug);
        $rows = get_post_meta((int)$race_post_id, $key, true);
        if (empty($rows)) return array();
        if (is_string($rows)) {
            $maybe = json_decode($rows, true);
            if (is_array($maybe)) $rows = $maybe;
        }
        if (!is_array($rows)) return array();
        $out = array();
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $pos = isset($r['pos']) ? (string)$r['pos'] : '';
            $drv = isset($r['driver']) ? (string)$r['driver'] : '';
            $p = (int)preg_replace('~[^\d]~', '', $pos);
            if ($p <= 0 || $p > (int)$topN) continue;
            $pid = self::match_driver_to_post_id($drv);
            if ($pid > 0) $out[$p] = $pid;
        }
        if (empty($out)) return array();
        ksort($out);
        $res = array();
        for ($i=1; $i<=$topN; $i++) {
            if (!isset($out[$i])) return array();
            $res[] = (int)$out[$i];
        }
        return $res;
    }
    public static function wm_has_result($race_post_id, $session_slug, $topN) {
        $res = self::get_auto_result_from_wm_meta((int)$race_post_id, (string)$session_slug, (int)$topN);
        return (is_array($res) && count($res) === (int)$topN);
    }
    public static function get_result($round_id, $session_slug, $topN) {
        global $wpdb; $t = self::tables();
        $round_id = (int)$round_id;
        $session_slug = sanitize_key($session_slug);
        $json = $wpdb->get_var($wpdb->prepare(
            "SELECT result_json FROM {$t['results']} WHERE round_id=%d AND session_slug=%s",
            $round_id, $session_slug
        ));
        if ($json) {
            $arr = json_decode($json, true);
            if (is_array($arr) && count($arr) === (int)$topN) return array_map('intval', $arr);
        }
        $race_post_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT race_post_id FROM {$t['rounds']} WHERE id=%d",
            $round_id
        ));
        if (!$race_post_id) return array();
        return self::get_auto_result_from_wm_meta($race_post_id, $session_slug, $topN);
    }
    public static function upsert_result_override($round_id, $session_slug, array $result_ids, $source='manual') {
        global $wpdb; $t = self::tables();
        $round_id = (int)$round_id;
        $session_slug = sanitize_key($session_slug);
        $json = wp_json_encode(array_values(array_map('intval', $result_ids)));
        $now = self::now_mysql();
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['results']} WHERE round_id=%d AND session_slug=%s",
            $round_id, $session_slug
        ));
        if ($exists) {
            $wpdb->update($t['results'], array(
                'result_json' => $json,
                'source' => $source,
                'imported_at' => $now,
            ), array('id'=>$exists));
        } else {
            $wpdb->insert($t['results'], array(
                'round_id' => $round_id,
                'session_slug' => $session_slug,
                'result_json' => $json,
                'source' => $source,
                'imported_at' => $now,
            ));
        }
    }

    // SCORING
    public static function get_rules($season_id, $session_type) {
		static $cache = array();
		$key = $season_id . '|' . $session_type;
		if ( isset( $cache[$key] ) ) return $cache[$key];

        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        $session_type = sanitize_key($session_type);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t['rules']} WHERE season_id=%d AND session_type=%s",
            $season_id, $session_type
        ), ARRAY_A);
        if (!$row) {
			if ($session_type === 'race') $res = array('exact'=>array(8,7,6,5,4,3,2,1),'wrong_mode'=>'absolute','wrong_points'=>1,'rel_penalty'=>1);
			else $res = array('exact'=>array(4,3,2,1),'wrong_mode'=>'absolute','wrong_points'=>1,'rel_penalty'=>1);
		} else {
			$exact = json_decode($row['exact_points_json'], true);
			if (!is_array($exact)) $exact = array();
			$res = array(
				'exact' => array_map('intval', array_values($exact)),
				'wrong_mode' => $row['wrong_mode'],
				'wrong_points' => (int)$row['wrong_points'],
				'rel_penalty' => (int)$row['rel_penalty'],
			);
        }
		$cache[$key] = $res;
		return $res;
    }
    public static function score_tip(array $tip, array $result, array $rules) {
        $N = count($result);
        if (count($tip) !== $N) return 0;
        $exactPts = isset($rules['exact']) ? $rules['exact'] : array();
        $wrongMode = $rules['wrong_mode'] ?? 'absolute';
        $wrongPoints = (int)($rules['wrong_points'] ?? 1);
        $relPenalty = (int)($rules['rel_penalty'] ?? 1);

        $posOf = array();
        for ($i=0; $i<$N; $i++) $posOf[(int)$result[$i]] = $i+1;

        $sum = 0;
        for ($i=0; $i<$N; $i++) {
            $drv = (int)$tip[$i];
            if ($drv <= 0) continue;
            $posTip = $i+1;
            $posRes = $posOf[$drv] ?? 0;
            if ($posRes === 0) continue;

            if ($posRes === $posTip) { $sum += (int)($exactPts[$i] ?? 0); continue; }

            if ($wrongMode === 'relative') {
                $base = (int)($exactPts[$i] ?? 0);
                $diff = abs($posRes - $posTip);
                $p = max(0, $base - ($relPenalty * $diff));
                $sum += $p;
            } else {
                $sum += $wrongPoints;
            }
        }
        return (int)$sum;
    }

    // TIPS
    public static function ensure_member($league_id, $user_id) {
        global $wpdb; $t = self::tables();
        $league_id = (int)$league_id; $user_id = (int)$user_id;
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$t['members']} WHERE league_id=%d AND user_id=%d",
            $league_id, $user_id
        ));
        if ($exists) return;
        $wpdb->insert($t['members'], array(
            'league_id' => $league_id,
            'user_id' => $user_id,
            'role' => 'member',
            'joined_at' => self::now_mysql(),
        ), array('%d','%d','%s','%s'));
    }
    public static function validate_tip_unique(array $ids) {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        return count($ids) === count(array_unique($ids));
    }
    public static function upsert_tip($round_id, $league_id, $user_id, $session_slug, array $tip_ids, $locked_at = null) {
        global $wpdb; $t = self::tables();
        $round_id = (int)$round_id;
        $league_id = (int)$league_id;
        $user_id = (int)$user_id;
        $session_slug = sanitize_key($session_slug);

        $json = wp_json_encode(array_values(array_map('intval', $tip_ids)));
        $now = self::now_mysql();
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['tips']} WHERE round_id=%d AND league_id=%d AND user_id=%d AND session_slug=%s",
            $round_id, $league_id, $user_id, $session_slug
        ));
        $data = array('tip_json' => $json, 'updated_at' => $now);
        $formats = array('%s','%s');
        if ($locked_at !== null) { $data['locked_at'] = $locked_at; $formats[] = '%s'; }
        if ($exists) {
            $wpdb->update($t['tips'], $data, array('id'=>$exists), $formats);
            return $exists;
        }
        $data = array_merge($data, array(
            'round_id' => $round_id,
            'league_id' => $league_id,
            'user_id' => $user_id,
            'session_slug' => $session_slug,
            'locked_at' => $locked_at,
            'created_at' => $now,
        ));
        $wpdb->insert($t['tips'], $data, array('%s','%s','%d','%d','%d','%s','%s','%s'));
        return (int)$wpdb->insert_id;
    }

    // BONUS
    public static function bonus_is_locked_row(array $q) {
        $status = sanitize_key($q['status'] ?? 'open');
        if ($status !== 'open') return true;
        $ts = 0;
        if (isset($q['closes_at']) && $q['closes_at']) {
            $tz = wp_timezone();
            try { $dt = new DateTime($q['closes_at'], $tz); $ts = $dt->getTimestamp(); } catch(Exception $e){}
        }
        if ($ts && time() >= $ts) return true;
        return false;
    }
    public static function bonus_is_scored_row(array $q) {
        $status = sanitize_key($q['status'] ?? 'open');
        $cv = trim((string)($q['correct_value'] ?? ''));
        return ($status === 'revealed' && $cv !== '');
    }
    public static function bonus_preset_definitions() {
        return array(
            'driver_wc' => array('label' => 'Fahrer-Weltmeister', 'question_text' => 'Wer wird Fahrer-Weltmeister?', 'question_type' => 'driver'),
            'team_wc' => array('label' => 'Konstrukteurs-Weltmeister', 'question_text' => 'Wer wird Konstrukteurs-Weltmeister?', 'question_type' => 'team'),
        );
    }
    public static function bonus_get_preset_question($season_id, $league_id, $preset_key) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        $league_id = (int)$league_id;
        $defs = self::bonus_preset_definitions();
        if (!isset($defs[$preset_key])) return null;
        $qt = (string)$defs[$preset_key]['question_text'];
        $type = (string)$defs[$preset_key]['question_type'];
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t['bonus_q']} WHERE season_id=%d AND (league_id IS NULL OR league_id=%d) AND question_type=%s AND question_text=%s ORDER BY id DESC LIMIT 1",
            $season_id, $league_id, $type, $qt
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }
    public static function bonus_delete_question($question_id) {
        global $wpdb; $t = self::tables();
        $question_id = (int)$question_id;
        if ($question_id <= 0) return false;
        $wpdb->delete($t['bonus_a'], array('question_id'=>$question_id), array('%d'));
        $wpdb->delete($t['bonus_q'], array('id'=>$question_id), array('%d'));
        return true;
    }
    public static function bonus_upsert_answer($question_id, $user_id, $answer_value) {
        global $wpdb; $t = self::tables();
        $question_id = (int)$question_id;
        $user_id = (int)$user_id;
        $answer_value = sanitize_text_field((string)$answer_value);
        if ($question_id <= 0 || $user_id <= 0 || $answer_value === '') return 0;
        $now = self::now_mysql();
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['bonus_a']} WHERE question_id=%d AND user_id=%d",
            $question_id, $user_id
        ));
        if ($exists) {
            $wpdb->update($t['bonus_a'], array('answer_value' => $answer_value, 'updated_at' => $now), array('id'=>$exists));
            return $exists;
        }
        $wpdb->insert($t['bonus_a'], array('question_id' => $question_id, 'user_id' => $user_id, 'answer_value' => $answer_value, 'created_at' => $now, 'updated_at' => $now));
        return (int)$wpdb->insert_id;
    }
    public static function bonus_get_questions($season_id, $league_id) {
        global $wpdb; $t = self::tables();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t['bonus_q']} WHERE season_id=%d AND (league_id IS NULL OR league_id=%d)",
            $season_id, $league_id
        ), ARRAY_A);
    }

    public static function bonus_points_map($season_id, $league_id, array $user_ids) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        $league_id = (int)$league_id;
        $uid_list = array_values(array_filter(array_map('intval', $user_ids)));
        $out = array();
        foreach ($uid_list as $uid) $out[$uid] = 0;
        if (!$season_id || !$league_id || empty($uid_list)) return $out;

        $qs = self::bonus_get_questions($season_id, $league_id);
        if (!$qs) return $out;

        $qmap = array(); $qids = array();
        foreach ($qs as $q) {
            if (!self::bonus_is_scored_row($q)) continue;
            $qid = (int)($q['id'] ?? 0);
            $cv = trim((string)($q['correct_value'] ?? ''));
            if ($qid > 0 && $cv !== '') {
                $qmap[$qid] = array(
                    'points' => (int)($q['points'] ?? 0),
                    'correct' => $cv,
                    'type' => sanitize_key($q['question_type'] ?? 'select')
                );
                $qids[] = $qid;
            }
        }
        if (!$qids) return $out;
        $phQ = implode(',', array_fill(0, count($qids), '%d'));
        $phU = implode(',', array_fill(0, count($uid_list), '%d'));
        $sql = $wpdb->prepare("SELECT user_id, question_id, answer_value FROM {$t['bonus_a']} WHERE question_id IN ($phQ) AND user_id IN ($phU)", array_merge($qids, $uid_list));
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!$rows) return $out;

        $teamTitleCache = array();
        $teamSlugCache  = array();
        $get_team_title = function($team_id) use (&$teamTitleCache) {
            $team_id = (int)$team_id; if ($team_id <= 0) return '';
            if (!isset($teamTitleCache[$team_id])) $teamTitleCache[$team_id] = (string)get_the_title($team_id);
            return (string)$teamTitleCache[$team_id];
        };
        $get_team_slug = function($team_id) use (&$teamSlugCache) {
            $team_id = (int)$team_id; if ($team_id <= 0) return '';
            if (!isset($teamSlugCache[$team_id])) $teamSlugCache[$team_id] = (string)get_post_meta($team_id, '_f1team_slug', true);
            return (string)$teamSlugCache[$team_id];
        };

        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            $qid = (int)$r['question_id'];
            $av  = trim((string)$r['answer_value']);
            if ($av === '' || !isset($qmap[$qid])) continue;
            $correct = (string)$qmap[$qid]['correct'];
            $type    = (string)$qmap[$qid]['type'];
            $match = false;
            if ($type === 'driver') {
                if (is_numeric($correct)) {
                    $cid = (int)$correct;
                    if (is_numeric($av) && (int)$av === $cid) $match = true;
                    else {
                        $cTitle = $cid > 0 ? (string)get_the_title($cid) : '';
                        $cSlug  = $cid > 0 ? (string)get_post_meta($cid, '_f1drv_slug', true) : '';
                        if ($cTitle && self::norm_key($cTitle) === self::norm_key($av)) $match = true;
                        elseif ($cSlug && self::norm_key($cSlug) === self::norm_key($av)) $match = true;
                        else {
                            $aid = self::match_driver_to_post_id($av);
                            if ($aid > 0 && $aid === $cid) $match = true;
                        }
                    }
                } else {
                    if ($correct === $av) $match = true;
                    else if (self::norm_key($correct) === self::norm_key($av)) $match = true;
                    else {
                        if (is_numeric($av)) {
                            $aid = (int)$av;
                            $aTitle = $aid > 0 ? (string)get_the_title($aid) : '';
                            $aSlug  = $aid > 0 ? (string)get_post_meta($aid, '_f1drv_slug', true) : '';
                            if ($aTitle && self::norm_key($aTitle) === self::norm_key($correct)) $match = true;
                            elseif ($aSlug && self::norm_key($aSlug) === self::norm_key($correct)) $match = true;
                        }
                    }
                }
            }
            else if ($type === 'team') {
                if (is_numeric($correct)) {
                    $cid = (int)$correct;
                    if (is_numeric($av) && (int)$av === $cid) $match = true;
                    else {
                        $tTitle = $get_team_title($cid);
                        $tSlug  = $get_team_slug($cid);
                        if ($tTitle && self::norm_key($tTitle) === self::norm_key($av)) $match = true;
                        elseif ($tSlug && self::norm_key($tSlug) === self::norm_key($av)) $match = true;
                    }
                } else {
                    if ($correct === $av) $match = true;
                    else {
                        if (is_numeric($av)) {
                            $aid = (int)$av;
                            $tTitle = $get_team_title($aid);
                            $tSlug  = $get_team_slug($aid);
                            if ($tTitle && self::norm_key($tTitle) === self::norm_key($correct)) $match = true;
                            elseif ($tSlug && self::norm_key($tSlug) === self::norm_key($correct)) $match = true;
                        } else {
                            if (self::norm_key($correct) === self::norm_key($av)) $match = true;
                        }
                    }
                }
            }
            else {
                $match = ((string)$av !== '' && (string)$correct !== '' && (string)$av === (string)$correct);
            }
            if ($match) $out[$uid] += (int)$qmap[$qid]['points'];
        }
        return $out;
    }

    public static function rebuild_scores_cache($season_id, $league_id) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        $league_id = (int)$league_id;

        $members = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$t['members']} WHERE league_id=%d", $league_id));
        if (!$members) return;

        // Init scores
        $tot = array(); $wins = array();
        foreach ($members as $uid) { $tot[(int)$uid]=0; $wins[(int)$uid]=0; }

        // Fetch Rounds
        $rounds = $wpdb->get_results($wpdb->prepare("SELECT id, race_post_id FROM {$t['rounds']} WHERE season_id=%d", $season_id), ARRAY_A);

        $round_ids = array_column($rounds, 'id');
        if (empty($round_ids)) return;

        // Optimized: Fetch ALL Tips for these rounds/league in ONE query
        // This avoids N+1 queries.
        $placeholders = implode(',', array_fill(0, count($round_ids), '%d'));
        $sqlTips = $wpdb->prepare(
            "SELECT round_id, user_id, session_slug, tip_json FROM {$t['tips']}
             WHERE league_id=%d AND round_id IN ($placeholders)",
            array_merge(array($league_id), $round_ids)
        );
        $allTips = $wpdb->get_results($sqlTips, ARRAY_A);

        // Organize tips: [round_id][session_slug][user_id] = tip_array
        $tipsMap = array();
        foreach ($allTips as $tipRow) {
            $rid = (int)$tipRow['round_id'];
            $uid = (int)$tipRow['user_id'];
            $slug = (string)$tipRow['session_slug'];
            $arr = json_decode($tipRow['tip_json'], true);
            if (is_array($arr)) {
                $tipsMap[$rid][$slug][$uid] = array_map('intval', $arr);
            }
        }

        foreach ($rounds as $r) {
            $round_id = (int)$r['id'];
            $race_post_id = (int)$r['race_post_id'];

            $sessions = array(
                'quali'  => array('type'=>'quali','top'=>4),
                'sq'     => array('type'=>'quali','top'=>4),
                'sprint' => array('type'=>'race','top'=>8),
                'race'   => array('type'=>'race','top'=>8),
            );
            $roundPoints = array();
            foreach ($members as $uid) $roundPoints[(int)$uid]=0;

            foreach ($sessions as $slug => $cfg) {
                // Check state & result - these are cached or simple calls, OK.
                $ts = self::get_race_session_start_ts($race_post_id, $slug);
                if (!$ts) continue;
                $state = self::get_session_state($race_post_id, $slug);
                if ($state !== 'ended') continue;

                $result = self::get_result($round_id, $slug, (int)$cfg['top']);
                if (count($result) !== (int)$cfg['top']) continue;

                $rules = self::get_rules($season_id, $cfg['type']);

                // Process in memory
                if (isset($tipsMap[$round_id][$slug])) {
                    foreach ($tipsMap[$round_id][$slug] as $uid => $tip) {
                        if (count($tip) !== (int)$cfg['top']) continue;
                        if (!isset($roundPoints[$uid])) continue; // Should be in members
                        $roundPoints[$uid] += self::score_tip($tip, $result, $rules);
                    }
                }
            }

            $max = null;
            foreach ($roundPoints as $uid => $p) if ($max === null || $p > $max) $max = $p;
            if ($max !== null && $max > 0) foreach ($roundPoints as $uid => $p) if ($p === $max) $wins[(int)$uid] += 1;
            foreach ($roundPoints as $uid => $p) $tot[(int)$uid] += (int)$p;
        }

        $bonusMap = self::bonus_points_map($season_id, $league_id, array_map('intval', (array)$members));
        foreach ($members as $uid) {
            $uid = (int)$uid;
            $tot[$uid] = (int)$tot[$uid] + (int)($bonusMap[$uid] ?? 0);
        }

        $now = self::now_mysql();
        foreach ($members as $uid) {
            $uid = (int)$uid;
            $wpdb->replace($t['scores'], array(
                'league_id' => $league_id,
                'season_id' => $season_id,
                'user_id' => $uid,
                'points_total' => (int)$tot[$uid],
                'wins_total' => (int)$wins[$uid],
                'updated_at' => $now,
            ), array('%d','%d','%d','%d','%d','%s'));
        }
    }

    public static function backup_export_payload_single($season_id, $league_id) {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        $league_id = (int)$league_id;
        $league = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['leagues']} WHERE id=%d AND season_id=%d", $league_id, $season_id), ARRAY_A);
        if (!$league) return null;
        $members = $wpdb->get_results($wpdb->prepare("SELECT user_id, role FROM {$t['members']} WHERE league_id=%d ORDER BY user_id ASC", $league_id), ARRAY_A);
        $tips = $wpdb->get_results($wpdb->prepare("SELECT round_id, user_id, session_slug, tip_json FROM {$t['tips']} WHERE league_id=%d ORDER BY round_id ASC, session_slug ASC, user_id ASC", $league_id), ARRAY_A);
        $roundIds = array();
        foreach ($tips as $row) $roundIds[(int)$row['round_id']] = true;
        $roundIds = array_keys($roundIds);
        $roundMap = array();
        if (!empty($roundIds)) {
            $placeholders = implode(',', array_fill(0, count($roundIds), '%d'));
            $sql = $wpdb->prepare("SELECT id, race_post_id FROM {$t['rounds']} WHERE season_id=%d AND id IN ($placeholders)", array_merge(array($season_id), $roundIds));
            $rows = $wpdb->get_results($sql, ARRAY_A);
            foreach ($rows as $r) $roundMap[(int)$r['id']] = (int)$r['race_post_id'];
        }
        $tipsOut = array();
        foreach ($tips as $row) {
            $rid = (int)$row['round_id'];
            $race_post_id = isset($roundMap[$rid]) ? (int)$roundMap[$rid] : 0;
            if (!$race_post_id) continue;
            $arr = json_decode((string)$row['tip_json'], true);
            if (!is_array($arr)) $arr = array();
            $tipsOut[] = array(
                'race_post_id' => $race_post_id,
                'session_slug' => (string)$row['session_slug'],
                'user_id' => (int)$row['user_id'],
                'tip' => array_map('intval', $arr),
            );
        }
        $seasonYear = self::get_year_for_season_id($season_id);
        return array(
            'ver' => 'f1tips-backup-v2-single',
            'year' => (int)$seasonYear,
            'season_id' => $season_id,
            'exported_at' => self::now_mysql(),
            'league' => array('name' => (string)$league['name'], 'slug' => (string)$league['slug']),
            'members' => array_map(function($m){ return array('user_id' => (int)$m['user_id'], 'role' => (string)$m['role']); }, is_array($members) ? $members : array()),
            'tips' => $tipsOut,
        );
    }

    public static function backup_import_payload_single($season_id, $league_id, array $payload, $mode='merge') {
        global $wpdb; $t = self::tables();
        $season_id = (int)$season_id;
        $league_id = (int)$league_id;
        $mode = sanitize_key($mode);
        if (!in_array($mode, array('merge','overwrite'), true)) $mode = 'merge';
        if (!isset($payload['ver']) || (string)$payload['ver'] !== 'f1tips-backup-v2-single') return array('ok'=>false,'message'=>'Ungültiges Backup-Format (ver).');

        if ($mode === 'overwrite') {
            $wpdb->delete($t['members'], array('league_id'=>$league_id));
            $wpdb->delete($t['tips'], array('league_id'=>$league_id));
            $wpdb->delete($t['scores'], array('league_id'=>$league_id, 'season_id'=>$season_id), array('%d','%d'));
        }
        $members = (isset($payload['members']) && is_array($payload['members'])) ? $payload['members'] : array();
        foreach ($members as $m) {
            if (!is_array($m)) continue;
            $uid = (int)($m['user_id'] ?? 0);
            if ($uid <= 0) continue;
            $role = sanitize_key($m['role'] ?? 'member');
            if (!in_array($role, array('member','admin','owner'), true)) $role = 'member';
            $wpdb->replace($t['members'], array('league_id' => $league_id, 'user_id' => $uid, 'role' => 'member', 'joined_at' => self::now_mysql()), array('%d','%d','%s','%s'));
        }
        $tips = (isset($payload['tips']) && is_array($payload['tips'])) ? $payload['tips'] : array();
        $sessN = array('quali'=>4,'sq'=>4,'sprint'=>8,'race'=>8);
        $imported = 0;
        foreach ($tips as $row) {
            if (!is_array($row)) continue;
            $race_post_id = (int)($row['race_post_id'] ?? 0);
            $session_slug = sanitize_key($row['session_slug'] ?? '');
            $uid = (int)($row['user_id'] ?? 0);
            $tip = (isset($row['tip']) && is_array($row['tip'])) ? $row['tip'] : array();
            if (!$race_post_id || !$uid || !$session_slug) continue;
            if (!isset($sessN[$session_slug])) continue;
            $N = (int)$sessN[$session_slug];
            $tip_ids = array_slice(array_map('intval', $tip), 0, $N);
            if (count($tip_ids) !== $N) continue;
            if (!self::validate_tip_unique($tip_ids)) continue;
            foreach ($tip_ids as $did) if (!self::driver_is_available($did)) continue 2;
            $round_id = self::ensure_round($season_id, $race_post_id);
            $wpdb->replace($t['members'], array('league_id' => $league_id, 'user_id' => $uid, 'role' => 'member', 'joined_at' => self::now_mysql()), array('%d','%d','%s','%s'));
            self::upsert_tip($round_id, $league_id, $uid, $session_slug, $tip_ids, null);
            $imported++;
        }
        return array('ok'=>true,'message'=>'Import abgeschlossen.','imported_tips'=>$imported);
    }

    /* =========================================================
       REST API
       ========================================================= */

    public function register_api() {
        $register_with_trailing = function($namespace, $route, $args) {
            register_rest_route($namespace, $route, $args);
            $route2 = rtrim($route, '/') . '/';
            if ($route2 !== $route) register_rest_route($namespace, $route2, $args);
        };

        $register_with_trailing('f1tips/v1', '/context', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'api_get_context' ),
        ));

        $register_with_trailing('f1tips/v1', '/leaderboard', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'api_get_leaderboard' ),
        ));

        $register_with_trailing('f1tips/v1', '/tip', array(
            'methods' => array('POST', 'PUT'),
            'permission_callback' => function () { return is_user_logged_in(); },
            'callback' => array( $this, 'api_save_tip' ),
        ));

        $register_with_trailing('f1tips/v1', '/tips', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array( $this, 'api_get_tips' ),
        ));

        $register_with_trailing('f1tips/v1', '/bonus-answer', array(
            'methods' => array('POST','PUT'),
            'permission_callback' => function () { return is_user_logged_in(); },
            'callback' => array( $this, 'api_save_bonus_answer' ),
        ));
    }

    public function api_get_context(WP_REST_Request $req) {
        $season_id = self::get_active_season_id();
        $year = self::get_year_for_season_id($season_id);
        $league_id = self::single_league_id($season_id);

        $qs = self::bonus_get_questions($season_id, $league_id);
        $openCount = 0;
        foreach ((array)$qs as $q) if (!self::bonus_is_locked_row($q)) $openCount++;

        return array(
            'year' => $year,
            'season_id' => $season_id,
            'league_slug' => 'gesamt',
            'league_id' => (int)$league_id,
            'user_id' => get_current_user_id(),
            'logged_in' => is_user_logged_in(),
            'nonce' => wp_create_nonce('wp_rest'),
            'bonus_open_count' => (int)$openCount,
        );
    }

    public function api_get_leaderboard(WP_REST_Request $req) {
        global $wpdb; $t = self::tables();

        $season_id = self::get_active_season_id();
        $league_id = self::single_league_id($season_id);

        $has = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['scores']} WHERE season_id=%d AND league_id=%d", $season_id, $league_id));
        if ($has === 0) self::rebuild_scores_cache($season_id, $league_id);

        $rows = $wpdb->get_results($wpdb->prepare("SELECT user_id, points_total, wins_total FROM {$t['scores']} WHERE season_id=%d AND league_id=%d", $season_id, $league_id), ARRAY_A);
        $items = array();
        foreach ((array)$rows as $r) {
            $uid = (int)$r['user_id'];
            $items[] = array(
                'user_id' => $uid,
                'display_name' => (string) get_the_author_meta('display_name', $uid),
                'avatar' => function_exists('f1fp_get_custom_avatar_url')
                    ? (string) f1fp_get_custom_avatar_url($uid, 64)
                    : (string) get_avatar_url($uid, array('size'=>64)),
                'points' => (int)$r['points_total'],
                'wins' => (int)$r['wins_total'],
            );
        }
        usort($items, function($a,$b){
            if ((int)$b['points'] !== (int)$a['points']) return (int)$b['points'] - (int)$a['points'];
            if ((int)$b['wins'] !== (int)$a['wins']) return (int)$b['wins'] - (int)$a['wins'];
            return strcasecmp((string)$a['display_name'], (string)$b['display_name']);
        });
        return array('ok' => true,'items' => $items);
    }

    public function api_save_tip(WP_REST_Request $req) {
        $user_id = get_current_user_id();
        $season_id = self::get_active_season_id();
        $league_id = self::single_league_id($season_id);

        $race_post_id = (int)($req->get_param('race_post_id') ?? 0);
        $session_slug = sanitize_key($req->get_param('session_slug') ?? '');
        $tip = $req->get_param('tip');

        if (!$race_post_id || !$session_slug || !is_array($tip)) {
            return new WP_REST_Response(array('ok'=>false,'message'=>'Ungültige Daten'), 400);
        }

        $session_cfg = array('quali'=>4,'sq'=>4,'sprint'=>8,'race'=>8);
        if (!isset($session_cfg[$session_slug])) return new WP_REST_Response(array('ok'=>false,'message'=>'Unbekannte Session'), 400);
        $N = (int)$session_cfg[$session_slug];

        $tip_ids = array_slice(array_map('intval', $tip), 0, $N);
        if (count($tip_ids) !== $N) return new WP_REST_Response(array('ok'=>false,'message'=>'Tipp unvollständig'), 400);
        if (!self::validate_tip_unique($tip_ids)) return new WP_REST_Response(array('ok'=>false,'message'=>'Keine Duplikate erlaubt'), 400);

        foreach ($tip_ids as $did) {
            if (!self::driver_is_available($did)) return new WP_REST_Response(array('ok'=>false,'message'=>'Ungültiger Fahrer im Tipp.'), 400);
        }
        if (self::is_locked($race_post_id, $session_slug)) return new WP_REST_Response(array('ok'=>false,'message'=>'Tippzeit ist abgelaufen.'), 403);

        $round_id = self::ensure_round($season_id, $race_post_id);
        self::ensure_member($league_id, $user_id);

        $id = self::upsert_tip($round_id, $league_id, $user_id, $session_slug, $tip_ids, null);
        return array('ok'=>true, 'tip_id'=>(int)$id);
    }

    public function api_get_tips(WP_REST_Request $req) {
        global $wpdb; $t = self::tables();
        $season_id = self::get_active_season_id();
        $league_id = self::single_league_id($season_id);

        $race_post_id = (int)($req->get_param('race_post_id') ?? 0);
        if ($race_post_id <= 0) return new WP_REST_Response(array('ok'=>false,'message'=>'race_post_id fehlt'), 400);

        $round_id = self::ensure_round($season_id, $race_post_id);

        $session_cfg = array(
            'quali'  => array('type'=>'quali','top'=>4),
            'sq'     => array('type'=>'quali','top'=>4),
            'sprint' => array('type'=>'race','top'=>8),
            'race'   => array('type'=>'race','top'=>8),
        );

        $locked = array();
        foreach (array_keys($session_cfg) as $slug) $locked[$slug] = self::is_locked($race_post_id, $slug);

        $states = array();
        foreach (array_keys($session_cfg) as $slug) $states[$slug] = self::get_session_state($race_post_id, $slug);

        $results = array();
        foreach ($session_cfg as $slug => $cfg) {
            $res = self::get_result($round_id, $slug, (int)$cfg['top']);
            $results[$slug] = (is_array($res) && count($res) === (int)$cfg['top']) ? array_map('intval',$res) : array();
        }

        $my_tips = array('quali'=>array(),'sq'=>array(),'sprint'=>array(),'race'=>array());
        $me = get_current_user_id();
        if ($me > 0) {
            $rowsMine = $wpdb->get_results($wpdb->prepare(
                "SELECT session_slug, tip_json FROM {$t['tips']} WHERE round_id=%d AND league_id=%d AND user_id=%d",
                $round_id, $league_id, $me
            ), ARRAY_A);
            foreach ((array)$rowsMine as $rm) {
                $slug = sanitize_key($rm['session_slug'] ?? '');
                if (!isset($my_tips[$slug])) continue;
                $arr = json_decode((string)($rm['tip_json'] ?? ''), true);
                if (!is_array($arr)) $arr = array();
                $my_tips[$slug] = array_map('intval', $arr);
            }
        }

        $member_ids = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$t['members']} WHERE league_id=%d ORDER BY user_id ASC", $league_id));
        $member_ids = array_values(array_filter(array_map('intval', (array)$member_ids)));

        $users = array();
        foreach ($member_ids as $uid) {
            $users[] = array(
                'user_id' => (int)$uid,
                'display_name' => (string) get_the_author_meta('display_name', $uid),
                'avatar' => function_exists('f1fp_get_custom_avatar_url')
                    ? (string) f1fp_get_custom_avatar_url($uid, 64)
                    : (string) get_avatar_url($uid, array('size'=>64)),
            );
        }

        $tips = array();
        $points = array();
        foreach ($session_cfg as $slug => $cfg) {
            $tips[$slug] = array();
            $points[$slug] = array();
            if (!self::is_locked($race_post_id, $slug)) continue;

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT user_id, tip_json FROM {$t['tips']} WHERE round_id=%d AND league_id=%d AND session_slug=%s ORDER BY user_id ASC",
                $round_id, $league_id, $slug
            ), ARRAY_A);

            $res = $results[$slug] ?? array();
            $hasFullResult = (is_array($res) && count($res) === (int)$cfg['top']);
            $ended = (($states[$slug] ?? '') === 'ended');
            $rules = ($hasFullResult && $ended) ? self::get_rules($season_id, (string)$cfg['type']) : null;

            foreach ((array)$rows as $r) {
                $uid = (int)$r['user_id'];
                $arr = json_decode((string)$r['tip_json'], true);
                if (!is_array($arr)) $arr = array();
                $arr = array_map('intval', $arr);
                $tips[$slug][] = array('user_id' => $uid, 'tip' => $arr);

                if ($hasFullResult && $ended && is_array($rules) && count($arr) === (int)$cfg['top']) {
                    $points[$slug][$uid] = (int) self::score_tip($arr, $res, $rules);
                }
            }
        }

        $bonus = self::rest_bonus_payload($season_id, $league_id, get_current_user_id());
        $my_points = array('quali'=>null,'sq'=>null,'sprint'=>null,'race'=>null);
        $my_points_total = null;
        $me_user = get_current_user_id();
        if ($me_user > 0) {
            $me_user = (int)$me_user;
            $sum = 0; $any = false;
            foreach (array_keys($session_cfg) as $slug) {
                if (($states[$slug] ?? '') !== 'ended') continue;
                if (isset($points[$slug]) && array_key_exists($me_user, $points[$slug])) {
                    $val = (int)$points[$slug][$me_user];
                    $my_points[$slug] = $val;
                    $sum += $val;
                    $any = true;
                }
            }
            if ($any) $my_points_total = (int)$sum;
        }

        return array(
            'ok' => true,
            'locked' => $locked,
            'states' => $states,
            'results' => $results,
            'my_tips' => $my_tips,
            'tips' => $tips,
            'users' => $users,
            'points' => $points,
            'my_points' => $my_points,
            'my_points_total' => $my_points_total,
            'bonus' => $bonus,
        );
    }

    public function api_save_bonus_answer(WP_REST_Request $req) {
        global $wpdb; $t = self::tables();
        $user_id = get_current_user_id();
        $season_id = self::get_active_season_id();
        $league_id = self::single_league_id($season_id);

        $qid = (int)($req->get_param('question_id') ?? 0);
        $answer = sanitize_text_field((string)($req->get_param('answer') ?? ''));

        if ($qid <= 0 || $answer === '') return new WP_REST_Response(array('ok'=>false,'message'=>'Ungültige Daten'), 400);

        $q = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t['bonus_q']} WHERE id=%d AND season_id=%d AND (league_id IS NULL OR league_id=%d) LIMIT 1",
            $qid, $season_id, $league_id
        ), ARRAY_A);

        if (!$q) return new WP_REST_Response(array('ok'=>false,'message'=>'Bonusfrage nicht gefunden.'), 404);
        if (self::bonus_is_locked_row($q)) return new WP_REST_Response(array('ok'=>false,'message'=>'Bonusfrage ist geschlossen.'), 403);

        self::ensure_member($league_id, $user_id);
        $id = self::bonus_upsert_answer($qid, $user_id, $answer);
        if (!$id) return new WP_REST_Response(array('ok'=>false,'message'=>'Konnte nicht speichern.'), 500);

        return array('ok'=>true, 'answer_id'=>(int)$id);
    }

    /* =========================================================
       OTHER HELPERS
       ========================================================= */

    public static function lastname_key($title) {
        $t = trim(wp_strip_all_tags((string)$title));
        $t = preg_replace('/\s+/', ' ', $t);
        if ($t === '') return '';
        $parts = explode(' ', $t);
        $last  = array_pop($parts);
        $first = implode(' ', $parts);
        $key = trim($last . ' ' . $first);
        return mb_strtolower($key, 'UTF-8');
    }
    public static function sort_posts_by_lastname(&$posts) {
        usort($posts, function($a, $b){
            $ta = is_object($a) && isset($a->post_title) ? $a->post_title : '';
            $tb = is_object($b) && isset($b->post_title) ? $b->post_title : '';
            $ka = self::lastname_key($ta);
            $kb = self::lastname_key($tb);
            return strnatcasecmp($ka, $kb);
        });
    }
}
