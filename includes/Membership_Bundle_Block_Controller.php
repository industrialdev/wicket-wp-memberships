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

  /**
   * Nav menu item IDs (or CSS IDs assigned to the menu item's markup) that the
   * "requires attention" badge is rendered against. Covers the desktop and
   * mobile account-menu placements, each duplicated for the logged-in-member
   * "two" variant used elsewhere in the theme.
   */
  private const NAV_BADGE_MENU_IDS = [
    'wicket-acc-menu',
    'wicket-acc-menu-mobile',
    'wicket-acc-menu-two',
    'wicket-acc-menu-mobile-two',
  ];

  public function __construct() {
    add_action( 'init', [ $this, 'register_block' ] );
    add_action( 'wp_head', [ $this, 'render_nav_badge_style' ] );
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

  /**
   * Count the current member's owned membership bundles that require
   * attention: bundles sitting in grace-period ("Renew Memberships") or
   * expired ("Lapsed - Renew Memberships") status, since both of those
   * statuses mean the member needs to take a renewal action.
   *
   * Reuses Membership_Bundle_Admin_Controller::get_membership_bundles_list()
   * (owner-scoped via $owner_user_id, same query/dedup path the member-facing
   * "mine" REST endpoint already relies on) instead of a bespoke WP_Query, so
   * this stays in sync with however that method defines "owned by" and
   * "matches this status" going forward. Called once per status and summed,
   * since that method only supports a single equality status filter, not an
   * IN comparison across both at once. $posts_per_page is 1 since only the
   * returned 'count' (pre-pagination total) is used.
   *
   * @return int
   */
  public static function get_bundles_requiring_attention_count(): int {
    if ( ! is_user_logged_in() ) {
      return 0;
    }

    $user_id = get_current_user_id();
    $count   = 0;

    foreach ( [ Wicket_Memberships::STATUS_GRACE, Wicket_Memberships::STATUS_EXPIRED ] as $status ) {
      $list = Membership_Bundle_Admin_Controller::get_membership_bundles_list(
        1,
        1,
        $status,
        '',
        [],
        null,
        null,
        $user_id
      );
      $count += (int) ( $list['count'] ?? 0 );
    }

    return $count;
  }

  /**
   * Determine the href substring(s) identifying the account-menu link the
   * "requires attention" badge attaches to.
   *
   * Derived from the actual slug of the configured "Manage Membership
   * Bundles" page (Settings > Wicket Memberships > Membership Bundles),
   * since that's the real URL the account nav links to. Returns an empty
   * array when no page is configured yet, or the configured page no longer
   * exists/is unpublished — callers should treat that as "nothing to match"
   * and render no badge.
   *
   * @return string[]
   */
  private static function get_nav_badge_href_fragments(): array {
    $page_id = Helper::get_membership_bundles_manage_page_id();

    if ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) {
      $slug = get_post_field( 'post_name', $page_id );
      if ( ! empty( $slug ) ) {
        return [ $slug ];
      }
    }

    return [];
  }

  /**
   * Echo a <style> block with a ::after badge on the membership-bundles /
   * membership-groups link inside each account nav menu ID variant, showing
   * the current member's "requires attention" bundle count. Hooked to
   * wp_head. Renders nothing when the count is zero so no empty/zero badge
   * is ever shown.
   *
   * Targets the specific `<a>` (matched by href substring), not the menu
   * container itself, so the badge only appears on the membership-bundles
   * link and not on every item in the account menu.
   *
   * @return void
   */
  public function render_nav_badge_style(): void {
    $count = self::get_bundles_requiring_attention_count();

    if ( $count <= 0 ) {
      return;
    }

    $href_fragments = self::get_nav_badge_href_fragments();

    // Nothing to attach the badge to until a "Manage Membership Bundles" page is configured.
    if ( empty( $href_fragments ) ) {
      return;
    }

    $badge_text = (string) $count;

    $selectors = [];
    foreach ( self::NAV_BADGE_MENU_IDS as $menu_id ) {
      foreach ( $href_fragments as $href_fragment ) {
        $selectors[] = '#' . $menu_id . ' > .menu-item > a[href*=' . esc_attr( $href_fragment ) . ']::after';
      }
    }
    ?>
    <style id="wicket-mship-nav-badge-style">
      <?php echo implode( ",\n      ", $selectors ); // phpcs:ignore WordPress.Security.EscapeOutput -- selectors built from hardcoded IDs and esc_attr()'d page slug above. ?> {
        content: "<?php echo esc_html( $badge_text ); ?>";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        min-width: var(--spacing-200, 1rem);
        height: var(--spacing-200, 1rem);
        margin-left: var(--spacing-75, 0.375rem);
        padding: 0 var(--spacing-50, 0.25rem);
        border-radius: var(--border-radius-600, 3rem);
        background-color: var(--state-error, #F26C6C);
        color: var(--text-content-reversed, #FFFFFF);
        font-family: inherit;
        font-size: var(--label-sm-font-size, 11px);
        font-weight: 600;
        line-height: 1;
        vertical-align: middle;
        white-space: nowrap;
      }
    </style>
    <?php
  }
}
