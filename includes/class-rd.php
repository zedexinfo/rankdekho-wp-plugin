<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("rankDekho")) {
    class rankDekho
    {
        protected static $instance;
        public $RankDekho_API;
        public $RankDekho_Admin;
        public $RankDekho_Ajax;
        public $RankDekho_Utils;

        public function __construct()
        {
            add_action('plugins_loaded', array($this, 'initialize'), 20);
        }

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function initialize()
        {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }

            $this->includes();
            $this->init();
        }

        public function includes()
        {
            include_once RD_INCLUDES_PATH . 'class-rd-api.php';
            include_once RD_INCLUDES_PATH . 'class-rd-admin.php';
            include_once RD_INCLUDES_PATH . 'class-rd-ajax.php';
            include_once RD_INCLUDES_PATH . 'class-rd-utils.php';
        }

        public function init()
        {
            $this->RankDekho_API = RankDekho_API::getInstance();
            $this->RankDekho_Admin = RankDekho_Admin::getInstance();
            $this->RankDekho_Ajax = RankDekho_Ajax::getInstance();
            $this->RankDekho_Utils = RankDekho_Utils::getInstance();
        }

        public static function create_tables() {
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

        public static function set_default_options() {
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
    }
}