const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		membership_config_create: './src/membership_config/index.js',
		membership_tier_create: './src/membership_tier_create.js',
		member_list: './src/members/index.js',
		member_edit: './src/members/edit.js',
	},
};