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
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'blikk_payment';
        $this->icon               = ''; // TODO: add Blikk logo
        $this->has_fields         = false;
        $this->method_title       = __('Blikk Payment', 'blikk-payment-gateway');
        $this->method_description = __('Accept payments through Blikk ECom API with secure form-based redirect.', 'blikk-payment-gateway');
        $this->supports           = array('products');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title            = $this->get_option('title');
        $this->description      = $this->get_option('description');
        $this->api_url          = $this->get_option('api_url');
        $this->api_key          = $this->get_option('api_key');
        $this->test_mode        = 'yes' === $this->get_option('test_mode');
        $this->debug            = 'yes' === $this->get_option('debug');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'handle_callback'));

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
            'title' => array(
                'title'       => __('Title', 'blikk-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'blikk-payment-gateway'),
                'default'     => __('Blikk Payment', 'blikk-payment-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'blikk-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'blikk-payment-gateway'),
                'default'     => __('Pay securely using Blikk Payment Gateway.', 'blikk-payment-gateway'),
                'desc_tip'    => true,
            ),
            'api_url' => array(
                'title'       => __('API URL', 'blikk-payment-gateway'),
                'type'        => 'url',
                'description' => __('Enter the Blikk ECom API URL for payment processing.', 'blikk-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_key' => array(
                'title'       => __('API Key', 'blikk-payment-gateway'),
                'type'        => 'password',
                'description' => __('Enter your Blikk API Key for authentication.', 'blikk-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'test_mode' => array(
                'title'   => __('Test Mode', 'blikk-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Test Mode', 'blikk-payment-gateway'),
                'default' => 'yes',
                'description' => __('Place the payment gateway in test mode using test API credentials.', 'blikk-payment-gateway'),
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
            if (!$this->api_url || !$this->api_key) {
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

        // Create payment request to Blikk API
        $payment_data = $this->create_payment_request($order);

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
    private function create_payment_request($order) {
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
            // 'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),  // TODO: remove
            // 'customer_email' => $order->get_billing_email(),     // TODO: Check if needed
            // 'return_url'    => $this->get_return_url($order),    // TODO: Check if needed
            // 'cancel_url'    => $order->get_cancel_order_url(),   // TODO: Check if needed
            // TODO: add basket to items
            // 'description'   => sprintf(__('Order #%s from %s', 'blikk-payment-gateway'), $order->get_order_number(), get_bloginfo('name')),
        );

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

        if (!$callback_data || !isset($callback_data['order_id']) || !isset($callback_data['status'])) {
            if ($this->debug) {
                $this->log->add('blikk-payment', 'Invalid callback data received');
            }
            status_header(400);
            exit;
        }

        $order_id = intval($callback_data['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            if ($this->debug) {
                $this->log->add('blikk-payment', 'Order not found for callback: ' . $order_id);
            }
            status_header(404);
            exit;
        }

        // Verify the callback (you may want to add signature verification here)
        $payment_id = $order->get_meta('_blikk_payment_id');
        if ($payment_id !== $callback_data['payment_id']) {
            if ($this->debug) {
                $this->log->add('blikk-payment', 'Payment ID mismatch for order ' . $order_id);
            }
            status_header(400);
            exit;
        }

        // Update order based on payment status
        switch ($callback_data['status']) {
            case 'SUCCESS':
                $order->payment_complete($callback_data['payment_id']);
                $order->add_order_note(__('Payment completed via Blikk Payment Gateway.', 'blikk-payment-gateway'));
                break;

            case 'REJECTED':
            case 'CANCELLED':
                $order->update_status('failed', __('Payment failed via Blikk Payment Gateway.', 'blikk-payment-gateway'));
                break;

            case 'SCA_COMPLETE' :
            case 'SCA_REQUIRED' :
                $order->update_status('on-hold', __('Payment pending via Blikk Payment Gateway.', 'blikk-payment-gateway'));
                break;

            default:
                if ($this->debug) {
                    $this->log->add('blikk-payment', 'Unknown payment status: ' . $callback_data['status']);
                }
                break;
        }

        status_header(200);
        exit;
    }
}