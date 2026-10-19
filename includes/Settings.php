<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Membership_Controller;
use Wicket_Memberships\Utilities;
use Wicket_Memberships\Helper;
use Wicket_Memberships\Autorenew_Sync;

/**
 * Settings entry points, options validation and timezone defaults for Wicket Memberships.
 * @package Wicket_Memberships
 */
class Settings {

  // Entry points kept for wicket.php hooks and existing callers. The page, its fields and the
  // Scheduled Jobs section live in Settings_Page, Settings_Fields and Settings_Jobs.

  /**
   * Query args that trigger the settings page's manual Run Now / Refresh All Now links.
   *
   * @see Settings_Jobs::MANUAL_ACTION_TRIGGER_PARAMS
   *
   * @var string[]
   */
  const MANUAL_ACTION_TRIGGER_PARAMS = Settings_Jobs::MANUAL_ACTION_TRIGGER_PARAMS;

  /** @see Settings_Page::add_page() */
  public static function wicket_membership_add_settings_page() {
    Settings_Page::add_page();
  }

  /** @see Settings_Page::render() */
  public static function wicket_membership_render_plugin_settings_page() {
    Settings_Page::render();
  }

  /** @see Settings_Page::enqueue_assets() */
  public static function enqueue_settings_assets( $hook_suffix ) {
    Settings_Page::enqueue_assets( $hook_suffix );
  }

  /** @see Settings_Page::body_class() */
  public static function settings_admin_body_class( $classes ) {
    return Settings_Page::body_class( $classes );
  }

  /** @see Settings_Page::render_environment_notices() */
  public static function wicket_membership_render_environment_notices() {
    Settings_Page::render_environment_notices();
  }

  /** @see Settings_Page::render_category_switch_notice() */
  public static function wicket_membership_render_category_switch_notice() {
    Settings_Page::render_category_switch_notice();
  }

  /** @see Settings_Page::section_debug_text() */
  public static function wicket_plugin_section_debug_text() {
    Settings_Page::section_debug_text();
  }

  /** @see Settings_Page::section_membership_products_text() */
  public static function wicket_plugin_section_membership_products_text() {
    Settings_Page::section_membership_products_text();
  }

  /** @see Settings_Page::section_import_text() */
  public static function wicket_plugin_section_import_text() {
    Settings_Page::section_import_text();
  }

  /** @see Settings_Page::section_debug_admin_text() */
  public static function wicket_plugin_section_debug_admin_text() {
    Settings_Page::section_debug_admin_text();
  }

  /** @see Settings_Page::section_debug_public_text() */
  public static function wicket_plugin_section_debug_public_text() {
    Settings_Page::section_debug_public_text();
  }

  /** @see Settings_Fields::register() */
  public static function wicket_membership_register_settings() {
    Settings_Fields::register();
  }

  /** @see Settings_Fields::get_form_membership_category_ids() */
  public static function get_form_membership_category_ids() {
    return Settings_Fields::get_form_membership_category_ids();
  }

  /** @see Settings_Fields::wicket_mship_membership_merge_key() */
  public static function wicket_mship_membership_merge_key() {
    Settings_Fields::wicket_mship_membership_merge_key();
  }

  /** @see Settings_Fields::wicket_mship_multi_tier_renewal() */
  public static function wicket_mship_multi_tier_renewal() {
    Settings_Fields::wicket_mship_multi_tier_renewal();
  }

  /** @see Settings_Fields::wicket_mship_disable_renewal() */
  public static function wicket_mship_disable_renewal() {
    Settings_Fields::wicket_mship_disable_renewal();
  }

  /** @see Settings_Fields::wicket_mship_hide_subscription_cancel() */
  public static function wicket_mship_hide_subscription_cancel() {
    Settings_Fields::wicket_mship_hide_subscription_cancel();
  }

  /** @see Settings_Fields::wicket_mship_hide_subscription_reactivate() */
  public static function wicket_mship_hide_subscription_reactivate() {
    Settings_Fields::wicket_mship_hide_subscription_reactivate();
  }

  /** @see Settings_Fields::wicket_mship_hide_subscription_resubscribe() */
  public static function wicket_mship_hide_subscription_resubscribe() {
    Settings_Fields::wicket_mship_hide_subscription_resubscribe();
  }

  /** @see Settings_Fields::wicket_mship_assign_subscription() */
  public static function wicket_mship_assign_subscription() {
    Settings_Fields::wicket_mship_assign_subscription();
  }

  /** @see Settings_Fields::wicket_mship_membership_categories() */
  public static function wicket_mship_membership_categories() {
    Settings_Fields::wicket_mship_membership_categories();
  }

  /** @see Settings_Fields::bypass_wicket() */
  public static function bypass_wicket() {
    Settings_Fields::bypass_wicket();
  }

  /** @see Settings_Fields::wicket_mship_enable_bundles() */
  public static function wicket_mship_enable_bundles() {
    Settings_Fields::wicket_mship_enable_bundles();
  }

  /** @see Settings_Fields::wicket_mship_bundles_manage_page_id() */
  public static function wicket_mship_bundles_manage_page_id() {
    Settings_Fields::wicket_mship_bundles_manage_page_id();
  }

  /** @see Settings_Fields::wicket_mship_consolidate_renewal_line_items() */
  public static function wicket_mship_consolidate_renewal_line_items() {
    Settings_Fields::wicket_mship_consolidate_renewal_line_items();
  }

  /**
   * Whether bundle renewal orders combine identical per-member charge lines. Defaults to on.
   */
  public static function is_renewal_line_item_consolidation_enabled(): bool {
    $options = get_option( 'wicket_membership_plugin_options' );

    if ( ! is_array( $options ) || ! array_key_exists( 'wicket_mship_consolidate_renewal_line_items', $options ) ) {
      return true;
    }

    return ! empty( $options['wicket_mship_consolidate_renewal_line_items'] );
  }

  /** @see Settings_Page::section_membership_bundles_text() */
  public static function wicket_plugin_section_membership_bundles_text() {
    Settings_Page::section_membership_bundles_text();
  }

  /** @see Settings_Fields::wicket_mship_autorenew_toggle() */
  public static function wicket_mship_autorenew_toggle() {
    Settings_Fields::wicket_mship_autorenew_toggle();
  }

  /** @see Settings_Fields::wicket_mship_autorenew_override() */
  public static function wicket_mship_autorenew_override() {
    Settings_Fields::wicket_mship_autorenew_override();
  }

  /** @see Settings_Fields::wicket_mship_autorenew_audit() */
  public static function wicket_mship_autorenew_audit() {
    Settings_Fields::wicket_mship_autorenew_audit();
  }

  /** @see Settings_Fields::wicket_mship_mdp_timezone() */
  public static function wicket_mship_mdp_timezone() {
    Settings_Fields::wicket_mship_mdp_timezone();
  }

  /** @see Settings_Fields::wicket_mship_import_create_subscriptions_tier_only() */
  public static function wicket_mship_import_create_subscriptions_tier_only() {
    Settings_Fields::wicket_mship_import_create_subscriptions_tier_only();
  }

  /** @see Settings_Fields::wicket_mship_import_create_subscriptions() */
  public static function wicket_mship_import_create_subscriptions() {
    Settings_Fields::wicket_mship_import_create_subscriptions();
  }

  /** @see Settings_Fields::wicket_mship_subscription_renew() */
  public static function wicket_mship_subscription_renew() {
    Settings_Fields::wicket_mship_subscription_renew();
  }

  /** @see Settings_Fields::wicket_memberships_debug_acc() */
  public static function wicket_memberships_debug_acc() {
    Settings_Fields::wicket_memberships_debug_acc();
  }

  /** @see Settings_Fields::wicket_memberships_debug_renew() */
  public static function wicket_memberships_debug_renew() {
    Settings_Fields::wicket_memberships_debug_renew();
  }

  /** @see Settings_Fields::wicket_memberships_debug_cart_ids() */
  public static function wicket_memberships_debug_cart_ids() {
    Settings_Fields::wicket_memberships_debug_cart_ids();
  }

  /** @see Settings_Fields::allow_local_imports() */
  public static function allow_local_imports() {
    Settings_Fields::allow_local_imports();
  }

  /** @see Settings_Fields::wicket_show_order_debug_data() */
  public static function wicket_show_order_debug_data() {
    Settings_Fields::wicket_show_order_debug_data();
  }

  /** @see Settings_Fields::bypass_status_change_lockout() */
  public static function bypass_status_change_lockout() {
    Settings_Fields::bypass_status_change_lockout();
  }

  /** @see Settings_Fields::wicket_membership_debug_mode() */
  public static function wicket_membership_debug_mode() {
    Settings_Fields::wicket_membership_debug_mode();
  }

  /** @see Settings_Jobs::get_next_run() */
  public function get_next_scheduled_membership_activation() {
    return Settings_Jobs::get_next_run( 'schedule_daily_membership_activation_hook' );
  }

  /** @see Settings_Jobs::get_next_run() */
  public function get_next_scheduled_membership_grace_period() {
    return Settings_Jobs::get_next_run( 'schedule_daily_membership_grace_period_hook' );
  }

  /** @see Settings_Jobs::get_next_run() */
  public function get_next_scheduled_membership_expiry() {
    return Settings_Jobs::get_next_run( 'schedule_daily_membership_expiry_hook' );
  }

  /** @see Settings_Jobs::get_next_run() */
  public function get_next_scheduled_membership_autorenew_audit() {
    return Settings_Jobs::get_next_run( 'wicket_mship_autorenew_audit_hook', 'wicket-memberships' );
  }

  /** @see Settings_Jobs::render() */
  public static function wicket_plugin_status_change_reporting() {
    Settings_Jobs::render();
  }

  /** @see Settings_Jobs::handle_manual_action_links() */
  public static function handle_manual_action_links() {
    Settings_Jobs::handle_manual_action_links();
  }

  /** @see Settings_Jobs::run_triggered_actions() */
  public static function run_triggered_actions() {
    return Settings_Jobs::run_triggered_actions();
  }

  /**
   * Render the legacy org-search category field.
   *
   * @deprecated 1.0.123 Use Settings::wicket_mship_membership_categories().
   *
   * @return void
   */
  public static function wicket_show_mship_order_org_search() {
    self::wicket_mship_membership_categories();
  }

  /**
   * Sanitize the plugin options array on save.
   *
   * Rebuilds the stored array from the submitted fields only. Membership Categories are
   * written to the new key and then mirrored to the legacy org-search key for older readers.
   *
   * @see Helper::get_membership_category_ids()
   *
   * @param  array  $input  Raw `wicket_membership_plugin_options` values from the settings form.
   *
   * @return array  Sanitized options to store.
   */
  public static function wicket_membership_plugin_options_validate( $input ) {
    $newinput['wicket_mship_membership_merge_key'] = trim( $input['wicket_mship_membership_merge_key'] ?? '' );
    $newinput['wicket_mship_multi_tier_renewal'] = trim( $input['wicket_mship_multi_tier_renewal'] ?? '' );
    $newinput['wicket_mship_assign_subscription'] = trim( $input['wicket_mship_assign_subscription'] ?? '' );
    $newinput['wicket_mship_autorenew_toggle'] = trim( $input['wicket_mship_autorenew_toggle'] ?? '' );
    $newinput['wicket_mship_autorenew_override'] = trim( $input['wicket_mship_autorenew_override'] ?? '' );
    $newinput['wicket_mship_autorenew_audit'] = trim( $input['wicket_mship_autorenew_audit'] ?? '' );
    $newinput['wicket_mship_disable_renewal'] = trim( $input['wicket_mship_disable_renewal'] ?? '' );
    $newinput['wicket_membership_debug_mode'] = trim( $input['wicket_membership_debug_mode'] ?? '' );
    $newinput['wicket_memberships_debug_acc'] = trim( $input['wicket_memberships_debug_acc'] ?? '' );
    $newinput['bypass_status_change_lockout'] = trim( $input['bypass_status_change_lockout'] ?? '' );
    $newinput['wicket_show_order_debug_data'] = trim( $input['wicket_show_order_debug_data'] ?? '' );
    $newinput['allow_local_imports'] = trim( $input['allow_local_imports'] ?? '' );
    $newinput['wicket_memberships_debug_cart_ids'] = trim( $input['wicket_memberships_debug_cart_ids'] ?? '' );
    $newinput['wicket_memberships_debug_renew'] = trim( $input['wicket_memberships_debug_renew'] ?? '' );
    $newinput['bypass_wicket'] = trim( $input['bypass_wicket'] ?? '' );
    $newinput['wicket_mship_subscription_renew'] = trim( $input['wicket_mship_subscription_renew'] ?? '' );
    $newinput['wicket_mship_mdp_timezone'] = trim( $input['wicket_mship_mdp_timezone'] ?? '' );
    $newinput['wicket_mship_import_create_subscriptions_tier_only'] = trim( $input['wicket_mship_import_create_subscriptions_tier_only'] ?? '' );
    $newinput['wicket_mship_import_create_subscriptions'] = trim( $input['wicket_mship_import_create_subscriptions'] ?? '' );
    $newinput['wicket_mship_hide_subscription_cancel'] = trim( $input['wicket_mship_hide_subscription_cancel'] ?? '' );
    $newinput['wicket_mship_hide_subscription_reactivate'] = trim( $input['wicket_mship_hide_subscription_reactivate'] ?? '' );
    $newinput['wicket_mship_hide_subscription_resubscribe'] = trim( $input['wicket_mship_hide_subscription_resubscribe'] ?? '' );
    $newinput['wicket_mship_enable_bundles'] = trim( $input['wicket_mship_enable_bundles'] ?? '' );
    $newinput['wicket_mship_bundles_manage_page_id'] = isset( $input['wicket_mship_bundles_manage_page_id'] ) ? (int) $input['wicket_mship_bundles_manage_page_id'] : 0;
    // Always written ('' when unticked) so an explicit "off" is distinguishable from never saved.
    $newinput['wicket_mship_consolidate_renewal_line_items'] = ! empty( $input['wicket_mship_consolidate_renewal_line_items'] ) ? '1' : '';

    // Programmatic writes (Portus import, backup restore) also run through this callback and may
    // only carry the legacy key; fall back to it so those categories are not wiped. The new key wins if both exist.
    if ( is_array( $input ) && array_key_exists( 'wicket_mship_membership_categories', $input ) ) {
      $raw_categories = $input['wicket_mship_membership_categories'];
    } else {
      $raw_categories = $input['wicket_show_mship_order_org_search']['categorychoice'] ?? [];
    }
    $membership_categories = array_values( array_filter( array_map( 'absint', (array) $raw_categories ) ) );
    $newinput['wicket_mship_membership_categories'] = $membership_categories;

    // Backwards compatibility: keep the legacy key fully populated for readers outside this
    // plugin (e.g. wicket-wp-financial-fields). An empty selection stays [] because a non-empty
    // legacy value also switches on the org-search metabox.
    $newinput['wicket_show_mship_order_org_search'] = empty( $membership_categories ) ? [] : [ 'categorychoice' => $membership_categories ];

    return $newinput;
  }

  /**
   * Intro text for the former single "Settings" section. No longer registered; kept for callers.
   *
   * @return void
   */
  public static function wicket_plugin_section_functional_text() {
    echo '<p>Control certain functionality available within the membership plugin.</p>';
  }

  public static function check_migrate_tier_slugs() {
    $args = array(
      'post_type' => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
    );
    $tiers = get_posts( $args );
    foreach($tiers as $tier) {
      $tier_slug = get_post_meta( $tier->ID, 'membership_tier_slug', true);
      if(empty($tier_slug)) {
        (new Helper)->add_slug_on_mship_tier_create( $tier->ID, $tier, true);
      }
    }
  }

  /**
   * Initialize default timezone if not set
   * Called during init hook to ensure all helper functions are available
   * 
   * @param bool $force_api_call Force an API call even if timezone is already set
   */
  public static function ensure_timezone_default($force_api_call = false)
  {
    $options = get_option('wicket_membership_plugin_options');

    // Only proceed if timezone is empty or force is requested
    if (empty($options['wicket_mship_mdp_timezone']) || $force_api_call) {
      
      if (!is_array($options)) {
        $options = [];
      }

      // Try to fetch from API first
      $api_timezone = self::fetch_mdp_timezone_from_api();

      // Use API timezone if available, otherwise default to UTC
      $options['wicket_mship_mdp_timezone'] = !empty($api_timezone) ? $api_timezone : 'UTC';
      update_option('wicket_membership_plugin_options', $options);

      // Log the timezone setting
      if (!empty($api_timezone)) {
        Utilities::wc_log_mship_error(['MDP Timezone set from API', 'Timezone: ' . $api_timezone]);
      }
    }
  }

  /**
   * Fetch timezone from MDP API
   * Override this method or use a filter to customize the API call
   * 
   * @return string|null Timezone identifier or null if API call fails
   */
  public static function fetch_mdp_timezone_from_api()
  {
    // Check if the wicket_api_client helper function exists
    if (!function_exists('wicket_api_client')) {
      return apply_filters('wicket_mship_mdp_timezone_from_api', null);
    }

    try {
      // Initialize the Wicket API client using helper function
      $client = wicket_api_client();
      
      if (!$client) {
        return apply_filters('wicket_mship_mdp_timezone_from_api', null);
      }

      // Fetch the app_config from MDP
      $response = $client->get('app_config');

      // Extract timezone from the response
      if (!empty($response['data']['attributes']['time_zone'])) {
        $timezone = $response['data']['attributes']['time_zone'];
        
        // Validate the timezone is valid
        if (in_array($timezone, timezone_identifiers_list())) {
          return apply_filters('wicket_mship_mdp_timezone_from_api', $timezone);
        }
      }
    } catch (\Exception $e) {
      // Log the error but don't fail - fallback to UTC
      Utilities::wc_log_mship_error(['MDP Timezone API Error', 'Error: ' . $e->getMessage()]);
    }

    // Allow developers to filter/override the timezone
    return apply_filters('wicket_mship_mdp_timezone_from_api', null);
  }
}

