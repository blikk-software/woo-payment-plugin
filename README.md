# Woo Payment Plugin

WooCommerce Payment Plugin for Blikk ECom API

## Features

- Secure form-based payment processing
- Integration with Blikk ECom API v3
- Automatic order status updates via callbacks
- Test mode for development and testing
- Comprehensive logging for debugging
- Multi-currency support
- WooCommerce Blocks checkout support

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Valid SSL certificate (recommended for live payments)
- Blikk merchant account with API access

## Installation

1. Upload the plugin files to `/wp-content/plugins/blikk-payment-gateway`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Settings > Payments
4. Enable "Blikk Payment" and configure your API settings

## WooCommerce Blocks Support

This plugin fully supports the WooCommerce block-based checkout experience. The payment method will automatically appear in both classic and block-based checkouts.

## Development

To build the JavaScript assets for WooCommerce Blocks:

```bash
npm install
npm run build
```

For development with watch mode:

```bash
npm run dev
```

## Local WooCommerce Setup

1. run `docker-compose up -d` to start the local WordPress and MySQL containers.
2. Log into the the admin panel of your WordPress installation, usually at <http://localhost:8080/wp-admin/>, select Plugins ⇒ Add New ⇒ search for "woocommerce" ⇒ and hit Install Now.
    1. After the installation is completed, click Activate and follow the setup process. FYI, WooCommerce will suggest to install and sign-up for a lot of other third-party plugins.
