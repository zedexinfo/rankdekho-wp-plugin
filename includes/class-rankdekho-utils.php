<?php
/**
 * RankDekho Utility Functions
 * 
 * Provides encryption, decryption and other utility functions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RankDekho_Utils {
    
    /**
     * Get encryption key
     */
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
    
    /**
     * Generate secure hash
     */
    public static function generate_secure_hash($data, $salt = '') {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        
        if (empty($salt)) {
            $salt = self::get_encryption_key();
        }
        
        return hash_hmac('sha256', $data, $salt);
    }
    
    /**
     * Verify hash
     */
    public static function verify_hash($data, $hash, $salt = '') {
        $expected_hash = self::generate_secure_hash($data, $salt);
        return hash_equals($expected_hash, $hash);
    }
    
    /**
     * Generate secure token
     */
    public static function generate_secure_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Sanitize and validate email
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }
    
    /**
     * Sanitize username
     */
    public static function sanitize_username($username) {
        $username = sanitize_user($username, true);
        
        // Ensure minimum length
        if (strlen($username) < 3) {
            return false;
        }
        
        // Check if username already exists
        if (username_exists($username)) {
            // Generate unique username
            $base_username = $username;
            $counter = 1;
            
            while (username_exists($username)) {
                $username = $base_username . '_' . $counter;
                $counter++;
                
                // Prevent infinite loop
                if ($counter > 999) {
                    return false;
                }
            }
        }
        
        return $username;
    }
    
    /**
     * Log debug information
     */
    public static function debug_log($message, $context = array()) {
        if (get_option('rankdekho_pg_debug_mode', 'no') !== 'yes') {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'context' => $context,
            'backtrace' => wp_debug_backtrace_summary()
        );
        
        // Store in transient for debugging
        $logs = get_transient('rankdekho_debug_logs') ?: array();
        $logs[] = $log_entry;
        
        // Keep only last 50 entries
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        set_transient('rankdekho_debug_logs', $logs, DAY_IN_SECONDS);
        
        // Also log to WordPress debug log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('RankDekho: ' . $message . ' | Context: ' . json_encode($context));
        }
    }
    
    /**
     * Get debug logs
     */
    public static function get_debug_logs() {
        return get_transient('rankdekho_debug_logs') ?: array();
    }
    
    /**
     * Clear debug logs
     */
    public static function clear_debug_logs() {
        delete_transient('rankdekho_debug_logs');
        delete_transient('rankdekho_auth_logs');
    }
    
    /**
     * Validate JSON data
     */
    public static function validate_json($json_string) {
        $data = json_decode($json_string, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : false;
    }
    
    /**
     * Format response for API
     */
    public static function format_api_response($success, $data = array(), $message = '', $code = 200) {
        $response = array(
            'success' => $success,
            'data' => $data,
            'message' => $message,
            'timestamp' => time(),
            'version' => RANKDEKHO_PG_VERSION
        );
        
        return rest_ensure_response($response)->set_status($code);
    }
    
    /**
     * Validate plan ID
     */
    public static function validate_plan_id($plan_id) {
        if (!is_numeric($plan_id) || $plan_id <= 0) {
            return false;
        }
        
        // Check if corresponding WooCommerce product exists
        $wc_integration = RankDekho_WooCommerce::get_instance();
        $plans = $wc_integration->get_subscription_plans();
        
        return isset($plans[$plan_id]);
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Rate limiting check
     */
    public static function check_rate_limit($identifier, $limit = 10, $window = 300) {
        $key = 'rankdekho_rate_limit_' . md5($identifier);
        $attempts = get_transient($key) ?: 0;
        
        if ($attempts >= $limit) {
            return false;
        }
        
        set_transient($key, $attempts + 1, $window);
        return true;
    }
    
    /**
     * Clean expired data
     */
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