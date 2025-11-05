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
    externals: {
        '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
        '@woocommerce/settings': ['wc', 'wcSettings'],
    },
};
