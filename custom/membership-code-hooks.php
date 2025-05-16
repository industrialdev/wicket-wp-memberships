<?php

/**
 * Use this for any GForm Renewal Flows when not using multi-tier renewals
 * Defaults to adding the membership product tied to the tier slug for the renewal tier
 */
if(empty( $_ENV['WICKET_MSHIP_MULTI_TIER_RENEWALS'] )) {
  add_action( 'gform_after_submission', 'wicket_gform_membership_operations', 10, 2 );
}

/**
 * Receiving renewal links and any late fee products passed through the gform query string
 * If the query string has no `membership_post_id_renew` key-value do not process this hook 
 * This works for all regular renewals using a gravity form flow ( BUT NOT multi-tier renewals )
 * @return void
 */
function wicket_gform_membership_operations() {
  $added_flag = false;

  if(!empty($_GET['membership_post_id_renew'])) {
    $membership_post_id_renew = sanitize_text_field( $_GET['membership_post_id_renew'] );
  } else {
    return;
  }

  if(!empty($_GET['late_fee_product_id'])) {
    $late_fee_product_id = sanitize_text_field( $_GET['late_fee_product_id'] );
  }

  //if the membership_post_id_renew is an array, we are in the multi-tier renewal flow
  if(!empty($membership_post_id_renew) && !is_array($membership_post_id_renew)) {
    $membership_tier_slug = get_post_meta( $membership_post_id_renew, 'membership_tier_slug', true );
    [$parent_product_id, $variation_id] = wicket_get_product_by_tier_reference_with_slug($membership_tier_slug);
    
    if(!empty($parent_product_id)) {
        $cart_item_data = [
        'membership_post_id_renew' =>  $membership_post_id_renew,
      ];

    $found = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $parent_product_id && isset($cart_item['membership_post_id_renew']) && $cart_item['membership_post_id_renew'] == $membership_post_id_renew) {
            $found = true;
            break;
        }
    }
    if (!$found) {    
        WC()->cart->add_to_cart(
          $parent_product_id, 
          1, 
          $variation_id, 
          [], 
          $cart_item_data
        );
      $added_flag = true;
      }
    }
  }
  
  if( !empty( $late_fee_product_id ) ) {
        $found = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $late_fee_product_id ) {
            $found = true;
            break;
        }
    }
    if (!$found) {    
      WC()->cart->add_to_cart(
          $late_fee_product_id,
        1,
      );
    }
  }
  if ($added_flag) {
    wp_safe_redirect(wc_get_cart_url());
    exit;
  }
}
  
/**
 * Get Product with Tier Reference from Tier Slug 
 * Currently called from the Account Centre as well when constructing the renewal links
 * @param string $membership_tier_slug
 * @return array
 */
function wicket_get_product_by_tier_reference_with_slug($membership_tier_slug) {

       $products = wc_get_products([
        'limit' => -1,
        'status' => 'publish',
        'type' => ['subscription', 'variable-subscription'],
        'meta_key' => 'tier_reference',
        'meta_value' => $membership_tier_slug,
      ]);

      foreach ($products as $product) {
        if( $product->get_type() == 'variable-subscription' ) {
          $variations = $product->get_children(); 
          foreach ( $variations as $variations_id ) {
              $tier_reference = get_post_meta( $variations_id, 'tier_reference', true );
              if($tier_reference == $membership_tier_slug) {
                break;
              }
          }
          $parent_product_id = $product->get_id();
          $variation_id = $variations_id;
        } else {
          $tier_reference = get_post_meta( $product->get_id(), 'tier_reference', true );
          if($tier_reference == $membership_tier_slug) {
            $parent_product_id = $product->get_id();
            $variation_id = '';
          }
        }
        if(!empty($parent_product_id)) {
          break;
        }
      }
      return [$parent_product_id,$variation_id];
    }

add_action('template_redirect', 'wicket_membership_maybe_add_renewals_to_cart');
/**
 * When we land in the cart with a query string containing the membership_post_id_renew as array
 * This is used for the multi-tier renewal flow and originating in the account center renewal links
 * This will add the membership products to the cart (keys) with the membership_post_id_renew meta (value) set
 * @return void
 */
function wicket_membership_maybe_add_renewals_to_cart() {
    if (is_cart() && isset($_GET['membership_post_id_renew']) && is_array($_GET['membership_post_id_renew'])) {
        $added_flag = false;
        $variation_id = '';

        if(!empty($_GET['late_fee_product_id'])) {
          $late_fee_product_id = sanitize_text_field( $_GET['late_fee_product_id'] );
        }

        foreach ($_GET['membership_post_id_renew'] as $product_id => $meta_value) {
            $parent_product_id = absint($product_id);
            $product = wc_get_product($parent_product_id);
            if ($product->is_type('variation')) {
                $parent_product_id = $product->get_parent_id();
                $variation_id = $product_id;
            }
            $meta_value = sanitize_text_field($meta_value);

            $found = false;
            foreach (WC()->cart->get_cart() as $cart_item) {
                if ($cart_item['product_id'] == $parent_product_id && isset($cart_item['membership_post_id_renew']) && $cart_item['membership_post_id_renew'] == $meta_value) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
              $cart_item_data = [
                'membership_post_id_renew' =>  $meta_value,
              ];

              WC()->cart->add_to_cart(
                $parent_product_id, 
                1, 
                $variation_id, 
                [], 
                $cart_item_data
              );
              $added_flag = true;
            }
          }
          if( !empty( $late_fee_product_id ) ) {
            WC()->cart->add_to_cart(
                $late_fee_product_id,
              1,
            );
          }

        if ($added_flag) {
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
    }
}

/******************************************************************
 * For adding tier_reference meta to the product pages
 ******************************************************************/
/**
 * Add the Tier reference tab to the products with category = membership
 */
add_filter('woocommerce_product_data_tabs', 'add_tier_reference_product_data_tab');

function add_tier_reference_product_data_tab($tabs) {
  global $post;

    if (!$post || $post->post_type !== 'product') {
        return $tabs;
    }
    $product_categories = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'slugs']);
    $target_categories = ['membership'];
    $has_category = array_intersect($target_categories, $product_categories);
    if (!empty($has_category)) {
      $tabs['tier_reference'] = [
          'label'    => __('Tier Reference', 'woocommerce'),
          'target'   => 'tier_reference_product_data',
          'class'    => ['show_if_simple', 'show_if_variable'], // or any product type
          'priority' => 80,
      ];
    }
    return $tabs;
}

/**
 * Add a custom field on the variation to connect it with the tier for renewals
 */
add_action('woocommerce_product_after_variable_attributes', 'wicket_mship_custom_variation_fields', 10, 3);
function wicket_mship_custom_variation_fields($loop, $variation_data, $variation) {
    $value = get_post_meta($variation->ID, 'tier_reference', true);
    ?>
    <tr>
        <td colspan="2">
            <label>Tier Reference</label>
            <input type="text"
                   name="tier_reference[<?php echo $loop; ?>]"
                   value="<?php echo esc_attr($value); ?>"
                   style="width: 100%;">
        </td>
    </tr>
    <?php
}

/**
 * Save the cutom field on the variation to connect it with the tier for renewals
 */
add_action('woocommerce_save_product_variation', 'wicket_mship_save_custom_variation_fields', 10, 2);
function wicket_mship_save_custom_variation_fields($variation_id, $i) {
  if (isset($_POST['tier_reference'][$i]) && !empty($_POST['tier_reference'][$i])) {
    update_post_meta($variation_id, 'tier_reference', sanitize_text_field($_POST['tier_reference'][$i]));
  }
}

/**
 * Tier reference tab for parent products
 */
add_action('woocommerce_product_data_panels', 'wicket_mship_add_tier_reference_product_data_fields');
function wicket_mship_add_tier_reference_product_data_fields() {
    global $post;
    ?>
    <div id="tier_reference_product_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <?php
            woocommerce_wp_text_input([
                'id'          => 'tier_reference',
                'label'       => __('Tier Reference', 'woocommerce'),
                'desc_tip'    => true,
                'description' => __('An internal identifier used for tier matching.', 'woocommerce'),
            ]);
            ?>
        </div>
    </div>
    <?php
}

/**
 * Save tier reference on parent product
 */
add_action('woocommerce_process_product_meta', 'wicket_mship_save_tier_reference_field');
function wicket_mship_save_tier_reference_field($post_id) {
    if (isset($_POST['tier_reference'])) {
      update_post_meta($post_id, 'tier_reference', sanitize_text_field($_POST['tier_reference']));
    }
}

