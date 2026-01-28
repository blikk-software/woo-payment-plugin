<?php
/**
 * Blikk Payment Gateway Class
 * 
 * Extends WooCommerce Payment Gateway to integrate with Blikk ECom API
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Blikk_Payment_Gateway class
 */
class WC_Blikk_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * Hard-coded test/staging API key
     * This is used when test mode is enabled
     */
    const TEST_API_KEY = 'CKEHNZ3P9H';

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'blikk_payment';
        $this->icon               = BLIKK_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/images/blikk-logo.svg';
        $this->has_fields         = true;
        $this->method_title       = __('Blikk Payment', 'blikk-payment-gateway');
        $this->method_description = __('Accept payments through Blikk ECom API with secure form-based redirect.', 'blikk-payment-gateway');
        $this->supports           = array('products');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        // Title and description are hardcoded, not editable
        $this->title            = __('Blikk Tafarlaus Millifærsla', 'blikk-payment-gateway');
        $this->description      = __('Borgaðu á öruggan og einfaldan hátt með tafarlausri millifærslu beint úr bankaappinu þínu', 'blikk-payment-gateway');
        $this->test_mode        = 'yes' === $this->get_option('test_mode');
        $this->debug            = 'yes' === $this->get_option('debug');
        
        // Set API URL and API Key based on test mode
        $this->api_url = $this->test_mode ? 'https://stage.blikk.tech/ecom' : 'https://api.blikk.tech/ecom';
        
        // Use hard-coded test key in test mode, otherwise use user-entered production key
        $this->api_key = $this->test_mode ? self::TEST_API_KEY : $this->get_option('api_key');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'handle_callback'));
        
        // Add admin JavaScript to handle API key field locking
        add_action('admin_footer', array($this, 'admin_scripts'));

        // Logging
        if ($this->debug) {
            $this->log = new WC_Logger();
        }
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'blikk-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Blikk Payment', 'blikk-payment-gateway'),
                'default' => 'no'
            ),
            'api_key' => array(
                'title'       => __('API Key (Production)', 'blikk-payment-gateway'),
                'type'        => 'password',
                'description' => __('Enter your Blikk production API Key for authentication. This field is only used when Test Mode is disabled.', 'blikk-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                'class'       => 'blikk-api-key-field',
            ),
            'test_mode' => array(
                'title'   => __('Test Mode', 'blikk-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Test Mode', 'blikk-payment-gateway'),
                'default' => 'yes',
                'description' => __('When enabled, uses staging API (stage.blikk.tech/ecom). When disabled, uses production API (api.blikk.tech/ecom).', 'blikk-payment-gateway'),
            ),
            'debug' => array(
                'title'   => __('Debug Log', 'blikk-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable logging', 'blikk-payment-gateway'),
                'default' => 'no',
                'description' => sprintf(__('Log Blikk events, such as API requests, inside WooCommerce > Status > Logs.', 'blikk-payment-gateway')),
            )
        );
    }

    /**
     * Check if this gateway is enabled and available
     */
    public function is_available() {
        if ('yes' === $this->enabled) {
            // In test mode, API key is always available (hard-coded)
            // In production mode, check if user has entered an API key
            if (!$this->test_mode && !$this->get_option('api_key')) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'blikk-payment-gateway'), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        // Log the payment processing start
        if ($this->debug) {
            $this->log->add('blikk-payment', 'Starting payment process for order #' . $order_id);
        }

        // Get phone number from POST data, order meta (blocks checkout), or order address
        $phone = isset($_POST['blikk_phone']) ? sanitize_text_field($_POST['blikk_phone']) : '';
        if (empty($phone)) {
            // Check order meta (set by blocks checkout)
            $phone = $order->get_meta('_blikk_phone');
        }
        if (empty($phone)) {
            // Fallback to billing or shipping phone
            $phone = $order->get_billing_phone();
            if (empty($phone)) {
                $phone = $order->get_shipping_phone();
            }
        }

        // Store phone number in order meta if not already stored
        if ($phone && !$order->get_meta('_blikk_phone')) {
            $order->update_meta_data('_blikk_phone', $phone);
            $order->save();
        }

        // Create payment request to Blikk API
        $payment_data = $this->create_payment_request($order, $phone);

        if (is_wp_error($payment_data)) {
            wc_add_notice($payment_data->get_error_message(), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        // Store payment URL in order meta
        $order->update_meta_data('_blikk_payment_url', $payment_data['scaRedirectUrl']);
        $order->update_meta_data('_blikk_payment_id', $payment_data['id']);
        $order->save();

        // Mark as pending payment
        $order->update_status('pending', __('Awaiting Blikk payment', 'blikk-payment-gateway'));

        // Log the payment processing end
        if ($this->debug) {
            $this->log->add('blikk-payment', 'Order redirect URL: '. $order->get_checkout_payment_url(true));
        }

        // Return success and redirect to receipt page
        return array(
            'result'   => 'success',
            'redirect' =>  $payment_data['scaRedirectUrl'], //$order->get_checkout_payment_url(true), // TODO: Check if redirect to payment URL directly is better, what is this other stuff
        );
    }

    /**
     * Create payment request to Blikk API
     */
    private function create_payment_request($order, $phone = '') {
        $api_url = trailingslashit($this->api_url) . 'v3/payments';


        /*
        {
            "amount": 1000,
            "callbackUrl": "https://example.com/callback",
            "currency": "ISK",
            "debtorCorpExternalId": "5005891499",
            "debtorExternalId": "2005891499",
            "debtorPhoneNo": "+3540002329",
            "items": [
                {
                "description": "",
                "id": "",
                "name": "",
                "quantity": 1,
                "sku": "",
                "unitPrice": "",
                "upc": "",
                "vat": ""
                }
            ],
            "partnerRedirectUrl": "https://example.com/redirect",
            "source": "pos-123",
            "sourceReferenceId": "1234567890"
        }
        */

        $request_data = array(
            'sourceReferenceId'      => (string)$order->get_id(),
            'amount'        => (int)$order->get_total(),
            'currency'      => $order->get_currency(),
            'callbackUrl'  => WC()->api_request_url(strtolower(get_class($this))),
            'partnerRedirectUrl' => $this->get_return_url($order),
        );
        
        // Add phone number if available
        if (!empty($phone)) {
            $request_data['debtorPhoneNo'] = $phone;
        }

        // Log the request
        if ($this->debug) {
            $this->log->add('blikk-payment', 'API Request Data: ' . print_r($request_data, true));
        }

        $response = wp_remote_post($api_url, array(
            'method'    => 'POST',
            'timeout'   => 60,
            'headers'   => array(
                'Content-Type' => 'application/json',
                'API-Key' => $this->api_key,
            ),
            'body'      => json_encode($request_data),
        ));

        if (is_wp_error($response)) {
            if ($this->debug) {
                $this->log->add('blikk-payment', 'API Request Error: ' . $response->get_error_message());
            }
            return new WP_Error('api_error', __('Unable to connect to payment gateway. Please try again.', 'blikk-payment-gateway'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($this->debug) {
            $this->log->add('blikk-payment', 'API Response Code: ' . $response_code);
            $this->log->add('blikk-payment', 'API Response Body: ' . $response_body);
        }

        if ($response_code !== 200) {
            return new WP_Error('api_error', __('Payment gateway returned an error. Please try again.', 'blikk-payment-gateway'));
        }

        $response_data = json_decode($response_body, true);

        if (!$response_data || !isset($response_data['scaRedirectUrl'])) {
            return new WP_Error('api_error', __('Invalid response from payment gateway.', 'blikk-payment-gateway'));
        }

        return $response_data;
    }

    /**
     * Output for the order received page.
     */
    public function receipt_page($order) {
        $order = wc_get_order($order);
        $payment_url = $order->get_meta('_blikk_payment_url');

        if (!$payment_url) {
            wc_add_notice(__('Payment URL not found. Please contact support.', 'blikk-payment-gateway'), 'error');
            return;
        }

        echo '<p>' . __('Thank you for your order. You will be redirected to Blikk Payment Gateway to complete your payment.', 'blikk-payment-gateway') . '</p>';
        echo '<div id="blikk-payment-form">';
        echo '<form action="' . esc_url($payment_url) . '" method="post" id="blikk_payment_form">';
        echo '<input type="submit" class="button alt" id="submit_blikk_payment_form" value="' . __('Pay Now', 'blikk-payment-gateway') . '" />';
        echo '<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order', 'blikk-payment-gateway') . '</a>';
        echo '</form>';
        echo '</div>';

        // Auto-submit the form with JavaScript
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            $('body').block({
                message: '<?php echo esc_js(__('Redirecting to payment gateway...', 'blikk-payment-gateway')); ?>',
                baseZ: 99999,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                },
                css: {
                    padding: '20px',
                    zindex: '9999999',
                    textAlign: 'center',
                    color: '#555',
                    border: '3px solid #aaa',
                    backgroundColor: '#fff',
                    cursor: 'wait',
                    lineHeight: '24px',
                }
            });
            
            // Auto-submit form after 3 seconds
            setTimeout(function() {
                $('#blikk_payment_form').submit();
            }, 3000);
        });
        </script>
        <?php
    }

    /**
     * Handle payment callback from Blikk
     */
    public function handle_callback() {
        $raw_post = file_get_contents('php://input');
        $callback_data = json_decode($raw_post, true);

        if ($this->debug) {
            $this->log->add('blikk-payment', 'Callback received: ' . print_r($callback_data, true));
        }

        if (!$callback_data || !isset($callback_data['sourceReferenceId']) || !isset($callback_data['status'])) {
            if ($this->debug) {
                $this->log->add('blikk-payment', 'Invalid callback data received');
            }
            status_header(400);
            exit;
        }

        $order_id = intval($callback_data['sourceReferenceId']);
        $order = wc_get_order($order_id);

        if (!$order) {
            if ($this->debug) {
                $this->log->add('blikk-payment', 'Order not found for callback: ' . $order_id);
            }
            status_header(404);
            exit;
        }

        // Verify the callback (you may want to add signature verification here)
        // make sure payment ID from callback matches the one stored in the order meta
        $payment_id = $order->get_meta('_blikk_payment_id');
        if ($payment_id !== $callback_data['id']) {
            if ($this->debug) {
                $this->log->add('blikk-payment', 'Payment ID mismatch for order ' . $order_id);
            }
            status_header(400);
            exit;
        }

        // TODO: fetch payment from Blikk ECOM API and use status from there for better security

        // Update order based on payment status
        switch ($callback_data['status']) {
            case 'SUCCESS':
                $order->payment_complete($callback_data['payment_id']);
                $order->add_order_note(__('Payment completed via Blikk Payment Gateway.', 'blikk-payment-gateway'));
                break;

            case 'REJECTED':
            case 'CANCELLED':
            case 'ERROR':
                $order->update_status('failed', __('Payment failed via Blikk Payment Gateway.', 'blikk-payment-gateway'));
                break;

            case 'PENDING' :
            case 'SCA_COMPLETE' :
            case 'SCA_REQUIRED' :
                $order->update_status('on-hold', __('Payment pending via Blikk Payment Gateway.', 'blikk-payment-gateway'));
                break;
                
            default:
                $order->update_status('on-hold', __('Unknown payment status: ' . $callback_data['status'], 'blikk-payment-gateway'));
                if ($this->debug) {
                    $this->log->add('blikk-payment', 'Unknown payment status: ' . $callback_data['status']);
                }
                break;
        }

        status_header(200);
        exit;
    }

    /**
     * Output payment fields on checkout page (classic checkout)
     */
    public function payment_fields() {
        // Get phone number from billing or shipping address
        $billing_phone = WC()->checkout()->get_value('billing_phone');
        $shipping_phone = WC()->checkout()->get_value('shipping_phone');
        $phone = $billing_phone ? $billing_phone : $shipping_phone;
        
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        ?>
        <fieldset id="blikk-payment-phone-field" class="wc-payment-form" style="background:transparent;">
            <p class="form-row form-row-wide">
                <label for="blikk_phone"><?php echo esc_html__('Phone Number', 'blikk-payment-gateway'); ?> <span class="required">*</span></label>
                <input type="tel" class="input-text" name="blikk_phone" id="blikk_phone" value="<?php echo esc_attr($phone); ?>" placeholder="<?php echo esc_attr__('Enter your phone number', 'blikk-payment-gateway'); ?>" />
            </p>
        </fieldset>
        <?php
    }

    /**
     * Validate payment fields
     */
    public function validate_fields() {
        // Skip validation for blocks checkout (handled in JavaScript via REST API)
        // Blocks checkout uses REST API, classic checkout uses form POST
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        
        // Only validate for classic checkout
        $phone = isset($_POST['blikk_phone']) ? sanitize_text_field($_POST['blikk_phone']) : '';
        
        if (empty($phone)) {
            wc_add_notice(__('Phone number is required for Blikk payment.', 'blikk-payment-gateway'), 'error');
            return false;
        }
        
        return true;
    }

    /**
     * Output admin JavaScript to lock/unlock API key field based on test mode
     */
    public function admin_scripts() {
        // Only output on payment gateway settings page
        if (!isset($_GET['section']) || $_GET['section'] !== $this->id) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleApiKeyField() {
                var testModeChecked = $('#woocommerce_<?php echo esc_js($this->id); ?>_test_mode').is(':checked');
                var apiKeyField = $('#woocommerce_<?php echo esc_js($this->id); ?>_api_key');
                var apiKeyRow = apiKeyField.closest('tr');
                
                if (testModeChecked) {
                    // Lock the field when test mode is enabled
                    apiKeyField.prop('disabled', true);
                    apiKeyField.prop('readonly', true);
                    apiKeyRow.addClass('blikk-test-mode-active');
                    
                    // Add visual indicator
                    if (!apiKeyRow.find('.blikk-test-mode-notice').length) {
                        apiKeyRow.find('td').first().append(
                            '<span class="blikk-test-mode-notice" style="display: block; margin-top: 5px; color: #666; font-style: italic; font-size: 12px;">' +
                            '<?php echo esc_js(__('Test mode is enabled. Using hard-coded test API key.', 'blikk-payment-gateway')); ?>' +
                            '</span>'
                        );
                    }
                } else {
                    // Unlock the field when test mode is disabled
                    apiKeyField.prop('disabled', false);
                    apiKeyField.prop('readonly', false);
                    apiKeyRow.removeClass('blikk-test-mode-active');
                    apiKeyRow.find('.blikk-test-mode-notice').remove();
                }
            }
            
            // Run on page load
            toggleApiKeyField();
            
            // Run when test mode checkbox changes
            $('#woocommerce_<?php echo esc_js($this->id); ?>_test_mode').on('change', toggleApiKeyField);
        });
        </script>
        <style type="text/css">
        .blikk-test-mode-active input[type="password"] {
            background-color: #f0f0f1;
            cursor: not-allowed;
        }
        </style>
        <?php
    }
}