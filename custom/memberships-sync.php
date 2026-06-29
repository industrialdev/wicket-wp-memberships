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

//to make sure and run the sync on the init hook
add_action( 'init', 'wicket_action_woocommerce_loaded', 10, 1 );

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
 * @return void Output is echoed directly; the request is terminated with die().
 */
function wicket_sync_subscriptions() {
  echo "<pre>";

  // Read and normalise the run parameters before emitting the summary so the operator
  // can confirm scope/mode at the top of the output before scanning per-record results.
  $subscription_to_update = !empty($_REQUEST['subscription_to_update']) ? $_REQUEST['subscription_to_update'] : '';
  $page_number = !empty($_REQUEST['page_number']) ? $_REQUEST['page_number'] : 1;
  $page_length = !empty($_REQUEST['page_length']) ? $_REQUEST['page_length'] : 100;
  $created_after = !empty($_REQUEST['created_after']) ? $_REQUEST['created_after'] : ''; //format YYYY-MM-DD
  $debug = empty($_REQUEST['no_debug']) ? true : false;
  $count_only = !empty($_REQUEST['count_only']) ? true : false;

  $log = [];

  // Run summary header — describes what this invocation is going to do.
  echo "<strong>Wicket Membership Subscription Sync</strong>\n";
  if (defined('WP_ENVIRONMENT_TYPE')) {
    echo "Environment: " . WP_ENVIRONMENT_TYPE . "\n";
  }
  echo "Mode: " . ($count_only ? "Count only (no changes)" : ($debug ? "DEBUG dry run — no changes will be written (add no_debug=1 to apply)" : "LIVE — changes will be written")) . "\n";
  if (!empty($subscription_to_update)) {
    echo "Scope: single subscription #{$subscription_to_update}\n";
  } else {
    echo "Scope: page {$page_number} ({$page_length} per page)" . (!empty($created_after) ? ", created on/after {$created_after}" : "") . "\n";
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
    die();
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
  die();
}

function memberships_update_seat_count( $client, $wicket_membership_uuid, $seat_count) {

    // build membership payload
    $payload = [
      'data' => [
        'type' => 'organization_memberships',
        'attributes' => [
          'max_assignments' => $seat_count
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