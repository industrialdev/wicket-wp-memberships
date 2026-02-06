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

function wicket_sync_subscriptions() {
  echo "<pre>";
  //for product mapping
  if (defined('WP_ENVIRONMENT_TYPE')) {
    $wp_env_type = WP_ENVIRONMENT_TYPE;
    echo $wp_env_type."\n<BR>";
  }
  $subscription_to_update = !empty($_REQUEST['subscription_to_update']) ? $_REQUEST['subscription_to_update'] : '';
  $page_number = !empty($_REQUEST['page_number']) ? $_REQUEST['page_number'] : 1;
  $page_length = !empty($_REQUEST['page_length']) ? $_REQUEST['page_length'] : 100;
  $created_after = !empty($_REQUEST['created_after']) ? $_REQUEST['created_after'] : ''; //format YYYY-MM-DD
  $debug = empty($_REQUEST['no_debug']) ? true : false;
  $count_only = !empty($_REQUEST['count_only']) ? true : false;

  $log = [];

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
    
    echo 'Total Active Subscriptions: '.count($subscription_ids)."\n<BR>";
    echo 'Subscriptions with Membership Products: '.count($membership_subscriptions)."\n<BR>";
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
    echo 'DEBUG'."\n<BR>";;
    //var_dump($argv,$subscription_to_update);
  } else {
    $wicket_api_client = wicket_api_client();
  }

  $cnt=0;
  foreach ($subscriptions as $sub_post) {
    $sub = wcs_get_subscription( $sub_post->ID );
      $cnt++;
      echo " $cnt: <br>";

    //get user and email from subscription
    $user_id = $sub->get_user_id();
    if(empty($user_id)) {
      $log[] = 'Failed Sub ID:' . $sub->ID . ' | User Missing';
      echo 'Failed Sub ID:' . $sub->ID . ' | User Missing' . "\n<br>";
      continue;
    }
    $user = get_user_by('id', $user_id);
    if (empty($user)) { 
      $log[] = 'Failed Sub ID:' . $sub->ID . ' | User Missing';
      echo 'Failed Sub ID:' . $sub->ID . ' | User Missing' . "\n<br>";
      continue;
    }

    $log[] = 'New Sub ID:' . $sub->ID . ' | User ID: ' . $user->ID . ' | User Email: ' . $user->user_email;
    echo 'New Sub ID:' . $sub->ID . ' | User ID: ' . $user->ID . ' | User Email: ' . $user->user_email . "\n<br>";

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

        $Tier = \Wicket_Memberships\Membership_Tier::get_tier_by_product_id($mapped_product_id);
        if(empty($Tier)) {
          $product_name = $item->get_name();
          $log[] = 'No mapped tier for new product ('.$product_name.') ID: ' . $mapped_product_id ;
          echo '<span style="color:red">No mapped tier ID for new product ('.$product_name.') ID:</span> ' . $mapped_product_id."\n<br>";
          continue;
        }
        $tier_id = $Tier->get_membership_tier_post_id();
        if(empty($tier_id)) {
          $log[] = 'No tier postID found for new product ID: ' . $mapped_product_id;
          echo 'No tier postID found for new product ID: ' . $mapped_product_id . "\n<br>";
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
          echo '<span style="color:red">No Membership found for User ID ' . $user_id . ' on Tier ID ' . $tier_id  . ' for productID: ' . $mapped_product_id . " with Product Name: <u>" . $product_name . "</u>" .  "</span>\n<br>";
          continue;
        }
          $log[] = 'Found Membership ( ID: '.$user_memberships[0]->ID.' ) for User ID ' . $user_id . ' on Tier ID ' . $tier_id; 
          echo 'Found '.count($user_memberships). 'Membership <a target="_blank" href="/wp/wp-admin/post.php?action=edit&post='.$user_memberships[0]->ID.'"> '.$user_memberships[0]->ID.'</a>for User ID ' . $user_id . ' on Tier ID ' . $tier_id . "with Product Name: <u>" . $product_name . "</u> (ID: " . $mapped_product_id . ")\n";
          
          $meta_check = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true);
          if(!empty($meta_check)) {
            $log[] = 'ITEM Meta _membership_post_id_renew ALREADY SET to '.$meta_check;
            echo '<span style="color:blue">ITEM Meta _membership_post_id_renew ALREADY SET to '.$meta_check."</span>\n<br>";
            continue;
          } else {
            $log[] = 'ITEM Meta _membership_post_id_renew NOT SET or NOT EQUAL to membership ID '.$user_memberships[0]->ID;
            echo '<span style="color:green;font-weight:bold;">ITEM Meta _membership_post_id_renew NOT SET or NOT EQUAL to membership ID '.$user_memberships[0]->ID."</span>\n<br>";
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
            //add the membership post_id to support subscription renewaitem_idl flow (only) in subscription item meta
            if(empty(wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true)) && !empty($user_memberships[0]->ID)) {
              wc_add_order_item_meta( $item_id, '_membership_post_id_renew', $user_memberships[0]->ID, true );
              $log[] = 'ITEM Meta _membership_post_id_renew Set to '.$user_memberships[0]->ID;
              echo 'ITEM Meta _membership_post_id_renew Set to '.$user_memberships[0]->ID."\n<br>";
            } else {
              $log[] = 'ITEM Meta _membership_post_id_renew ALREADY SET to '.wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true);
              echo 'ITEM Meta _membership_post_id_renew ALREADY SET to '.wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true)."\n<br>";
            }
          }
          //update membership json from post data
          wicket_update_membership_json_data( $user_memberships[0]->ID, $debug, $membership_update_array);          
          
          //FOR Org Memberships with Per Seat ONLY we sync product quantity to MDP seats
          if($Tier->is_organization_tier() && $Tier->is_per_seat() && (!empty($wicket_api_client) || !empty($debug))) {
            $quantity = $item->get_quantity();
            $log[] = 'Organization Tier - Syncing MDP Seats to Subscription Item with Product: ' . $product_name . ' and Quantity: '.$quantity;
            echo 'Organization Tier - Syncing MDP Seats to Subscription Item with Product: ' . $product_name . ' and Quantity: '.$quantity."\n<br>";
            if(empty($debug)) {
              $wicket_membership_uuid = get_post_meta($user_memberships[0]->ID, 'membership_wicket_uuid', true);
              update_post_meta($user_memberships[0]->ID, 'org_seats', $quantity);
              $updated = memberships_update_seat_count( $wicket_api_client, $wicket_membership_uuid, $quantity);
              if(is_wp_error($updated)) {
                $log[] = 'Error updating MDP Seats via API: ' . $updated->get_error_message();
                echo '<span style="color:red">Error updating MDP Seats via API: ' . $updated->get_error_message() . '</span>'."\n<br>";
              } else {
                $log[] = 'MDP Seats updated to '.$quantity;
                echo 'MDP Seats updated to '.$quantity."\n<br>";
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
    echo 'JSON Updated:<br><pre>';
    var_dump($membership_json);
    echo '</pre><br>';
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
    if (class_exists('WC_Logger')) {
      $logger = new \WC_Logger();
      if(is_array( $data )) {
        $data = wc_print_r( $data, true );
      }
      $logger->log($level, $data, ['source' => 'wicket-membership-sync-'.time().'-'.$append_file_name]);
    }
  }