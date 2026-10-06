<?php

namespace Wicket_Memberships;

/**
 * Combines identical line items added to a bundle renewal order into one line with a
 * quantity, and records the consolidation in an order note.
 *
 * Membership_Bundle_Renewal_Order_Controller::apply_bundle_renewal_line_item_price_filter()
 * decides whether to run this (Line Item Consolidation setting + filter) and which lines
 * are eligible (those added by wicket_mship_bundle_renewal_line_item_price callbacks).
 *
 * @package Wicket_Memberships
 */
class Membership_Bundle_Line_Item_Consolidator {

  /**
   * Combine identical lines that wicket_mship_bundle_renewal_line_item_price callbacks added
   * (e.g. the same late fee for several members) into one line with a quantity, and record
   * it in an order note. Member lines and lines copied from the subscription are never
   * touched — only IDs in $added_line_members are considered.
   *
   * The caller must run calculate_totals() afterwards: it recalculates taxes and its save
   * deletes the removed duplicates.
   *
   * @param array<int|string, int[]> $added_line_members Added line item ID => membership post IDs whose callback added it.
   * @param bool                     $forced_by_filter   True when the code filter enabled this despite the setting being off.
   */
  public static function consolidate( \WC_Order $renewal_order, array $added_line_members, bool $forced_by_filter ): void {
    $groups = [];
    foreach ( $renewal_order->get_items() as $item_id => $item ) {
      // Unsaved lines (add_item() without save) have no ID yet, so they can't be removed
      // or attributed — leave them as they are.
      if ( ! isset( $added_line_members[ $item_id ] ) || ! $item instanceof \WC_Order_Item_Product || ! $item->get_id() || $item->get_quantity() <= 0 ) {
        continue;
      }

      $signature = self::line_item_signature( $item );
      if ( $signature !== null ) {
        $groups[ $signature ][] = $item;
      }
    }

    $combined_lines = 0;
    $summary        = [];

    foreach ( $groups as $items ) {
      if ( count( $items ) < 2 ) {
        continue;
      }

      $keeper          = array_shift( $items );
      $quantity        = $keeper->get_quantity();
      $subtotal        = (float) $keeper->get_subtotal();
      $total           = (float) $keeper->get_total();
      $member_post_ids = $added_line_members[ $keeper->get_id() ];

      foreach ( $items as $duplicate ) {
        $quantity       += $duplicate->get_quantity();
        $subtotal       += (float) $duplicate->get_subtotal();
        $total          += (float) $duplicate->get_total();
        $member_post_ids = array_merge( $member_post_ids, $added_line_members[ $duplicate->get_id() ] );
        $renewal_order->remove_item( $duplicate->get_id() );
      }

      $keeper->set_quantity( $quantity );
      $keeper->set_subtotal( wc_format_decimal( $subtotal ) );
      $keeper->set_total( wc_format_decimal( $total ) );
      // Keeps the per-member audit trail the separate lines carried implicitly.
      $keeper->update_meta_data( '_wicket_bundle_renewal_member_post_ids', array_values( array_unique( $member_post_ids ) ) );
      $keeper->save();

      $combined_lines += count( $items ) + 1;
      $summary[]       = $keeper->get_name() . ' × ' . $quantity;
    }

    if ( empty( $summary ) ) {
      return;
    }

    $renewal_order->add_order_note(
      '<strong>' . esc_html__( 'Membership Bundles: line item consolidation', 'wicket-memberships' ) . '</strong><br>'
      . esc_html( sprintf(
        /* translators: 1: number of lines combined, 2: number of lines they became */
        _n( 'Combined %1$d identical lines into %2$d line:', 'Combined %1$d identical lines into %2$d lines:', count( $summary ), 'wicket-memberships' ),
        $combined_lines,
        count( $summary )
      ) )
      . '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $summary ) ) . '</li></ul>'
      . ( $forced_by_filter ? esc_html__( '(enabled by code filter)', 'wicket-memberships' ) : '' )
    );
  }

  /**
   * What makes two added lines identical: product, variation, name, tax class, unit price
   * (before and after discounts), and every item meta key/value.
   *
   * @return string|null Null when the line can't be fingerprinted reliably (meta that won't
   *                     encode), so it is never merged.
   */
  private static function line_item_signature( \WC_Order_Item_Product $item ): ?string {
    $quantity = $item->get_quantity();

    $meta = [];
    foreach ( $item->get_meta_data() as $meta_item ) {
      $encoded = wp_json_encode( [ $meta_item->key, $meta_item->value ] );
      if ( false === $encoded ) {
        return null;
      }
      $meta[] = $encoded;
    }
    sort( $meta );

    $signature = wp_json_encode( [
      $item->get_product_id(),
      $item->get_variation_id(),
      $item->get_name(),
      $item->get_tax_class(),
      wc_format_decimal( (float) $item->get_subtotal() / $quantity ),
      wc_format_decimal( (float) $item->get_total() / $quantity ),
      $meta,
    ] );

    return false === $signature ? null : md5( $signature );
  }
}
