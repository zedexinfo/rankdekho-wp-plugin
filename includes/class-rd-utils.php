<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("RankDekho_Utils")) {
    class RankDekho_Utils
    {
        protected static $instance;

        public function __construct()
        {
        }

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private static function get_encryption_key() {
            $key = get_option('rankdekho_pg_encryption_key');

            if (empty($key)) {
                $key = wp_generate_password(32, false);
                update_option('rankdekho_pg_encryption_key', $key);
            }

            return $key;
        }

        /**
         * Encrypt data
         */
        public static function encrypt_data($data) {
            if (!is_string($data)) {
                $data = json_encode($data);
            }

            $key = self::get_encryption_key();
            $method = 'AES-256-CBC';
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));

            $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);

            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }

            // Combine IV and encrypted data
            $result = base64_encode($iv . $encrypted);

            return $result;
        }

        /**
         * Decrypt data
         */
        public static function decrypt_data($encrypted_data) {
            $key = self::get_encryption_key();
            $method = 'AES-256-CBC';

            $data = base64_decode($encrypted_data);

            if ($data === false) {
                throw new Exception('Invalid encrypted data');
            }

            $iv_length = openssl_cipher_iv_length($method);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);

            $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);

            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }

            // Try to decode as JSON
            $json_data = json_decode($decrypted, true);

            return $json_data !== null ? $json_data : $decrypted;
        }

        public static function cleanup_expired_data() {
            global $wpdb;

            // Clean expired hash tokens
            $table_name = $wpdb->prefix . 'rankdekho_user_sync';

            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET hash_token = NULL WHERE hash_token IS NOT NULL AND updated_at < %s",
                date('Y-m-d H:i:s', time() - (15 * MINUTE_IN_SECONDS))
            ));

            // Clean old sync records (older than 30 days)
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE sync_status = 'synced' AND created_at < %s",
                date('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS))
            ));
        }
    }
}