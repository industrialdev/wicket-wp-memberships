<?php
/**
 * Membership Bundle - List
 *
 * Server-rendered PHP + Alpine.js template for the member-facing "Manage
 * Group Membership" screen. Fetches GET /wicket_member/v1/membership_bundles/mine
 * client-side (owner-scoped: only bundles the current member owns).
 *
 * Included by includes/blocks/membership-bundles-list/render.php, which has
 * already verified the requester is logged in and that Alpine.js is available
 * on the page. Do not include this file directly from anywhere else without
 * the same guards.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

$rest_url  = esc_url_raw( rest_url( 'wicket_member/v1/membership_bundles/mine' ) );
$rest_nonce = wp_create_nonce( 'wp_rest' );
$mdp_timezone = $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC';

// TODO: point at the real bundle-detail screen once it exists. See TODO.md.
$manage_bundle_base_url = esc_url_raw( add_query_arg( [] ) );

// Built as one JSON blob and esc_attr()'d as a whole below — embedding several
// separate wp_json_encode() calls directly inside a double-quoted HTML
// attribute breaks the attribute the moment any of them contains a literal
// double quote (e.g. any URL), since HTML doesn't know those quotes are
// JSON-internal. esc_attr() turns them into &quot; entities, which the
// browser decodes before Alpine parses the expression.
$block_config = [
  'restUrl'             => $rest_url,
  'restNonce'           => $rest_nonce,
  'mdpTimezone'         => $mdp_timezone,
  'manageBundleBaseUrl' => $manage_bundle_base_url,
];
?>
<div
  class="wicket-mship-bundle-list"
  x-data="wicketMembershipBundlesList(<?php echo esc_attr( wp_json_encode( $block_config ) ); ?>)"
  x-init="fetchBundles()"
>
  <h1 class="wicket-mship-bundle-list__title">
    <?php esc_html_e( 'Manage Group Membership', 'wicket-memberships' ); ?>
  </h1>
  <p class="wicket-mship-bundle-list__subtitle">
    <?php esc_html_e( "View your organization's membership bundle, add eligible members, and manage who's included — all in one place.", 'wicket-memberships' ); ?>
  </p>
  <hr class="wicket-mship-bundle-list__divider" />

  <template x-if="loading">
    <p class="wicket-mship-bundle-list__loading"><?php esc_html_e( 'Loading your membership bundles…', 'wicket-memberships' ); ?></p>
  </template>

  <template x-if="!loading && error">
    <p class="wicket-mship-bundle-list__error" x-text="error"></p>
  </template>

  <template x-if="!loading && !error">
    <div>
      <h2 class="wicket-mship-bundle-list__count">
        <?php esc_html_e( 'Organizations Found:', 'wicket-memberships' ); ?> <span x-text="bundles.length"></span>
      </h2>

      <template x-if="bundles.length === 0">
        <p class="wicket-mship-bundle-list__empty">
          <?php esc_html_e( "You don't own any membership bundles yet.", 'wicket-memberships' ); ?>
        </p>
      </template>

      <div class="wicket-mship-bundle-list__cards">
        <template x-for="bundle in bundles" :key="bundle.post_id">
          <div class="wicket-mship-bundle-card">
            <h3 class="wicket-mship-bundle-card__title" x-text="cardTitle(bundle)"></h3>

            <div class="wicket-mship-bundle-card__status">
              <span
                class="wicket-mship-bundle-card__status-dot"
                :class="'wicket-mship-bundle-card__status-dot--' + bundle.status.slug"
              ></span>
              <span x-text="bundle.status.label + ' (<?php echo esc_js( __( 'Bundle Status', 'wicket-memberships' ) ); ?>)'"></span>
            </div>

            <div class="wicket-mship-bundle-card__fields">
              <div>
                <p class="wicket-mship-bundle-card__field-label"><?php esc_html_e( 'Start Date', 'wicket-memberships' ); ?></p>
                <p class="wicket-mship-bundle-card__field-value" :title="isoTooltip(bundle.starts_at)" x-text="formatDate(bundle.starts_at)"></p>
              </div>
              <div>
                <p class="wicket-mship-bundle-card__field-label"><?php esc_html_e( 'Renewal Date', 'wicket-memberships' ); ?></p>
                <p class="wicket-mship-bundle-card__field-value" :title="isoTooltip(bundle.ends_at)" x-text="formatDate(bundle.ends_at)"></p>
              </div>
              <div>
                <p class="wicket-mship-bundle-card__field-label"><?php esc_html_e( 'Total Memberships', 'wicket-memberships' ); ?></p>
                <p class="wicket-mship-bundle-card__field-value" x-text="bundle.total_memberships"></p>
              </div>
            </div>

            <a class="wicket-mship-bundle-card__manage-link" :href="manageBundleUrl(bundle)">
              <?php esc_html_e( 'Manage Bundle', 'wicket-memberships' ); ?>
            </a>
          </div>
        </template>
      </div>
    </div>
  </template>
</div>

<script>
  // Global Alpine component factory — no bundler/build step for this block, so
  // this stays a plain global function rather than an ES module import.
  function wicketMembershipBundlesList( config ) {
    return {
      restUrl: config.restUrl,
      restNonce: config.restNonce,
      mdpTimezone: config.mdpTimezone,
      manageBundleBaseUrl: config.manageBundleBaseUrl,
      loading: true,
      error: '',
      bundles: [],

      async fetchBundles() {
        this.loading = true;
        this.error = '';

        try {
          const response = await fetch( this.restUrl, {
            headers: { 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
          } );

          if ( ! response.ok ) {
            throw new Error( 'Request failed with status ' + response.status );
          }

          const data = await response.json();
          this.bundles = Array.isArray( data.results ) ? data.results : [];
        } catch ( err ) {
          this.error = <?php echo wp_json_encode( __( 'We couldn\'t load your membership bundles. Please try again later.', 'wicket-memberships' ) ); ?>;
        } finally {
          this.loading = false;
        }
      },

      cardTitle( bundle ) {
        const parts = [ bundle.org_name, bundle.bundle_name ].filter( Boolean );
        return parts.join( ' – ' );
      },

      // Mirrors the "always show raw ISO in a tooltip, never render a bare
      // date string" convention used by formatDateWithTooltip() in the React
      // admin UI (frontend/src/shared/constants.js) — same principle, applied
      // here since this screen is plain Alpine, not React.
      formatDate( isoString ) {
        if ( ! isoString ) {
          return '—';
        }
        const date = new Date( isoString );
        if ( isNaN( date.getTime() ) ) {
          return '—';
        }
        try {
          return new Intl.DateTimeFormat( undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            timeZone: this.mdpTimezone,
          } ).format( date );
        } catch ( err ) {
          return new Intl.DateTimeFormat( undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
          } ).format( date );
        }
      },

      isoTooltip( isoString ) {
        return isoString || '';
      },

      manageBundleUrl( bundle ) {
        const url = new URL( this.manageBundleBaseUrl, window.location.origin );
        url.searchParams.set( 'bundle_post_id', bundle.post_id );
        return url.toString();
      },
    };
  }
</script>
