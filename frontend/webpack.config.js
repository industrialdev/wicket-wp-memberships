const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
    rules: [
			...defaultConfig.module.rules,
      {
        test: /\.css$/i,
        use: 'raw-loader',
      },
    ],
  },
	entry: {
		...defaultConfig.entry(),
		membership_config_create: './src/membership_configs/edit.js',
		membership_tier_create: './src/membership_tiers/edit.js',
		member_list: './src/members/index.js',
		member_edit: './src/members/edit.js',
		tier_member_count: './src/membership_tiers/member_count.js',
	},
};