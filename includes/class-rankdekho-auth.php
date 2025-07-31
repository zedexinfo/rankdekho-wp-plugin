<?php
/**
 * RankDekho Authentication Handler
 * 
 * Handles custom authentication for hash-based login
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RankDekho_Auth {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('authenticate', array($this, 'custom_authenticate'), 10, 3);
        add_action('init', array($this, 'init_session'));
    }
    
    /**
     * Initialize session handling
     */
    public function init_session() {
        if (!session_id()) {
            session_start();
        }
    }
    
    /**
     * Custom authentication override
     * 
     * This function overrides the default wp_authenticate behavior
     * when we have a valid hash-based authentication in progress
     */
    public function custom_authenticate($user, $username, $password) {
        global $rankdekho_auth_data;
        
        // If we have valid auth data from hash processing, use it
        if (isset($rankdekho_auth_data) && 
            is_array($rankdekho_auth_data) && 
            isset($rankdekho_auth_data['authenticated']) && 
            $rankdekho_auth_data['authenticated'] === true &&
            isset($rankdekho_auth_data['wp_user_id'])) {
            
            // Get the user object
            $auth_user = get_user_by('ID', $rankdekho_auth_data['wp_user_id']);
            
            if ($auth_user && !is_wp_error($auth_user)) {
                // Store additional data in session for later use
                $_SESSION['rankdekho_java_user_id'] = $rankdekho_auth_data['java_user_id'];
                $_SESSION['rankdekho_plan_id'] = $rankdekho_auth_data['plan_id'];
                
                // Clear the global auth data to prevent reuse
                unset($rankdekho_auth_data);
                
                return $auth_user;
            }
        }
        
        // If no custom auth data, proceed with normal authentication
        return $user;
    }
    
    /**
     * Check if current request is a hash-based authentication
     */
    public function is_hash_authentication() {
        global $rankdekho_auth_data;
        return isset($rankdekho_auth_data) && 
               is_array($rankdekho_auth_data) && 
               isset($rankdekho_auth_data['authenticated']) && 
               $rankdekho_auth_data['authenticated'] === true;
    }
    
    /**
     * Get Java user ID from session
     */
    public function get_java_user_id() {
        return isset($_SESSION['rankdekho_java_user_id']) ? $_SESSION['rankdekho_java_user_id'] : null;
    }
    
    /**
     * Get plan ID from session
     */
    public function get_plan_id() {
        return isset($_SESSION['rankdekho_plan_id']) ? $_SESSION['rankdekho_plan_id'] : null;
    }
    
    /**
     * Clear authentication session data
     */
    public function clear_auth_session() {
        unset($_SESSION['rankdekho_java_user_id']);
        unset($_SESSION['rankdekho_plan_id']);
    }
    
    /**
     * Validate hash token
     */
    public function validate_hash_token($hash) {
        try {
            $data = RankDekho_Utils::decrypt_data($hash);
            
            if (!$data || !is_array($data)) {
                return false;
            }
            
            // Check required fields
            $required_fields = array('java_user_id', 'wp_user_id', 'plan_id', 'timestamp', 'expires');
            foreach ($required_fields as $field) {
                if (!isset($data[$field])) {
                    return false;
                }
            }
            
            // Check expiration
            if ($data['expires'] < time()) {
                return false;
            }
            
            // Verify user exists
            $user = get_user_by('ID', $data['wp_user_id']);
            if (!$user) {
                return false;
            }
            
            // Verify mapping in database
            global $wpdb;
            $table_name = $wpdb->prefix . 'rankdekho_user_sync';
            
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE java_user_id = %d AND wp_user_id = %d AND hash_token = %s",
                $data['java_user_id'],
                $data['wp_user_id'],
                $hash
            ));
            
            return $mapping ? $data : false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate secure authentication token
     */
    public function generate_auth_token($user_id, $java_user_id) {
        $token_data = array(
            'wp_user_id' => $user_id,
            'java_user_id' => $java_user_id,
            'timestamp' => time(),
            'expires' => time() + (24 * HOUR_IN_SECONDS), // 24 hours
            'nonce' => wp_create_nonce('rankdekho_auth_' . $user_id)
        );
        
        return RankDekho_Utils::encrypt_data($token_data);
    }
    
    /**
     * Verify authentication token
     */
    public function verify_auth_token($token) {
        try {
            $data = RankDekho_Utils::decrypt_data($token);
            
            if (!$data || !is_array($data)) {
                return false;
            }
            
            // Check expiration
            if (isset($data['expires']) && $data['expires'] < time()) {
                return false;
            }
            
            // Verify nonce
            if (isset($data['nonce']) && isset($data['wp_user_id'])) {
                if (!wp_verify_nonce($data['nonce'], 'rankdekho_auth_' . $data['wp_user_id'])) {
                    return false;
                }
            }
            
            return $data;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Log authentication attempt
     */
    public function log_auth_attempt($user_id, $java_user_id, $status, $method = 'hash') {
        if (get_option('rankdekho_pg_debug_mode', 'no') !== 'yes') {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'wp_user_id' => $user_id,
            'java_user_id' => $java_user_id,
            'status' => $status,
            'method' => $method,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
        
        // Store in transient for debugging (expires in 1 day)
        $logs = get_transient('rankdekho_auth_logs') ?: array();
        $logs[] = $log_entry;
        
        // Keep only last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        set_transient('rankdekho_auth_logs', $logs, DAY_IN_SECONDS);
    }
    
    /**
     * Get authentication logs
     */
    public function get_auth_logs() {
        return get_transient('rankdekho_auth_logs') ?: array();
    }
}