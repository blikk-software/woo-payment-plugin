<?php
/**
 * Blikk Payment Gateway Blocks Support
 * 
 * Adds support for WooCommerce Blocks checkout
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blikk Payment Gateway Blocks Support class
 */
final class WC_Blikk_Payment_Gateway_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * Payment method name defined by payment methods extending this class.
     *
     * @var string
     */
    protected $name = 'blikk_payment';

    /**
     * An instance of the gateway class.
     *
     * @var WC_Blikk_Payment_Gateway
     */
    private $gateway;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'add_payment_request_data'), 10, 2);
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_blikk_payment_settings', array());
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways['blikk_payment']) ? $gateways['blikk_payment'] : null;
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return !is_null($this->gateway) && $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_asset_path = BLIKK_PAYMENT_GATEWAY_PLUGIN_PATH . 'build/blocks/checkout.asset.php';
        
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version'      => BLIKK_PAYMENT_GATEWAY_VERSION
            );
        
        $script_url = BLIKK_PAYMENT_GATEWAY_PLUGIN_URL . 'build/blocks/checkout.js';

        wp_register_script(
            'wc-blikk-payment-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-blikk-payment-blocks', 'blikk-payment-gateway');
        }

        return array('wc-blikk-payment-blocks');
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return array(
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => $this->gateway ? $this->gateway->supports : array(),
        );
    }

    /**
     * Add payment request data to the order.
     *
     * @param \PaymentContext $context Holds context for the payment.
     * @param \PaymentResult  $result  Result object for the payment.
     */
    public function add_payment_request_data($context, &$result) {
        if ($this->name !== $context->payment_method) {
            return;
        }

        // Additional processing can be added here if needed
        // For example, adding custom data to the order
    }
}
