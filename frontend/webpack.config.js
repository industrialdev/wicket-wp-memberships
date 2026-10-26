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
		membership_config_create: './src/membership_configs/edit.js',
		membership_bundle_config_create: './src/membership_bundle_configs/pages/edit.js',
		membership_tier_create: './src/membership_tiers/edit.js',
		member_list: './src/members/index.js',
		bundle_member_list: './src/members/bundle_list.js',
		bundle_member_edit: './src/membership_bundles/pages/edit.js',
		create_membership_bundle: './src/create_membership_bundle/pages/create.js',
		member_edit: './src/members/edit.js',
		tier_member_count: './src/membership_tiers/member_count.js',
		wicket_memberships_tier_cell_info: './src/membership_tiers/tier_cell_info.js',
	},
};
