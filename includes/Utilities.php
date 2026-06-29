<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

defined( 'ABSPATH' ) || exit;

class Utilities {

  private $membership_cpt_slug = '';

  public function __construct() {
    if( $this->is_wicket_show_mship_order_org_search() ) {
      //org search metabox
      add_action( 'add_meta_boxes', [$this, 'wicket_sub_org_select_metabox'], 10, 2 );
      add_action('admin_enqueue_scripts', [$this, 'enqueue_suborg_scripts'] );
      add_action('wp_ajax_suborg_search', [$this, 'handle_suborg_search'] );
      add_action('woocommerce_process_shop_subscription_meta', [$this, 'set_subscription_postmeta_suborg_uuid']);
    }
    if(isset($_ENV['ALLOW_LOCAL_IMPORTS']) && $_ENV['ALLOW_LOCAL_IMPORTS']) {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_suborg_scripts'] );
      //post_row_action tier uuid update
      add_action('wp_ajax_wicket_tier_uuid_update', [$this, 'handle_wicket_tier_uuid_update'] );
    }
    add_action('delete_user', [$this, 'handle_wp_delete_user'], 10, 3);
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();

    add_filter( 'woocommerce_cart_item_quantity', [$this, 'disable_cart_item_quantity'], 10, 3);
    add_filter( 'woocommerce_cart_item_remove_link', [$this, 'hide_cart_item_remove_link'], 10, 3);
    add_action( 'wp_trash_post', [$this, 'delete_wicket_membership_in_mdp' ], 10, 2);
    // Use the pre_trash_post FILTER (not the wp_trash_post action) so we can
    // abort the trash before WordPress changes the post status, and redirect
    // cleanly rather than calling wp_die().
    add_filter( 'pre_trash_post', [$this, 'prevent_trash_linked_product'], 10, 3 );
    // Intercept the WooCommerce variation-removal AJAX at priority 1, before WC's
    // own handler registers at default priority 10.
    add_action( 'wp_ajax_woocommerce_remove_variations', [$this, 'intercept_variation_removal'], 1 );
    // Render a notice on the product edit page when a variation removal is blocked.
    add_action( 'admin_enqueue_scripts', [$this, 'enqueue_variation_protection_js'] );
    add_action('admin_notices', [$this, 'show_membership_delete_error'], 1);
    add_action('template_redirect', [$this, 'wicket_membership_clear_the_cart'], 10);
    // Register and handle custom bulk actions for product trash protection
    add_filter('bulk_actions-edit-product', [$this, 'register_bulk_trash_protection'], 20, 1);
    add_filter('handle_bulk_actions-edit-product', [$this, 'handle_bulk_trash_protection'], 20, 3);
    // Unified error notice for membership product delete protection
    add_action('admin_notices', [$this, 'show_combined_membership_product_delete_error'], 1);
    // Enqueue JS for admin error notice on AJAX trash block
    add_action('admin_enqueue_scripts', [$this, 'enqueue_trash_block_notice_js']);

    //allows connect a subscription to a membership on subscription edit page
    if(isset($_ENV['WICKET_MSHIP_ASSIGN_SUBSCRIPTION']) && $_ENV['WICKET_MSHIP_ASSIGN_SUBSCRIPTION']) {
      add_action('woocommerce_admin_order_data_after_order_details', [$this, 'wicket_display_membership_id_input_on_order'], 10, 1);
      add_action('woocommerce_process_shop_subscription_meta', [$this, 'wicket_assign_subscription_to_membership'], 10, 1);
    }
    add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_autopay_status_row_admin'], 15, 1);

    //renewal coupon issues identified on GF form redirects in Onboarding and Renewal
    add_action( 'woocommerce_add_to_cart', [$this, 'apply_renewal_coupon_on_add'], 10, 2 );
    add_filter( 'woocommerce_add_to_cart_redirect', [$this, 'prevent_renewal_checkout_redirect'], 11 );
  }

   /**
   * Enqueue JS to display admin notice if AJAX trash is blocked.
   */
  public function enqueue_trash_block_notice_js() {
    $screen = get_current_screen();
    if ($screen && $screen->base === 'post' && $screen->post_type === 'product') {
      ?>
      <script>
      jQuery(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        if (settings && settings.data && settings.data.indexOf('action=trash-post') !== -1) {
          let response = {};
          try { response = JSON.parse(jqxhr.responseText); } catch (e) {}
          if (response && response.data && response.data.wicket_mship_block_message) {
            // Remove any existing notices
            jQuery('.wicket-mship-trash-block-notice').remove();
            // Add new notice
            jQuery('<div class="notice notice-error wicket-mship-trash-block-notice is-dismissible" style="border-color: red; background-color: #ff000024;"><p><strong>WICKET MEMBERSHIP ERROR:</strong> ' + response.data.wicket_mship_block_message + '</p></div>').prependTo('#wpbody-content');
          }
        }
      });
      </script>
      <?php
    }
  }


  /**
   * Show a single error notice for membership product delete protection (bulk, quick, or single).
   * Prefers the most specific message available.
   */
  public function show_combined_membership_product_delete_error() {
    $transient_key = '_wicket_product_delete_error_' . get_current_user_id();
    $message = get_transient($transient_key);
    // If this is an AJAX trash request, return JSON error and block reload
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'trash-post') {
      if ($message) {
        wp_send_json_error([
          'wicket_mship_block_message' => $message
        ], 403);
      } else {
        wp_send_json_error([
          'wicket_mship_block_message' => __('This product cannot be trashed or deleted because it is assigned to a membership. Remove the assignment first.', 'wicket-memberships')
        ], 403);
      }
      exit;
    }
    if ($message) {
      echo '<div class="notice notice-error error is-dismissible" style="border-color: red; background-color: #ff000024;"><p><strong>WICKET MEMBERSHIP ERROR:</strong> ' . esc_html($message) . '</p></div>';
      delete_transient($transient_key);
      if (isset($_GET['show_membership_product_delete_error'])) {
        unset($_GET['show_membership_product_delete_error']);
      }
      return;
    }
    if (isset($_GET['show_membership_product_delete_error'])) {
      echo '<div class="notice notice-error error is-dismissible" style="border-color: red; background-color: #ff000024;"><p><strong>WICKET MEMBERSHIP ERROR:</strong> THE PRODUCT YOU ATTEMPTED TO DELETE IS ASSIGNED TO A MEMBERSHIP. It must first be removed from the Membership Tier or Config. You cannot trash or delete a product before removing your membership plugin assignment(s).</p></div>';
      return;
    }
  }

  /**
   * Register custom bulk actions for product trash protection (no-op, but required for handle_bulk_actions).
   *
   * @param array $bulk_actions
   * @return array
   */
  public function register_bulk_trash_protection($bulk_actions) {
    // No changes needed, just ensure our handler runs for the default 'trash' action
    return $bulk_actions;
  }

  /**
   * Handle bulk trash actions for products to enforce membership protection.
   *
   * @param string $redirect_to
   * @param string $doaction
   * @param array $post_ids
   * @return string
   */
  public function handle_bulk_trash_protection($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'trash') {
      return $redirect_to;
    }
    $blocked = [];
    foreach ($post_ids as $post_id) {
      $post = get_post($post_id);
      $message = $this->get_membership_product_block_message($post_id);
      if ($post && $post->post_type === 'product' && $message !== null) {
        $blocked[] = $post_id;
        set_transient('_wicket_product_delete_error_' . get_current_user_id(), $message, 120);
      }
    }
    if (!empty($blocked)) {
      $redirect_to = add_query_arg('show_membership_product_delete_error', '1', $redirect_to);
    }
    return $redirect_to;
  }

  /**
   * Show error notice for bulk product trash protection on the product list page.
   */

  public function display_autopay_status_row_admin($order) {
    if (!is_object($order) || !method_exists($order, 'get_type')) {
      return;
    }
    if ($order->get_type() !== 'shop_subscription') {
      return;
    }
    if (!method_exists($order, 'get_requires_manual_renewal')) {
      return;
    }
    $is_manual = $order->get_requires_manual_renewal();
    $autopay_status = $is_manual ? __('Off', 'wicket-memberships') : __('On', 'wicket-memberships');
    echo '<div class="order_data_column">
      <h4>' . esc_html__('Autopay Enabled', 'wicket-memberships') . '</h4>
      <p><strong>' . esc_html($autopay_status) . '</strong></p>
    </div>';
  }

  function wicket_membership_clear_the_cart() {
    if (is_cart() && isset($_GET['empty-cart']) && $_GET['empty-cart'] === 'true') {
        WC()->cart->empty_cart();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
  }

  public static function wicket_logger( $message, $data = [], $format = 'json', $logFile = "mship_error.log"){
    if('development' == wp_get_environment_type()) {
      $date = new \DateTime();
      $date = $date->format("Y-m-d H:i:s") . ' ';
      if(!is_array($data)) {
        $data = [$data];
      }
      $formatted_data = $format == 'json' ? json_encode($data) : print_r($data, true);
      $message = $date.'MSHIP: '.$message.' : '.$formatted_data;
      $message .= PHP_EOL;
      $path = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR.'/../../' : getcwd();
      file_put_contents($path.'/'.$logFile, $message, FILE_APPEND);
    }
  }

 public static function wc_log_mship_error( $data, $level = 'error' ) {
    if(is_array( $data )) {
      $data = wc_print_r( $data, true );
    }
    if ( class_exists( '\Wicket' ) ) {
      \Wicket()->log($level, $data, ['source' => 'wicket-membership-plugin']);
    } else if (class_exists('WC_Logger')) {
      $logger = new \WC_Logger();
      if(is_array( $data )) {
        $data = wc_print_r( $data, true );
      }
      $logger->log($level, $data, ['source' => 'wicket-membership-plugin']);
    }
  }

   /**
   * Delete all memberships for a person_uuid from the MDP
   *
   * @param string $person_uuid
   * @return array|null
   */
  public static function delete_all_person_memberships_from_mdp( $person_uuid ) {
    if ( empty( $person_uuid ) || ! function_exists( 'wicket_get_person_memberships' ) || ! function_exists( 'wicket_delete_person_membership' ) ) {
      return 'failed';
    }
    $memberships =  wicket_get_person_memberships( $person_uuid );

    foreach($memberships['data'] as $membership) {
      $membership_wicket_uuid = $membership['id'];
      if($membership['type'] == 'person_memberships') {
        $response_api = wicket_delete_person_membership( $membership_wicket_uuid );
      } elseif($membership['type'] == 'organization_memberships') {
        $response_api = wicket_delete_organization_membership( $membership_wicket_uuid );
      }
      if(is_wp_error( $response_api )) {
        $response[$membership_wicket_uuid] = $response_api->get_error_message( 'wicket_api_error' );
      } else {
        $response[$membership_wicket_uuid] = 'deleted from mdp';
      }
    }
    return $response;
  }

  function delete_wicket_membership_in_mdp( $post_id ) {
    if(function_exists('wicket_delete_person_membership')) {
      $membership_wicket_uuid = get_post_meta( $post_id, 'membership_wicket_uuid', true);
      $membership_type = get_post_meta( $post_id, 'membership_type', true);
      if(!empty($membership_type) && !empty($membership_wicket_uuid)) {
        if($membership_type == 'individual') {
          $response = wicket_delete_person_membership( $membership_wicket_uuid );
        } else {
          $response = wicket_delete_organization_membership( $membership_wicket_uuid );
        }
        if( empty($response) || is_wp_error( $response ) ) {
          wp_redirect(get_admin_url() . "edit.php?post_type=wicket_membership&all_posts=1&show_membership_delete_error=1");
        }
      }
    }

    if ( get_post_type( $post_id ) === Helper::get_membership_bundle_cpt_slug() ) {
      $bundle = new Membership_Bundle( $post_id );
      $bundle->sync_mdp_delete();
    }
  }

  function show_membership_delete_error() {
    if (isset($_GET['show_membership_delete_error'])) {
        echo '<div class="notice notice-error error is-dismissible" style="border-color: red; background-color: #ff000024;"><p><strong>WICKET MEMBERSHIP ERROR:</strong> THE MEMBERSHIP YOU ATTEMPTED TO DELETE WAS NOT SUCCESSFULLY REMOVED IN WICKET MDP OR MAY NOT EXIST. </p></div>';
    }
  }


  /**
   * Returns the block-reason message if a product is currently assigned to a
   * Membership Tier or used as a late-fee product in a Membership Config.
   * Returns null when the product is not protected and deletion may proceed.
   *
   * This is a private helper called by both prevent_trash_linked_product() and
   * intercept_variation_removal() so the business logic lives in one place.
   *
   * @param  int         $product_id  WooCommerce product post ID (parent, not variation).
   * @return string|null  Human-readable reason string, or null if unprotected.
   */
  private function get_membership_product_block_message( int $product_id ): ?string {
    $membership_categories = wicket_get_option( 'wicket_admin_settings_membership_categories' );

    if ( ! has_term( $membership_categories, 'product_cat', $product_id ) ) {
      return null;
    }

    $is_late_fee_product = false;
    $membership_tier     = Membership_Tier::get_tier_by_product_id( $product_id );

    if ( empty( $membership_tier ) ) {
      $config_posts = get_posts( [
        'post_type'   => Helper::get_membership_config_cpt_slug(),
        'numberposts' => -1,
      ] );
      foreach ( $config_posts as $config ) {
        if ( ( new Membership_Config( $config->ID ) )->get_late_fee_window_product_id() == $product_id ) {
          $is_late_fee_product = true;
          break;
        }
      }
    }

    if ( ! $is_late_fee_product && empty( $membership_tier ) ) {
      // In the membership category but not actually assigned anywhere — allow deletion.
      return null;
    }

    // get_the_title() returns HTML-encoded entities (e.g. &#8211; for –).
    // Decode to plain text first; esc_html() below will then re-encode only
    // actual HTML-special chars, so the message is clean in both JSON and HTML contexts.
    $product_title = html_entity_decode( get_the_title( $product_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $assignment    = ! empty( $membership_tier )
      ? 'Membership Tier'
      : 'Membership Config (late fee product)';

    return sprintf(
      /* translators: 1: product title  2: assignment type (Membership Tier / Membership Config) */
      __( '"%s" cannot be trashed or deleted — it is currently assigned to a %s. Remove the product assignment first, then try again.', 'wicket-memberships' ),
      esc_html( $product_title ),
      $assignment
    );
  }

  /**
   * Pre-trash filter — aborts trashing a WooCommerce product that is still
   * assigned to a Membership Tier or used as a late-fee product in a Config.
   *
   * WHY pre_trash_post (filter) instead of wp_trash_post (action):
   *   wp_trash_post fires INSIDE wp_trash_post() after the filter, but before
   *   the post status changes.  Calling wp_die() from that action produces a
   *   raw error page and leaves the request in an inconsistent state.  The
   *   pre_trash_post filter fires first; returning any non-null value causes
   *   wp_trash_post() to return that value immediately without changing any
   *   post status — a clean, no-side-effect abort.
   *
   * Product variations are NOT checked here; they are protected by
   * intercept_variation_removal() which intercepts at the AJAX layer.
   *
   * @param  bool|null  $abort           Existing filter value (null = proceed).
   * @param  \WP_Post   $post            The post about to be trashed.
   * @param  string     $previous_status Current post status before trash.
   * @return bool|null  false to abort; null to pass through unchanged.
   */
  function prevent_trash_linked_product( $abort, $post, $previous_status ) {
    // Only gate top-level WooCommerce products. Skip variations so that
    // cascading variation trashes (triggered by trashing the parent) are not
    // double-intercepted.  All other post types pass through unchanged.
    if ( get_post_type( $post->ID ) !== 'product' ) {
      return $abort;
    }

    $message = $this->get_membership_product_block_message( $post->ID );
    if ( null === $message ) {
      return $abort; // not a protected product
    }

    Utilities::wicket_logger( 'prevent_trash_linked_product: BLOCKING trash', [
      'post_id'    => $post->ID,
      'post_title' => get_the_title( $post->ID ),
    ] );

    // Persist the message so admin_notices can display it on the next page view.
    set_transient( '_wicket_product_delete_error_' . get_current_user_id(), $message, 120 );

    if ( wp_doing_ajax() ) {
      // Returning false causes wp_trash_post() to return false, which in turn
      // causes wp_ajax_trash_post() to call wp_die(0) — a clean AJAX failure.
      // The transient message will surface on the next admin page load.
      return false;
    }

    // Full-page request (standard product-list "Trash" inline action).
    // Redirect cleanly to the product list with an error flag so the
    // admin_notices hook can render a proper inline notice.
    wp_safe_redirect(
      esc_url_raw(
        add_query_arg(
          'show_membership_product_delete_error', '1',
          admin_url( 'edit.php?post_type=product' )
        )
      )
    );
    exit;
  }

  /**
   * Early AJAX interceptor for WooCommerce's variation-removal request.
   *
   * WHY hook at priority 1 on wp_ajax_woocommerce_remove_variations:
   *   WooCommerce registers its own handler for this action at default
   *   priority 10.  Its handler iterates the submitted variation IDs, calls
   *   $variation->delete(true), and always ends with wp_die(-1) regardless
   *   of outcome.  WC's JS callback (`$.post(..., function() {...})`) takes
   *   no parameters and removes the variation from the UI unconditionally
   *   after any 2xx response.
   *
   *   By firing at priority 1 we run first.  If any variation belongs to a
   *   protected parent product we send an HTTP 403 JSON error and exit —
   *   WC's handler never executes, the variation is NOT deleted.
   *
   *   HTTP 403 means jQuery's success callback does not fire, so WC's JS
   *   does NOT remove the variation from the UI.  The companion JS added
   *   by enqueue_variation_protection_js() listens on ajaxComplete (which
   *   fires on 4xx as well as 2xx) and renders a visible admin notice.
   *
   * @return void  Exits via wp_send_json_error() when a protected variation is found.
   */
  function intercept_variation_removal() {
    // Missing or empty variation_ids — nothing to validate; let WC handle it.
    if ( empty( $_POST['variation_ids'] ) || ! is_array( $_POST['variation_ids'] ) ) {
      return;
    }

    $variation_ids = array_map( 'absint', (array) wp_unslash( $_POST['variation_ids'] ) );

    foreach ( $variation_ids as $variation_id ) {
      if ( 'product_variation' !== get_post_type( $variation_id ) ) {
        continue;
      }

      $parent_id = wp_get_post_parent_id( $variation_id );
      if ( ! $parent_id ) {
        continue;
      }

      $message = $this->get_membership_product_block_message( $parent_id );
      if ( null !== $message ) {
        Utilities::wicket_logger( 'intercept_variation_removal: BLOCKING removal', [
          'variation_id' => $variation_id,
          'parent_id'    => $parent_id,
        ] );

        // Set transient as a fallback in case the JS notice mechanism fails.
        set_transient( '_wicket_product_delete_error_' . get_current_user_id(), $message, 120 );

        // HTTP 403: jQuery's $.post() success callback will NOT fire, so WC's JS
        // will NOT remove the variation row from the UI.  The wicket_blocked flag
        // lets our ajaxComplete JS listener distinguish this from other 403s.
        wp_send_json_error( [ 'message' => $message, 'wicket_blocked' => true ], 403 );
        // wp_send_json_error() calls wp_die() — execution terminates here.
      }
    }

    // No protected variations found — fall through so WC's handler (priority 10) runs.
  }

  /**
   * Enqueue a small inline JS snippet on the WooCommerce product edit page.
   *
   * The snippet attaches a jQuery ajaxComplete listener scoped to the
   * woocommerce_remove_variations action.  When our intercept_variation_removal()
   * blocks a deletion it returns HTTP 403.  jQuery's ajaxComplete event fires on
   * all completed AJAX requests, including 4xx errors, so we can read the JSON
   * payload, check for the wicket_blocked flag, and render an inline WP admin
   * notice without touching WooCommerce's core JS.
   *
   * @param string $hook Current admin page hook suffix.
   * @return void
   */
  function enqueue_variation_protection_js( $hook ) {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
      return;
    }
    global $post;
    if ( ! $post || get_post_type( $post->ID ) !== 'product' ) {
      return;
    }

    // Print at admin_footer so jQuery and all WC scripts are guaranteed loaded
    // and the product-data DOM is fully rendered before the listener attaches.
    add_action( 'admin_footer', [ $this, 'print_variation_protection_js' ] );
  }

  /**
   * Print the inline ajaxComplete listener in the admin footer.
   * Called only on the product edit page via enqueue_variation_protection_js().
   *
   * KEY FIXES over the original approach:
   *  - xhr.responseJSON is undefined (not null) on 4xx in many jQuery builds;
   *    we fall back to JSON.parse(xhr.responseText) with a try/catch.
   *  - We only test for the wicket_blocked flag rather than also asserting
   *    response.success === false, so a slightly malformed payload still works.
   *  - Insertion uses #wpbody-content (always present in WP admin) then scrolls
   *    the window to the top so the notice is immediately visible.
   */
  function print_variation_protection_js() {
    $dismiss_label = esc_js( __( 'Dismiss this notice.', 'wicket-memberships' ) );
    ?>
    <script>
    (function($){
      /**
       * Use ajaxError (not ajaxComplete) — it fires only on failed/4xx requests,
       * which is exactly what our priority-1 intercept returns (HTTP 403).
       *
       * Two things must happen on a blocked variation removal:
       *   1. Unblock the WC product-data panel — WC calls .unblock() only inside
       *      its success callback, which never fires on 4xx, leaving the spinner
       *      indefinitely.
       *   2. Insert a visible error notice — placed directly before
       *      #woocommerce-product-data (the variations panel) so it appears in
       *      context, then scroll to it.
       */
      $(document).ajaxError(function(event, xhr, settings) {
        // Scope to variation-removal requests only.
        var params = (typeof settings.data === 'string')
          ? settings.data
          : $.param(settings.data || {});
        if (params.indexOf('woocommerce_remove_variations') === -1) { return; }

        // Parse from responseText directly — more reliable than xhr.responseJSON
        // which is not populated by jQuery on 4xx responses in all versions.
        var response;
        try { response = JSON.parse(xhr.responseText); } catch(e) {}

        // Only act when our PHP interceptor explicitly set wicket_blocked.
        if (!response || !response.data || !response.data.wicket_blocked) { return; }

        // 1. Clear the WC spinner — blockUI is used by WC on the product-data
        //    metabox; calling .unblock() restores the UI regardless of nesting.
        $('#woocommerce-product-data').unblock();

        // 2. Build and show a dismissible admin notice.
        var msg = $('<div>').text(response.data.message).html();

        // Remove any stacked notice from a rapid retry.
        $('.wicket-variation-block-notice').remove();

        var notice = $(
          '<div class="notice notice-error is-dismissible wicket-variation-block-notice" style="margin:10px 20px 10px 2px;">'
          + '<p><strong>Wicket Memberships:</strong> ' + msg + '</p>'
          + '<button type="button" class="notice-dismiss">'
          + '<span class="screen-reader-text"><?php echo $dismiss_label; ?></span>'
          + '</button>'
          + '</div>'
        );

        notice.find('.notice-dismiss').on('click', function() {
          notice.fadeOut(200, function() { notice.remove(); });
        });

        // Insert immediately before the WC product-data metabox — always present
        // on the product edit page and already in the visible viewport area.
        var $anchor = $('#woocommerce-product-data');
        if ($anchor.length) {
          $anchor.before(notice);
          $('html, body').animate({ scrollTop: notice.offset().top - 80 }, 300);
        } else {
          // Fallback: top of the admin content area.
          $('#wpbody-content').prepend(notice);
          $('html, body').animate({ scrollTop: 0 }, 300);
        }
      });
    }(jQuery));
    </script>
    <?php
  }

  /**
 * Disable remove item on cart page when in Membership category.
 *
 * @param string $product_remove The remove html.
 * @param string $cart_item_key The cart item key.
 * @param array $cart_item The cart item object.
 * @return string The modified product quantity html (with hidden input).
 */
  function hide_cart_item_remove_link($product_remove, $cart_item_key)
  {
    if (is_cart()) {
      $item = WC()->cart->get_cart_item( $cart_item_key );
      $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');
      $category = get_term_by( 'slug', 'membership_late_fee', 'product_cat' );
      $cat_id = $category->term_id;
      $membership_categories[] = $cat_id;
      if ( has_term( $membership_categories, 'product_cat', $item['product_id']) ) {
        $product_remove = '<a href="'.esc_url( add_query_arg( 'empty-cart', 'true', wc_get_cart_url() ) ).'" class="remove" aria-label="Remove" onclick="event.stopImmediatePropagation(); return confirm(\''.__("This will empty the cart of all items.", 'wicket-memberships').'\');">×</a>';
      }
    }
    return $product_remove;
  }


  /**
 * Disable quantity input on cart page when in Membership category.
 *
 * @param string $product_quantity The current product quantity html.
 * @param string $cart_item_key The cart item key.
 * @param array $cart_item The cart item object.
 * @return string The modified product quantity html (with hidden input).
 */
  function disable_cart_item_quantity($product_quantity, $cart_item_key, $cart_item)
  {
    if (is_cart()) {
      $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');
      $category = get_term_by( 'slug', 'membership_late_fee', 'product_cat' );
      $cat_id = $category->term_id;
      $membership_categories[] = $cat_id;
      // We don't want to disable quantity input if the membership categories are not set (triggers bug: https://app.asana.com/1/1138832104141584/task/1210499044933981?focus=true)
      if(empty($membership_categories)) {
        return $product_quantity;
      }
      if ( has_term( $membership_categories, 'product_cat', $cart_item['product_id']) ) {
        $product_quantity = sprintf('<strong>%s</strong><input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item['quantity'], $cart_item_key, $cart_item['quantity']);
      }
    }
    return $product_quantity;
  }


  function handle_wp_delete_user( $user_id, $reassign = false, $user = false) {
    $args = array(
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query' => array(
        array(
          'key'     => 'user_id',
          'value'   => $user_id,
          'compare' => '='
        )
      )
    );
    $memberships = new \WP_Query( $args );
    foreach($memberships->posts as $mship) {
      if(get_post_meta( $mship->ID, 'user_email', true) == $user->user_email) {
        //clear the meta on the user for this membership
        $user_meta_removed = delete_user_meta( $user_id, '_wicket_membership_'.$mship->ID );
        if( $order_id = get_post_meta( $mship->ID, 'membership_parent_order_id', true)) {
          if(!empty($order_id)) {
            //clear the meta on the order for this membership
            $order_meta_removed = delete_post_meta( $order_id, '_wicket_membership_'.$mship->membership_product_id );
            $order = wc_get_order( $order_id );
            if(!empty($order)) {
              $order->add_order_note( "Membership ID: {$mship->ID} deleted when user deleted.");
              $order->add_order_note( " Order meta removed: ".$order_id . '_wicket_membership_'.$mship->membership_product_id);
              $order->add_order_note( " User meta removed: " . $user_id . '_wicket_membership_'.$mship->ID);
            }
          }
        }
        wp_delete_post($mship->ID);
      }
    }
  }

  function is_wicket_show_mship_order_org_search() {
    $options = get_option( 'wicket_membership_plugin_options' );
    if(isset($options['wicket_show_mship_order_org_search'])) {
      if(!empty($options['wicket_show_mship_order_org_search'])) {
        return true;
      }
    }
    return false;
  }

  function set_subscription_postmeta_suborg_uuid() {
    if(!empty($_REQUEST['wicket_sub_org_select_uuid'])) {
      wc_update_order_item_meta( $_REQUEST['wicket_update_suborg_item_id'], '_org_uuid', $_REQUEST['wicket_sub_org_select_uuid'] );
    }
  }

  function wicket_sub_org_select_metabox( $post_type, $post ) {
    $sub = wcs_get_subscription($post->ID);
    if(empty($sub)) {
      return;
    }
    $options = get_option( 'wicket_membership_plugin_options' );
    $categories_selected = $options['wicket_show_mship_order_org_search']['categorychoice'];
    $subscription_products = $sub->get_items();
    foreach( $subscription_products as $item ) {
      if(empty($product_id)) {
        $product_id = $item->get_product_id();
      }
      if ( ! has_term( $categories_selected, 'product_cat', $item->get_product_id() )
          && ! has_term( $categories_selected, 'product_cat', $item->get_variation_id() ) ) {
        continue;
      }
      if( $item->get_meta('_org_uuid' ,true)) {
        continue;
      }
      $org_uuid_missing = true;
    }
    if(empty($org_uuid_missing)) {
      return;
    }

    $screen =  wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled( 'shop_subscription' ) ? 'woocommerce_page_wc-orders--shop_subscription' : 'shop_subscription';

        add_meta_box(
            'custom',
            __('Membership Order > SELECT Organization'),
            [$this, 'wicket_sub_org_select_callback'],
            $screen,
            'normal',
            'high'
        );
}

function wicket_sub_org_select_callback( $subscription ) {
      $org_uuid = '';
      $sub = wcs_get_subscription($subscription);
      if(empty($sub)) {
        return;
      }
      $subscription_products = $sub->get_items();
      foreach( $subscription_products as $item ) {
        $product_id = $item->get_variation_id();
        if(empty($product_id)) {
          $product_id = $item->get_product_id();
        }
        if ( ! has_term( 'Membership', 'product_cat', $product_id )) {
          continue;
        }
      }
      if(!empty($item->get_meta('_org_uuid'))){
        return;
      }
      wp_nonce_field('suborg_nonce', 'suborg_nonce_field');
    ?>
    <div class="search-container">
      <input type="hidden" id="suborg-search-id" name="wicket_sub_org_select_uuid" value="<?php echo $org_uuid;?>">
      <input type="hidden" id="suborg-search-item-id" name="wicket_update_suborg_item_id" value="<?php echo $item->get_id();?>">
      <input type="text" id="suborg-search" name="wicket_sub_org_select_name" class="woocommerce-input" value="<?php echo $org_uuid;?>" placeholder="Organization Search..." />
      <div id="suborg-results" class="woocommerce-results"></div>
    </div>
    <style>
      /* Container for the search input and results */
      .search-container {
          position: relative; /* For positioning the results */
      }

      /* Style the input field */
      .woocommerce-input {
          width: 100%; /* Full width */
          padding: 12px; /* Padding for comfortable click area */
          border: 1px solid #ccc; /* Border color */
          border-radius: 4px; /* Rounded corners */
          background-color: #fff; /* Background color */
          font-size: 16px; /* Font size */
          color: #333; /* Text color */
          transition: border-color 0.3s ease; /* Smooth border transition */
      }

      /* Input focus style */
      .woocommerce-input:focus {
          border-color: #0071a1; /* Change border color on focus */
          outline: none; /* Remove default outline */
      }

      /* Style for results container */
      .woocommerce-results {
          position: absolute; /* Position results below the input */
          top: 100%; /* Align to the bottom of the input */
          left: 0; /* Align to the left */
          right: 0; /* Stretch to the right */
          background-color: #fff; /* Background color */
          border: 1px solid #ccc; /* Border around results */
          border-radius: 4px; /* Rounded corners */
          z-index: 999; /* Ensure it appears above other elements */
          max-height: 200px; /* Limit height for scrolling */
          overflow-y: auto; /* Scroll if too many results */
          display: none; /* Initially hidden */
      }

      /* Individual result item */
      .woocommerce-results .result-item {
          padding: 10px; /* Padding for items */
          cursor: pointer; /* Pointer cursor on hover */
          color: #333; /* Text color */
      }

      /* Hover effect for result items */
      .woocommerce-results .result-item:hover {
          background-color: #f7f7f7; /* Change background on hover */
      }

      /* No results found message */
      .woocommerce-results .no-results {
          padding: 10px; /* Padding for no results */
          color: #999; /* Color for no results text */
          text-align: center; /* Center text */
      }
    </style>
    <?php
  }

  function enqueue_suborg_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('custom-suborg', plugins_url('../assets/js/wicket_suborg.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('custom-suborg', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
  }

  function handle_suborg_search() {
    check_ajax_referer('suborg_nonce', 'nonce');
    $search_term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
    $search_json = json_encode(['searchTerm' => $search_term, 'autocomplete' => true]);
    $request = new \WP_REST_Request('POST');
    $request->set_headers(['Content-Type' => 'application/json']);
    $request->set_body($search_json); // Set the body as the JSON string
    $results = wicket_internal_endpoint_search_orgs( $request);
    wp_reset_postdata();
    wp_send_json($results);
  }

    /**
   * Ipdate the tier uuid using Wicket Tier Page [post_row_action], necessary for migrating a tier data from staging to production
   * @return json
   */
  public function handle_wicket_tier_uuid_update() {
    check_ajax_referer('tier_uuid_update_nonce', 'nonce');
    $new_tier_name = isset($_POST['tierUUID']) ? sanitize_text_field(explode('|', $_POST['tierUUID'])[1]) : '';
    $new_tier_uuid = isset($_POST['tierUUID']) ? sanitize_text_field(explode('|', $_POST['tierUUID'])[0]) : '';
    $tier_post_id = isset($_POST['postID']) ? sanitize_text_field($_POST['postID']) : '';
    $tier_data = get_post_meta($tier_post_id, 'tier_data');
    $tier_data[0]['mdp_tier_uuid'] = $new_tier_uuid;
    $tier_data[0]['mdp_tier_name'] = $new_tier_name;
    $post_args = array(
        'ID'         => $tier_post_id,
        'post_title' => $new_tier_name,
    );
    try {
      wp_update_post( $post_args );
      update_post_meta(($tier_post_id), 'tier_data', $tier_data[0]);
      wp_send_json_success($tier_data[0]);
    } catch (\Exception $e) {
      wp_send_json_error($tier_data[0]);
    }
    wp_reset_postdata();
  }

    /**
   * Input added to assign a Membership ID to subscription
   * @param mixed $subscription
   * @return void
   */
  public function wicket_display_membership_id_input_on_order($subscription)
  {
    $subscription = wcs_get_subscription($subscription->get_id());
    if (empty($subscription)) {
      return;
    }
    $membership_id = $subscription->get_meta('_membership_id');
  ?>
    <style>
      .custom-meta-container {
        display: none;
        margin-top: 5px;
      }
    </style>
    <p class="form-field form-field-wide">
      <label for="membership_id" class="toggle-meta" style="cursor: pointer; color: #0073aa;">Click Here to attach to an existing Membership by Post ID</label>
    </p>
    <div id="membership_id" class="custom-meta-container">
      <input placeholder="Membership Post ID" onfocus="if(!this.dataset.alerted){alert('Only add this field if you understand exactly what it is and why it is needed!\n\nThe customer/user assigned to subscription will become the membership owner.\n\nIt is not necessary and it can create membership problems if done incorrectly.\n\nYou have been WARNED!'); this.dataset.alerted = true;}" type="text" name="wicket_subscription_add_membership_id" value="<?php echo $membership_id; ?>"><br>
      <small><span style="color:red;font-weight:bold;">IMPORTANT:</span> <strong style="color:black;">NOT REQUIRED. Do not enter any value here unless you understand why. <i>IF SET INCORRECTLY IT WILL BREAK YOUR MEMBERSHIPS.</i></strong></small>
    </div>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".toggle-meta").forEach(function(label) {
          label.addEventListener("click", function() {
            var container = document.getElementById(this.getAttribute("for"));
            if (container) {
              container.style.display = container.style.display === "none" ? "block" : "none";
            }
          });
        });
      });
    </script>
  <?php
  }

  /**
   * This assigns a subscription to a membership so it can be used in a particular renewal flow
   * SPECIFICALLY: Associate-Retailer and Associate-Clinic will try to renew an existing subscription
   * if the subscription does not yet exist it must be created and assigned the product with the custom price
   * once it is created this method allows you to assign it to a membership and then the renewal order will get generated correctly.
   */
  public function wicket_assign_subscription_to_membership($membership_subscription_id, $membership_id = null) {
    if (! class_exists('Wicket_Memberships\Membership_Controller') || !$membership_subscription_id || ( empty($membership_id) && empty($_REQUEST['wicket_subscription_add_membership_id']) )) {
      return;
    }
    if(!empty($_REQUEST['wicket_subscription_add_membership_id']) && empty($membership_id) ) {
      $membership_id = sanitize_text_field($_REQUEST['wicket_subscription_add_membership_id']);
    }
    $subscription = wcs_get_subscription($membership_subscription_id);
    if (!empty($subscription) && $membership_id == $subscription->get_meta('_membership_id')) {
      return;
    } else if (!empty($subscription)) {
      $subscription->update_meta_data('_membership_id', $membership_id);
      $subscription->save();
      $membership_order_id = $subscription->get_parent_id();
      foreach ($subscription->get_items() as $item_id => $subscription_item) {
        $product_id = $subscription_item->get_product_id();
        $product = wc_get_product($product_id);
        if (has_term('Membership', 'product_cat', $product_id) && $product->get_sku() != 'LBM') {
          $membership_product_id = $product_id;
          wc_add_order_item_meta($item_id, '_membership_post_id_renew', $membership_id);
        }
      }

      $old_user_id = get_post_meta($membership_id, 'user_id', true);

      //we need this later on to merge the new and old membership data to save back to user and order meta
      $membership_array = (new \Wicket_Memberships\Membership_Controller())->get_membership_array_from_user_meta_by_post_id($membership_id, $old_user_id);

      $user_id = $subscription->get_customer_id();
      $user = get_user_by('id', $user_id);

      update_post_meta($membership_id, 'membership_post_id', $membership_id);
      $membership_meta['membership_post_id'] = $membership_id;

      update_post_meta($membership_id, 'user_id', $user_id);
      $membership_meta['user_id'] = $user_id;

      update_post_meta($membership_id, 'user_name', $user->display_name);
      $membership_meta['user_name'] = $user->display_name;

      update_post_meta($membership_id, 'user_email', $user->user_email);
      $membership_meta['user_email'] = $user->user_email;

      update_post_meta($membership_id, 'membership_user_uuid', $user->user_login);
      $membership_meta['membership_user_uuid'] = $user->user_login;

      update_post_meta($membership_id, 'membership_parent_order_id', $membership_order_id);
      $membership_meta['membership_parent_order_id'] = $membership_order_id;

      update_post_meta($membership_id, 'membership_subscription_id', $membership_subscription_id);
      $membership_meta['membership_subscription_id'] = $membership_subscription_id;

      update_post_meta($membership_id, 'membership_product_id', $membership_product_id);
      $membership_meta['membership_product_id'] = $membership_product_id;

      //just in case - may need to be synced
      $membership_next_tier_form_page_id = get_post_meta($membership_id, 'membership_next_tier_form_page_id', true);
      $membership_meta['membership_next_tier_form_page_id'] = $membership_next_tier_form_page_id;
      $membership_next_tier_id = get_post_meta($membership_id, 'membership_next_tier_id', true);
      $membership_meta['membership_next_tier_id'] = $membership_next_tier_id;
      $membership_next_tier_subscription_renewal = get_post_meta($membership_id, 'membership_next_tier_subscription_renewal', true);
      $membership_meta['membership_next_tier_subscription_renewal'] = $membership_next_tier_subscription_renewal;

      if (! empty($membership_array)) {
        $updated_membership_array = array_merge($membership_array, $membership_meta);
        $updated_user_meta = update_user_meta($membership_meta['user_id'], '_wicket_membership_' . $membership_id, json_encode($updated_membership_array));
        if ($membership_meta['user_id'] != $old_user_id && !empty($updated_user_meta)) {
          delete_user_meta($old_user_id, '_wicket_membership_' . $membership_id);
        }
        update_post_meta($updated_membership_array['membership_parent_order_id'], '_wicket_membership_' . $updated_membership_array['membership_product_id'], json_encode($updated_membership_array));
        update_post_meta($updated_membership_array['membership_subscription_id'], '_wicket_membership_' . $updated_membership_array['membership_product_id'], json_encode($updated_membership_array));
        $subscription->add_order_note("Reassigned subscription to membership ID: " . $membership_id, false);
      }
    }
  }

  public static function autorenew_checkbox_toggle_switch() {
    if(!empty($_ENV['WICKET_MSHIP_AUTORENEW_TOGGLE'])) {
      add_action('wp', [__NAMESPACE__.'\\Utilities', 'wc_autorenew_toggle_filters']);
      add_action('wp_footer', [__NAMESPACE__.'\\Utilities', 'wicket_wc_enqueue_scripts_autorenew_toggle']);
      add_action('wp_ajax_auto_renew_enabled_for_user', [__NAMESPACE__.'\\Utilities', 'handle_user_auto_renew_toggle']);
      add_action('wp_ajax_nopriv_auto_renew_enabled_for_user', [__NAMESPACE__.'\\Utilities', 'handle_user_auto_renew_toggle']); // Allow guests if needed
      add_action('wp_enqueue_scripts', [__NAMESPACE__.'\\Utilities', 'enqueue_mship_ajax_script']);
    }
  }

  static function wc_autorenew_toggle_filters() {
    $self = new self();
    add_shortcode('wicket_autorenew_toggle_shortcode', [$self, 'wc_autorenew_toggle_shortcode']);
    add_filter('gform_field_value_wicket_autorenew_toggle_shortcode', function($value) {
      return do_shortcode('[wicket_autorenew_toggle_shortcode]');
    });
  }

  /**
   * Summary of wc_autorenew_toggle_shortcode
   * @param mixed $atts
   * @return void
   */
  function wc_autorenew_toggle_shortcode($atts) {
    if(isset($_REQUEST['subscription_id']) && !empty($_REQUEST['subscription_id'])) {
      $subscription_id = !empty(intval($atts['subscription_id'])) ? intval($atts['subscription_id']) : intval($_REQUEST['subscription_id']);
    } else if(isset($_REQUEST['membership_post_id_renew']) && !empty($_REQUEST['membership_post_id_renew'])) {
      $subscription_id = get_post_meta( intval($_REQUEST['membership_post_id_renew']), 'membership_subscription_id', true );
    }
    if(!empty($subscription_id)) {
      $sub = wcs_get_subscription( $subscription_id );
      if(!empty($sub)) {
        $is_autopay_enabled = !empty($sub->get_requires_manual_renewal()) ? false : true;
      }
      $checked = !empty($is_autopay_enabled) ? 'checked' : '';
    } else {
      $user_autopay_enabled = get_user_meta( get_current_user_id(), 'subscription_autopay_enabled', true);
      $checked = !empty($user_autopay_enabled) && $user_autopay_enabled == 'yes' ? 'checked' : '';
    }
    ob_start();
    ?>
    <label class="wicket-wc-toggle">
      <input type="checkbox" id="wicket-wc-toggle" <?php echo $checked; ?>>
      <span class="slider"></span>
    </label>
    <?php
    return ob_get_clean();
  }

  /**
   *  Includes the styling and js for the autorenew toggle in the footer
   *
   *  <label class="wicket-wc-toggle">
   *    <input type="checkbox" id="wicket-wc-toggle">
   *    <span class="slider"></span>
   *  </label>
   * @return void
   */
  public static function wicket_wc_enqueue_scripts_autorenew_toggle() {
    wp_localize_script('auto_renew_enabled_for_user',
      'wicket_mship_ajax_object',
      ['ajaxurl' => admin_url('admin-ajax.php'),
      'user_id' => get_current_user_id()
      ]
    );
    ?>
    <style>
        .wicket-wc-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .wicket-wc-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .wicket-wc-toggle .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            border-radius: 12px;
            transition: 0.3s;
        }

        .wicket-wc-toggle .slider::before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        .wicket-wc-toggle input:checked + .slider {
            background-color: #2c6fbb;
        }

        .wicket-wc-toggle input:checked + .slider::before {
            transform: translateX(26px);
        }
    </style>

    <script>
      jQuery(document).ready(function($) {

          // Update the Gravity Forms hidden field text field "Autorenew Selected". It must also have a custom CSS class set to "autorenew_selected"
          // This is used mainly for confirmation redirects
          function sync_autorenew_field() {
            if ($('#wicket-wc-toggle').is(':checked')) {
              $('.autorenew_selected input').val('yes').attr('value','yes');
            } else {
              $('.autorenew_selected input').val('').attr('value','');
            }
          }

          // Run once on page load (value might be pre-set by PHP above)
          sync_autorenew_field();

          $('#wicket-wc-toggle').on('change', function() {
              var isChecked = $(this).is(':checked') ? 1 : 0;

              sync_autorenew_field();
              console.log('wicket-wc-toggle changed');

              $.ajax({
                  url: wicket_mship_ajax_object.ajaxurl,
                  type: 'POST',
                  data: {
                      action: 'auto_renew_enabled_for_user',
                      user_id: wicket_mship_ajax_object.user_id,
                      <?php
                        if(isset($_REQUEST['subscription_id']) && !empty($_REQUEST['subscription_id'])) {
                          echo "subscription_id: '".esc_js($_REQUEST['subscription_id'])."',\n";
                        }
                        if(isset($_REQUEST['membership_post_id_renew']) && !empty($_REQUEST['membership_post_id_renew'])) {
                          echo "membership_post_id_renew: '".esc_js($_REQUEST['membership_post_id_renew'])."',\n";
                        }
                      ?>
                      enabled: isChecked
                  },
                  success: function(response) {
                      console.log('Success:', response);
                  },
                  error: function(error) {
                      console.log('Error:', error);
                  }
              });
          });
      });
    </script>
    <?php
  }

  public static function handle_user_auto_renew_toggle() {
    if (!isset($_POST['user_id']) || !isset($_POST['enabled'])) {
        wp_send_json_error(['message' => 'Invalid request.']);
    }
    $user_id = intval($_POST['user_id']);
    $enabled = $_POST['enabled'] == 1 ? 'yes' : 'no';
    if( isset($_POST['subscription_id']) ) {
      $subscription_id = $_POST['subscription_id'];
    } else if( isset($_POST['membership_post_id_renew']) ) {
      $subscription_id = get_post_meta( intval($_REQUEST['membership_post_id_renew']), 'membership_subscription_id', true );
    }
    if(!empty($subscription_id)) {
      $subscription = wcs_get_subscription($subscription_id);
      $subscription_renewal_boolean = $enabled == 'yes' ? 'false' : 'true'; //reverse to set manual_renewal_enabeld;
      $subscription->update_meta_data('_requires_manual_renewal', $subscription_renewal_boolean);
      $subscription->save();
      $subscription->add_order_note('Customer turned '.($enabled == 'yes' ? 'on' : 'off').' automatic renewals via the Wicket Toggle. Manual renewal flag set to '.$subscription_renewal_boolean);
    }
    update_user_meta($user_id, 'subscription_autopay_enabled', $enabled);
    wp_send_json_success(['message' => 'Auto-renew status updated for '.$user_id, 'status' => $enabled]);
  }

  /**
   * Enqueue membership AJAX script if it exists
   *
   * Checks for the existence of custom-ajax.js in the theme directory
   * and enqueues it along with localization data if found.
   *
   * @return void
   */
  public static function enqueue_mship_ajax_script() {
    // get_stylesheet_directory() automatically checks child theme first and falls back to parent theme
    $script_path = get_stylesheet_directory() . '/js/custom-ajax.js';

    // Always register a script handle to ensure wp_localize_script works
    if (file_exists($script_path)) {
      // Enqueue the custom script if it exists
      wp_enqueue_script('ajax-script', get_stylesheet_directory_uri() . '/js/custom-ajax.js', ['jquery'], null, true);
    } else {
      // Register a dummy script to ensure wp_localize_script has something to attach to
      wp_register_script('ajax-script', false, ['jquery'], null, true);
      wp_enqueue_script('ajax-script');
    }

    // Always register the ajax object for use by other scripts
    wp_localize_script('ajax-script', 'wicket_mship_ajax_object', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'user_id' => get_current_user_id()
    ]);
  }


  /**
   * Get a DateTime object in UTC timezone.
   * This is used to provide consistent time handling across different server configurations.
   *
   * @param string $date_string Optional date string to initialize the DateTime object. Defaults to 'now'.
   * @return \DateTime DateTime object set to UTC timezone.
   */
  public static function get_utc_datetime($date_string = 'now')
  {
    return new \DateTime($date_string, new \DateTimeZone('UTC'));
  }

  /**
   * Get the current datetime in UTC, based on the configured MDP timezone.
   *
   * @return \DateTime DateTime object representing "now", normalized to UTC.
   */
  public static function get_mdp_now()
  {
    // Get MDP timezone from environment variable, fallback to UTC
    $mdp_timezone = new \DateTimeZone($_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC');

    // Build "now" in MDP timezone, then normalize output to UTC
    $mdp_now = new \DateTime('now', $mdp_timezone);

    return $mdp_now->setTimezone(new \DateTimeZone('UTC'));
  }

  /**
   * Get the start of an MDP day (midnight) in UTC timezone.
   * This converts a date to the start of the day in the MDP timezone, then converts to UTC.
   *
   * @param string $date_string Optional date string to initialize. Defaults to 'now' for today.
   * @return \DateTime DateTime object set to the start of the MDP day in UTC timezone.
   */
  public static function get_mdp_day_start($date_string = 'now')
  {
    // Get MDP timezone from environment variable, fallback to UTC
    $mdp_timezone = new \DateTimeZone($_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC');

    // Create DateTime (timezone in string may override $mdp_timezone parameter)
    $mdp_date = new \DateTime($date_string);

    // Force timezone to MDP (handles cases where input string contains timezone info)
    $mdp_date->setTimezone($mdp_timezone);

    // Set to start of day (midnight) in MDP timezone
    $mdp_date->setTime(0, 0, 0);

    // Convert to UTC and return
    return $mdp_date->setTimezone(new \DateTimeZone('UTC'));
  }

  /**
   * Get the end of an MDP day (23:59:59) in UTC timezone.
   * This converts a date to the end of the day in the MDP timezone, then converts to UTC.
   *
   * @param string $date_string Optional date string to initialize. Defaults to 'now' for today.
   * @return \DateTime DateTime object set to the end of the MDP day in UTC timezone.
   */
  public static function get_mdp_day_end($date_string = 'now')
  {
    // Get MDP timezone from environment variable, fallback to UTC
    $mdp_timezone = new \DateTimeZone($_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC');

    // Create DateTime (timezone in string may override $mdp_timezone parameter)
    $mdp_date = new \DateTime($date_string);

    // Force timezone to MDP (handles cases where input string contains timezone info)
    $mdp_date->setTimezone($mdp_timezone);

    // Set to end of day (23:59:59) in MDP timezone
    $mdp_date->setTime(23, 59, 59);

    // Convert to UTC and return
    return $mdp_date->setTimezone(new \DateTimeZone('UTC'));
  }


  /**
   * Apply URL coupons immediately after the membership renewal product is added to cart.
   *
   * Hooking into `woocommerce_add_to_cart` (fired by WC_Form_Handler at wp_loaded:20,
   * right after the product lands in the cart) ensures the cart has items before coupon
   * validation runs. The applied coupon is stored in the WC session and survives the
   * subsequent redirect to /cart/.
   *
   * Supports both `?coupon-code=` and `?wt_coupon=` (Smart Coupons), comma-separated.
   *
   * @param string $cart_item_key
   * @param int    $product_id
   * @return void
   */
  public function apply_renewal_coupon_on_add( $cart_item_key, $product_id ) {
    if ( empty( $_GET['coupon-code'] ) && empty( $_GET['wt_coupon'] ) ) {
      return;
    }

    $query_keys = ['coupon-code', 'wt_coupon'];
    $applied    = WC()->cart->get_applied_coupons();

    foreach ( $query_keys as $key ) {
      if ( empty( $_GET[ $key ] ) ) {
        continue;
      }
      $coupons = explode( ',', sanitize_text_field( $_GET[ $key ] ) );
      foreach ( $coupons as $code ) {
        $code = trim( $code );
        if ( ! $code || in_array( $code, $applied ) ) {
          continue;
        }
        WC()->cart->apply_coupon( $code );
      }
    }
  }

  /**
   * Prevent WooCommerce Subscriptions from skipping the cart page on membership renewals.
   *
   * WCS hooks `woocommerce_add_to_cart_redirect` at priority 10 and returns
   * `wc_get_checkout_url()`, bypassing the cart entirely. When `membership_post_id_renew`
   * is present in the URL the user must land on the cart page so they can review the
   * discounted price before proceeding. Returning false cancels the WCS redirect.
   *
   * @param string|false $url The redirect URL proposed by WCS.
   * @return string|false Original URL unchanged, or false to cancel the redirect.
   */
  public function prevent_renewal_checkout_redirect( $url ) {
    if ( empty( $_GET['membership_post_id_renew'] ) ) {
      return $url;
    }

    return false;
  }
}
