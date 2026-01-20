<?php
/**
 * Plugin Name: Blikk ECom Payments
 * Plugin URI: https://github.com/blikk-software/woo-payment-plugin
 * Description: WooCommerce Payment Plugin for Blikk ECom API
 * Version: 1.0.0
 * Author: Blikk Software
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * WooCommerce HPOS compatible: yes
 * Text Domain: blikk-payment-gateway
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BLIKK_PAYMENT_GATEWAY_VERSION', '1.0.0');
define('BLIKK_PAYMENT_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BLIKK_PAYMENT_GATEWAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Declare HPOS (High-Performance Order Storage) compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Check if WooCommerce is active
 */
function blikk_payment_gateway_check_woocommerce() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', 'blikk_payment_gateway_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * WooCommerce missing notice
 */
function blikk_payment_gateway_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>Blikk ECom Payments</strong> requires WooCommerce to be installed and active.</p></div>';
}

/**
 * Initialize the gateway class
 */
function blikk_payment_gateway_init() {
    if (!blikk_payment_gateway_check_woocommerce()) {
        return;
    }

    // Include the gateway class
    require_once BLIKK_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-blikk-payment-gateway.php';

    // Register the gateway with WooCommerce
    add_filter('woocommerce_payment_gateways', 'blikk_payment_gateway_add_to_woocommerce');
}

/**
 * Add the gateway to WooCommerce
 */
function blikk_payment_gateway_add_to_woocommerce($gateways) {
    $gateways[] = 'WC_Blikk_Payment_Gateway';
    return $gateways;
}

// Initialize the plugin after WooCommerce loads
add_action('plugins_loaded', 'blikk_payment_gateway_init');

/**
 * Register block integration
 */
function blikk_payment_gateway_register_blocks_support() {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once BLIKK_PAYMENT_GATEWAY_PLUGIN_PATH . 'includes/class-blikk-blocks-support.php';
        
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function($payment_method_registry) {
                $payment_method_registry->register(new WC_Blikk_Payment_Gateway_Blocks_Support());
            }
        );
    }
}
add_action('woocommerce_blocks_loaded', 'blikk_payment_gateway_register_blocks_support');

/**
 * Plugin activation hook
 */
function blikk_payment_gateway_activate() {
    if (!blikk_payment_gateway_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and active.');
    }
}
register_activation_hook(__FILE__, 'blikk_payment_gateway_activate');

/**
 * Add settings link to plugin page
 */
function blikk_payment_gateway_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=blikk_payment">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'blikk_payment_gateway_add_settings_link');

/**
 * Enqueue frontend styles
 */
function blikk_payment_gateway_enqueue_styles() {
    if (is_checkout() || is_order_received_page()) {
        wp_enqueue_style(
            'blikk-payment-gateway-styles',
            BLIKK_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/blikk-payment-gateway.css',
            array(),
            BLIKK_PAYMENT_GATEWAY_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'blikk_payment_gateway_enqueue_styles');