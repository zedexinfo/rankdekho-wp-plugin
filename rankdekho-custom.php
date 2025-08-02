<?php
/*
Plugin Name: RankDekho Custom
Description: Custom plugin for RankDekho
Version: 1.0.0
Author: Dev@StableWP
*/

if (!defined('ABSPATH')){ die(); }

if (!defined('RD_PLUGIN_DIR')){
    define('RD_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ )));
    define('RD_PLUGIN_VERSION', '1.0.0');
    define('RD_INCLUDES_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/includes/');
    define('RD_TEMPLATES_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/');
    define('RD_JS_PATH', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) . '/assets/js/' );
    define('RD_CSS_PATH', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) . '/assets/css/' );
}

if ( ! class_exists( 'rankDekho' ) ) {
    include_once RD_INCLUDES_PATH . "class-rd.php";
}

function rankDekho(): rankDekho
{
    return rankDekho::getInstance();
}
rankDekho();

register_activation_hook(__FILE__, 'activate');
function activate() {
    // Create database tables if needed
    rankDekho::create_tables();

    // Set default options
    rankDekho::set_default_options();

    // Flush rewrite rules
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'deactivate');
function deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}