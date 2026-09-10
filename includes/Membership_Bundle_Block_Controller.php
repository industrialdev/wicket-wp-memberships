<?php

namespace Wicket_Memberships;

/**
 * Registers the membership-bundles-list Gutenberg block.
 *
 * Plain (non-ACF) dynamic block: block.json declares a "render" file, so
 * WordPress calls includes/blocks/membership-bundles-list/render.php directly
 * with no editor script and no attributes to manage. Kept intentionally
 * separate from the REST/admin controllers in this plugin since block
 * registration is a distinct concern (init-time, editor-facing) from REST
 * routing or admin CRUD.
 *
 * @package Wicket_Memberships
 */
class Membership_Bundle_Block_Controller {

  public function __construct() {
    add_action( 'init', [ $this, 'register_block' ] );
  }

  /**
   * Register the membership-bundles-list block from its block.json.
   */
  public function register_block(): void {
    register_block_type( __DIR__ . '/blocks/membership-bundles-list' );
  }

  /**
   * Determine whether Alpine.js is available on the current page.
   *
   * Mirrors wicket-wp-base-plugin's Assets::enqueue_plugin_scripts() logic:
   * that class enqueues its own Alpine build (handle
   * 'wicket-plugin-alpine-script') only on non-Wicket themes, and assumes the
   * Wicket theme ships its own Alpine instance otherwise. Neither path can be
   * taken for granted here (base-plugin version drift, deactivated plugin, a
   * theme that doesn't actually bundle Alpine), so callers should treat a
   * `false` result as "do not render Alpine-dependent markup."
   *
   * @return bool
   */
  public static function is_alpine_available(): bool {

    if ( wp_script_is( 'wicket-plugin-alpine-script', 'registered' ) || wp_script_is( 'wicket-plugin-alpine-script', 'enqueued' ) ) {
      return true;
    }

    if ( function_exists( 'is_wicket_theme_active' ) && is_wicket_theme_active() ) {
      return true;
    }

    return false;
  }
}
