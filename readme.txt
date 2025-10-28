=== Blikk Payment Gateway ===
Contributors: blikk-software
Tags: woocommerce, payment, gateway, blikk, ecommerce
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments on your WooCommerce store using Blikk ECom API with secure form-based redirect.

== Description ==

The Blikk Payment Gateway plugin enables WooCommerce store owners to accept payments through the Blikk ECom API. This plugin provides a secure, form-based redirect payment flow that integrates seamlessly with your WooCommerce checkout process.

**Key Features:**

* Secure form-based payment processing
* Integration with Blikk ECom API
* Automatic order status updates via callbacks
* Test mode for development and testing
* Comprehensive logging for debugging
* Responsive payment forms
* Multi-currency support
* Order management integration

**How it works:**

1. Customer proceeds to checkout and selects Blikk Payment
2. Plugin creates a payment request via Blikk ECom API
3. Customer is redirected to secure Blikk payment page
4. After payment completion, customer returns to your store
5. Order status is automatically updated via secure callbacks

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/blikk-payment-gateway` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Settings > Payments
4. Enable "Blikk Payment" and configure your API settings
5. Enter your Blikk Merchant ID, API Key, and API URL
6. Test the integration using test mode before going live

== Configuration ==

After installing and activating the plugin:

1. Navigate to **WooCommerce → Settings → Payments**
2. Click on **Blikk Payment** to configure the gateway
3. Fill in the required fields:
   - **API URL**: Your Blikk ECom API endpoint
   - **Merchant ID**: Your Blikk merchant identifier
   - **API Key**: Your Blikk API authentication key
4. Enable **Test Mode** for testing
5. Save your settings

== Frequently Asked Questions ==

= Do I need a Blikk merchant account? =

Yes, you need a valid Blikk merchant account with API access to use this plugin.

= Does this plugin support test transactions? =

Yes, the plugin includes a test mode for development and testing purposes.

= What currencies are supported? =

The plugin supports all currencies that are supported by your Blikk merchant account and WooCommerce.

= How are payment notifications handled? =

The plugin automatically handles payment status updates through secure API callbacks from Blikk.

== Screenshots ==

1. Payment gateway settings page
2. Checkout page with Blikk payment option
3. Payment redirect page

== Changelog ==

= 1.0.0 =
* Initial release
* Form-based payment processing
* Blikk ECom API integration
* Automatic order status updates
* Test mode support
* Comprehensive logging

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Blikk Payment Gateway plugin.

== Support ==

For support, please contact Blikk Software or visit our documentation.

== Technical Requirements ==

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Valid SSL certificate (recommended for live payments)
* Blikk merchant account with API access