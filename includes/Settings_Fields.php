<?php

namespace Wicket_Memberships;

/**
 * Registers and renders the fields on the Settings > Wicket Memberships page.
 *
 * Option keys, input names and defaults are owned by these renderers; saving is
 * Settings::wicket_membership_plugin_options_validate().
 *
 * @see Settings  Public entry points that delegate here.
 * @package Wicket_Memberships
 */
class Settings_Fields {

  /**
   * Register the plugin option, settings sections and fields.
   *
   * Sections are grouped into tabs via per-tab Settings API page ids. Field ids, option keys
   * and input names are unchanged; only where each field appears on the page.
   *
   * @see Settings_Page::SETTINGS_TABS
   *
   * @return void
   */
  public static function register() {
    register_setting( 'wicket_membership_plugin_options', 'wicket_membership_plugin_options', [__NAMESPACE__.'\\Settings', 'wicket_membership_plugin_options_validate'] );

    $sections = [
      'general' => [
        'woocommerce' => [ 'WooCommerce', [ Settings_Page::class, 'section_membership_products_text' ], [
          'wicket_mship_membership_categories' => 'Membership Categories',
          'wicket_mship_assign_subscription'   => 'Membership Subscription Assignment',
          'wicket_mship_hide_subscription_cancel' => 'Hide Subscription Self-Serve Cancellation',
        ] ],
        'mdp_connection' => [ 'Wicket MDP', null, [
          'wicket_mship_mdp_timezone'         => 'MDP Timezone',
          'wicket_mship_membership_merge_key' => 'Merge Webhook API Key',
        ] ],
      ],
      'renewals' => [
        'renewal_settings' => [ 'Renewals', null, [
          'wicket_mship_disable_renewal'     => 'Disable Renewal Callouts',
          'wicket_mship_multi_tier_renewal'  => 'Multi-Tier Renewals',
        ] ],
        'autorenew_settings' => [ 'Auto-Renew', null, [
          'wicket_mship_autorenew_toggle'   => 'Member Auto-Renew Toggle',
          'wicket_mship_autorenew_override' => 'Auto-Renew Override',
        ] ],
      ],
      'import' => [
        'import_access' => [ 'Command-Line Import', [ Settings_Page::class, 'section_import_text' ], [
          'allow_local_imports' => 'Allow Local Imports',
        ] ],
        'import_subscriptions' => [ 'Import Subscription Options', [ Settings_Page::class, 'section_import_subscriptions_text' ], [
          'wicket_mship_import_create_subscriptions_tier_only' => 'Subscription Tiers',
          'wicket_mship_import_create_subscriptions'           => 'All Memberships',
        ] ],
      ],
      'developer' => [
        'debug_settings' => [ 'Debug Settings', [ Settings_Page::class, 'section_debug_text' ], [] ],
        'debug_admin' => [ 'Admin Debug Output', [ Settings_Page::class, 'section_debug_admin_text' ], [
          'wicket_membership_debug_mode' => 'Admin Debug Mode',
          'bypass_status_change_lockout' => 'Bypass Status Change Rules',
          'wicket_show_order_debug_data' => 'Order Membership Data',
        ] ],
        'debug_public' => [ 'Front-End Debug Output', [ Settings_Page::class, 'section_debug_public_text' ], [
          'wicket_memberships_debug_acc'      => 'Account Centre Debug',
          'wicket_memberships_debug_renew'    => 'Renewal Window Debug',
          'wicket_memberships_debug_cart_ids' => 'Cart Item IDs',
        ] ],
        'debug_mdp' => [ 'Wicket MDP Sync', null, [
          'bypass_wicket' => 'Bypass Wicket MDP',
        ] ],
        // The drift audit's on/off checkbox renders inside the Scheduled Jobs table.
        'status_settings' => [ 'Scheduled Jobs', [ Settings_Jobs::class, 'render' ], [] ],
      ],
    ];

    foreach ( $sections as $tab => $tab_sections ) {
      $page = Settings_Page::settings_page_id( $tab );
      foreach ( $tab_sections as $section_id => [ $title, $description_callback, $fields ] ) {
        add_settings_section( $section_id, $title, $description_callback, $page );
        foreach ( $fields as $field => $label ) {
          // label_for ties the row label to the input with the same id.
          add_settings_field( $field, $label, [ __CLASS__, $field ], $page, $section_id, [ 'label_for' => $field ] );
        }
      }
    }
  }

  /**
   * Render one option checkbox with an inline label and an optional description.
   *
   * @param  string       $key          Option key inside `wicket_membership_plugin_options`; also the input id.
   * @param  string       $label        Text beside the checkbox. May contain markup.
   * @param  string       $description  Extra help shown below. May contain markup.
   * @param  int|string   $default      Value assumed when the option has never been saved.
   *
   * @return void
   */
  private static function render_checkbox( $key, $label, $description = '', $default = '' ) {
    $options = get_option( 'wicket_membership_plugin_options' );
    $value   = isset( $options[ $key ] ) ? $options[ $key ] : $default;

    printf(
      '<label for="%1$s"><input id="%1$s" name="wicket_membership_plugin_options[%1$s]" type="checkbox" value="1" %2$s /> %3$s</label>',
      esc_attr( $key ),
      checked( 1, $value, false ),
      $label
    );

    if ( $description ) {
      echo '<p class="description">' . $description . '</p>';
    }
  }

  /**
   * Render the merge webhook signature key field.
   *
   * @return void
   */
  public static function wicket_mship_membership_merge_key() {
    $options = get_option( 'wicket_membership_plugin_options' );
    $value   = isset( $options['wicket_mship_membership_merge_key'] ) ? $options['wicket_mship_membership_merge_key'] : '';
    echo "<input id='wicket_mship_membership_merge_key' name='wicket_membership_plugin_options[wicket_mship_membership_merge_key]' type='text' class='regular-text' value='" . esc_attr( $value ) . "' />";
    echo '<p class="description">Secret used to verify merge webhooks the MDP sends to <code>' . esc_html( str_replace( '/wp', '', get_site_url() ) ) . '/wp-json/wicket_member/v1/membership/merge</code>. Must match the secret on the MDP webhook.</p>';
  }

  /**
   * Render the multi-tier renewals checkbox.
   *
   * @return void
   */
  public static function wicket_mship_multi_tier_renewal() {
    self::render_checkbox( 'wicket_mship_multi_tier_renewal', 'Enable multi-tier renewals', 'Adds multi-tier options to membership configs, renewal callouts and Gravity Forms. Configure the flow before enabling.' );
  }

  /**
   * Render the disable renewal callouts checkbox.
   *
   * @return void
   */
  public static function wicket_mship_disable_renewal() {
    self::render_checkbox( 'wicket_mship_disable_renewal', 'Hide renewal callouts in the Account Centre', 'Turns off early renewal, grace period and pending approval callouts. Useful during bulk imports.' );
  }

  /**
   * Render the membership subscription assignment checkbox.
   *
   * @return void
   */
  public static function wicket_mship_assign_subscription() {
    self::render_checkbox( 'wicket_mship_assign_subscription', 'Allow assigning a membership by ID on subscriptions', 'Adds a Membership ID field to the admin subscription edit screen. Saving it links the subscription to that membership so renewals use it.' );
  }

  /**
   * Render the hide subscription self-serve cancellation checkbox.
   *
   * @since 1.0.123
   *
   * @see Utilities::maybe_hide_subscription_cancel_action()
   *
   * @return void
   */
  public static function wicket_mship_hide_subscription_cancel() {
    self::render_checkbox( 'wicket_mship_hide_subscription_cancel', 'Hide the Cancel button on the member\'s View Subscription page', 'Members will not be able to cancel on their own. Admins can still cancel subscriptions in WooCommerce.' );
  }

  /**
   * Get the Membership Categories the settings form pre-selects, i.e. what a save will store.
   *
   * The saved or inherited list, or the "Membership" product category on sites that have
   * never saved either key. An explicit empty save is respected.
   *
   * @since 1.0.123
   *
   * @see Helper::get_membership_category_ids()
   *
   * @return int[]  Product category term IDs.
   */
  public static function get_form_membership_category_ids() {
    $options = get_option( 'wicket_membership_plugin_options' );
    $never_saved = ! is_array( $options )
      || ( ! array_key_exists( 'wicket_mship_membership_categories', $options ) && ! array_key_exists( 'wicket_show_mship_order_org_search', $options ) );

    if ( $never_saved ) {
      // Match by name or slug, the same way has_term( 'Membership' ) does in Helper::is_in_membership_category().
      $default_category = get_term_by( 'name', 'Membership', 'product_cat' ) ?: get_term_by( 'slug', 'membership', 'product_cat' );
      if ( $default_category ) {
        return [ (int) $default_category->term_id ];
      }
    }

    return Helper::get_membership_category_ids();
  }

  /**
   * Render the Membership Categories multi-select.
   *
   * Pre-selects the saved categories, inheriting the legacy org-search selection
   * until this field is first saved. On sites that have never saved either key,
   * the "Membership" product category is pre-selected as the default.
   *
   * @since 1.0.123
   *
   * @see Settings::get_form_membership_category_ids()
   *
   * @return void
   */
  public static function wicket_mship_membership_categories() {
    $categories_selected = self::get_form_membership_category_ids();

    // Include empty categories so a saved selection with no products yet is not dropped on the next save.
    $product_categories = get_terms( [
      'taxonomy'   => 'product_cat',
      'hide_empty' => false,
    ] );

    // `.wicket-membership-categories` gets select2 from the base plugin's wicket_admin.js.
    ?><select id="wicket_mship_membership_categories" class="wicket-membership-categories wicket-admin-select2" multiple="multiple" name="wicket_membership_plugin_options[wicket_mship_membership_categories][]"><?php
    if ( ! is_wp_error( $product_categories ) ) {
      foreach ( $product_categories as $category ) {
        ?>
          <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( in_array( $category->term_id, $categories_selected, true ) ); ?>>
            <?php echo esc_html( $category->name ); ?>
          </option>
        <?php
      }
    }
    ?></select>
    <p class="description">Products in these categories are membership products across the Wicket platform.</p>
    <?php
  }

  /**
   * Render the bypass Wicket MDP checkbox.
   *
   * @return void
   */
  public static function bypass_wicket() {
    self::render_checkbox( 'bypass_wicket', 'Do not create memberships in the Wicket MDP', 'Saves memberships locally with a fake MDP ID and skips MDP updates. Testing only. <code>BYPASS_WICKET</code>' );
  }

  /**
   * Render the user auto-renew toggle checkbox.
   *
   * @return void
   */
  public static function wicket_mship_autorenew_toggle() {
    self::render_checkbox( 'wicket_mship_autorenew_toggle', 'Let members choose auto-renew with the Wicket toggle', 'Adds the <code>[wicket_autorenew_toggle_shortcode]</code> shortcode and Gravity Forms value. On a specific subscription, it turns that subscription\'s auto-renew on or off right away. Elsewhere, it saves the member\'s preference for future subscriptions, which is applied only while Auto-Renew Override is on.' );
  }

  /**
   * Renders the auto-renew override setting checkbox.
   *
   * When enabled, order processing reads the subscription_autopay_enabled user meta
   * and overwrites _requires_manual_renewal on the subscription accordingly.
   * Defaults to enabled (value 1) to preserve pre-existing behaviour when not yet saved.
   *
   * @return void
   */
  public static function wicket_mship_autorenew_override() {
    // Default to enabled when the option has never been saved, preserving legacy behaviour.
    self::render_checkbox( 'wicket_mship_autorenew_override', 'Match subscription auto-renew to the member\'s Wicket toggle preference on every order', 'On: each new or renewal order sets the subscription\'s auto-renew to the member\'s saved preference. Off: WooCommerce Subscriptions decides, and changes members make to a subscription (in WooCommerce or with the Wicket toggle) are kept. Not applied to tiers with renewal type <code>Subscription</code>.', 1 );
  }

  /**
   * Renders the autorenew drift-audit setting checkbox.
   *
   * When enabled, a nightly Action Scheduler job compares 100 memberships' stored autorenew
   * meta against a fresh Autorenew::resolve_status() recompute and logs any disagreement.
   * Report-only: this never corrects the stored value, since it exists to catch cases where
   * an Autorenew_Sync trigger should have refreshed a membership but didn't.
   *
   * Defaults to disabled: unlike the override setting above, there's no legacy behaviour to
   * preserve here, and running the audit costs a nightly batch of ~2 queries per membership.
   *
   * @return void
   */
  public static function wicket_mship_autorenew_audit() {
    // Rendered inside the Scheduled Jobs table; saved with the rest of the form.
    self::render_checkbox( 'wicket_mship_autorenew_audit', 'Run nightly', '', 0 );
  }

  /**
   * Render the MDP timezone select.
   *
   * @return void
   */
  public static function wicket_mship_mdp_timezone() {
    $options           = get_option( 'wicket_membership_plugin_options' );
    $selected_timezone = isset( $options['wicket_mship_mdp_timezone'] ) ? $options['wicket_mship_mdp_timezone'] : '';
    ?>
    <select id="wicket_mship_mdp_timezone" class="wicket-mship-select2" name="wicket_membership_plugin_options[wicket_mship_mdp_timezone]">
      <?php foreach ( timezone_identifiers_list() as $timezone ) : ?>
        <option value="<?php echo esc_attr( $timezone ); ?>" <?php selected( $timezone, $selected_timezone ); ?>><?php echo esc_html( $timezone ); ?></option>
      <?php endforeach; ?>
    </select>
    <p class="description">Membership dates use this timezone. It is preloaded from the MDP and rarely needs changing.</p>
    <?php
  }

  /**
   * Renders the "tier subscription only" import option checkbox.
   *
   * This is the middle of three mutually understood import-subscription states:
   *   - Neither this nor the "every membership" option checked -> no subscriptions on import.
   *   - This checked -> only memberships whose Tier renewal type is "Subscription" get one.
   *   - The "every membership" option checked -> all imported memberships get one (overrides this).
   *
   * Defaults to enabled (opt-out) when never saved, preserving the legacy behaviour where
   * subscription-renewal tiers always received a subscription on import.
   *
   * @see wicket_mship_import_create_subscriptions() The broader "every membership" option.
   * @see Import_Controller::create_individual_memberships() Consumes both options.
   *
   * @return void
   */
  public static function wicket_mship_import_create_subscriptions_tier_only() {
    // Default to checked when the option has never been saved, preserving legacy import behaviour.
    self::render_checkbox( 'wicket_mship_import_create_subscriptions_tier_only', 'Create subscriptions for memberships in tiers with renewal type <code>Subscription</code>', 'On by default.', 1 );
  }

  /**
   * Renders the "every imported membership" subscription option checkbox.
   *
   * When checked this overrides the tier-only option and creates a subscription for
   * every imported membership regardless of its Tier renewal type. See
   * wicket_mship_import_create_subscriptions_tier_only() for the three-state behaviour.
   *
   * @see wicket_mship_import_create_subscriptions_tier_only() The narrower tier-only option.
   *
   * @return void
   */
  public static function wicket_mship_import_create_subscriptions() {
    self::render_checkbox( 'wicket_mship_import_create_subscriptions', 'Create subscriptions for every imported membership', 'Overrides the Subscription Tiers option.' );
  }

  /**
   * Render the subscription renewal flow checkbox. Not registered on the page.
   *
   * @return void
   */
  public static function wicket_mship_subscription_renew() {
    self::render_checkbox( 'wicket_mship_subscription_renew', '<span class="attention">[BETA]</span> Use the subscription renewal flow for Tiers', 'Checks for an existing subscription renewal order to checkout, and creates one if not found.' );
  }

  /**
   * Render the Account Centre debug checkbox.
   *
   * @return void
   */
  public static function wicket_memberships_debug_acc() {
    self::render_checkbox( 'wicket_memberships_debug_acc', 'Show renewal callout debug info in the Account Centre', 'Shows each membership\'s callout data and notes, such as memberships skipped for auto-renew. <code>WICKET_MEMBERSHIPS_DEBUG_ACC</code>' );
  }

  /**
   * Render the renewal window debug checkbox.
   *
   * @return void
   */
  public static function wicket_memberships_debug_renew() {
    self::render_checkbox( 'wicket_memberships_debug_renew', 'Allow renewals at any time', '<span class="attention">Any member can renew and check out outside their renewal window.</span> Add <code>?wicket_wp_membership_debug_days=N</code> to a URL to preview renewal callouts. <code>WICKET_MEMBERSHIPS_DEBUG_RENEW</code>' );
  }

  /**
   * Render the cart product meta debug checkbox.
   *
   * @return void
   */
  public static function wicket_memberships_debug_cart_ids() {
    self::render_checkbox( 'wicket_memberships_debug_cart_ids', 'Show organization and renewal IDs in the cart', 'Also adds editable ID fields to product pages. <code>WICKET_MEMBERSHIPS_DEBUG_CART_IDS</code>' );
  }

  /**
   * Render the allow local imports checkbox and the command-line import steps.
   *
   * @return void
   */
  public static function allow_local_imports() {
    self::render_checkbox( 'allow_local_imports', 'Allow command-line CSV imports', 'Requires SSH access. <code>ALLOW_LOCAL_IMPORTS</code>' );

    // The subscription sync tool is loaded from wicket.php only while this setting is enabled.
    $sync_tool_url = esc_url( home_url( '/?mship_subscription_sync=1' ) );
    ?>
    <details>
      <summary>How to run an import</summary>
      <ol>
        <li>Run the MDP User Sync first, so the WordPress users exist.</li>
        <li>Upload the membership CSV to the media folder. Use separate files for individuals and organizations.</li>
        <li>From the plugin folder, run <code>php ./csv_import_threads.php</code> on the SSH command line.</li>
        <li>Follow the prompts to add the correct arguments. The import runs in batches: wait for it to finish, or run it in the background.</li>
        <li>To populate a remote site, run the import locally with <code>api_domain</code> pointing at that site. The plugin must be installed there.</li>
      </ol>
      <p><code>php ./csv_import_threads.php { individual|organization } { file_path from /uploads/ } { api_domain - optional str } { skip_approval - optional bool }</code></p>
      <p><strong>Reusing existing subscriptions:</strong> When importing memberships <em>without</em> creating new subscriptions, use the Subscription Sync tool to attach them to subscriptions that already exist. It walks active and on-hold subscriptions and links each to the current membership of the same user (matched by email) on the same tier (resolved from the subscription's product). Only available while this setting is enabled.</p>
      <p><a target="_blank" rel="noopener" href="<?php echo $sync_tool_url; ?>">Open the Subscription Sync tool in a new tab &#8599;</a></p>
    </details>
    <?php
  }

  /**
   * Render the order membership data debug checkbox.
   *
   * @return void
   */
  public static function wicket_show_order_debug_data() {
    self::render_checkbox( 'wicket_show_order_debug_data', 'Show raw membership data on orders and subscriptions', 'Shown on admin edit screens only. <code>WICKET_SHOW_ORDER_DEBUG_DATA</code>' );
  }

  /**
   * Render the bypass status change rules checkbox.
   *
   * @return void
   */
  public static function bypass_status_change_lockout() {
    self::render_checkbox( 'bypass_status_change_lockout', 'Allow any status change', 'Ignores the status sequence rules. <code>BYPASS_STATUS_CHANGE_LOCKOUT</code>' );
  }

  /**
   * Render the admin debug mode checkbox.
   *
   * @return void
   */
  public static function wicket_membership_debug_mode() {
    self::render_checkbox( 'wicket_membership_debug_mode', 'Show debug info in the plugin admin', 'Adds the raw Memberships post list to the menu and shows tier and config data on list pages. <code>WICKET_MEMBERSHIPS_DEBUG_MODE</code>' );
  }
}
