<?php
/**
 * RankDekho WooCommerce Integration
 * 
 * Handles WooCommerce cart management and checkout integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RankDekho_WooCommerce {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'), 20);
        add_action('woocommerce_checkout_order_processed', array($this, 'order_processed'), 10, 3);
        add_filter('woocommerce_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
    }
    
    /**
     * Initialize WooCommerce integration
     */
    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Ensure WooCommerce session is initialized
        if (WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }
    
    /**
     * Add subscription plan to cart
     */
    public function add_plan_to_cart($plan_id) {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }
        
        // Clear existing cart to ensure only the selected plan is in cart
        WC()->cart->empty_cart();
        
        // Get plan product mapping
        $product_id = $this->get_product_id_by_plan($plan_id);
        
        if (!$product_id) {
            throw new Exception('Product not found for plan ID: ' . $plan_id);
        }
        
        // Verify product exists and is purchasable
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_purchasable()) {
            throw new Exception('Product is not available for purchase');
        }
        
        // Add custom cart item data
        $cart_item_data = array(
            'rankdekho_plan_id' => $plan_id,
            'rankdekho_java_user_id' => RankDekho_Auth::get_instance()->get_java_user_id(),
            'rankdekho_source' => 'java_backend'
        );
        
        // Add product to cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
        
        if (!$cart_item_key) {
            throw new Exception('Failed to add product to cart');
        }
        
        return $cart_item_key;
    }
    
    /**
     * Get WooCommerce product ID by plan ID
     * This assumes you have a meta field or custom mapping
     */
    private function get_product_id_by_plan($plan_id) {
        // First try to find by meta field
        $products = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_rankdekho_plan_id',
                    'value' => $plan_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (!empty($products)) {
            return $products[0]->ID;
        }
        
        // Fallback: check if plan_id directly corresponds to product_id
        $product = wc_get_product($plan_id);
        if ($product && $product->exists()) {
            return $plan_id;
        }
        
        // If no mapping found, try to find by SKU
        $product_id = wc_get_product_id_by_sku('plan_' . $plan_id);
        if ($product_id) {
            return $product_id;
        }
        
        return false;
    }
    
    /**
     * Add custom data to cart items
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        // Check if this is a RankDekho initiated cart addition
        if (isset($cart_item_data['rankdekho_plan_id'])) {
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        
        return $cart_item_data;
    }
    
    /**
     * Display custom cart item data
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['rankdekho_plan_id'])) {
            $item_data[] = array(
                'key' => __('Plan ID', 'rankdekho-payment-gateway'),
                'value' => $cart_item['rankdekho_plan_id']
            );
        }
        
        if (isset($cart_item['rankdekho_source'])) {
            $item_data[] = array(
                'key' => __('Source', 'rankdekho-payment-gateway'),
                'value' => __('RankDekho Platform', 'rankdekho-payment-gateway')
            );
        }
        
        return $item_data;
    }
    
    /**
     * Handle order processing
     */
    public function order_processed($order_id, $posted_data, $order) {
        $java_user_id = RankDekho_Auth::get_instance()->get_java_user_id();
        
        if ($java_user_id) {
            // Store Java user ID in order meta
            $order->update_meta_data('_rankdekho_java_user_id', $java_user_id);
            
            // Store plan information from cart items
            foreach ($order->get_items() as $item_id => $item) {
                $cart_item_data = $item->get_meta_data();
                foreach ($cart_item_data as $meta) {
                    if ($meta->key === 'rankdekho_plan_id') {
                        $order->update_meta_data('_rankdekho_plan_id', $meta->value);
                        break;
                    }
                }
            }
            
            $order->save();
            
            // Clear authentication session
            RankDekho_Auth::get_instance()->clear_auth_session();
            
            // Notify Java backend about successful order
            $this->notify_java_backend_order_success($order_id, $java_user_id);
        }
    }
    
    /**
     * Notify Java backend about successful order
     */
    private function notify_java_backend_order_success($order_id, $java_user_id) {
        $java_webhook_url = get_option('rankdekho_pg_java_webhook_url');
        
        if (empty($java_webhook_url)) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $notification_data = array(
            'order_id' => $order_id,
            'java_user_id' => $java_user_id,
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'plan_id' => $order->get_meta('_rankdekho_plan_id'),
            'timestamp' => time()
        );
        
        // Send webhook notification asynchronously
        wp_remote_post($java_webhook_url, array(
            'body' => json_encode($notification_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => get_option('rankdekho_pg_api_key')
            ),
            'timeout' => 30,
            'blocking' => false // Non-blocking request
        ));
    }
    
    /**
     * Get subscription plans as WooCommerce products
     */
    public function get_subscription_plans() {
        $plans = array();
        
        // Get all subscription products
        $products = wc_get_products(array(
            'type' => 'subscription',
            'status' => 'publish',
            'limit' => -1
        ));
        
        foreach ($products as $product) {
            $plan_id = $product->get_meta('_rankdekho_plan_id');
            if ($plan_id) {
                $plans[$plan_id] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'plan_id' => $plan_id,
                    'subscription_period' => $product->get_meta('_subscription_period'),
                    'subscription_period_interval' => $product->get_meta('_subscription_period_interval')
                );
            }
        }
        
        return $plans;
    }
    
    /**
     * Create WooCommerce session for user
     */
    public function create_user_session($user_id) {
        if (!function_exists('WC') || !WC()->session) {
            return false;
        }
        
        // Set customer data
        WC()->customer = new WC_Customer($user_id, true);
        
        // Initialize session
        WC()->session->init();
        WC()->session->set_customer_session_cookie(true);
        
        return true;
    }
    
    /**
     * Validate cart for RankDekho items
     */
    public function validate_rankdekho_cart() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return false;
        }
        
        $has_rankdekho_items = false;
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['rankdekho_source'])) {
                $has_rankdekho_items = true;
                break;
            }
        }
        
        return $has_rankdekho_items;
    }
}