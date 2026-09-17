<?php
/**
 * Membership Bundle - Detail (Manage)
 *
 * Server-rendered PHP + Alpine.js template for the member-facing "Manage
 * Group Membership" screen's detail view. Shown in place of list.php by
 * render.php whenever the current URL carries a `bundle_post_id` query
 * param — both views live in the same block/page, so "Manage Bundle" in
 * list.php just adds that param to the current URL.
 *
 * Fetches, client-side, three member-scoped endpoints restricted to bundles
 * the current member's MDP organisation owns:
 *   - GET /wicket_member/v1/membership_bundle_entity/mine  (status + dates)
 *   - GET /wicket_member/v1/bundle/{id}/members_by_tier/mine (tier summary)
 *   - GET /wicket_member/v1/bundle/{id}/members/mine          (members table)
 *
 * Included by includes/blocks/membership-bundles-list/render.php, which has
 * already verified the requester is logged in, that Alpine.js is available
 * on the page, and that $bundle_post_id is a positive integer. Do not
 * include this file directly from anywhere else without the same guards.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

/** @var int $bundle_post_id Set by render.php before requiring this file. */

$rest_base    = esc_url_raw( untrailingslashit( rest_url( 'wicket_member/v1' ) ) );
$rest_nonce   = wp_create_nonce( 'wp_rest' );
$mdp_timezone = $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC';

// Same page/block as the list view — "back" just drops the query param that
// switches render.php into detail mode.
$back_url = esc_url_raw( remove_query_arg( 'bundle_post_id' ) );

// Same single-JSON-blob-via-esc_attr() pattern as list.php — see that file's
// comment for why several separate wp_json_encode() calls in one HTML
// attribute is unsafe once any value contains a literal double quote.
$block_config = [
  'restBase'     => $rest_base,
  'restNonce'    => $rest_nonce,
  'mdpTimezone'  => $mdp_timezone,
  'bundlePostId' => $bundle_post_id,
  'backUrl'      => $back_url,
];
?>
<div
  class="wicket-mship-bundle-detail"
  x-data="wicketMembershipBundleDetail(<?php echo esc_attr( wp_json_encode( $block_config ) ); ?>)"
  x-init="init()"
  x-on:wicket-mship-bundle-member-added.window="fetchTiers(); fetchMembers(1)"
>
  <a class="wicket-mship-bundle-detail__back" :href="backUrl">
    <?php esc_html_e( '← Back to Membership Bundles', 'wicket-memberships' ); ?>
  </a>

  <template x-if="loading">
    <div class="wicket-mship-skeleton-stack" aria-hidden="true" aria-label="<?php echo esc_attr__( 'Loading bundle…', 'wicket-memberships' ); ?>">
      <div class="wicket-mship-skeleton-bar" style="height:28px;width:260px;"></div>
      <div class="wicket-mship-skeleton-bar" style="height:11px;width:120px;border-radius:999px;"></div>
      <div class="wicket-mship-skeleton-row" style="grid-template-columns:repeat(4, 1fr);">
        <div class="wicket-mship-skeleton-bar" style="height:52px;"></div>
        <div class="wicket-mship-skeleton-bar" style="height:52px;"></div>
        <div class="wicket-mship-skeleton-bar" style="height:52px;"></div>
        <div class="wicket-mship-skeleton-bar" style="height:52px;"></div>
      </div>
    </div>
  </template>

  <template x-if="!loading && error">
    <p class="wicket-mship-bundle-detail__error" x-text="error"></p>
  </template>

  <template x-if="!loading && !error && bundle">
    <div>
      <div class="wicket-mship-bundle-detail__title-row">
        <h1 class="wicket-mship-bundle-detail__title" x-text="bundle.title"></h1>

        <?php
        // get_component('button', ...) from wicket-wp-base-plugin — Alpine
        // bindings (x-show, x-on) are passed through as raw strings in
        // 'atts', the same pattern used by that plugin's own Alpine-driven
        // org-search-select component.
        get_component( 'button', [
          'variant' => 'primary',
          'size'    => 'sm',
          'label'   => __( 'Add Member', 'wicket-memberships' ),
          'type'    => 'button',
          'atts'    => [
            'x-show="[\'pending\', \'active\', \'delayed\'].includes(bundle.data.membership_status_slug)"',
            'x-on:click="window.dispatchEvent(new CustomEvent(\'wicket-mship-open-add-member-modal\'))"',
          ],
        ] );
        ?>
      </div>

      <div class="wicket-mship-bundle-detail__status">
        <span
          class="wicket-mship-bundle-detail__status-dot"
          :class="'wicket-mship-bundle-detail__status-dot--' + bundle.data.membership_status_slug"
        ></span>
        <span x-text="bundle.data.membership_status"></span>
      </div>

      <div class="wicket-mship-bundle-detail__fields">
        <div class="wicket-mship-bundle-detail__field">
          <p class="wicket-mship-bundle-detail__field-label"><?php esc_html_e( 'Organization', 'wicket-memberships' ); ?></p>
          <p class="wicket-mship-bundle-detail__field-value" x-text="bundle.data.org_name"></p>
        </div>
        <div class="wicket-mship-bundle-detail__field">
          <p class="wicket-mship-bundle-detail__field-label"><?php esc_html_e( 'Start Date', 'wicket-memberships' ); ?></p>
          <p
            class="wicket-mship-bundle-detail__field-value"
            :title="isoTooltip(bundle.data.membership_starts_at)"
            x-text="formatDate(bundle.data.membership_starts_at)"
          ></p>
        </div>
        <div class="wicket-mship-bundle-detail__field">
          <p class="wicket-mship-bundle-detail__field-label"><?php esc_html_e( 'Renewal Date', 'wicket-memberships' ); ?></p>
          <p
            class="wicket-mship-bundle-detail__field-value"
            :title="isoTooltip(bundle.data.membership_ends_at)"
            x-text="formatDate(bundle.data.membership_ends_at)"
          ></p>
        </div>
        <div class="wicket-mship-bundle-detail__field">
          <p class="wicket-mship-bundle-detail__field-label"><?php esc_html_e( 'Total Memberships', 'wicket-memberships' ); ?></p>
          <p class="wicket-mship-bundle-detail__field-value" x-text="totalMembers"></p>
        </div>
      </div>

      <!-- Tier summary counts -->
      <template x-if="tiersLoading">
        <div class="wicket-mship-skeleton-stack" aria-hidden="true" aria-label="<?php echo esc_attr__( 'Loading breakdown…', 'wicket-memberships' ); ?>">
          <div class="wicket-mship-skeleton-bar" style="height:11px;width:180px;border-radius:999px;"></div>
          <div class="wicket-mship-skeleton-row" style="grid-template-columns:repeat(3, 1fr);">
            <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
            <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
            <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
          </div>
          <div class="wicket-mship-skeleton-row" style="grid-template-columns:repeat(3, 1fr);">
            <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
            <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
            <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
          </div>
        </div>
      </template>

      <template x-if="!tiersLoading && tiersError">
        <p class="wicket-mship-bundle-detail__error" x-text="tiersError"></p>
      </template>

      <template x-if="!tiersLoading && !tiersError">
        <div class="wicket-mship-bundle-detail__tier-summary">
          <div class="wicket-mship-bundle-detail__tier-summary-header">
            <div class="wicket-mship-bundle-detail__tier-summary-heading">
              <span class="wicket-mship-bundle-detail__tier-summary-title">
                <?php esc_html_e( 'Members by Tier', 'wicket-memberships' ); ?>
              </span>
              <span class="wicket-mship-bundle-detail__tier-summary-count" x-text="tiers.length + ' <?php echo esc_js( __( 'Tiers', 'wicket-memberships' ) ); ?>'"></span>
            </div>

            <template x-if="tiers.length > 0">
              <button
                type="button"
                class="wicket-mship-bundle-detail__tier-summary-toggle"
                @click="tiersExpanded = !tiersExpanded"
                :aria-expanded="tiersExpanded.toString()"
              >
                <span x-text="tiersExpanded
                  ? '<?php echo esc_js( __( 'Hide all', 'wicket-memberships' ) ); ?> ' + tiers.length + ' <?php echo esc_js( __( 'tiers', 'wicket-memberships' ) ); ?>'
                  : '<?php echo esc_js( __( 'Show all', 'wicket-memberships' ) ); ?> ' + tiers.length + ' <?php echo esc_js( __( 'tiers', 'wicket-memberships' ) ); ?>'"
                ></span>
                <svg
                  class="wicket-mship-bundle-detail__tier-summary-chevron"
                  :class="{ 'wicket-mship-bundle-detail__tier-summary-chevron--open': tiersExpanded }"
                  width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true"
                >
                  <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
            </template>
          </div>

          <hr class="wicket-mship-bundle-detail__tier-summary-divider" />

          <template x-if="tiers.length === 0">
            <p class="wicket-mship-bundle-detail__empty">
              <?php esc_html_e( 'No tiers with members yet.', 'wicket-memberships' ); ?>
            </p>
          </template>

          <div class="wicket-mship-bundle-detail__tier-grid" x-show="tiersExpanded">
            <template x-for="tier in tiers" :key="tier.tier_uuid">
              <div class="wicket-mship-bundle-detail__tier-row">
                <span class="wicket-mship-bundle-detail__tier-name" x-text="tier.tier_name"></span>
                <span class="wicket-mship-bundle-detail__tier-count" x-text="tier.member_count"></span>
              </div>
            </template>
          </div>
        </div>
      </template>

      <!-- Members table -->
      <template x-if="membersLoading">
        <div class="wicket-mship-skeleton-stack" aria-hidden="true" aria-label="<?php echo esc_attr__( 'Loading members…', 'wicket-memberships' ); ?>">
          <div class="wicket-mship-skeleton-bar" style="height:11px;width:140px;border-radius:999px;"></div>
          <template x-for="n in 4" :key="n">
            <div class="wicket-mship-skeleton-row" style="grid-template-columns:1fr 1fr 2fr 1fr 1fr;">
              <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
              <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
              <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
              <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
              <div class="wicket-mship-skeleton-bar" style="height:24px;"></div>
            </div>
          </template>
        </div>
      </template>

      <template x-if="!membersLoading && membersError">
        <p class="wicket-mship-bundle-detail__error" x-text="membersError"></p>
      </template>

      <template x-if="!membersLoading && !membersError">
        <div>
          <template x-if="members.length === 0">
            <p class="wicket-mship-bundle-detail__empty">
              <?php esc_html_e( 'No members have been added to this bundle yet.', 'wicket-memberships' ); ?>
            </p>
          </template>

          <div class="wicket-mship-bundle-detail__members-table-wrap" x-show="members.length > 0">
            <table class="wicket-mship-bundle-detail__members-table">
              <thead>
                <tr>
                  <th>
                    <button type="button" class="wicket-mship-bundle-detail__sort-btn" @click="sortMembersBy('first_name')">
                      <?php esc_html_e( 'First Name', 'wicket-memberships' ); ?>
                      <span class="wicket-mship-bundle-detail__sort-icon" aria-hidden="true">⇅</span>
                    </button>
                  </th>
                  <th>
                    <button type="button" class="wicket-mship-bundle-detail__sort-btn" @click="sortMembersBy('last_name')">
                      <?php esc_html_e( 'Last Name', 'wicket-memberships' ); ?>
                      <span class="wicket-mship-bundle-detail__sort-icon" aria-hidden="true">⇅</span>
                    </button>
                  </th>
                  <th><?php esc_html_e( 'Email', 'wicket-memberships' ); ?></th>
                  <th>
                    <button type="button" class="wicket-mship-bundle-detail__sort-btn" @click="sortMembersBy('tier')">
                      <?php esc_html_e( 'Tier', 'wicket-memberships' ); ?>
                      <span class="wicket-mship-bundle-detail__sort-icon" aria-hidden="true">⇅</span>
                    </button>
                  </th>
                  <th>
                    <button type="button" class="wicket-mship-bundle-detail__sort-btn" @click="sortMembersBy('start_date')">
                      <?php esc_html_e( 'Start Date', 'wicket-memberships' ); ?>
                      <span class="wicket-mship-bundle-detail__sort-icon" aria-hidden="true">⇅</span>
                    </button>
                  </th>
                </tr>
              </thead>
              <tbody>
                <template x-for="(member, index) in members" :key="member.ID">
                  <tr :class="{ 'wicket-mship-bundle-detail__member-row--grouped': isGroupedMemberRow(index) }">
                    <td x-text="member.first_name"></td>
                    <td x-text="member.last_name"></td>
                    <td x-text="member.email"></td>
                    <td x-text="tierName(member.tier_uuid)"></td>
                    <td :title="isoTooltip(member.membership_starts_at)" x-text="formatDate(member.membership_starts_at)"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <template x-if="members.length > 0">
            <div class="wicket-mship-bundle-detail__pagination-row">
              <nav class="wicket-mship-bundle-detail__pagination" aria-label="<?php echo esc_attr__( 'Members pages', 'wicket-memberships' ); ?>">
                <button
                  type="button"
                  class="wicket-mship-bundle-detail__page-arrow"
                  :disabled="membersPage <= 1"
                  @click="goToMembersPage(membersPage - 1)"
                >
                  ← <?php esc_html_e( 'Previous', 'wicket-memberships' ); ?>
                </button>

                <template x-for="(pageNum, idx) in membersPageNumbers()" :key="idx">
                  <button
                    type="button"
                    class="wicket-mship-bundle-detail__page-num"
                    :class="{ 'wicket-mship-bundle-detail__page-num--active': pageNum === membersPage }"
                    :disabled="pageNum === '…'"
                    @click="pageNum !== '…' && goToMembersPage(pageNum)"
                    x-text="pageNum"
                  ></button>
                </template>

                <button
                  type="button"
                  class="wicket-mship-bundle-detail__page-arrow"
                  :disabled="membersPage >= membersTotalPages"
                  @click="goToMembersPage(membersPage + 1)"
                >
                  <?php esc_html_e( 'Next', 'wicket-memberships' ); ?> →
                </button>
              </nav>

              <p class="wicket-mship-bundle-detail__page-status">
                <?php esc_html_e( 'Showing', 'wicket-memberships' ); ?> <span x-text="members.length"></span> <?php esc_html_e( 'of', 'wicket-memberships' ); ?> <span x-text="membersTotal"></span> <?php esc_html_e( 'members', 'wicket-memberships' ); ?>
              </p>
            </div>
          </template>
        </div>
      </template>
    </div>
  </template>
</div>

<?php
// Sibling Alpine component, not nested inside the x-data above — see
// add-member-modal.php's own header comment for why it's decoupled via a
// window event rather than shared scope. Reuses $block_config as-is since
// its restBase/restNonce/bundlePostId/mdpTimezone already match what the
// modal needs.
require __DIR__ . '/add-member-modal.php';
?>

<script>
  // Global Alpine component factory — no bundler/build step for this block,
  // so this stays a plain global function rather than an ES module import.
  // Mirrors the conventions established in list.php's wicketMembershipBundlesList().
  function wicketMembershipBundleDetail( config ) {
    return {
      restBase: config.restBase,
      restNonce: config.restNonce,
      mdpTimezone: config.mdpTimezone,
      bundlePostId: config.bundlePostId,
      backUrl: config.backUrl,

      // Bundle entity (status/dates)
      loading: true,
      error: '',
      bundle: null,

      // Tier summary counts
      tiersLoading: true,
      tiersError: '',
      tiers: [],
      totalMembers: 0,
      tiersExpanded: true,

      // Members table
      membersLoading: true,
      membersError: '',
      members: [],
      membersPage: 1,
      membersPerPage: 10,
      membersTotal: 0,
      membersTotalPages: 1,
      membersOrderCol: '',
      membersOrderDir: 'asc',

      // The three sections are independent REST calls with independent
      // loading/error state, so a slow or failing one (e.g. the members
      // table on a very large bundle) doesn't block the status/dates or
      // tier summary from rendering.
      init() {
        this.fetchBundle();
        this.fetchTiers();
        this.fetchMembers();
      },

      async fetchBundle() {
        this.loading = true;
        this.error = '';

        try {
          const url = new URL( this.restBase + '/membership_bundle_entity/mine' );
          url.searchParams.set( 'bundle_post_id', this.bundlePostId );

          const response = await fetch( url.toString(), {
            headers: { 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
          } );

          if ( ! response.ok ) {
            throw new Error( 'Request failed with status ' + response.status );
          }

          this.bundle = await response.json();
        } catch ( err ) {
          this.error = <?php echo wp_json_encode( __( "We couldn't load this membership bundle. Please try again later.", 'wicket-memberships' ) ); ?>;
        } finally {
          this.loading = false;
        }
      },

      async fetchTiers() {
        this.tiersLoading = true;
        this.tiersError = '';

        try {
          const url = this.restBase + '/bundle/' + this.bundlePostId + '/members_by_tier/mine';

          const response = await fetch( url, {
            headers: { 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
          } );

          if ( ! response.ok ) {
            throw new Error( 'Request failed with status ' + response.status );
          }

          const data = await response.json();
          this.tiers = Array.isArray( data.tiers ) ? data.tiers : [];
          this.totalMembers = typeof data.total_members === 'number' ? data.total_members : 0;
        } catch ( err ) {
          this.tiersError = <?php echo wp_json_encode( __( "We couldn't load the membership breakdown. Please try again later.", 'wicket-memberships' ) ); ?>;
        } finally {
          this.tiersLoading = false;
        }
      },

      // Server-paginated via GET /bundle/{id}/members/mine's page/posts_per_page
      // args — response shape is { results, page, posts_per_page, count }.
      async fetchMembers( page ) {
        const targetPage = page || this.membersPage;
        this.membersLoading = true;
        this.membersError = '';

        try {
          const url = new URL( this.restBase + '/bundle/' + this.bundlePostId + '/members/mine' );
          url.searchParams.set( 'page', targetPage );
          url.searchParams.set( 'posts_per_page', this.membersPerPage );

          if ( this.membersOrderCol ) {
            url.searchParams.set( 'order_col', this.membersOrderCol );
            url.searchParams.set( 'order_dir', this.membersOrderDir );
          }

          const response = await fetch( url.toString(), {
            headers: { 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
          } );

          if ( ! response.ok ) {
            throw new Error( 'Request failed with status ' + response.status );
          }

          const data = await response.json();
          this.members = Array.isArray( data.results ) ? data.results : [];
          this.membersPage = data.page || targetPage;
          this.membersTotal = typeof data.count === 'number' ? data.count : this.members.length;
          this.membersTotalPages = Math.max( 1, Math.ceil( this.membersTotal / this.membersPerPage ) );
        } catch ( err ) {
          this.membersError = <?php echo wp_json_encode( __( "We couldn't load this bundle's members. Please try again later.", 'wicket-memberships' ) ); ?>;
        } finally {
          this.membersLoading = false;
        }
      },

      goToMembersPage( page ) {
        if ( page < 1 || page > this.membersTotalPages || page === this.membersPage ) {
          return;
        }
        this.fetchMembers( page );
      },

      // Maps a table column to the order_col value GET /bundle/{id}/members/mine
      // (Membership_Controller::get_members_list()) expects. `last_name` and
      // `start_date` are fully supported (real per-field sorting server-side).
      // `first_name` and `tier` are best-effort: the backend has no split
      // first-name meta field, so it sorts by the full concatenated user_name
      // meta instead; `tier` sorts by the tier's UUID string, not its display
      // name, since tier name isn't stored on the membership post itself.
      sortMembersBy( column ) {
        const orderColMap = {
          first_name: 'user_name',
          last_name: 'user_last_name',
          tier: 'membership_tier_uuid',
          start_date: 'start_date',
        };
        const orderCol = orderColMap[ column ];
        if ( ! orderCol ) {
          return;
        }

        this.membersOrderDir = ( this.membersOrderCol === orderCol && this.membersOrderDir === 'asc' ) ? 'desc' : 'asc';
        this.membersOrderCol = orderCol;
        this.fetchMembers( 1 );
      },

      // Numbered pager with ellipsis truncation, e.g. [1, '…', 4, 5, 6, '…', 12].
      // Always shows the first and last page and a window around the current one.
      membersPageNumbers() {
        const total = this.membersTotalPages;
        const current = this.membersPage;

        if ( total <= 7 ) {
          return Array.from( { length: total }, ( _, i ) => i + 1 );
        }

        const pages = [ 1 ];
        if ( current > 3 ) {
          pages.push( '…' );
        }
        for ( let i = Math.max( 2, current - 1 ); i <= Math.min( total - 1, current + 1 ); i++ ) {
          pages.push( i );
        }
        if ( current < total - 2 ) {
          pages.push( '…' );
        }
        pages.push( total );
        return pages;
      },

      // Visually clusters consecutive rows belonging to the same person (a
      // left accent border) with a shared blue accent, matching the mockup's
      // grouping of a member's multiple tier records. Grouped by email since
      // that's the closest stable per-person identifier this table currently
      // has — there's no external member/bar-number field wired in yet.
      isGroupedMemberRow( index ) {
        const member = this.members[ index ];
        if ( ! member || ! member.email ) {
          return false;
        }
        const prev = this.members[ index - 1 ];
        const next = this.members[ index + 1 ];
        return ( !! prev && prev.email === member.email ) || ( !! next && next.email === member.email );
      },

      // tier_uuid on a member row is joined against the tier summary's own
      // tiers[] (already fetched for the breakdown section) rather than
      // resolving the tier name a second time server-side.
      tierName( tierUuid ) {
        const tier = this.tiers.find( ( t ) => t.tier_uuid === tierUuid );
        return tier ? tier.tier_name : '—';
      },

      // Mirrors the "always show raw ISO in a tooltip, never render a bare
      // date string" convention used by formatDateWithTooltip() in the React
      // admin UI (frontend/src/shared/constants.js), and by list.php's own
      // formatDate()/isoTooltip() pair.
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
    };
  }
</script>
