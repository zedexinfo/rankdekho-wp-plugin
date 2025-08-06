<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("RankDekho_API")) {
    class RankDekho_API
    {
        protected static $instance;

        public function __construct()
        {
            add_action('rest_api_init', array($this, 'add_cors_headers'));
            add_action('rest_api_init', array($this, 'register_routes'));

//            add_action("rest_api_init", [$this, "process_checkout"]);
//            add_action("template_redirect", [$this, "auto_checkout"]);
        }

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function add_cors_headers()
        {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function ($value) {
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
                header("Access-Control-Allow-Credentials: true");
                header("Access-Control-Allow-Headers: Authorization, X-Requested-With, X-API-Key, Content-Type");
                return $value;
            });
        }

        public function register_routes() {
            register_rest_route('rankdekho/v1', '/sync-user', array(
                'methods' => 'POST',
                'callback' => array($this, 'sync_user'),
                'permission_callback' => array($this, 'check_api_permissions'),
                'args' => array(
                    'java_user_id' => array(
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param) && $param > 0;
                        }
                    ),
                    'email' => array(
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => function($param, $request, $key) {
                            return is_email($param);
                        }
                    ),
                    'username' => array(
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => function($param, $request, $key) {
                            return !empty($param) && strlen($param) >= 3;
                        }
                    ),
                    'first_name' => array(
                        'required' => false,
                        'type' => 'string'
                    ),
                    'last_name' => array(
                        'required' => false,
                        'type' => 'string'
                    ),
                    'plan_id' => array(
                        'required' => true,
                        'type' => 'integer'
                    )
                )
            ));

            register_rest_route('rankdekho/v1', '/process-payment', array(
                'methods' => 'GET',
                'callback' => array($this, 'process_payment'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'hash' => array(
                        'required' => true,
                        'type' => 'string'
                    )
                )
            ));
        }

        public function check_api_permissions($request) {
            // Check if API is enabled
            if (get_option('rankdekho_pg_api_enabled', 'yes') !== 'yes') {
                return new WP_Error('api_disabled', 'API is disabled', array('status' => 503));
            }

            // Verify API key or other authentication method
            $api_key = $request->get_header('X-API-Key');
            $expected_key = get_option('rankdekho_pg_api_key');

            if (empty($expected_key)) {
                // If no API key is set, generate one
                $expected_key = wp_generate_password(32, false);
                update_option('rankdekho_pg_api_key', $expected_key);
            }

            if ($api_key !== $expected_key) {
                return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
            }

            return true;
        }

        public function sync_user($request) {
            $params = $request->get_params();

            try {
                // Check if user already exists by email
                $existing_user = get_user_by('email', $params['email']);

                if ($existing_user) {
                    // Update existing user mapping
                    $wp_user_id = $existing_user->ID;
                } else {
                    // Create new WordPress user
                    $user_data = array(
                        'user_login' => $params['username'],
                        'user_email' => $params['email'],
                        'user_pass' => wp_generate_password(12, false),
                        'first_name' => isset($params['first_name']) ? $params['first_name'] : '',
                        'last_name' => isset($params['last_name']) ? $params['last_name'] : '',
                        'role' => 'customer'
                    );

                    $wp_user_id = wp_insert_user($user_data);

                    if (is_wp_error($wp_user_id)) {
                        return new WP_Error('user_creation_failed', $wp_user_id->get_error_message(), array('status' => 400));
                    }
                }

                // Create user mapping in database
                global $wpdb;
                $table_name = $wpdb->prefix . 'rankdekho_user_sync';

                // Check if mapping already exists
                $existing_mapping = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE java_user_id = %d",
                    $params['java_user_id']
                ));

                if ($existing_mapping) {
                    // Update existing mapping
                    $wpdb->update(
                        $table_name,
                        array(
                            'wp_user_id' => $wp_user_id,
                            'sync_status' => 'synced',
                            'updated_at' => current_time('mysql')
                        ),
                        array('java_user_id' => $params['java_user_id'])
                    );
                } else {
                    // Insert new mapping
                    $wpdb->insert(
                        $table_name,
                        array(
                            'java_user_id' => $params['java_user_id'],
                            'wp_user_id' => $wp_user_id,
                            'sync_status' => 'synced',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        )
                    );
                }

                // Generate hash for payment processing
                $payment_data = array(
                    'java_user_id' => $params['java_user_id'],
                    'wp_user_id' => $wp_user_id,
                    'plan_id' => $params['plan_id'],
                    'timestamp' => time(),
                    'expires' => time() + (15 * MINUTE_IN_SECONDS) // 15 minutes expiry
                );

                $hash = RankDekho_Utils::encrypt_data($payment_data);

                // Store hash in database for verification
                $wpdb->update(
                    $table_name,
                    array('hash_token' => $hash),
                    array('java_user_id' => $params['java_user_id'])
                );

                // Generate payment URL
                $payment_url = add_query_arg(
                    array('hash' => urlencode($hash)),
                    rest_url('rankdekho/v1/process-payment')
                );

                return rest_ensure_response(array(
                    'success' => true,
                    'user_id' => $wp_user_id,
                    'java_user_id' => $params['java_user_id'],
                    'payment_url' => $payment_url,
                    'hash' => $hash,
                    'expires_in' => 900 // 15 minutes in seconds
                ));

            } catch (Exception $e) {
                return new WP_Error('sync_failed', $e->getMessage(), array('status' => 500));
            }
        }

        public function process_payment($request) {
            $hash = $request->get_param('hash');

            if (empty($hash)) {
                wp_die('Invalid payment request', 'Error', array('response' => 400));
            }

            try {
                // Decrypt and verify hash
                $payment_data = RankDekho_Utils::decrypt_data($hash);

                if (!$payment_data || !isset($payment_data['expires']) || $payment_data['expires'] < time()) {
                    wp_die('Payment link has expired', 'Error', array('response' => 400));
                }

                // Verify hash exists in database
                global $wpdb;
                $table_name = $wpdb->prefix . 'rankdekho_user_sync';

                $user_mapping = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE java_user_id = %d AND hash_token = %s",
                    $payment_data['java_user_id'],
                    $hash
                ));

                if (!$user_mapping) {
                    wp_die('Invalid payment request', 'Error', array('response' => 400));
                }

                // Set global variable for custom authentication
                global $rankdekho_auth_data;
                $rankdekho_auth_data = array(
                    'wp_user_id' => $payment_data['wp_user_id'],
                    'java_user_id' => $payment_data['java_user_id'],
                    'plan_id' => $payment_data['plan_id'],
                    'authenticated' => true
                );

                // Manually authenticate and login user
                $user = get_user_by('ID', $payment_data['wp_user_id']);
                if (!$user) {
                    wp_die('User not found', 'Error', array('response' => 404));
                }

                // Log in the user
                wp_set_current_user($payment_data['wp_user_id']);
                wp_set_auth_cookie($payment_data['wp_user_id'], true);
                do_action('wp_login', $user->user_nicename, $user);

                if (!class_exists('WC_Cart')) {
                    return new WP_Error('woocommerce_missing', 'WooCommerce is not available', array('status' => 500));
                }

                if (!WC()->session) {
                    WC()->initialize_session();
                }
                if (!WC()->cart) {
                    wc_load_cart();
                }

                $product = wc_get_product($payment_data['plan_id']);
                if (!$product || !$product->is_purchasable()) {
                    return new WP_Error('invalid_product', 'Product not found or not purchasable', array('status' => 400));
                }

                WC()->cart->empty_cart();
                WC()->cart->add_to_cart($payment_data['plan_id']);

                // Clear the hash token to prevent reuse
                $wpdb->update(
                    $table_name,
                    array('hash_token' => null),
                    array('java_user_id' => $payment_data['java_user_id'])
                );

                // Redirect to checkout
                wp_redirect(wc_get_checkout_url());
                exit;

            } catch (Exception $e) {
                if (get_option('rankdekho_pg_debug_mode', 'no') === 'yes') {
                    wp_die('Payment processing error: ' . $e->getMessage(), 'Error', array('response' => 500));
                } else {
                    wp_die('Payment processing error', 'Error', array('response' => 500));
                }
            }
        }

//        public function process_checkout()
//        {
//            register_rest_route('rankdekho', '/process-checkout', array(
//                'methods' => ['GET', 'POST'],
//                'callback' => [$this, 'process_checkout_callback'],
//                'permission_callback' => '__return_true',
//            ));
//        }
//
//        public function process_checkout_callback($request)
//        {
//            $data = $request->get_json_params();
//            $username = sanitize_user($data['user']['username']);
//            $email = sanitize_email($data['user']['email']);
//            $password = sanitize_text_field($data['user']['password']);
//            $product_id = absint($data['productId']);
//
//            $user_id = email_exists($email) ?: username_exists($username);
//            if (!$user_id) {
//                $user_id = wp_create_user($username, $password, $email);
//                if (is_wp_error($user_id)) {
//                    return new WP_Error('user_creation_failed', 'User creation failed', ['status' => 500]);
//                }
//            }
//
//            $redirect_url = add_query_arg(array(
//                'auto_login_email' => $email,
//                'product_id' => $product_id
//            ), site_url('/auto-checkout'));
//
//            return rest_ensure_response(['redirect' => $redirect_url]);
//        }
//
//        public function auto_checkout () {
//            if (is_page('auto-checkout') && isset($_GET['auto_login_email'], $_GET['product_id'])) {
//                $email = sanitize_email($_GET['auto_login_email']);
//                $product_id = absint($_GET['product_id']);
//
//                $user = get_user_by('email', $email);
//                if ($user) {
//                    wp_set_current_user($user->ID);
//                    wp_set_auth_cookie($user->ID);
//                    do_action('wp_login', $user->user_nicename, $user);
//                }
//
//                if (!class_exists('WC_Cart')) {
//                    return new WP_Error('woocommerce_missing', 'WooCommerce is not available', array('status' => 500));
//                }
//
//                if (!WC()->session) {
//                    WC()->initialize_session();
//                }
//                if (!WC()->cart) {
//                    wc_load_cart();
//                }
//
//                $product = wc_get_product($product_id);
//                if (!$product || !$product->is_purchasable()) {
//                    return new WP_Error('invalid_product', 'Product not found or not purchasable', array('status' => 400));
//                }
//
//                WC()->cart->empty_cart();
//                WC()->cart->add_to_cart($product_id);
//
//                wp_redirect(wc_get_checkout_url());
//                exit;
//            }
//        }
    }
}