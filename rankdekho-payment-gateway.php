<?php
/**
 * Plugin Name: RankDekho Payment Gateway
 * Plugin URI: https://github.com/zedexinfo/rankdekho-wp-plugin
 * Description: WordPress plugin to handle payment processing for RankDekho platform with user synchronization from Java backend.
 * Version: 1.0.0
 * Author: RankDekho
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rankdekho-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RANKDEKHO_PG_VERSION', '1.0.0');
define('RANKDEKHO_PG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RANKDEKHO_PG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RANKDEKHO_PG_PLUGIN_FILE', __FILE__);

/**
 * Main RankDekho Payment Gateway Class
 */
class RankDekho_Payment_Gateway {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin text domain
        load_plugin_textdomain('rankdekho-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once RANKDEKHO_PG_PLUGIN_DIR . 'includes/class-rankdekho-api.php';
        require_once RANKDEKHO_PG_PLUGIN_DIR . 'includes/class-rankdekho-auth.php';
        require_once RANKDEKHO_PG_PLUGIN_DIR . 'includes/class-rankdekho-woocommerce.php';
        require_once RANKDEKHO_PG_PLUGIN_DIR . 'includes/class-rankdekho-utils.php';
        
        // Include admin classes only in admin area
        if (is_admin()) {
            require_once RANKDEKHO_PG_PLUGIN_DIR . 'admin/class-rankdekho-admin.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        RankDekho_API::get_instance();
        RankDekho_Auth::get_instance();
        RankDekho_WooCommerce::get_instance();
        
        // Initialize admin only in admin area
        if (is_admin()) {
            RankDekho_Admin::get_instance();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create required database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rankdekho_user_sync';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            java_user_id bigint(20) NOT NULL,
            wp_user_id bigint(20) NOT NULL,
            hash_token varchar(255) DEFAULT NULL,
            sync_status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY java_user_id (java_user_id),
            KEY wp_user_id (wp_user_id),
            KEY hash_token (hash_token)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'encryption_key' => wp_generate_password(32, false),
            'api_enabled' => 'yes',
            'debug_mode' => 'no'
        );
        
        foreach ($default_options as $key => $value) {
            $option_name = 'rankdekho_pg_' . $key;
            if (!get_option($option_name)) {
                update_option($option_name, $value);
            }
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('RankDekho Payment Gateway requires WooCommerce to be installed and active.', 'rankdekho-payment-gateway'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
RankDekho_Payment_Gateway::get_instance();