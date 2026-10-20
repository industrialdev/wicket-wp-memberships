<?php

namespace Wicket_Memberships;

/**
 * Renders the Settings > Wicket Memberships page: shell, tabs, notices, assets and section intros.
 *
 * @see Settings  Public entry points that delegate here.
 * @package Wicket_Memberships
 */
class Settings_Page {

  /**
   * Settings page tabs, keyed by the `tab` query arg.
   *
   * Each tab maps to its own Settings API page id (see settings_page_id()).
   *
   * @var array<string, string>
   */
  const SETTINGS_TABS = [
    'general'   => 'General',
    'renewals'  => 'Renewals',
    'import'    => 'Import',
    'developer' => 'Developer',
  ];

  /**
   * Register the Settings > Wicket Memberships page.
   *
   * @return void
   */
  public static function add_page() {
    add_options_page( 'Wicket Memberships', 'Wicket Memberships', 'manage_options', 'wicket-membership-settings', [ __CLASS__, 'render' ] );
  }

  /**
   * Settings API page id for one tab's sections.
   *
   * @param  string  $tab  A SETTINGS_TABS key.
   *
   * @return string  Page id passed to add_settings_section() / do_settings_sections().
   */
  public static function settings_page_id( $tab ) {
    return 'wicket_membership_plugin_' . $tab;
  }

  /**
   * The tab to show, from the `tab` query arg; falls back to the first tab.
   *
   * @return string  A SETTINGS_TABS key.
   */
  private static function get_active_tab() {
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

    return array_key_exists( $tab, self::SETTINGS_TABS ) ? $tab : array_key_first( self::SETTINGS_TABS );
  }

  /**
   * Render the settings page.
   *
   * All tabs share one form and every panel is rendered; inactive panels are only `hidden`.
   * The options validate callback rebuilds the whole option from the submitted fields, so
   * every field must be posted on each save or other tabs' values would be wiped.
   *
   * @see Settings::wicket_membership_plugin_options_validate()
   *
   * @return void
   */
  public static function render() {
    $active_tab = self::get_active_tab();
    $page_url   = admin_url( 'options-general.php?page=wicket-membership-settings' );
    ?>
    <div class="wrap">
      <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
      <?php
        // Surface active local-import / sync-tool state at the top of the page so admins
        // are not surprised that command-line import endpoints or the sync tool are live.
        self::render_environment_notices();
        self::render_category_switch_notice();
        Settings::check_migrate_tier_slugs();
      ?>
      <nav class="nav-tab-wrapper wp-clearfix">
        <?php foreach ( self::SETTINGS_TABS as $tab => $label ) : ?>
          <a href="<?php echo esc_url( add_query_arg( 'tab', $tab, $page_url ) ); ?>" class="nav-tab<?php echo $tab === $active_tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>
      <form action="options.php" method="post">
        <?php settings_fields( 'wicket_membership_plugin_options' ); ?>
        <?php foreach ( self::SETTINGS_TABS as $tab => $label ) : ?>
          <div id="wicket-mship-tab-<?php echo esc_attr( $tab ); ?>"<?php echo $tab === $active_tab ? '' : ' hidden'; ?>>
            <?php do_settings_sections( self::settings_page_id( $tab ) ); ?>
          </div>
        <?php endforeach; ?>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  /**
   * Load the base plugin's admin styles, select2 and admin script on this settings page.
   *
   * The base plugin is a hard dependency, so its assets are reused rather than duplicated.
   * Its wicket_admin.js applies select2 to `.wicket-membership-categories`.
   *
   * @param  string  $hook_suffix  Current admin page hook.
   *
   * @return void
   */
  public static function enqueue_assets( $hook_suffix ) {
    if ( 'settings_page_wicket-membership-settings' !== $hook_suffix ) {
      return;
    }

    // Core accordion behaviour for render_accordion(); its styles are already in wp-admin common.css.
    wp_enqueue_script( 'accordion' );

    if ( ! defined( 'WICKET_URL' ) ) {
      return;
    }

    wp_enqueue_style( 'wicket_admin_css', WICKET_URL . 'assets/css/wicket_admin.css', [], '1.0' );
    wp_enqueue_style( 'select2', WICKET_URL . 'assets/css/select2.css', [], '1.0' );
    wp_enqueue_script( 'select2', WICKET_URL . 'assets/js/select2.js', [ 'jquery' ], '1.0', false );
    wp_enqueue_script( 'wicket_admin_js', WICKET_URL . 'assets/js/wicket_admin.js', [ 'jquery', 'select2' ], '1.0', false );
    // Searchable select for long single-choice lists such as the MDP timezone.
    wp_add_inline_script( 'wicket_admin_js', "jQuery(function($){ $('.wicket-mship-select2').select2(); });" );
  }

  /**
   * Render a collapsed panel using core's admin accordion markup (as on Appearance > Menus).
   *
   * Each panel gets its own container because core's accordion closes open siblings.
   *
   * @since 1.0.123
   *
   * @see enqueue_assets()  Enqueues core's `accordion` script.
   *
   * @param  string    $id       Unique HTML id for the panel.
   * @param  string    $title    Plain-text panel heading.
   * @param  callable  $content  Echoes the panel body.
   *
   * @return void
   */
  public static function render_accordion( $id, $title, $content ) {
    ?>
    <div class="accordion-container" style="max-width:990px;margin-top:12px;">
      <div class="control-section accordion-section">
        <?php // Body-size heading so the panel reads as help text, not a section title. ?>
        <h3 class="accordion-section-title" style="font-size:inherit;font-weight:400;">
          <button type="button" class="accordion-trigger" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-content">
            <span class="accordion-title">
              <?php echo esc_html( $title ); ?>
              <span class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
            </span>
          </button>
        </h3>
        <div class="accordion-section-content" id="<?php echo esc_attr( $id ); ?>-content">
          <?php call_user_func( $content ); ?>
        </div>
      </div>
    </div>
    <?php
  }

  /**
   * Add the base plugin's settings body class so its scoped admin styles apply here.
   *
   * @param  string  $classes  Space-separated admin body classes.
   *
   * @return string  Body classes, with `wicket-admin-settings` on this page.
   */
  public static function body_class( $classes ) {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( $screen && 'settings_page_wicket-membership-settings' === $screen->id ) {
      $classes .= ' wicket-admin-settings ';
    }

    return $classes;
  }

  /**
   * Renders warning notices at the top of the settings page for active local-import state.
   *
   * Two independent conditions are surfaced because they have real security/operational
   * implications and are easy to leave enabled unintentionally:
   *  - ALLOW_LOCAL_IMPORTS is on, exposing the command-line CSV import tooling.
   *  - The memberships-sync.php tool is actively included (its function is defined),
   *    meaning the browser-triggered subscription sync endpoint is reachable.
   *
   * The sync tool is loaded from wicket.php whenever the `allow_local_imports` option key
   * exists, so detecting the function presence is the only reliable "is it live" signal.
   *
   * @see wicket_sync_subscriptions() Defined in custom/memberships-sync.php when included.
   *
   * @return void
   */
  public static function render_environment_notices() {
    $options = get_option( 'wicket_membership_plugin_options' );

    // Local imports flagged on — command-line CSV import endpoints are enabled.
    $local_imports_enabled = ! empty( $options['allow_local_imports'] );

    // The sync tool defines this function only when custom/memberships-sync.php is included.
    $sync_tool_active = function_exists( 'wicket_sync_subscriptions' );

    if ( ! $local_imports_enabled && ! $sync_tool_active ) {
      return;
    }

    echo '<div class="notice notice-warning inline">';
    echo '<p><strong>Local import or sync tools are active</strong></p>';

    if ( $local_imports_enabled ) {
      echo '<p><strong>Allow Local Imports is on.</strong> Command-line CSV imports are enabled. Keep this off in production unless an import is in progress.</p>';
    }

    if ( $sync_tool_active ) {
      echo '<p><strong>The Subscription Sync tool (<code>custom/memberships-sync.php</code>) is loaded.</strong> Its browser endpoint (<code>?mship_subscription_sync=1</code>) is reachable. See the Import tab before using it.</p>';
    }

    echo '</div>';
  }

  /**
   * Warn that the first save switches the base plugin's Hide redirects to this page's Membership Categories.
   *
   * Until Membership Categories is saved, the base plugin's "Hide membership categories" and
   * "Hide membership product single pages" redirects use the base plugin's deprecated list.
   * Shown only when one of those redirects is on and that list differs from what this page will save.
   *
   * @since 1.0.123
   *
   * @see Helper::has_saved_membership_categories()
   * @see Settings_Fields::get_form_membership_category_ids()
   *
   * @return void
   */
  public static function render_category_switch_notice() {
    // The redirects and their deprecated list live in the base plugin.
    if ( ! function_exists( 'wicket_get_option' ) || Helper::has_saved_membership_categories() ) {
      return;
    }

    $redirects_on = wicket_get_option( 'wicket_admin_settings_woo_remove_membership_categories' ) === '1'
      || wicket_get_option( 'wicket_admin_settings_woo_remove_membership_product_single' ) === '1';

    $base_ids = array_values( array_filter( array_map( 'absint', (array) wicket_get_option( 'wicket_admin_settings_membership_categories', [] ) ) ) );
    $form_ids = Settings_Fields::get_form_membership_category_ids();
    $start    = array_diff( $form_ids, $base_ids );
    $stop     = array_diff( $base_ids, $form_ids );

    if ( ! $redirects_on || ( empty( $start ) && empty( $stop ) ) ) {
      return;
    }

    $names = function ( $ids ) {
      $labels = [];
      foreach ( $ids as $id ) {
        $term     = get_term( $id, 'product_cat' );
        $labels[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : sprintf( '#%d', $id );
      }
      return $labels ? esc_html( implode( ', ', $labels ) ) : '<em>none</em>';
    };

    $base_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wicket-settings&tab=integrations' ) ) . '">Hide membership categories / product single pages</a>';

    echo '<div class="notice notice-warning inline"><p><strong>Saving this page changes which products are hidden.</strong> ';
    echo 'The ' . $base_link . ' redirects currently use the legacy Membership Categories in Wicket Settings. After you save here, they use the Membership Categories on the General tab.</p>';
    echo '<p>Will start redirecting: ' . $names( $start ) . '<br>Will stop redirecting: ' . $names( $stop ) . '</p></div>';
  }

  /**
   * Intro text for the Developer tab's Debug Settings section.
   *
   * @return void
   */
  public static function section_debug_text() {
    echo '<p>Keep these disabled in production unless you are debugging.</p>';
    echo '<p><a class="button button-secondary" href="' . esc_url( admin_url( 'edit.php?post_type=wicket_membership' ) ) . '" target="_blank">View Raw Membership Posts</a></p>';
  }

  /**
   * Intro text for the Membership Products section.
   *
   * @return void
   */
  public static function section_membership_products_text() {
    echo '<p>Selecting any category also enables organization search on subscription orders.</p>';
  }

  /**
   * Intro text for the Command-Line Import section.
   *
   * @return void
   */
  public static function section_import_text() {
    echo '<p>Imports run from the SSH command line. Keep this off unless an import is in progress.</p>';
  }

  /**
   * Intro text for the Import Subscription Options section.
   *
   * @return void
   */
  public static function section_import_subscriptions_text() {
    echo '<p>Choose whether imported memberships get a WooCommerce subscription. Leave both unchecked to create none.</p>';
  }

  /**
   * Intro text for the Developer tab's Admin debug section.
   *
   * @return void
   */
  public static function section_debug_admin_text() {
    echo '<p class="attention">These settings can display sensitive debug data on the back end of the website.</p>';
  }

  /**
   * Intro text for the Front-End Debug Output section.
   *
   * @return void
   */
  public static function section_debug_public_text() {
    echo '<p class="attention">These settings can publicly display sensitive debug data on the website.</p>';
  }
}
