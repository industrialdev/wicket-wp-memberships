<?php

namespace Wicket_Memberships;

/**
 * Class Settings
 * @package Wicket_Memberships
 */
class Settings {


  /**
   * Settings Page
   */
  public static function wicket_membership_add_settings_page() {
    add_options_page( 'Wicket Memberships', 'Wicket Memberships', 'manage_options', 'wicket-membership-settings', [__NAMESPACE__.'\\Settings', 'wicket_membership_render_plugin_settings_page'] );
  }

  public static function wicket_membership_render_plugin_settings_page() {
    ?>
    <h2>Wicket Membership Settings</h2>
    <form action="options.php" method="post">
        <?php 
        settings_fields( 'wicket_membership_plugin_options' );
        do_settings_sections( 'wicket_membership_plugin' ); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
    </form>
    <?php
  }

  public static function wicket_membership_register_settings() {
    register_setting( 'wicket_membership_plugin_options', 'wicket_membership_plugin_options', [__NAMESPACE__.'\\Settings', 'wicket_membership_plugin_options_validate'] );
    //add_settings_section( 'functional_settings', 'Settings', [__NAMESPACE__.'\\Settings', 'wicket_plugin_section_functional_text'], 'wicket_membership_plugin' );
    add_settings_section( 'debug_settings', 'Debug Settings', [__NAMESPACE__.'\\Settings', 'wicket_plugin_section_debug_text'], 'wicket_membership_plugin' );
    add_settings_field( 'wicket_membership_debug_mode', '<p>WICKET_MEMBERSHIPS_DEBUG_MODE</p>'.'<p style="font-weight:400">Show debug info throughout the plugin admin only, including membership posts menu item, and tier/config data on archive pages.</p>', [__NAMESPACE__.'\\Settings', 'wicket_membership_debug_mode'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'bypass_status_change_lockout', '<p>BYPASS_STATUS_CHANGE_LOCKOUT</p>'.'<p style="font-weight:400">Disable status change sequence rules. Change to any status from any status.</p>', [__NAMESPACE__.'\\Settings', 'bypass_status_change_lockout'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'wicket_show_order_debug_data', '<p>WICKET_SHOW_ORDER_DEBUG_DATA</p>'.'<p style="font-weight:400">Show membership json attached as meta on order and subscription pages.</p>', [__NAMESPACE__.'\\Settings', 'wicket_show_order_debug_data'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'allow_local_imports', '<p>ALLOW_LOCAL_IMPORTS</p>'.'<p style="font-weight:400">Enable MDP exports to be <a target="_blank" href="'.plugins_url('wicket-wp-memberships').'/csv_post.php">imported here</a>.</p>', [__NAMESPACE__.'\\Settings', 'allow_local_imports'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'wicket_memberships_debug_renew', '<p>WICKET_MEMBERSHIPS_DEBUG_RENEW</p>'.'<p style="font-weight:400">Allow renewals outside of membership\'s renewal period.</p>', [__NAMESPACE__.'\\Settings', 'wicket_memberships_debug_renew'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'wicket_memberships_debug_cart_ids', '<p>WICKET_MEMBERSHIPS_DEBUG_CART_IDS</p>'.'<p style="font-weight:400">Show product meta set on cart/checkout pages.</p>'.'<p style="color:red;font-style:italic">WARNING: Displays debug data publicly.</p>', [__NAMESPACE__.'\\Settings', 'wicket_memberships_debug_cart_ids'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'bypass_wicket', '<p>BYPASS_WICKET</p>'.'<p style="font-weight:400">Do not create memberships in wicket.</p>', [__NAMESPACE__.'\\Settings', 'bypass_wicket'], 'wicket_membership_plugin', 'debug_settings' );
  }

  public static function bypass_wicket() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[bypass_wicket]' type='checkbox' value='1' ".checked(1, esc_attr( $options['bypass_wicket']), false). " />";
  }

  public static function wicket_memberships_debug_renew() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_memberships_debug_renew]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_memberships_debug_renew']), false). " />";
  }

  public static function wicket_memberships_debug_cart_ids() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_memberships_debug_cart_ids]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_memberships_debug_cart_ids']), false). " />";
  }

  public static function allow_local_imports() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[allow_local_imports]' type='checkbox' value='1' ".checked(1, esc_attr( $options['allow_local_imports']), false). " />";
  }

  public static function wicket_show_order_debug_data() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_show_order_debug_data]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_show_order_debug_data']), false). " />";
  }

  public static function bypass_status_change_lockout() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[bypass_status_change_lockout]' type='checkbox' value='1' ".checked(1, esc_attr( $options['bypass_status_change_lockout']), false). " />";
  }

  public static function wicket_membership_debug_mode() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_membership_debug_mode]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_membership_debug_mode']), false). " />";
  }

  public static function wicket_membership_plugin_options_validate( $input ) {
    $newinput['wicket_membership_debug_mode'] = trim($input['wicket_membership_debug_mode']);
    $newinput['bypass_status_change_lockout'] = trim($input['bypass_status_change_lockout']);
    $newinput['wicket_show_order_debug_data'] = trim($input['wicket_show_order_debug_data']);
    $newinput['allow_local_imports'] = trim($input['allow_local_imports']);
    $newinput['wicket_memberships_debug_cart_ids'] = trim($input['wicket_memberships_debug_cart_ids']);
    $newinput['wicket_memberships_debug_renew'] = trim($input['wicket_memberships_debug_renew']);
    $newinput['bypass_wicket'] = trim($input['bypass_wicket']);
    return $newinput;
  }

  public static function wicket_plugin_section_functional_text() {
    echo '<p>Control certain functionality available within the membership plugin.</p>';
  }

  public static function wicket_plugin_section_debug_text() {
    echo '<p>These DEBUG values expose functionality and data that can help verify proper operation of different aspects of the plugin.</p><p>These settings should remain disabled in production unless actively debugging an issue.</p><p style="font-style:italic">NOTE: Some settings will expose raw data publicly in the cart and checkout, as well as in the membership admin interface.</p>';
  }
}

