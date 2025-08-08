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

        public function rankdekho_customize_my_account_menu($menu_links) {
            unset($menu_links['orders']);
            unset($menu_links['downloads']);
            unset($menu_links['edit-address']);
            unset($menu_links['edit-account']);
            unset($menu_links['customer-logout']);

            return $menu_links;
        }
    }
}