<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("RankDekho_Admin")) {
    class RankDekho_Admin
    {
        protected static $instance;

        public function __construct()
        {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function add_admin_menu() {
            add_options_page(
                __('RankDekho Payment Gateway', 'rankdekho-payment-gateway'),
                __('RankDekho Gateway', 'rankdekho-payment-gateway'),
                'manage_options',
                'rankdekho-payment-gateway',
                array($this, 'admin_page')
            );
        }

        public function admin_init() {
            register_setting('rankdekho_pg_settings', 'rankdekho_pg_api_enabled');
            register_setting('rankdekho_pg_settings', 'rankdekho_pg_api_key');
            register_setting('rankdekho_pg_settings', 'rankdekho_pg_encryption_key');
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
        }

        public function enqueue_admin_scripts($hook) {
            if ($hook !== 'settings_page_rankdekho-payment-gateway') {
                return;
            }

            wp_enqueue_style('rankdekho-admin', RD_CSS_PATH . 'admin.css', array(), RD_PLUGIN_VERSION);
            wp_enqueue_script('rankdekho-admin', RD_JS_PATH . 'admin.js', array('jquery'), RD_PLUGIN_VERSION, true);

            wp_localize_script('rankdekho-admin', 'rankdekho_admin', array(
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }

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

                        <div id="tools" class="tab-content">
                            <?php $this->tools_tab_content(); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

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
    }
}