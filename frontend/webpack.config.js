const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		membership_config_create: './src/membership_config_create.js',
	},
};