<?php

/************************************************************************************
 * This file contains all the code required to use the optional Tier Checkbox Field
 * in the Gravity Form Multi-Tier Renewal Flow. It will expose all the tiers in the 
 * configured Membership Plugin while pre-selecting only those tiers being renewed.
 * 
 * When the form is submitted it will add all the products to the cart necessary to
 * create memberships in each of the selected tiers. It will create both new tier 
 * memberships as well as renewal memberships when accedd from the multi-tier callout.
 ************************************************************************************/

/**
 * Allow testing form redirects with localhost
 */
add_filter('allowed_redirect_hosts', 'allow_localhost_redirect');

function allow_localhost_redirect($hosts) {
    $hosts[] = 'localhost';
    return $hosts;
}

add_filter('gform_pre_render', 'wicket_multi_tier_renewal_checkbox_option_field');
add_filter('gform_pre_validation', 'wicket_multi_tier_renewal_checkbox_option_field');
add_filter('gform_pre_submission_filter', 'wicket_multi_tier_renewal_checkbox_option_field');
add_filter('gform_admin_pre_render', 'wicket_multi_tier_renewal_checkbox_option_field');

/**
 * All Tiers selectable as Checkbox Fields ( set field parameter name = multi_tier_renewal )
 * Membership Renewal Tier Post IDs being passed in to get Existing Tiers pre-selected
 * Membership Tier Post IDs passed as array keys / membership post id passed as value
 * @param mixed $form
 */
function wicket_multi_tier_renewal_checkbox_option_field($form) {
    $target_input_name = 'multi_tier_renewal';
    $choices = [];

    foreach ($form['fields'] as &$field) {
        if ($field->type !== 'checkbox') {
            continue;
        }
        
        if (isset($field->inputName) && $field->inputName === $target_input_name) {
          //what renewal arrays of have we received - the keys are the tier post_id - the value are the membership post_id being renewed
          $multi_tier_renewal = isset($_GET['multi_tier_renewal']) && is_array($_GET['multi_tier_renewal'])
            ? array_map('sanitize_text_field', $_GET['multi_tier_renewal'])
            : [];

            $membership_tier_renewal_post_ids = array_keys($multi_tier_renewal);

            if(empty($membership_tier_renewal_post_ids)) {
              return $form;
            }

            //get all the tiers configured from the membership plugin
            $tiers = get_posts([
              'post_type'      => 'wicket_mship_tier',
              'posts_per_page' => -1,
              'post_status'    => 'publish',
              'orderby'        => 'title',
              'order'          => 'ASC',
            ]);
  
            //create the checkbox options
            foreach($tiers as $tier) {
              $tier_slug = get_post_meta( $tier->ID, 'membership_tier_slug', true);
              $products = wc_get_products([
                'limit' => -1,
                'status' => 'publish',
                'type' => ['subscription', 'variable-subscription'],
                'meta_key' => 'tier_reference',
                'meta_value' => $tier_slug,
              ]);
              foreach($products as $product) {
                $tier_reference = get_post_meta( $product->get_id(), 'tier_reference', true );
                //this is checking that there is a product with the tier_slug set as the tier_reference meta
                //this needs to be set so we know which product we want to put in the cart
                //it is possible a tier can have multiple products attached so we cannot just use that
                if($tier_reference == $tier_slug) {
                  $choices[] = ['text' =>  $tier->post_title, 'value' => $tier->ID];
                }  
              }
            }

            //create the checkboxes - select the ones for tiers being renewed 
            $field->choices = [];
            foreach ($choices as $choice) {
                $field->choices[] = [
                    'text' => $choice['text'],
                    'value' => $choice['value'],
                    'isSelected' => in_array($choice['value'], $membership_tier_renewal_post_ids),
                ];
            }
        }
    }

    return $form;
}

add_action('gform_after_submission', 'wicket_add_multi_tier_renewal_products_to_cart', 10, 2);

/**
 * Add multiple tiers selected to cart and configure for renewal and new memberships
 * This works with the custom checkbox field in the Gravity Forms to manage the renewal tiers
 * @param mixed $entry
 * @param mixed $form
 * @return void
 */
function wicket_add_multi_tier_renewal_products_to_cart( $entry, $form ) {
  $target_input_name = 'multi_tier_renewal';
    foreach ($form['fields'] as $field) {
        if ($field->type === 'checkbox' && isset($field->inputName) && $field->inputName === $target_input_name) {
          // get all the tiers selected for the renewal flow
          foreach ($entry as $key => $value) {
              if (strpos($key, $field->id . '.') === 0 && !empty($value)) {
                  $tiers_renewed[] = $value;
              }
          }        
          break;
        }
    }

    if (empty($tiers_renewed)) {
        return;
    }

    if(!empty($_GET['late_fee_product_id'])) {
      $late_fee_product_id = sanitize_text_field( $_GET['late_fee_product_id'] );
    }

    //get all the existing memberships being renewed off the query string
    $multi_tier_renewal = isset($_GET['multi_tier_renewal']) && is_array($_GET['multi_tier_renewal'])
    ? array_map('sanitize_text_field', $_GET['multi_tier_renewal'])
    : [];

    //get the tier posts 
    $post_ids = array_map('intval', $tiers_renewed);
    $tiers = get_posts([
        'post_type' => 'wicket_mship_tier',
        'post__in'  => $post_ids,
        'numberposts' => -1,
    ]);

    foreach ($tiers as $tier) {
      $cart_item_data = [];

      //get the tier slugs matching the Product ACF: Tier Reference Slug to get the membership product
      $tier_slug = get_post_meta( $tier->ID, 'membership_tier_slug', true);
      $products = wc_get_products([
        'limit' => -1,
        'status' => 'publish',
        'type' => ['subscription', 'variable-subscription'],
        'meta_key' => 'tier_reference',
        'meta_value' => $tier_slug,
      ]);

      //set the membership post id on the product if renewing tier
      if(!empty($multi_tier_renewal[ $tier->ID ])) {
        $cart_item_data = [
          'membership_post_id_renew' => $multi_tier_renewal[  $tier->ID ],
        ];
      }

          //add product to the cart
          //the search returns the parent product ids for all the products found with tier_reference set
          //if it is a variable product look for the one with the tier_reference value set on it as well
          foreach ($products as $product) {
          if( $product->get_type() == 'variable-subscription' ) {
            $variations = $product->get_children(); 
            foreach ( $variations as $variations_id ) {
                $tier_reference = get_post_meta( $variations_id, 'tier_reference', true );
                if($tier_reference == $tier_slug) {
                  break;
                }
            }
            $parent_product_id = $product->get_id();
            $variation_id = $variations_id;
          } else {
            $parent_product_id = $product->get_id();
            $variation_id = '';
          }

          //echo '<pre>'; var_dump([$product->get_type(),$parent_product_id, $variation_id]);
          $found = false;
          foreach (WC()->cart->get_cart() as $cart_item) {
              if ($cart_item['product_id'] == $parent_product_id && isset($cart_item['membership_post_id_renew']) && $cart_item['membership_post_id_renew'] == $multi_tier_renewal[  $tier->ID ]) {
                  $found = true;
                  break;
              }
          }
          //if we have not found the product in the cart already, add it
          if (!$found) {    
            WC()->cart->add_to_cart(
              $parent_product_id, 
              1, 
              $variation_id, 
              [], 
              $cart_item_data
            );
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

