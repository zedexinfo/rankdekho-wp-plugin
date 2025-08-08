<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists("RankDekho_Theme")) {
    class RankDekho_Theme
    {
        protected static $instance;

        public function __construct()
        {
            add_filter( 'woocommerce_checkout_fields' , [$this, 'make_phone_field_required'] );
            add_action('woocommerce_review_order_before_submit', [$this, 'rankdekho_terms_and_conditions_checkbox'], 9);
            add_action('woocommerce_checkout_process', [$this, 'rankdekho_terms_and_conditions_validation']);
            add_filter('woocommerce_account_menu_items', [$this, 'rankdekho_customize_my_account_menu']);
            add_filter('woocommerce_get_endpoint_url', [$this, 'rankdekho_custom_dashboard'], 10, 4);
            add_action('woocommerce_before_checkout_form', [$this, 'back_to_pricing_page']);
        }

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function make_phone_field_required( $fields ) {
            $fields['billing']['billing_phone']['required'] = true;
            return $fields;
        }

        public function rankdekho_terms_and_conditions_checkbox() {
            woocommerce_form_field('terms_conditions_checkbox', array(
                'type'  => 'checkbox',
                'class' => ['form-row terms'],
                'label' => __(
                    'I have read and agree to the 
            <a href="https://rankdekho.com/terms-of-use" target="_blank"><u>Terms & Conditions</u></a>, 
            <a href="https://rankdekho.com/privacy-policy" target="_blank"><u>Privacy Policy</u></a>, and 
            <a href="https://rankdekho.com/refund-and-cancellation-policy" target="_blank"><u>Refund Policy</u></a>.'
                ),
                'required' => true,
            ));
        }

        public function rankdekho_terms_and_conditions_validation() {
            if (empty($_POST['terms_conditions_checkbox'])) {
                wc_add_notice(__('You must accept the Terms & Conditions, Privacy Policy, and Refund Policy to proceed.'), 'error');
            }
        }

        public function rankdekho_customize_my_account_menu($items) {
            unset($items['dashboard']);
            unset($items['orders']);
            unset($items['downloads']);
            unset($items['edit-address']);
            unset($items['edit-account']);
            unset($items['customer-logout']);

            $new_items['custom-dashboard'] = '← Dashboard';

            return $new_items + $items;
        }

        public function rankdekho_custom_dashboard($url, $endpoint, $value, $permalink) {
            if ($endpoint === 'custom-dashboard') {
                return 'https://stage-app.rankdekho.com/';
            }
            return $url;
        }

        public function back_to_pricing_page() {
            $product_id = 0;
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                break;
            }

            $pricing_url = add_query_arg('product_id', $product_id, 'https://stage-app.rankdekho.com/pricing');

            echo '<div style="margin-bottom:20px;">
                    <a href="' . esc_url($pricing_url) . '" class="button" style="background:#7952BD; color:#fff; padding:10px 20px; border-radius:5px; text-decoration: unset">
                        ← Back to Pricing
                    </a>
                  </div>';
        }
    }
}