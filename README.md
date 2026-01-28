# Blikk WooCommerce Payment Plugin

WooCommerce payment gateway plugin for Blikk ECom API v3, enabling secure instant bank transfer payments directly in the WooCommerce checkout.

---

## ✨ Features

- Instant bank transfer payments via Blikk
- Integration with Blikk ECom API v3
- Secure, form-based checkout flow
- Automatic order status updates via callbacks
- Test Mode for development and staging
- Detailed logging for debugging and support
- Multi-currency support
- Full support for WooCommerce Blocks (block-based checkout)

---

## 📦 Download

The plugin is distributed as a pre-built ZIP artifact via GitHub Releases.

**Download the latest release:**  
https://github.com/blikk-software/woo-payment-plugin/releases

### Important

Under **Assets**, download the file named:

```
blikk-payment-gateway-vX.Y.Z.zip
```

- ✅ This ZIP is ready to upload to WooCommerce
- ❌ Do not download Source code (zip) or Source code (tar.gz) — those are not installable plugins

---

## 🚀 Installation

1. Log in to your WordPress Admin Dashboard
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin**
4. Select the downloaded `blikk-payment-gateway-vX.Y.Z.zip`
5. Click **Install Now**
6. Activate the plugin

After activation, Blikk will be available as a payment method in WooCommerce.

---

## ⚙️ Configuration

### Enable the payment method

1. Go to **WooCommerce → Settings → Payments**
2. Locate **Blikk**
3. Click **Manage**

### API Key setup

To process payments, the plugin must be connected to a Blikk sales channel.

#### Generate an API key

1. Log in to https://merchants.blikk.tech
2. Navigate to **Sölurásir** (Sales Channels)
3. Select the desired sales channel
4. Click **"Búa til API lykil"** (Create API key)
5. Copy and store the API key securely

> **⚠️ Note:** API keys are shown only once and cannot be retrieved later.

Paste the API key into the plugin settings and save changes.

---

## 🧪 Test Mode

The plugin includes a Test Mode for development and QA environments.

- Enable Test Mode in the Blikk plugin settings
- No real funds will be transferred

### Landsbankinn staging credentials

- **Phone number:** `0002329`
- **Username:** `LaNDsuSERg`
- **Password:** `pAsSwoRd_`

Use these credentials to simulate payments during testing.

---

## ✅ Going Live

Before enabling Blikk in production:

- Disable Test Mode
- Ensure a production API key is configured
- Verify checkout flow on both mobile and desktop

Once enabled, customers will see **Blikk – Pay by Bank** at checkout.

---

## 🧩 WooCommerce Blocks Support

This plugin fully supports the WooCommerce block-based checkout.

The Blikk payment method automatically appears in:

- Classic checkout
- Block-based checkout

No additional configuration is required.

---

## 🔧 Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- SSL certificate (required for production)
- Blikk merchant account with API access

---

## 📄 Documentation

- **ECom API v3:** https://api.blikk.tech/ecom/docs
- **Merchant onboarding & setup:** https://merchants.blikk.tech

---

## 📩 Support

For implementation help or questions, contact:

**hello@blikk.tech**
