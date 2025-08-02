<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("RankDekho_Ajax")) {
    class RankDekho_Ajax
    {
        protected static $instance;

        public function __construct()
        {
            add_action('wp_ajax_rankdekho_regenerate_api_key', array($this, 'ajax_regenerate_api_key'));
            add_action('wp_ajax_rankdekho_regenerate_encryption_key', array($this, 'ajax_regenerate_encryption_key'));
            add_action('wp_ajax_rankdekho_cleanup_expired_data', array($this, 'ajax_cleanup_expired_data'));
        }

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function ajax_regenerate_api_key() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }

            $new_key = wp_generate_password(32, false);
            update_option('rankdekho_pg_api_key', $new_key);

            wp_send_json_success(array('api_key' => $new_key));
        }

        public function ajax_regenerate_encryption_key() {
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

        public function ajax_cleanup_expired_data() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }

            RankDekho_Utils::cleanup_expired_data();

            wp_send_json_success(array('message' => 'Expired data cleaned up successfully.'));
        }
    }
}