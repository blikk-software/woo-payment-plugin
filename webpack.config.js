const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'blocks/checkout': './assets/js/blocks/checkout.js',
    },
    output: {
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
    // WooCommerce packages are provided by WooCommerce at runtime
    // and should not be bundled into the output
    externals: {
        '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
        '@woocommerce/settings': ['wc', 'wcSettings'],
        '@woocommerce/block-data': ['wc', 'wcBlocksData'],
        '@wordpress/element': ['wp', 'element'],
        '@wordpress/data': ['wp', 'data'],
        '@wordpress/i18n': ['wp', 'i18n'],
        '@wordpress/html-entities': ['wp', 'htmlEntities'],
    },
};
