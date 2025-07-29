<?php
/**
 * RankDekho Admin Interface
 * 
 * Provides admin panel for plugin configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RankDekho_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_rankdekho_regenerate_api_key', array($this, 'ajax_regenerate_api_key'));
        add_action('wp_ajax_rankdekho_regenerate_encryption_key', array($this, 'ajax_regenerate_encryption_key'));
        add_action('wp_ajax_rankdekho_cleanup_expired_data', array($this, 'ajax_cleanup_expired_data'));
        add_action('wp_ajax_rankdekho_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_rankdekho_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_rankdekho_refresh_logs', array($this, 'ajax_refresh_logs'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('RankDekho Payment Gateway', 'rankdekho-payment-gateway'),
            __('RankDekho Gateway', 'rankdekho-payment-gateway'),
            'manage_options',
            'rankdekho-payment-gateway',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('rankdekho_pg_settings', 'rankdekho_pg_api_enabled');
        register_setting('rankdekho_pg_settings', 'rankdekho_pg_api_key');
        register_setting('rankdekho_pg_settings', 'rankdekho_pg_encryption_key');
        register_setting('rankdekho_pg_settings', 'rankdekho_pg_debug_mode');
        register_setting('rankdekho_pg_settings', 'rankdekho_pg_java_webhook_url');
        
        add_settings_section(
            'rankdekho_pg_api_settings',
            __('API Settings', 'rankdekho-payment-gateway'),
            array($this, 'api_settings_section_callback'),
            'rankdekho_pg_settings'
        );
        
        add_settings_field(
            'api_enabled',
            __('Enable API', 'rankdekho-payment-gateway'),
            array($this, 'api_enabled_callback'),
            'rankdekho_pg_settings',
            'rankdekho_pg_api_settings'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'rankdekho-payment-gateway'),
            array($this, 'api_key_callback'),
            'rankdekho_pg_settings',
            'rankdekho_pg_api_settings'
        );
        
        add_settings_field(
            'encryption_key',
            __('Encryption Key', 'rankdekho-payment-gateway'),
            array($this, 'encryption_key_callback'),
            'rankdekho_pg_settings',
            'rankdekho_pg_api_settings'
        );
        
        add_settings_field(
            'java_webhook_url',
            __('Java Backend Webhook URL', 'rankdekho-payment-gateway'),
            array($this, 'java_webhook_url_callback'),
            'rankdekho_pg_settings',
            'rankdekho_pg_api_settings'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'rankdekho-payment-gateway'),
            array($this, 'debug_mode_callback'),
            'rankdekho_pg_settings',
            'rankdekho_pg_api_settings'
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_rankdekho-payment-gateway') {
            return;
        }
        
        wp_enqueue_style('rankdekho-admin', RANKDEKHO_PG_PLUGIN_URL . 'admin/css/admin.css', array(), RANKDEKHO_PG_VERSION);
        wp_enqueue_script('rankdekho-admin', RANKDEKHO_PG_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), RANKDEKHO_PG_VERSION, true);
        
        wp_localize_script('rankdekho-admin', 'rankdekho_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rankdekho_admin_nonce')
        ));
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('RankDekho Payment Gateway Settings', 'rankdekho-payment-gateway'); ?></h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'rankdekho-payment-gateway'); ?></p>
                </div>
            <?php endif; ?>
            
            <div id="rankdekho-admin-content">
                <div class="rankdekho-admin-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#settings" class="nav-tab nav-tab-active"><?php _e('Settings', 'rankdekho-payment-gateway'); ?></a>
                        <a href="#status" class="nav-tab"><?php _e('Status', 'rankdekho-payment-gateway'); ?></a>
                        <a href="#logs" class="nav-tab"><?php _e('Logs', 'rankdekho-payment-gateway'); ?></a>
                        <a href="#tools" class="nav-tab"><?php _e('Tools', 'rankdekho-payment-gateway'); ?></a>
                    </nav>
                    
                    <div id="settings" class="tab-content active">
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('rankdekho_pg_settings');
                            do_settings_sections('rankdekho_pg_settings');
                            submit_button();
                            ?>
                        </form>
                    </div>
                    
                    <div id="status" class="tab-content">
                        <?php $this->status_tab_content(); ?>
                    </div>
                    
                    <div id="logs" class="tab-content">
                        <?php $this->logs_tab_content(); ?>
                    </div>
                    
                    <div id="tools" class="tab-content">
                        <?php $this->tools_tab_content(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * API settings section callback
     */
    public function api_settings_section_callback() {
        echo '<p>' . __('Configure API settings for integration with Java backend.', 'rankdekho-payment-gateway') . '</p>';
    }
    
    /**
     * API enabled field callback
     */
    public function api_enabled_callback() {
        $value = get_option('rankdekho_pg_api_enabled', 'yes');
        echo '<input type="checkbox" name="rankdekho_pg_api_enabled" value="yes" ' . checked($value, 'yes', false) . '>';
        echo '<p class="description">' . __('Enable or disable the API endpoints.', 'rankdekho-payment-gateway') . '</p>';
    }
    
    /**
     * API key field callback
     */
    public function api_key_callback() {
        $value = get_option('rankdekho_pg_api_key', '');
        if (empty($value)) {
            $value = wp_generate_password(32, false);
            update_option('rankdekho_pg_api_key', $value);
        }
        echo '<input type="text" name="rankdekho_pg_api_key" value="' . esc_attr($value) . '" class="regular-text" readonly>';
        echo '<button type="button" class="button" onclick="regenerateApiKey()">' . __('Regenerate', 'rankdekho-payment-gateway') . '</button>';
        echo '<p class="description">' . __('API key for authenticating requests from Java backend.', 'rankdekho-payment-gateway') . '</p>';
    }
    
    /**
     * Encryption key field callback
     */
    public function encryption_key_callback() {
        $value = get_option('rankdekho_pg_encryption_key', '');
        if (empty($value)) {
            $value = wp_generate_password(32, false);
            update_option('rankdekho_pg_encryption_key', $value);
        }
        echo '<input type="text" name="rankdekho_pg_encryption_key" value="' . esc_attr($value) . '" class="regular-text" readonly>';
        echo '<button type="button" class="button" onclick="regenerateEncryptionKey()">' . __('Regenerate', 'rankdekho-payment-gateway') . '</button>';
        echo '<p class="description">' . __('Encryption key for securing hash tokens. WARNING: Changing this will invalidate all existing tokens.', 'rankdekho-payment-gateway') . '</p>';
    }
    
    /**
     * Java webhook URL field callback
     */
    public function java_webhook_url_callback() {
        $value = get_option('rankdekho_pg_java_webhook_url', '');
        echo '<input type="url" name="rankdekho_pg_java_webhook_url" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('URL to notify Java backend about successful orders.', 'rankdekho-payment-gateway') . '</p>';
    }
    
    /**
     * Debug mode field callback
     */
    public function debug_mode_callback() {
        $value = get_option('rankdekho_pg_debug_mode', 'no');
        echo '<input type="checkbox" name="rankdekho_pg_debug_mode" value="yes" ' . checked($value, 'yes', false) . '>';
        echo '<p class="description">' . __('Enable debug logging. Only enable for troubleshooting.', 'rankdekho-payment-gateway') . '</p>';
    }
    
    /**
     * Status tab content
     */
    private function status_tab_content() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rankdekho_user_sync';
        $total_synced = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE sync_status = 'synced'");
        $pending_syncs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE sync_status = 'pending'");
        $active_hashes = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE hash_token IS NOT NULL");
        
        ?>
        <h3><?php _e('System Status', 'rankdekho-payment-gateway'); ?></h3>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong><?php _e('Plugin Version', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><?php echo RANKDEKHO_PG_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('WooCommerce Active', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><?php echo class_exists('WooCommerce') ? __('Yes', 'rankdekho-payment-gateway') : __('No', 'rankdekho-payment-gateway'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('API Status', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><?php echo get_option('rankdekho_pg_api_enabled', 'yes') === 'yes' ? __('Enabled', 'rankdekho-payment-gateway') : __('Disabled', 'rankdekho-payment-gateway'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Total Synced Users', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><?php echo $total_synced; ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Pending Syncs', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><?php echo $pending_syncs; ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Active Hash Tokens', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><?php echo $active_hashes; ?></td>
                </tr>
            </tbody>
        </table>
        
        <h3><?php _e('API Endpoints', 'rankdekho-payment-gateway'); ?></h3>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong><?php _e('Sync User', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><code><?php echo rest_url('rankdekho/v1/sync-user'); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Process Payment', 'rankdekho-payment-gateway'); ?></strong></td>
                    <td><code><?php echo rest_url('rankdekho/v1/process-payment'); ?></code></td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Logs tab content
     */
    private function logs_tab_content() {
        $debug_logs = RankDekho_Utils::get_debug_logs();
        $auth_logs = RankDekho_Auth::get_instance()->get_auth_logs();
        
        ?>
        <h3><?php _e('Debug Logs', 'rankdekho-payment-gateway'); ?></h3>
        <button type="button" class="button" onclick="clearLogs('debug')"><?php _e('Clear Debug Logs', 'rankdekho-payment-gateway'); ?></button>
        
        <div id="debug-logs" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0;">
            <?php if (empty($debug_logs)): ?>
                <p><?php _e('No debug logs available.', 'rankdekho-payment-gateway'); ?></p>
            <?php else: ?>
                <?php foreach (array_reverse($debug_logs) as $log): ?>
                    <div style="border-bottom: 1px solid #eee; padding: 5px 0;">
                        <strong><?php echo esc_html($log['timestamp']); ?></strong>: 
                        <?php echo esc_html($log['message']); ?>
                        <?php if (!empty($log['context'])): ?>
                            <pre style="font-size: 11px; margin: 5px 0;"><?php echo esc_html(json_encode($log['context'], JSON_PRETTY_PRINT)); ?></pre>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <h3><?php _e('Authentication Logs', 'rankdekho-payment-gateway'); ?></h3>
        <div id="auth-logs" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0;">
            <?php if (empty($auth_logs)): ?>
                <p><?php _e('No authentication logs available.', 'rankdekho-payment-gateway'); ?></p>
            <?php else: ?>
                <?php foreach (array_reverse($auth_logs) as $log): ?>
                    <div style="border-bottom: 1px solid #eee; padding: 5px 0;">
                        <strong><?php echo esc_html($log['timestamp']); ?></strong>: 
                        User ID <?php echo esc_html($log['wp_user_id']); ?> 
                        (Java ID: <?php echo esc_html($log['java_user_id']); ?>) - 
                        Status: <?php echo esc_html($log['status']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Tools tab content
     */
    private function tools_tab_content() {
        ?>
        <h3><?php _e('Maintenance Tools', 'rankdekho-payment-gateway'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Cleanup Expired Data', 'rankdekho-payment-gateway'); ?></th>
                <td>
                    <button type="button" class="button" onclick="cleanupExpiredData()">
                        <?php _e('Run Cleanup', 'rankdekho-payment-gateway'); ?>
                    </button>
                    <p class="description"><?php _e('Remove expired hash tokens and old sync records.', 'rankdekho-payment-gateway'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Test API Connection', 'rankdekho-payment-gateway'); ?></th>
                <td>
                    <button type="button" class="button" onclick="testApiConnection()">
                        <?php _e('Test Connection', 'rankdekho-payment-gateway'); ?>
                    </button>
                    <p class="description"><?php _e('Test the API endpoints to ensure they are working correctly.', 'rankdekho-payment-gateway'); ?></p>
                </td>
            </tr>
        </table>
        
        <div id="tools-results" style="margin-top: 20px;"></div>
        <?php
    }
    
    /**
     * AJAX handler for regenerating API key
     */
    public function ajax_regenerate_api_key() {
        check_ajax_referer('rankdekho_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $new_key = wp_generate_password(32, false);
        update_option('rankdekho_pg_api_key', $new_key);
        
        wp_send_json_success(array('api_key' => $new_key));
    }
    
    /**
     * AJAX handler for regenerating encryption key
     */
    public function ajax_regenerate_encryption_key() {
        check_ajax_referer('rankdekho_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $new_key = wp_generate_password(32, false);
        update_option('rankdekho_pg_encryption_key', $new_key);
        
        // Clear existing hash tokens since they'll be invalid
        global $wpdb;
        $table_name = $wpdb->prefix . 'rankdekho_user_sync';
        $wpdb->update($table_name, array('hash_token' => null), array());
        
        wp_send_json_success(array('encryption_key' => $new_key));
    }
    
    /**
     * AJAX handler for cleanup expired data
     */
    public function ajax_cleanup_expired_data() {
        check_ajax_referer('rankdekho_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        RankDekho_Utils::cleanup_expired_data();
        
        wp_send_json_success(array('message' => 'Expired data cleaned up successfully.'));
    }
    
    /**
     * AJAX handler for testing API
     */
    public function ajax_test_api() {
        check_ajax_referer('rankdekho_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $results = array(
            'sync_user' => false,
            'process_payment' => false
        );
        
        // Test sync user endpoint
        $sync_url = rest_url('rankdekho/v1/sync-user');
        $response = wp_remote_get($sync_url);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) !== 404) {
            $results['sync_user'] = true;
        }
        
        // Test process payment endpoint
        $payment_url = rest_url('rankdekho/v1/process-payment');
        $response = wp_remote_get($payment_url);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) !== 404) {
            $results['process_payment'] = true;
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('rankdekho_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        RankDekho_Utils::clear_debug_logs();
        
        wp_send_json_success(array('message' => 'Logs cleared successfully.'));
    }
    
    /**
     * AJAX handler for refreshing logs
     */
    public function ajax_refresh_logs() {
        check_ajax_referer('rankdekho_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $debug_logs = RankDekho_Utils::get_debug_logs();
        $auth_logs = RankDekho_Auth::get_instance()->get_auth_logs();
        
        ob_start();
        if (empty($debug_logs)) {
            echo '<p>' . __('No debug logs available.', 'rankdekho-payment-gateway') . '</p>';
        } else {
            foreach (array_reverse($debug_logs) as $log) {
                echo '<div style="border-bottom: 1px solid #eee; padding: 5px 0;">';
                echo '<strong>' . esc_html($log['timestamp']) . '</strong>: ';
                echo esc_html($log['message']);
                if (!empty($log['context'])) {
                    echo '<pre style="font-size: 11px; margin: 5px 0;">' . esc_html(json_encode($log['context'], JSON_PRETTY_PRINT)) . '</pre>';
                }
                echo '</div>';
            }
        }
        $debug_html = ob_get_clean();
        
        ob_start();
        if (empty($auth_logs)) {
            echo '<p>' . __('No authentication logs available.', 'rankdekho-payment-gateway') . '</p>';
        } else {
            foreach (array_reverse($auth_logs) as $log) {
                echo '<div style="border-bottom: 1px solid #eee; padding: 5px 0;">';
                echo '<strong>' . esc_html($log['timestamp']) . '</strong>: ';
                echo 'User ID ' . esc_html($log['wp_user_id']) . ' ';
                echo '(Java ID: ' . esc_html($log['java_user_id']) . ') - ';
                echo 'Status: ' . esc_html($log['status']);
                echo '</div>';
            }
        }
        $auth_html = ob_get_clean();
        
        wp_send_json_success(array(
            'debug_logs' => $debug_html,
            'auth_logs' => $auth_html
        ));
    }
}