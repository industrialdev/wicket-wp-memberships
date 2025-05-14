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
      
          //add the tier products to the cart
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

/**
 * Add a custom field on the variation to connect it with the tier for renewals
 */
add_action('woocommerce_product_after_variable_attributes', 'custom_variation_fields', 10, 3);
function custom_variation_fields($loop, $variation_data, $variation) {
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
add_action('woocommerce_save_product_variation', 'save_custom_variation_fields', 10, 2);
function save_custom_variation_fields($variation_id, $i) {
    if (isset($_POST['tier_reference'][$i])) {
        update_post_meta($variation_id, 'tier_reference', sanitize_text_field($_POST['tier_reference'][$i]));
    }
}
