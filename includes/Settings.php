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
    //options
    add_settings_field( 'wicket_show_mship_order_org_search', '<p>Set the Organization on Subscription Membership in Admin</p>', [__NAMESPACE__.'\\Settings', 'wicket_show_mship_order_org_search'], 'wicket_membership_plugin', 'debug_settings' );
    //debug
    add_settings_section( 'debug_settings', 'Debug Settings', [__NAMESPACE__.'\\Settings', 'wicket_plugin_section_debug_text'], 'wicket_membership_plugin' );
    add_settings_field( 'wicket_membership_debug_mode', '<p>WICKET_MEMBERSHIPS_DEBUG_MODE</p>', [__NAMESPACE__.'\\Settings', 'wicket_membership_debug_mode'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'wicket_memberships_debug_acc', '<p>WICKET_MEMBERSHIPS_DEBUG_ACC</p>', [__NAMESPACE__.'\\Settings', 'wicket_memberships_debug_acc'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'bypass_status_change_lockout', '<p>BYPASS_STATUS_CHANGE_LOCKOUT</p>', [__NAMESPACE__.'\\Settings', 'bypass_status_change_lockout'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'wicket_show_order_debug_data', '<p>WICKET_SHOW_ORDER_DEBUG_DATA</p>', [__NAMESPACE__.'\\Settings', 'wicket_show_order_debug_data'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'allow_local_imports', '<p>ALLOW_LOCAL_IMPORTS</p>', [__NAMESPACE__.'\\Settings', 'allow_local_imports'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'wicket_memberships_debug_renew', '<p>WICKET_MEMBERSHIPS_DEBUG_RENEW</p>', [__NAMESPACE__.'\\Settings', 'wicket_memberships_debug_renew'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'wicket_memberships_debug_cart_ids', '<p>WICKET_MEMBERSHIPS_DEBUG_CART_IDS</p>', [__NAMESPACE__.'\\Settings', 'wicket_memberships_debug_cart_ids'], 'wicket_membership_plugin', 'debug_settings' );
    add_settings_field( 'bypass_wicket', '<p>BYPASS_WICKET</p>', [__NAMESPACE__.'\\Settings', 'bypass_wicket'], 'wicket_membership_plugin', 'debug_settings' );
  }

  public static function wicket_show_mship_order_org_search() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo 'Option to [Search & Select Organization] for the membership on WC Subscription Order admin page for products in the selected categories. Useful for manually creating subscription memberships.';
    ?><br /><select class="" multiple="multiple" name="wicket_membership_plugin_options[wicket_show_mship_order_org_search][categorychoice][]"><?
    $option = $options['wicket_show_mship_order_org_search'];
    $categories_selected = $options['wicket_show_mship_order_org_search'];
          $product_categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
        ));        
          foreach ($product_categories as $category) {
            $selected = '';
            if(!empty($categories_selected['categorychoice']) && is_array($categories_selected['categorychoice'])) {
              $selected = in_array( $category->term_id, $categories_selected['categorychoice'] ) ? ' selected="selected" ' : ''; 
            }
            ?>
              <option value="<?php echo $category->term_id; ?>" <?php echo $selected; ?> >
                <?php echo $category->name; ?>
              </option>
          <?php } ?>
      </select><?php
      }

  public static function bypass_wicket() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[bypass_wicket]' type='checkbox' value='1' ".checked(1, esc_attr( $options['bypass_wicket']), false). " />"
      .'Do not create memberships in wicket MDP.';
  }
  
  public static function wicket_memberships_debug_acc() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_memberships_debug_acc]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_memberships_debug_acc']), false). " />"
      .'Show membership renewal callouts and debug info in account center.'.'<p style="color:red;font-style:italic">WARNING: Displays debug data publicly.</p>';
  }

  public static function wicket_memberships_debug_renew() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_memberships_debug_renew]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_memberships_debug_renew']), false). " />"
      .'Allow renewals outside of membership\'s renewal period and allow adjusting days in url (?wicket_wp_membership_debug_days=###) for displaying the renewal callout.'.'<p style="color:red;font-style:italic">WARNING: Displays debug data publicly.</p>';
  }

  public static function wicket_memberships_debug_cart_ids() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_memberships_debug_cart_ids]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_memberships_debug_cart_ids']), false). " />"
      .'Show product meta set on cart/checkout pages.'.'<p style="color:red;font-style:italic">WARNING: Displays debug data publicly.</p>';
  }

  public static function allow_local_imports() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[allow_local_imports]' type='checkbox' value='1' ".checked(1, esc_attr( $options['allow_local_imports']), false). " />"
      .'Enable MDP exports to be <a target="_blank" href="'.plugins_url('wicket-wp-memberships').'/csv_post.php">imported here</a>.';
  }

  public static function wicket_show_order_debug_data() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_show_order_debug_data]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_show_order_debug_data']), false). " />"
      .'Show membership json attached as meta on order and subscription pages.'.'<p style="color:red;font-style:italic">WARNING: Displays debug data publicly.</p>';
  }

  public static function bypass_status_change_lockout() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[bypass_status_change_lockout]' type='checkbox' value='1' ".checked(1, esc_attr( $options['bypass_status_change_lockout']), false). " />"
      .'Disable status change sequence rules. Change to any status from any status.';
  }

  public static function wicket_membership_debug_mode() {
    $options = get_option( 'wicket_membership_plugin_options' );
    echo "<input id='wicket_membership_plugin_debug' name='wicket_membership_plugin_options[wicket_membership_debug_mode]' type='checkbox' value='1' ".checked(1, esc_attr( $options['wicket_membership_debug_mode']), false). " />"
      .' Show debug info throughout the plugin admin only, including membership posts menu item, and tier/config data on archive pages.';
  }

  public static function wicket_membership_plugin_options_validate( $input ) {
    $newinput['wicket_membership_debug_mode'] = trim($input['wicket_membership_debug_mode']);
    $newinput['wicket_memberships_debug_acc'] = trim($input['wicket_memberships_debug_acc']);
    $newinput['bypass_status_change_lockout'] = trim($input['bypass_status_change_lockout']);
    $newinput['wicket_show_order_debug_data'] = trim($input['wicket_show_order_debug_data']);
    $newinput['allow_local_imports'] = trim($input['allow_local_imports']);
    $newinput['wicket_memberships_debug_cart_ids'] = trim($input['wicket_memberships_debug_cart_ids']);
    $newinput['wicket_memberships_debug_renew'] = trim($input['wicket_memberships_debug_renew']);
    $newinput['bypass_wicket'] = trim($input['bypass_wicket']);
    $newinput['wicket_show_mship_order_org_search'] = is_array($input['wicket_show_mship_order_org_search']) ? $input['wicket_show_mship_order_org_search'] : [];
    return $newinput;
  }

  public static function wicket_plugin_section_functional_text() {
    echo '<p>Control certain functionality available within the membership plugin.</p>';
  }

  public static function wicket_plugin_section_debug_text() {
    echo '<p>These DEBUG values expose functionality and data that can help verify proper operation of different aspects of the plugin.</p><p>These settings should remain disabled in production unless actively debugging an issue.</p><p style="font-style:italic">NOTE: Some settings will expose raw data publicly in the cart and checkout, as well as in the membership admin interface.</p>';
  }
}

