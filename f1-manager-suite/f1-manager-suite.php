<?php
/**
 * Plugin Name: F1 Manager Suite
 * Description: Complete F1 Manager solution including Login, Profile, Betting (Tippspiel), Standings (WM-Stand), and Data Management (Teams, Drivers, Calendar).
 * Version: 1.0.0
 * Author: Bitplayground
 * Text Domain: f1-manager-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Plugin Paths
define( 'F1_MANAGER_SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'F1_MANAGER_SUITE_URL', plugin_dir_url( __FILE__ ) );
define( 'F1_MANAGER_SUITE_VERSION', '1.0.0' );

// ==========================================================================
// 1. CONFIGURATION CONSTANTS (Overridable in wp-config.php)
// ==========================================================================

/* Cloudflare Turnstile */
if ( ! defined( 'BP_ACC_TURNSTILE_SITEKEY' ) ) {
    define( 'BP_ACC_TURNSTILE_SITEKEY', '0x4AAAAAACVvfx4JCGwLy55a' );
}
if ( ! defined( 'BP_ACC_TURNSTILE_SECRET' ) ) {
    define( 'BP_ACC_TURNSTILE_SECRET', '0x4AAAAAACVvf7P05QtbJ4J-c_Q0Jfe20Kg' );
}

/* Rate Limit Settings */
if ( ! defined( 'BP_LOSTPASS_RL_SECONDS' ) ) {
    define( 'BP_LOSTPASS_RL_SECONDS', 60 );
}

/* Social Login - Google */
if ( ! defined( 'BP_SOCIAL_GOOGLE_CLIENT_ID' ) ) {
    define( 'BP_SOCIAL_GOOGLE_CLIENT_ID', '164843719829-lehr73n8jq1prcsf44cbn3luorj24qt7.apps.googleusercontent.com' );
}
if ( ! defined( 'BP_SOCIAL_GOOGLE_CLIENT_SECRET' ) ) {
    define( 'BP_SOCIAL_GOOGLE_CLIENT_SECRET', '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' );
}

/* Database Version for Tippspiel */
if ( ! defined( 'F1TIPS_DB_VER' ) ) {
    define( 'F1TIPS_DB_VER', '1.1.0' );
}

// ==========================================================================
// 2. INCLUDE CLASSES
// ==========================================================================

// Modules
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-login.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-profile.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-tippspiel.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-wm-stand.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-teams.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-drivers.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-calendar.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-theme-tweaks.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-footer.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-logo-switcher.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-countdown.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-team-overview.php';
require_once F1_MANAGER_SUITE_PATH . 'includes/class-f1-ticker.php';

// ==========================================================================
// 3. INITIALIZATION
// ==========================================================================

function f1_manager_suite_init() {
    F1_Login::get_instance();
    F1_Profile::get_instance();
    F1_Tippspiel::get_instance();
    F1_WM_Stand::get_instance();
    F1_Teams::get_instance();
    F1_Drivers::get_instance();
    F1_Manager_Calendar::get_instance();
    F1_Theme_Tweaks::get_instance();
    F1_Footer::get_instance();
    F1_Logo_Switcher::get_instance();
    F1_Countdown::get_instance();
    F1_Team_Overview::get_instance();
    F1_Ticker::get_instance();
}
add_action( 'plugins_loaded', 'f1_manager_suite_init' );

// ==========================================================================
// 4. ACTIVATION HOOK (DB Setup)
// ==========================================================================

register_activation_hook( __FILE__, array( 'F1_Tippspiel', 'activate_plugin' ) );
