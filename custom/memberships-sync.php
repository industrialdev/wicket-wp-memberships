<?php

/**
 * Everything to get the imported memberships attached and synced with order/subscription/products
 * 1) Set the different IDs in the membership post metadata
 * 2) Add the Order/Subscription membership json blob in the post_meta ('_wicket_membership_'.$product_id )
 * 3) Update the Customer membership json blob in the user_meta ( '_wicket_membership_'.$membership_post_id )
 *
 * Browser :: https://{domain}/?mship_subscription_sync=1
 * Review :: simulate with results dumped to page
 * Run:: with 'no_debug' to make live updates
 *
 * NOTE: Without query_var: no_debug=1 it will always run in debug by default.
 */


#error_reporting(0);
#ini_set('error_reporting', 0);
ini_set('max_execution_time', '0');

// Run on template_redirect (not init): the current user, capability checks, and the admin
// bar are all available here, and exiting now cleanly replaces the theme output with our page.
add_action( 'template_redirect', 'wicket_action_woocommerce_loaded', 10, 1 );

/**
 * Launches the subscription sync tool when its trigger query var is present.
 *
 * @return void
 */
function wicket_action_woocommerce_loaded() {
  if(!empty($_REQUEST['mship_subscription_sync'])) {
    wicket_sync_subscriptions();
  }
}

/**
 * Reconciles WooCommerce membership subscriptions with their plugin membership records.
 *
 * Triggered by the `?mship_subscription_sync=1` browser request. Walks active/on-hold
 * subscriptions, maps each line-item product to a Membership Tier, finds the matching
 * membership for the subscriber, and links the subscription/order/product meta back to it
 * (and syncs MDP seat counts for per-seat organization tiers). All output is streamed to
 * the screen inside a <pre> block as a human-readable, grouped-per-record log.
 *
 * Runs as a dry-run by default; add `no_debug=1` to write changes. Supported request args:
 * `count_only`, `subscription_to_update`, `page_number`, `page_length`, `created_after`.
 *
 * Renders as a standalone admin-styled page: only the WordPress admin bar and admin UI styles
 * are loaded (not the active theme), with the current user's admin colour scheme. Admin-only.
 *
 * @return void Output is echoed directly; the request is terminated via wicket_sync_page_footer().
 */
function wicket_sync_subscriptions() {
  // Admin-only: this tool reads and (in LIVE mode) writes membership data and renders the
  // logged-in admin bar, so require the same capability as the plugin's other admin tools.
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access the membership subscription sync tool.' );
  }

  // Sanitise the request parameters — they arrive raw from the URL/control bar.
  // A single subscription id is treated as an int; 0/invalid means "batch mode".
  $subscription_to_update = isset($_REQUEST['subscription_to_update']) ? (int) $_REQUEST['subscription_to_update'] : 0;
  $subscription_to_update = $subscription_to_update > 0 ? (string) $subscription_to_update : '';
  $page_number = isset($_REQUEST['page_number']) ? max(1, (int) $_REQUEST['page_number']) : 1;
  $page_length = isset($_REQUEST['page_length']) ? (int) $_REQUEST['page_length'] : 100;
  // Constrain page length to the values offered in the control bar to avoid runaway queries.
  if (!in_array($page_length, [25, 50, 100, 200, 500, 1000], true)) {
    $page_length = 100;
  }
  // Accept a created-after floor only when it is a valid YYYY-MM-DD date; otherwise ignore it.
  $created_after = '';
  if (!empty($_REQUEST['created_after']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_REQUEST['created_after'])) {
    $created_after = $_REQUEST['created_after'];
  }
  // LIVE mode is opt-in only: debug stays true unless the control bar explicitly sends no_debug.
  $debug = empty($_REQUEST['no_debug']) ? true : false;
  $count_only = !empty($_REQUEST['count_only']) ? true : false;

  // Only the control bar's buttons set this marker; a bare page load lacks it. We use it to
  // distinguish "operator clicked Run/Count/Prev/Next" from "just landed on the page", so a
  // plain load shows the form and runs nothing.
  $action_requested = !empty($_REQUEST['wmsync_run']);

  // Lightweight count so the control bar can show position within the total — but only once an
  // action has been requested (the bare landing must run nothing). Uses IDs only (no per-
  // subscription loading); the heavier membership-product breakdown stays behind "Count Only".
  $pagination_total = 0;
  $total_pages = 1;
  if ($action_requested && $subscription_to_update === '' && !$count_only) {
    $count_args = [
      'status' => ['active', 'on-hold'],
      'return' => 'ids',
      'subscriptions_per_page' => -1,
    ];
    if (!empty($created_after)) {
      $count_args['date_created'] = '>=' . $created_after;
    }
    $pagination_total = count(wcs_get_subscriptions($count_args));
    $total_pages = max(1, (int) ceil($pagination_total / $page_length));
    // Clamp a page request that overshoots the available pages back to the last valid page.
    if ($page_number > $total_pages) {
      $page_number = $total_pages;
    }
  }

  // Open the standalone admin-styled page (title + admin bar + WordPress admin colours).
  wicket_sync_page_header();

  // Render the interactive control bar (plain HTML) above the streamed <pre> log.
  wicket_sync_render_control_bar([
    'subscription_to_update' => $subscription_to_update,
    'page_number'            => $page_number,
    'page_length'            => $page_length,
    'created_after'          => $created_after,
    'pagination_total'       => $pagination_total,
    'total_pages'            => $total_pages,
    'action_requested'       => $action_requested,
  ]);

  // Bare page load: show the form to get started and run nothing else.
  if (!$action_requested) {
    wicket_sync_page_footer();
  }

  // Style the log like an admin "card" so it sits naturally on the admin-grey page.
  echo '<pre style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 16px;max-width:900px;margin:0;overflow:auto;">';

  $log = [];

  // Run summary header — describes what this invocation is going to do.
  echo "<strong>Wicket Membership Subscription Sync</strong>\n";
  if (defined('WP_ENVIRONMENT_TYPE')) {
    echo "Environment: " . WP_ENVIRONMENT_TYPE . "\n";
  }
  echo "Mode: " . ($count_only ? "Count only (no changes)" : ($debug ? "DEBUG dry run — no changes will be written (switch to LIVE in the control bar to apply)" : "LIVE — changes will be written")) . "\n";
  if (!empty($subscription_to_update)) {
    echo "Scope: single subscription #{$subscription_to_update}\n";
  } else {
    // Show position within the total so the operator knows where they are in the batch.
    $range_start = $pagination_total > 0 ? (($page_number - 1) * $page_length) + 1 : 0;
    $range_end = min($page_number * $page_length, $pagination_total);
    echo "Scope: page {$page_number} of {$total_pages} — records {$range_start}–{$range_end} of {$pagination_total} ({$page_length} per page)" . (!empty($created_after) ? ", created on/after {$created_after}" : "") . "\n";
  }
  echo str_repeat("=", 60) . "\n";

  //get all subscriptions for page_number by page_length

  if(!empty($count_only)) {
    $argv = [
      'status' => ['active', 'on-hold'],
      'return' => 'ids',
      'subscriptions_per_page'  => '-1',
    ];
    if(!empty($created_after)) {
      $argv['date_created'] = '>=' . $created_after;
    }
    $subscription_ids = wcs_get_subscriptions($argv);

    // Filter subscriptions with products in "Membership products" category
    $membership_subscriptions = [];
    foreach($subscription_ids as $sub_id) {
      $subscription = wcs_get_subscription($sub_id);
      $items = $subscription->get_items();
      foreach($items as $item) {
        $product_id = $item->get_variation_id();
        if(empty($product_id)) {
          $product_id = $item->get_product_id();
        }
        if(has_term('membership', 'product_cat', $product_id)) {
          $membership_subscriptions[] = $sub_id;
          break; // Found a membership product, no need to check other items
        }
      }
    }

    echo "Total active/on-hold subscriptions: " . count($subscription_ids) . "\n";
    echo "Subscriptions containing membership products: " . count($membership_subscriptions) . "\n";
    echo "</pre>";
    wicket_sync_page_footer();
  }
  if(!empty($subscription_to_update)) {
    $subscription = wcs_get_subscription($subscription_to_update);
    $subscriptions = [$subscription];
  } else {
    $argv = [
      'status' => ['active', 'on-hold'],
      'subscriptions_per_page'  => $page_length,
      'offset' => ($page_number - 1) * $page_length,
    ];
    if(!empty($created_after)) {
      $argv['date_created'] = '>=' . $created_after;
    }
    $subscriptions = wcs_get_subscriptions($argv);
  }

  if($debug) {
    //var_dump($argv,$subscription_to_update);
  } else {
    // Only initialise the live MDP API client when we intend to write changes.
    $wicket_api_client = wicket_api_client();
  }

  $cnt = 0;
  // Total drives the "Record X of Y" header so the operator can gauge progress through the batch.
  $total = count($subscriptions);
  foreach ($subscriptions as $sub_post) {
    $sub = wcs_get_subscription( $sub_post->ID );
    $cnt++;

    // Begin a visually distinct block for this subscription record.
    echo "\n" . str_repeat("-", 60) . "\n";
    echo "Record {$cnt} of {$total} — Subscription #{$sub->ID}\n";

    //get user and email from subscription
    $user_id = $sub->get_user_id();
    if(empty($user_id)) {
      $log[] = 'Failed Sub ID:' . $sub->ID . ' | User Missing';
      echo "  Skipped — no user is associated with this subscription.\n";
      continue;
    }
    $user = get_user_by('id', $user_id);
    if (empty($user)) {
      $log[] = 'Failed Sub ID:' . $sub->ID . ' | User Missing';
      echo "  Skipped — user #{$user_id} no longer exists.\n";
      continue;
    }

    $log[] = 'New Sub ID:' . $sub->ID . ' | User ID: ' . $user->ID . ' | User Email: ' . $user->user_email;
    echo "  User #{$user->ID} ({$user->user_email})\n";

    $subscription_items = $sub->get_items();
    foreach($subscription_items as $item_id => $item) {
        $product_name = $item->get_name();

        $product_id = $item->get_variation_id();
        if(empty($product_id)) {
          $product_id = $item->get_product_id();
        }
        //sometimes we change products when migrating to plugin so we need to map the old product to the new one
        //the subscriptions will have the old products attached and the Tiers have the new ones required for lookup
        $mapped_product_id = wicket_get_mapped_product_id_for_tier( $product_id);

        // Item-level header groups all output for this line item under the subscription record.
        // Show the product remap explicitly when the stored product differs from the tier's product.
        $product_label = "“{$product_name}” (product #{$product_id}";
        $product_label .= ($mapped_product_id != $product_id) ? " → mapped to #{$mapped_product_id})" : ")";
        echo "  Item: {$product_label}\n";

        $Tier = \Wicket_Memberships\Membership_Tier::get_tier_by_product_id($mapped_product_id);
        if(empty($Tier)) {
          $product_name = $item->get_name();
          $log[] = 'No mapped tier for new product ('.$product_name.') ID: ' . $mapped_product_id ;
          echo "    <span style=\"color:red\">No Tier maps to product #{$mapped_product_id} — skipping item.</span>\n";
          continue;
        }
        $tier_id = $Tier->get_membership_tier_post_id();
        if(empty($tier_id)) {
          $log[] = 'No tier postID found for new product ID: ' . $mapped_product_id;
          echo "    <span style=\"color:red\">Tier for product #{$mapped_product_id} has no post ID — skipping item.</span>\n";
          continue; //entry not mapped to a tier
        }

        // - find a membership for user_id on tier_id
        $user_memberships = get_posts(array(
          'post_type'      => 'wicket_membership',
          'post_status'    => 'publish',
          'posts_per_page' => -1,
          'meta_query'     => array(
            'relation' => 'AND',
            array(
              'key'     => 'user_id',
              'value'   => $user_id,
              'compare' => '='
            ),
            array(
              'key'     => 'membership_tier_post_id',
              'value'   => $tier_id,
              'compare' => '='
            ),
            // limit to org memberships only on this site
          )
        ));
        //update meta on membership if found
        if (empty($user_memberships)) {
          $log[] = 'No Membership found for User ID ' . $user_id . ' on Tier ID ' . $tier_id . ' for productID: ' . $mapped_product_id;
          echo "    <span style=\"color:red\">No membership found for user #{$user_id} on Tier #{$tier_id} — skipping item.</span>\n";
          continue;
        }
          $log[] = 'Found Membership ( ID: '.$user_memberships[0]->ID.' ) for User ID ' . $user_id . ' on Tier ID ' . $tier_id;
          // Note when more than one membership matches; only the first is linked.
          $match_note = count($user_memberships) > 1 ? " (" . count($user_memberships) . " matched, using first)" : "";
          echo "    Matched membership <a target=\"_blank\" href=\"/wp/wp-admin/post.php?action=edit&post={$user_memberships[0]->ID}\">#{$user_memberships[0]->ID}</a> on Tier #{$tier_id}.{$match_note}\n";

          $meta_check = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true);
          if(!empty($meta_check)) {
            $log[] = 'ITEM Meta _membership_post_id_renew ALREADY SET to '.$meta_check;
            echo "    <span style=\"color:blue\">Renewal link already set (_membership_post_id_renew = {$meta_check}) — skipping item.</span>\n";
            continue;
          } else {
            $log[] = 'ITEM Meta _membership_post_id_renew NOT SET or NOT EQUAL to membership ID '.$user_memberships[0]->ID;
            echo "    <span style=\"color:green;font-weight:bold;\">Renewal link not set — will link to membership #{$user_memberships[0]->ID}.</span>\n";
          }
          //var_dump($user_memberships);
          //var_dump(get_post_meta($user_memberships[0]->ID));exit;
          $log[] = $membership_update_array['membership_product_id'] = $mapped_product_id;
          $log[] = $membership_update_array['membership_subscription_id'] = $sub->ID;
          $log[] = $membership_update_array['membership_parent_order_id'] = $sub->get_parent_id();
          if(empty($debug)) {
            update_post_meta($user_memberships[0]->ID, 'membership_product_id', $mapped_product_id);
            update_post_meta($user_memberships[0]->ID, 'membership_subscription_id', $sub->ID);
            update_post_meta($user_memberships[0]->ID, 'membership_parent_order_id', $sub->get_parent_id());
            echo "    <span style=\"color:green\">Updated membership #{$user_memberships[0]->ID} meta — product #{$mapped_product_id}, subscription #{$sub->ID}, order #{$sub->get_parent_id()}.</span>\n";
            //add the membership post_id to support subscription renewaitem_idl flow (only) in subscription item meta
            if(empty(wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true)) && !empty($user_memberships[0]->ID)) {
              wc_add_order_item_meta( $item_id, '_membership_post_id_renew', $user_memberships[0]->ID, true );
              $log[] = 'ITEM Meta _membership_post_id_renew Set to '.$user_memberships[0]->ID;
              echo "    <span style=\"color:green\">Linked subscription item to membership #{$user_memberships[0]->ID} (_membership_post_id_renew).</span>\n";
            } else {
              $log[] = 'ITEM Meta _membership_post_id_renew ALREADY SET to '.wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true);
              echo "    <span style=\"color:blue\">Renewal link already set to " . wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true ) . ".</span>\n";
            }
          }
          //update membership json from post data
          wicket_update_membership_json_data( $user_memberships[0]->ID, $debug, $membership_update_array);

          //FOR Org Memberships with Per Seat ONLY we sync product quantity to MDP seats
          if($Tier->is_organization_tier() && $Tier->is_per_seat() && (!empty($wicket_api_client) || !empty($debug))) {
            $quantity = $item->get_quantity();
            $log[] = 'Organization Tier - Syncing MDP Seats to Subscription Item with Product: ' . $product_name . ' and Quantity: '.$quantity;
            echo "    Organization tier — syncing {$quantity} seat(s) to MDP from item quantity.\n";
            if(empty($debug)) {
              $wicket_membership_uuid = get_post_meta($user_memberships[0]->ID, 'membership_wicket_uuid', true);
              update_post_meta($user_memberships[0]->ID, 'org_seats', $quantity);
              $updated = memberships_update_seat_count( $wicket_api_client, $wicket_membership_uuid, $quantity);
              if(is_wp_error($updated)) {
                $log[] = 'Error updating MDP Seats via API: ' . $updated->get_error_message();
                echo "      <span style=\"color:red\">Error updating MDP seats via API: " . $updated->get_error_message() . "</span>\n";
              } else {
                $log[] = 'MDP Seats updated to '.$quantity;
                echo "      <span style=\"color:green\">MDP seats updated to {$quantity}.</span>\n";
              }
            }
          }

          #if(empty($debug)) {
            //wicket_wc_log_mship_sync( $log, $page_number . '-' . $page_length );
          #}
    } // end foreach item
  } //end foreach subscription
  echo "</pre>";
  wicket_sync_page_footer();
}

/**
 * Opens the standalone, admin-styled HTML page for the sync tool.
 *
 * The tool replaces the normal theme output (it exits before the template renders), so this
 * prints a minimal document that loads ONLY the WordPress admin bar and core admin UI styles
 * — not the active theme — and adopts the current user's admin colour scheme. Pair every call
 * with wicket_sync_page_footer() to render the admin bar and close the document.
 *
 * @global \WP_Admin_Bar $wp_admin_bar  The admin bar instance, force-initialised if needed.
 * @return void
 */
function wicket_sync_page_header() {
  global $wp_admin_bar;

  // Guarantee the admin bar shows even if the user's profile hides it on the front end, and
  // initialise it directly when the normal (priority 0) template_redirect init was skipped.
  add_filter( 'show_admin_bar', '__return_true' );
  if ( empty( $wp_admin_bar ) && function_exists( '_wp_admin_bar_init' ) ) {
    _wp_admin_bar_init();
  }

  // Queue only the admin-bar + core admin UI styles, plus the user's colour scheme. The
  // theme's wp_enqueue_scripts never fires here, so no theme CSS reaches the page.
  wp_enqueue_style( 'admin-bar' );
  wp_enqueue_style( 'common' );
  wp_enqueue_style( 'buttons' );
  wp_enqueue_style( 'forms' );
  wp_enqueue_style( 'dashicons' );
  wicket_sync_enqueue_admin_color_scheme();

  if ( ! headers_sent() ) {
    header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
  }
  ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Wicket Membership Subscription Sync</title>
<?php wp_print_styles(); ?>
</head>
<?php // admin-bar.css reserves the bar's height via html margin-top; the extra top padding adds breathing room below it. ?>
<body class="wp-core-ui" style="background:#f0f0f1;color:#3c434a;margin:0;padding:40px 20px 20px;">
<?php
}

/**
 * Renders the admin bar, prints its script, closes the page, and terminates the request.
 *
 * @return void  Ends the request via exit.
 */
function wicket_sync_page_footer() {
  if ( function_exists( 'wp_admin_bar_render' ) ) {
    wp_admin_bar_render();
  }
  // Only the admin-bar script is needed (its submenu/keyboard behaviour); deps load with it.
  wp_print_scripts( array( 'admin-bar' ) );
  echo "\n</body>\n</html>";
  exit;
}

/**
 * Enqueues the current user's WordPress admin colour scheme stylesheet, when one applies.
 *
 * Colour schemes are normally registered only inside wp-admin; this loads and registers them
 * so this front-end tool page can match the admin appearance. The default "fresh" scheme has
 * no separate stylesheet (its colours are baked into the base styles), so nothing is added.
 *
 * @global array $_wp_admin_css_colors  Registered admin colour schemes, keyed by slug.
 * @return void
 */
function wicket_sync_enqueue_admin_color_scheme() {
  global $_wp_admin_css_colors;

  // Register the schemes on the front end where wp-admin would normally have done it.
  if ( empty( $_wp_admin_css_colors ) ) {
    if ( ! function_exists( 'register_admin_color_schemes' ) && file_exists( ABSPATH . 'wp-admin/includes/misc.php' ) ) {
      require_once ABSPATH . 'wp-admin/includes/misc.php';
    }
    if ( function_exists( 'register_admin_color_schemes' ) ) {
      register_admin_color_schemes();
    }
  }

  $scheme = get_user_option( 'admin_color' );
  if ( empty( $scheme ) ) {
    $scheme = 'fresh';
  }

  // Only non-default schemes ship a dedicated stylesheet URL; "fresh" relies on the base CSS.
  if ( ! empty( $_wp_admin_css_colors[ $scheme ]->url ) ) {
    wp_register_style( 'wicket-sync-admin-colors', $_wp_admin_css_colors[ $scheme ]->url, array( 'admin-bar', 'common' ), null );
    wp_enqueue_style( 'wicket-sync-admin-colors' );
  }
}

/**
 * Renders the sync tool's interactive control bar above the streamed <pre> log.
 *
 * Emits a GET form that re-submits to the current URL with pagination, date, scope, and
 * run-mode controls, pre-filled from the current request. Safety model:
 *  - The dry/live radio always defaults to "dry" on every load (LIVE is never persisted),
 *    so writes are an explicit, per-run decision.
 *  - Submitting LIVE requires both selecting the LIVE radio AND ticking the confirm box;
 *    this is enforced client-side before the form is allowed to submit no_debug=1.
 *  - Prev, Next, and Count Only always submit as a dry run regardless of the radio, so
 *    navigation and counting can never write data.
 *
 * @param array $state {
 *   Normalised request state and computed pagination figures.
 *
 *   @type string $subscription_to_update  Single subscription id, or '' for batch mode.
 *   @type int    $page_number             Current (clamped) page number.
 *   @type int    $page_length             Records per page.
 *   @type string $created_after           YYYY-MM-DD floor, or '' when unset.
 *   @type int    $pagination_total        Total subscriptions in the paginated set.
 *   @type int    $total_pages             Total pages for the current page length.
 * }
 *
 * @global void   No globals; reads $_SERVER['REQUEST_URI'] for the form action.
 * @return void   Outputs HTML directly.
 */
function wicket_sync_render_control_bar( array $state ) {
  // Re-submit to the same path (query string is rebuilt from the form fields).
  $action = esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) );

  $single  = $state['subscription_to_update'];
  $page    = (int) $state['page_number'];
  $len     = (int) $state['page_length'];
  $created = $state['created_after'];
  $total   = (int) $state['pagination_total'];
  $pages   = (int) $state['total_pages'];
  // False on a bare landing — totals haven't been queried yet, so show a hint not "0 of 0".
  $action_requested = !empty( $state['action_requested'] );

  // 1-based record range shown beside the pager, clamped to the total.
  $range_start = $total > 0 ? ( ( $page - 1 ) * $len ) + 1 : 0;
  $range_end   = min( $page * $len, $total );

  // In single-subscription scope the pager and date are ignored, so dim that section.
  $is_batch       = ( $single === '' );
  $prev_disabled  = ( $page <= 1 )     ? 'disabled' : '';
  $next_disabled  = ( $page >= $pages ) ? 'disabled' : '';
  $batch_opacity  = $is_batch ? '1' : '0.4';

  // Page-length dropdown options, current value pre-selected.
  $len_options = '';
  foreach ( [ 25, 50, 100, 200, 500, 1000 ] as $opt ) {
    $len_options .= '<option value="' . $opt . '" ' . selected( $opt, $len, false ) . '>' . $opt . '</option>';
  }

  $env = defined( 'WP_ENVIRONMENT_TYPE' ) ? esc_html( WP_ENVIRONMENT_TYPE ) : '';
  $created_attr = esc_attr( $created );
  $single_attr  = esc_attr( $single );

  ?>
  <div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;border:1px solid #ccd0d4;background:#fff;padding:14px 16px;margin:0 0 12px;max-width:900px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <strong style="font-size:14px;">Wicket Membership Subscription Sync</strong>
      <?php if ( $env ) : ?><span style="color:#666;font-size:12px;">env: <?php echo $env; ?></span><?php endif; ?>
    </div>

    <form id="wmsync-form" method="get" action="<?php echo $action; ?>">
      <?php // Persist the trigger and the run-mode flags (set by the buttons via JS). ?>
      <input type="hidden" name="mship_subscription_sync" value="1" />
      <input type="hidden" id="wmsync-no_debug" name="no_debug" value="" />
      <input type="hidden" id="wmsync-count_only" name="count_only" value="" />
      <input type="hidden" id="wmsync-page" name="page_number" value="<?php echo $page; ?>" />
      <?php // Set to 1 by every action button; absent on a bare load so the server runs nothing. ?>
      <input type="hidden" id="wmsync-run" name="wmsync_run" value="" />

      <div style="opacity:<?php echo $batch_opacity; ?>;margin-bottom:8px;">
        <label>Created after
          <input type="date" id="wmsync-created" name="created_after" value="<?php echo $created_attr; ?>" />
        </label>
        <a href="#" title="Clear date" style="text-decoration:none;margin-right:14px;"
           onclick="document.getElementById('wmsync-created').value='';return false;">&#10005;</a>
        <label style="margin-left:6px;">Per page
          <?php // Changing page length resets to page 1 so the offset stays meaningful. ?>
          <select name="page_length" onchange="document.getElementById('wmsync-page').value=1;">
            <?php echo $len_options; ?>
          </select>
        </label>
      </div>

      <div style="margin-bottom:8px;">
        <label>Single subscription ID
          <input type="text" name="subscription_to_update" value="<?php echo $single_attr; ?>" size="10" />
        </label>
        <span style="color:#666;font-size:12px;">&larr; overrides pagination &amp; date</span>
      </div>

      <div style="opacity:<?php echo $batch_opacity; ?>;margin:10px 0;display:flex;align-items:center;gap:10px;">
        <button type="button" onclick="wmsyncSubmit('count');">&#128290; Count Only</button>
        <span style="border-left:1px solid #ddd;height:18px;"></span>
        <button type="button" <?php echo $prev_disabled; ?> onclick="wmsyncPage(-1);">&#9664; Prev</button>
        <span style="font-size:13px;"><?php
          // Totals are only known after a run/count; before that, prompt the operator.
          if ( $action_requested ) {
            echo 'Page ' . $page . ' of ' . $pages . ' &middot; records ' . $range_start . '&ndash;' . $range_end . ' of ' . $total;
          } else {
            echo '<em style="color:#666;">Run or Count to load totals</em>';
          }
        ?></span>
        <button type="button" <?php echo $next_disabled; ?> onclick="wmsyncPage(1);">Next &#9654;</button>
      </div>

      <div style="border-top:1px solid #eee;padding-top:10px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <span>Mode:</span>
        <?php // Radio ALWAYS defaults to dry on load — LIVE is never pre-selected from the request. ?>
        <label><input type="radio" name="wmsync_mode" value="dry" checked onchange="wmsyncModeChange();" /> Dry run</label>
        <label><input type="radio" name="wmsync_mode" value="live" onchange="wmsyncModeChange();" /> <span style="color:#b32d2e;font-weight:bold;">LIVE</span></label>
        <label style="color:#b32d2e;"><input type="checkbox" id="wmsync-confirm" disabled /> I understand LIVE writes data</label>
        <button type="button" style="margin-left:auto;font-weight:bold;" onclick="wmsyncSubmit('run');">&#9654; Run</button>
      </div>
    </form>
  </div>

  <script>
  // Set the hidden run-mode flags, then submit. Navigation/counting force a safe dry run;
  // only an explicit "run" honours the dry/live radio, and live additionally requires the
  // confirmation box to be ticked.
  function wmsyncSubmit(mode){
    var f=document.getElementById('wmsync-form');
    var nd=document.getElementById('wmsync-no_debug');
    var co=document.getElementById('wmsync-count_only');
    // Mark this submission as an explicit action so the server processes it (vs. a bare load).
    document.getElementById('wmsync-run').value='1';
    if(mode==='count'){ co.value='1'; nd.value=''; f.submit(); return; }
    co.value='';
    if(mode==='prev'||mode==='next'){ nd.value=''; f.submit(); return; }
    // mode === 'run'
    var live=document.querySelector('input[name=wmsync_mode]:checked').value==='live';
    if(live){
      if(!document.getElementById('wmsync-confirm').checked){
        alert('To run LIVE you must tick “I understand LIVE writes data”.');
        return;
      }
      nd.value='1';
    } else {
      nd.value='';
    }
    f.submit();
  }
  // Adjust the hidden page field then submit as a dry-run navigation.
  function wmsyncPage(delta){
    var p=document.getElementById('wmsync-page');
    var v=(parseInt(p.value,10)||1)+delta;
    if(v<1){ v=1; }
    p.value=v;
    wmsyncSubmit(delta<0?'prev':'next');
  }
  // The confirm box only matters in LIVE mode; disable+clear it otherwise.
  function wmsyncModeChange(){
    var live=document.querySelector('input[name=wmsync_mode]:checked').value==='live';
    var c=document.getElementById('wmsync-confirm');
    c.disabled=!live;
    if(!live){ c.checked=false; }
  }
  </script>
  <?php
}

/**
 * Pushes an organization membership's seat limit (max_assignments) to the MDP.
 *
 * @param  object      $client                 The Wicket API client.
 * @param  string      $wicket_membership_uuid The MDP organization membership UUID.
 * @param  int         $seat_count             Desired seat count; values < 1 mean "unlimited".
 *
 * @return mixed|\WP_Error  The API response, or a WP_Error if the request throws.
 */
function memberships_update_seat_count( $client, $wicket_membership_uuid, $seat_count) {

    // Mirror Membership_Controller: a seat count below 1 means "no limit", which the MDP
    // represents as a null max_assignments rather than 0. Keeps both sync paths consistent.
    $max_assignments = ( (int) $seat_count < 1 ) ? null : (int) $seat_count;

    // build membership payload
    $payload = [
      'data' => [
        'type' => 'organization_memberships',
        'attributes' => [
          'max_assignments' => $max_assignments
        ],
      ]
    ];
    $membership_uuid = trim($wicket_membership_uuid);
    try {
        $response = $client->patch("organization_memberships/$membership_uuid", ['json' => $payload]);
    } catch (Exception $e) {
        $response = new \WP_Error('wicket_api_error', $e->getMessage());
    }
    return $response;
}

function wicket_get_mapped_product_id_for_tier( $product_id ) {
    // Mapping of old product_id to [label, new_product_id]
    $product_map = array(
        //old_product_id => new_product_id
    );
    if (isset($product_map[$product_id])) {
        return $product_map[$product_id];
    }
    return $product_id;
}

/**
 * Rebuilds and stores the membership JSON blob across order, subscription, and user meta.
 *
 * Flattens the membership post meta into a single record, overlays the incoming IDs
 * (product/subscription/order), regenerates the membership JSON, and writes it to the
 * parent order meta, the subscription meta, and the subscriber's user meta. In debug mode
 * it instead dumps the would-be JSON to the screen and writes nothing.
 *
 * @param  int    $post_id             Membership CPT post ID to rebuild the JSON for.
 * @param  bool|int $debug             When truthy, preview only — no meta is written.
 * @param  array  $membership_incoming Incoming overrides (product/subscription/order IDs).
 *
 * @return string|void  The encoded user-meta JSON on a live write; nothing on debug preview.
 */
function wicket_update_membership_json_data( $post_id, $debug = 0, $membership_incoming = []) {

  $membership_meta = get_post_meta( $post_id );
  foreach ($membership_meta as $key => $membership_array) {
    $membership[$key] = array_shift($membership_array);
  }
  if(!empty($membership_incoming) && $debug) {
    $membership = array_merge( $membership, $membership_incoming);
  }

  $user_id = $membership['user_id'];
  $order_id = $membership['membership_parent_order_id'];
  $subscription_id = $membership['membership_subscription_id'];
  $product_id = $membership['membership_product_id'];

  $membership_next_tier_id = $membership['membership_next_tier_id'];
  $membership_next_tier_form_page_id = $membership['membership_next_tier_form_page_id'];

  $membership_json = \Wicket_Memberships\Helper::get_membership_json_from_membership_post_data( $membership );

  if($debug) {
    // Dry-run: capture (don't echo) the JSON that would be written so it can be shown on
    // demand instead of flooding the log; var_dump writes to output, so buffer it.
    ob_start();
    var_dump($membership_json);
    $json_preview = ob_get_clean();

    // Unique id per preview so multiple records on one page toggle independently.
    static $preview_seq = 0;
    $preview_id = 'wmsync-json-' . (++$preview_seq);

    // Render a small clickable icon that toggles the hidden JSON block; collapsed by default
    // to keep the per-record log readable. Inline onclick avoids a separate script per block.
    echo "    Membership JSON preview (not written in debug mode) ";
    echo "<a href=\"#\" title=\"Show/hide JSON\" style=\"text-decoration:none;\" onclick=\"var e=document.getElementById('{$preview_id}');e.style.display=(e.style.display==='none'?'block':'none');return false;\">&#128269;</a>\n";
    echo "<span id=\"{$preview_id}\" style=\"display:none;margin-left:2em;\">" . htmlspecialchars($json_preview) . "</span>";
    return;
  }

  delete_post_meta( $order_id, '_wicket_membership_'.$product_id );
  $order_meta_id = add_post_meta( $order_id, '_wicket_membership_'.$product_id,  $membership_json, 1 );

  delete_post_meta( $subscription_id, '_wicket_membership_'.$product_id );
  $subscription_meta_id = add_post_meta( $subscription_id, '_wicket_membership_'.$product_id,  $membership_json, 1 );

  $user_membership_json = get_user_meta( $user_id,'_wicket_membership_'.$post_id, true);
  if(empty($user_membership_json)) {
    $user_membership_json = $membership_json;
  }
  $user_json_array = json_decode($user_membership_json, true);
  $user_json_array['membership_parent_order_id'] = $order_id;
  $user_json_array['membership_subscription_id'] = $subscription_id;
  $user_json_array['membership_product_id'] = $product_id;

  $user_json_array['membership_next_tier_id'] = $membership_next_tier_id;
  $user_json_array['membership_next_tier_form_page_id'] = $membership_next_tier_form_page_id;

  $user_json = json_encode($user_json_array);

  delete_user_meta( $user_id, '_wicket_membership_'.$post_id );
  $user_meta_id = add_user_meta( $user_id, '_wicket_membership_'.$post_id,  $user_json, 1 );
  return $user_json;
}

 function wicket_wc_log_mship_sync( $data, $append_file_name = '', $level = 'error' ) {
    if(is_array( $data )) {
      $data = wc_print_r( $data, true );
    }
    if( class_exists( '\Wicket' ) ) {
      \Wicket()->log($level, $data, ['source' => 'wicket-membership-sync-'.time().'-'.$append_file_name]);
    } else if (class_exists('WC_Logger')) {
      $logger = new \WC_Logger();
      $logger->log($level, $data, ['source' => 'wicket-membership-sync-'.time().'-'.$append_file_name]);
  }
 }