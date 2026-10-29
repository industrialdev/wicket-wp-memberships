<?php
/**
 * Membership Bundle - Add Member Modal
 *
 * Server-rendered PHP + Alpine.js three-step modal (search → results →
 * review/confirm) for adding a new individual member seat to a membership
 * bundle from the member-facing "Manage Group Membership" detail screen.
 *
 * Included by detail.php, which has already verified the requester is
 * logged in, Alpine.js is available, and $bundle_post_id/$block_config are
 * set. Do not include this file directly from anywhere else without the
 * same guards.
 *
 * Decoupled from detail.php's own Alpine component (wicketMembershipBundleDetail)
 * on purpose — this modal is opened and closed via a global window event
 * rather than shared x-data, so detail.php doesn't need to know anything
 * about the modal's internal step state, and the modal doesn't need to know
 * how detail.php's members table/tier summary are fetched. On a successful
 * add, this dispatches `wicket-mship-bundle-member-added` on window; detail.php
 * listens for it to refresh the members table and tier summary.
 *
 * Calls three member-scoped endpoints, all restricted to bundles the current
 * member's MDP organisation owns (see permissions_check_bundle_org_member):
 *   - POST /wicket_member/v1/bundle/{id}/search_eligible_members  (search step)
 *   - GET  /wicket_member/v1/bundle/{id}/eligible_tiers/mine       (results step)
 *   - POST /wicket_member/v1/bundle/{id}/add_member/mine           (review step)
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

/**
 * @var int    $bundle_post_id             Set by detail.php before requiring this file.
 * @var array  $block_config               Set by detail.php before requiring this file — reused
 *                                          as-is (restBase/restNonce/bundlePostId already match).
 * @var string $extra_columns_search_hint  Set by detail.php before requiring this file — see that
 *                                          file's header for how it's built from wicket_mship_bundle_member_extra_columns.
 */
?>
<div
  class="wicket-mship-add-member-modal"
  x-data="wicketAddMemberModal(<?php echo esc_attr( wp_json_encode( $block_config ) ); ?>)"
  x-on:wicket-mship-open-add-member-modal.window="openModal()"
  x-show="open"
  x-cloak
  style="display: none;"
  role="dialog"
  aria-modal="true"
  :aria-label="<?php echo esc_attr( wp_json_encode( __( 'Add memberships to your bundle', 'wicket-memberships' ) ) ); ?>"
  x-on:keydown.escape.window="closeModal()"
>
  <div class="wicket-mship-add-member-modal__overlay"></div>

  <div class="wicket-mship-add-member-modal__panel">
    <div class="wicket-mship-add-member-modal__header">
      <h2 class="wicket-mship-add-member-modal__title">
        <?php esc_html_e( 'Add memberships to your bundle', 'wicket-memberships' ); ?>
      </h2>
      <?php
      get_component( 'button', [
        'variant'            => 'ghost',
        'size'               => 'sm',
        'label'              => '',
        'type'               => 'button',
        'prefix_icon'        => 'fa-solid fa-xmark',
        'screen_reader_text' => __( 'Close', 'wicket-memberships' ),
        'atts'               => [
          'x-on:click="closeModal()"',
        ],
      ] );
      ?>
    </div>

    <div class="wicket-mship-add-member-modal__body">

      <!-- Step 1: Search -->
      <template x-if="step === 'search'">
        <div>
          <p class="wicket-mship-add-member-modal__description">
            <?php
            echo esc_html( sprintf(
              /* translators: %s: comma-separated list of extra searchable field labels, e.g. " (or by Bar ID)" */
              __( 'Search for the member by name or email%s to add them to your membership bundle. Only members with an eligible membership tier can be added to a bundle.', 'wicket-memberships' ),
              $extra_columns_search_hint
            ) );
            ?>
          </p>

          <label class="wicket-mship-add-member-modal__label" for="wicket-mship-add-member-search">
            <?php echo esc_html( __( 'Search Members by Name or Email', 'wicket-memberships' ) . $extra_columns_search_hint ); ?>
          </label>
          <div class="wicket-mship-add-member-modal__search-row">
            <input
              id="wicket-mship-add-member-search"
              type="text"
              class="wicket-mship-add-member-modal__search-input"
              placeholder="<?php echo esc_attr( __( 'Search members by name or email...', 'wicket-memberships' ) . $extra_columns_search_hint ); ?>"
              x-model="searchTerm"
              @keydown.enter.prevent="search()"
            />
            <?php
            get_component( 'button', [
              'variant' => 'primary',
              'size'    => 'sm',
              'label'   => '',
              'type'    => 'button',
              'atts'    => [
                'x-on:click="search()"',
                ':disabled="searching"',
                'x-text' => sprintf(
                  'searching ? %s : %s',
                  wp_json_encode( __( 'Searching…', 'wicket-memberships' ) ),
                  wp_json_encode( __( 'Search', 'wicket-memberships' ) )
                ),
              ],
            ] );
            ?>
          </div>

          <template x-if="searchError">
            <p class="wicket-mship-add-member-modal__error" x-text="searchError"></p>
          </template>

          <div class="wicket-mship-add-member-modal__footer">
            <?php
            get_component( 'button', [
              'variant' => 'secondary',
              'size'    => 'sm',
              'label'   => __( 'Cancel', 'wicket-memberships' ),
              'type'    => 'button',
              'atts'    => [ 'x-on:click="closeModal()"' ],
            ] );
            ?>
          </div>
        </div>
      </template>

      <!-- Step 2: Results -->
      <template x-if="step === 'results'">
        <div>
          <?php
          get_component( 'button', [
            'variant' => 'ghost',
            'size'    => 'sm',
            'label'   => __( '← Search again', 'wicket-memberships' ),
            'type'    => 'button',
            'classes' => [ 'wicket-mship-add-member-modal__back-link' ],
            'atts'    => [ 'x-on:click="backToSearch()"' ],
          ] );
          ?>

          <template x-if="results.length === 0">
            <p class="wicket-mship-add-member-modal__empty">
              <?php esc_html_e( 'No members matched your search. Try a different search.', 'wicket-memberships' ); ?>
            </p>
          </template>

          <template x-if="!selectedPerson && results.length > 0">
            <div>
              <p
                class="wicket-mship-add-member-modal__results-summary"
                x-show="ineligibleResultCount() > 0"
                x-text="resultsSummaryText()"
              ></p>
              <div class="wicket-mship-add-member-modal__results-list">
                <!-- People with zero eligible tiers stay in the list (so the
                     member can see the search did find them) but are labelled,
                     visually muted, sorted below selectable people, and locked
                     via aria-disabled + a guarded click handler. aria-disabled
                     rather than the disabled attribute keeps the row focusable,
                     so screen-reader users still hear why it can't be chosen. -->
                <template x-for="person in sortedResults()" :key="person.id">
                  <button
                    type="button"
                    class="wicket-mship-add-member-modal__result-row"
                    :class="{ 'wicket-mship-add-member-modal__result-row--ineligible': !isPersonSelectable(person) }"
                    :aria-disabled="isPersonSelectable(person) ? 'false' : 'true'"
                    @click="selectPerson(person)"
                  >
                    <span class="wicket-mship-add-member-modal__result-avatar" aria-hidden="true">
                      <template x-if="person.avatar_url">
                        <img :src="person.avatar_url" alt="" loading="lazy" />
                      </template>
                    </span>
                    <span class="wicket-mship-add-member-modal__result-info">
                      <span class="wicket-mship-add-member-modal__result-name" x-text="person.full_name"></span>
                      <span class="wicket-mship-add-member-modal__result-email" x-text="person.primary_email_address"></span>
                      <template x-for="col in extraColumns" :key="col.key">
                        <span class="wicket-mship-add-member-modal__result-extra" x-show="person[col.key]" x-text="person[col.key] ? col.label + ': ' + person[col.key] : ''"></span>
                      </template>
                      <template x-if="!isPersonSelectable(person)">
                        <span class="wicket-mship-add-member-modal__result-reason" x-text="personIneligibleReason(person)"></span>
                      </template>
                    </span>
                    <template x-if="person.bundle_eligibility">
                      <span
                        class="wicket-mship-add-member-modal__badge wicket-mship-add-member-modal__result-badge"
                        :class="personBadgeClass(person)"
                        x-text="personBadgeLabel(person)"
                      ></span>
                    </template>
                  </button>
                </template>
              </div>
            </div>
          </template>

          <template x-if="selectedPerson">
            <div>
              <div class="wicket-mship-add-member-modal__person-card">
                <span class="wicket-mship-add-member-modal__result-avatar" aria-hidden="true">
                  <template x-if="selectedPerson.avatar_url">
                    <img :src="selectedPerson.avatar_url" alt="" loading="lazy" />
                  </template>
                </span>
                <span class="wicket-mship-add-member-modal__result-info">
                  <span class="wicket-mship-add-member-modal__result-name" x-text="selectedPerson.full_name"></span>
                  <span class="wicket-mship-add-member-modal__result-email" x-text="selectedPerson.primary_email_address"></span>
                  <template x-for="col in extraColumns" :key="col.key">
                    <span class="wicket-mship-add-member-modal__result-extra" x-show="selectedPerson[col.key]" x-text="selectedPerson[col.key] ? col.label + ': ' + selectedPerson[col.key] : ''"></span>
                  </template>
                </span>
              </div>

              <template x-if="tiersLoading">
                <div class="wicket-mship-skeleton-stack" aria-hidden="true">
                  <div class="wicket-mship-skeleton-bar" style="height:44px;"></div>
                  <div class="wicket-mship-skeleton-bar" style="height:44px;"></div>
                </div>
              </template>

              <template x-if="!tiersLoading && tiersError">
                <p class="wicket-mship-add-member-modal__error" x-text="tiersError"></p>
              </template>

              <template x-if="!tiersLoading && !tiersError && tiers.length === 0">
                <p class="wicket-mship-add-member-modal__empty">
                  <!-- With a person selected, the endpoint omits tiers they
                       hold no membership for, so an empty list means this
                       person has nothing to add — not that the bundle has no tiers. -->
                  <template x-if="selectedPerson">
                    <span><?php esc_html_e( 'This member has no memberships that can be added to this bundle.', 'wicket-memberships' ); ?></span>
                  </template>
                  <template x-if="!selectedPerson">
                    <span><?php esc_html_e( 'No eligible membership tiers are available for this bundle.', 'wicket-memberships' ); ?></span>
                  </template>
                </p>
              </template>

              <template x-if="!tiersLoading && !tiersError && tiers.length > 0 && eligibleTiers().length === 0">
                <p class="wicket-mship-add-member-modal__empty">
                  <?php esc_html_e( 'This member has no memberships that can be added to this bundle.', 'wicket-memberships' ); ?>
                </p>
              </template>

              <!-- Eligible tiers are always listed first; 'in_bundle' and
                   'not_eligible' tiers are collapsed behind the toggle below
                   by default (expanded automatically when nothing is
                   eligible, so the member can see why) to keep the
                   selectable rows and the footer actions near the top. -->
              <div class="wicket-mship-add-member-modal__tier-list" x-show="!tiersLoading && !tiersError && visibleTiers().length > 0">
                <template x-for="tier in visibleTiers()" :key="tier.id">
                  <div
                    class="wicket-mship-add-member-modal__tier-row"
                    :class="{ 'wicket-mship-add-member-modal__tier-row--muted': !isTierSelectable(tier) }"
                  >
                    <label
                      class="wicket-mship-add-member-modal__tier-checkbox-label"
                      :class="{ 'wicket-mship-add-member-modal__tier-checkbox-label--disabled': !isTierSelectable(tier) }"
                    >
                      <input
                        type="checkbox"
                        :checked="tier.eligibility_status === 'in_bundle' || !!checkedTiers[tier.id]"
                        :disabled="!isTierSelectable(tier)"
                        @change="toggleTier(tier)"
                      />
                      <span x-text="tier.name"></span>
                    </label>

                    <div class="wicket-mship-add-member-modal__tier-meta" x-show="tier.eligibility_status">
                      <span
                        class="wicket-mship-add-member-modal__badge"
                        :class="eligibilityBadgeClass(tier.eligibility_status)"
                        x-text="eligibilityBadgeLabel(tier.eligibility_status)"
                      ></span>
                      <template x-if="tier.membership_status_label">
                        <span
                          class="wicket-mship-add-member-modal__badge"
                          :class="statusBadgeClass(tier.membership_status)"
                          x-text="tier.membership_status_label"
                        ></span>
                      </template>
                      <template x-if="tier.starts_at || tier.ends_at">
                        <span class="wicket-mship-add-member-modal__tier-dates" x-text="formatDate(tier.starts_at) + ' - ' + formatDate(tier.ends_at)"></span>
                      </template>
                    </div>

                    <select
                      class="wicket-mship-add-member-modal__product-select"
                      x-show="checkedTiers[tier.id] && tier.products.length > 1"
                      x-model.number="selectedTierProducts[tier.id]"
                    >
                      <option value="" disabled><?php echo esc_js( __( 'Select a product…', 'wicket-memberships' ) ); ?></option>
                      <template x-for="(product, index) in tier.products" :key="index">
                        <option :value="index" x-text="product.name + (product.price ? ' — ' + product.price : '')"></option>
                      </template>
                    </select>
                  </div>
                </template>
              </div>

              <template x-if="!tiersLoading && !tiersError && otherTiers().length > 0 && eligibleTiers().length > 0">
                <button
                  type="button"
                  class="wicket-mship-add-member-modal__other-tiers-toggle"
                  :aria-expanded="showOtherTiers ? 'true' : 'false'"
                  @click="showOtherTiers = ! showOtherTiers"
                  x-text="otherTiersToggleLabel()"
                ></button>
              </template>

              <div class="wicket-mship-add-member-modal__footer wicket-mship-add-member-modal__footer--split">
                <span class="wicket-mship-add-member-modal__selected-count" x-text="selectedCount() + ' <?php echo esc_js( __( 'Membership(s) selected', 'wicket-memberships' ) ); ?>'"></span>
                <div class="wicket-mship-add-member-modal__footer-actions">
                  <?php
                  get_component( 'button', [
                    'variant' => 'secondary',
                    'size'    => 'sm',
                    'label'   => __( 'Cancel', 'wicket-memberships' ),
                    'type'    => 'button',
                    'atts'    => [ 'x-on:click="closeModal()"' ],
                  ] );
                  get_component( 'button', [
                    'variant' => 'primary',
                    'size'    => 'sm',
                    'label'   => __( 'Review Selection', 'wicket-memberships' ),
                    'type'    => 'button',
                    'atts'    => [
                      ':disabled="!canReview()"',
                      'x-on:click="goToReview()"',
                    ],
                  ] );
                  ?>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>

      <!-- Step 3: Review / Confirm -->
      <template x-if="step === 'review'">
        <div>
          <p class="wicket-mship-add-member-modal__description">
            <?php esc_html_e( "Confirm the memberships below. Start and end dates are calculated from your bundle's schedule.", 'wicket-memberships' ); ?>
          </p>

          <div class="wicket-mship-add-member-modal__review-table-wrap">
            <table class="wicket-mship-add-member-modal__review-table">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Member', 'wicket-memberships' ); ?></th>
                  <th><?php esc_html_e( 'Tier', 'wicket-memberships' ); ?></th>
                  <th><?php esc_html_e( 'Start', 'wicket-memberships' ); ?></th>
                  <th><?php esc_html_e( 'Renewal Date', 'wicket-memberships' ); ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <template x-for="row in reviewRows()" :key="row.tier.id">
                  <tr>
                    <td>
                      <div class="wicket-mship-add-member-modal__result-name" x-text="selectedPerson.full_name"></div>
                      <div class="wicket-mship-add-member-modal__result-email" x-text="selectedPerson.primary_email_address"></div>
                      <template x-for="col in extraColumns" :key="col.key">
                        <div class="wicket-mship-add-member-modal__result-extra" x-show="selectedPerson[col.key]" x-text="selectedPerson[col.key] ? col.label + ': ' + selectedPerson[col.key] : ''"></div>
                      </template>
                    </td>
                    <td x-text="row.tier.name"></td>
                    <td x-text="formatDate(bundleStartsAt)"></td>
                    <td x-text="formatDate(bundleEndsAt)"></td>
                    <td>
                      <?php
                      get_component( 'button', [
                        'variant' => 'secondary',
                        'size'    => 'sm',
                        'label'   => __( 'Remove', 'wicket-memberships' ),
                        'type'    => 'button',
                        'atts'    => [ 'x-on:click="removeFromReview(row.tier.id)"' ],
                      ] );
                      ?>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <div class="wicket-mship-add-member-modal__info-banner">
            <?php esc_html_e( "Each selected individual membership will be added to your bundle, inheriting your bundle's status and dates.", 'wicket-memberships' ); ?>
          </div>

          <template x-if="submitError">
            <p class="wicket-mship-add-member-modal__error" x-text="submitError"></p>
          </template>

          <div class="wicket-mship-add-member-modal__footer">
            <?php
            get_component( 'button', [
              'variant' => 'secondary',
              'size'    => 'sm',
              'label'   => __( 'Back', 'wicket-memberships' ),
              'type'    => 'button',
              'atts'    => [
                'x-on:click="backToResults()"',
                ':disabled="submitting"',
              ],
            ] );
            get_component( 'button', [
              'variant' => 'primary',
              'size'    => 'sm',
              'label'   => '',
              'type'    => 'button',
              'atts'    => [
                'x-on:click="confirmAdd()"',
                ':disabled="submitting || reviewRows().length === 0"',
                'x-text' => sprintf(
                  'submitting ? %s : (%s + reviewRows().length + %s)',
                  wp_json_encode( __( 'Adding…', 'wicket-memberships' ) ),
                  wp_json_encode( __( 'Confirm & add ', 'wicket-memberships' ) ),
                  wp_json_encode( __( ' membership(s)', 'wicket-memberships' ) )
                ),
              ],
            ] );
            ?>
          </div>
        </div>
      </template>

    </div>
  </div>
</div>

<script>
  // Global Alpine component factory — no bundler/build step for this block,
  // so this stays a plain global function, mirroring detail.php's
  // wicketMembershipBundleDetail() and list.php's wicketMembershipBundlesList().
  function wicketAddMemberModal( config ) {
    return {
      restBase: config.restBase,
      restNonce: config.restNonce,
      bundlePostId: config.bundlePostId,
      mdpTimezone: config.mdpTimezone,
      // [{ key, label, position }, ...] — resolved server-side once via the
      // wicket_mship_bundle_member_extra_columns filter (see detail.php).
      // Surfaced on person search results when the search response carries
      // the field (a Bar-ID hit does; a name/email hit does not), so a
      // client-specific identifier like Bar ID is visible before selecting.
      extraColumns: config.extraColumns || [],
      // Populated lazily from the bundle entity once the modal opens (see
      // openModal()) rather than passed in at page load, since detail.php's
      // own bundle fetch may not have resolved yet by the time Alpine
      // initializes both components.
      bundleStartsAt: null,
      bundleEndsAt: null,

      open: false,
      step: 'search',

      searchTerm: '',
      searching: false,
      searchError: '',
      results: [],

      selectedPerson: null,
      tiersLoading: false,
      tiersError: '',
      tiers: [],
      checkedTiers: {},
      selectedTierProducts: {},
      // Whether 'in_bundle'/'not_eligible' tiers are expanded on the
      // selection step — see visibleTiers().
      showOtherTiers: false,

      submitting: false,
      submitError: '',

      openModal() {
        this.resetState();
        this.open = true;
        this.fetchBundleDates();
      },

      closeModal() {
        this.open = false;
      },

      resetState() {
        this.step = 'search';
        this.searchTerm = '';
        this.searching = false;
        this.searchError = '';
        this.results = [];
        this.selectedPerson = null;
        this.tiersLoading = false;
        this.tiersError = '';
        this.tiers = [];
        this.checkedTiers = {};
        this.selectedTierProducts = {};
        this.showOtherTiers = false;
        this.submitting = false;
        this.submitError = '';
      },

      // The review step's Start/Renewal Date columns mirror the bundle's own
      // schedule (see the info banner copy) rather than any per-membership
      // date, so a lightweight dedicated fetch is simpler than threading the
      // already-loaded value through from detail.php's separate component.
      async fetchBundleDates() {
        try {
          const url = new URL( this.restBase + '/membership_bundle_entity/mine' );
          url.searchParams.set( 'bundle_post_id', this.bundlePostId );
          const response = await fetch( url.toString(), {
            headers: { 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
          } );
          if ( ! response.ok ) {
            return;
          }
          const data = await response.json();
          this.bundleStartsAt = data?.data?.membership_starts_at || null;
          this.bundleEndsAt = data?.data?.membership_ends_at || null;
        } catch ( err ) {
          // Non-fatal — review step just shows '—' for both date columns.
        }
      },

      async search() {
        const term = this.searchTerm.trim();
        if ( term.length < 3 ) {
          this.searchError = <?php echo wp_json_encode( __( 'Type at least 3 characters to search.', 'wicket-memberships' ) ); ?>;
          return;
        }

        this.searching = true;
        this.searchError = '';

        try {
          const response = await fetch( this.restBase + '/bundle/' + this.bundlePostId + '/search_eligible_members', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
            body: JSON.stringify( { term } ),
          } );

          if ( ! response.ok ) {
            throw new Error( 'Request failed with status ' + response.status );
          }

          const data = await response.json();
          this.results = Array.isArray( data ) ? data : [];
          this.step = 'results';
        } catch ( err ) {
          this.searchError = <?php echo wp_json_encode( __( "We couldn't search for members. Please try again.", 'wicket-memberships' ) ); ?>;
        } finally {
          this.searching = false;
        }
      },

      async selectPerson( person ) {
        if ( ! this.isPersonSelectable( person ) ) {
          return;
        }
        this.selectedPerson = person;
        this.checkedTiers = {};
        this.selectedTierProducts = {};
        this.showOtherTiers = false;
        await this.fetchTiers();
      },

      // bundle_eligibility is attached by search_eligible_members:
      // { eligible, in_bundle, not_eligible } tier counts for this bundle.
      // A missing summary means "unknown" (e.g. a search override returned
      // people without an id) — keep those selectable so this can never
      // block a valid add; add_member still validates on submit.
      isPersonSelectable( person ) {
        const summary = person && person.bundle_eligibility;
        if ( ! summary ) {
          return true;
        }
        return Number( summary.eligible ) > 0;
      },

      // Selectable people first; original (relevance) order kept otherwise.
      sortedResults() {
        return [
          ...this.results.filter( ( person ) => this.isPersonSelectable( person ) ),
          ...this.results.filter( ( person ) => ! this.isPersonSelectable( person ) ),
        ];
      },

      ineligibleResultCount() {
        return this.results.filter( ( person ) => ! this.isPersonSelectable( person ) ).length;
      },

      resultsSummaryText() {
        const total = this.results.length;
        const selectable = total - this.ineligibleResultCount();
        return <?php echo wp_json_encode( __( '%1$d of %2$d members found can be added to this bundle.', 'wicket-memberships' ) ); ?>
          .replace( '%1$d', selectable )
          .replace( '%2$d', total );
      },

      // True when every tier this person holds is already a bundle seat.
      isPersonAllInBundle( person ) {
        const summary = person && person.bundle_eligibility;
        return !! summary
          && Number( summary.eligible ) === 0
          && Number( summary.in_bundle ) > 0
          && Number( summary.not_eligible ) === 0;
      },

      personBadgeLabel( person ) {
        const summary = person && person.bundle_eligibility;
        if ( ! summary ) {
          return '';
        }
        const eligible = Number( summary.eligible );
        if ( eligible === 1 ) {
          return <?php echo wp_json_encode( __( '1 eligible membership', 'wicket-memberships' ) ); ?>;
        }
        if ( eligible > 1 ) {
          return <?php echo wp_json_encode( __( '%d eligible memberships', 'wicket-memberships' ) ); ?>.replace( '%d', eligible );
        }
        if ( this.isPersonAllInBundle( person ) ) {
          return <?php echo wp_json_encode( __( 'Already in bundle', 'wicket-memberships' ) ); ?>;
        }
        return <?php echo wp_json_encode( __( 'Not eligible', 'wicket-memberships' ) ); ?>;
      },

      personBadgeClass( person ) {
        if ( this.isPersonSelectable( person ) ) {
          return 'wicket-mship-add-member-modal__badge--eligible';
        }
        return this.isPersonAllInBundle( person )
          ? 'wicket-mship-add-member-modal__badge--in-bundle'
          : 'wicket-mship-add-member-modal__badge--not-eligible';
      },

      personIneligibleReason( person ) {
        if ( this.isPersonAllInBundle( person ) ) {
          return <?php echo wp_json_encode( __( "Can't be selected — their memberships are already in a bundle.", 'wicket-memberships' ) ); ?>;
        }
        return <?php echo wp_json_encode( __( "Can't be selected — not eligible for this bundle.", 'wicket-memberships' ) ); ?>;
      },

      backToSearch() {
        this.selectedPerson = null;
        this.results = [];
        this.step = 'search';
      },

      async fetchTiers() {
        this.tiersLoading = true;
        this.tiersError = '';

        try {
          const url = new URL( this.restBase + '/bundle/' + this.bundlePostId + '/eligible_tiers/mine' );
          if ( this.selectedPerson && this.selectedPerson.id ) {
            url.searchParams.set( 'person_uuid', this.selectedPerson.id );
          }
          const response = await fetch( url.toString(), {
            headers: { 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
          } );

          if ( ! response.ok ) {
            throw new Error( 'Request failed with status ' + response.status );
          }

          const data = await response.json();
          this.tiers = Array.isArray( data ) ? data : [];

          // Pre-select the only product on single-product tiers so a bare
          // checkbox is enough — mirrors handleTierChange() in the legacy
          // React AddMemberToBundleModal.
          this.tiers.forEach( ( tier ) => {
            if ( Array.isArray( tier.products ) && tier.products.length === 1 ) {
              this.selectedTierProducts[ tier.id ] = 0;
            }
          } );
        } catch ( err ) {
          this.tiersError = <?php echo wp_json_encode( __( "We couldn't load membership tiers. Please try again.", 'wicket-memberships' ) ); ?>;
        } finally {
          this.tiersLoading = false;
        }
      },

      toggleTier( tier ) {
        if ( ! this.isTierSelectable( tier ) ) {
          return;
        }
        const isChecked = !! this.checkedTiers[ tier.id ];
        this.checkedTiers[ tier.id ] = ! isChecked;
      },

      // A tier the person is already in ('in_bundle') is shown checked but
      // locked — it's already a bundle seat, not a new one to add. A tier
      // they're 'not_eligible' for is shown unchecked and locked. Only
      // 'eligible' tiers (or, with no person context at all, every tier)
      // can actually be toggled/submitted.
      isTierSelectable( tier ) {
        if ( ! tier.eligibility_status ) {
          return true;
        }
        return tier.eligibility_status === 'eligible';
      },

      // Tiers the member can act on: 'eligible', or no eligibility context.
      eligibleTiers() {
        return this.tiers.filter( ( tier ) => this.isTierSelectable( tier ) );
      },

      // 'in_bundle' + 'not_eligible' tiers — informational only.
      otherTiers() {
        return this.tiers.filter( ( tier ) => ! this.isTierSelectable( tier ) );
      },

      // Eligible tiers first; the rest only when expanded, or when there is
      // nothing eligible (so the member can still see why).
      visibleTiers() {
        const eligible = this.eligibleTiers();
        if ( eligible.length === 0 || this.showOtherTiers ) {
          return [ ...eligible, ...this.otherTiers() ];
        }
        return eligible;
      },

      otherTiersToggleLabel() {
        const count = this.otherTiers().length;
        if ( this.showOtherTiers ) {
          return <?php echo wp_json_encode( __( 'Hide memberships that can\'t be added', 'wicket-memberships' ) ); ?>;
        }
        return count === 1
          ? <?php echo wp_json_encode( __( 'Show 1 membership that can\'t be added', 'wicket-memberships' ) ); ?>
          : <?php echo wp_json_encode( __( 'Show %d memberships that can\'t be added', 'wicket-memberships' ) ); ?>.replace( '%d', count );
      },

      eligibilityBadgeLabel( status ) {
        if ( status === 'in_bundle' ) {
          return <?php echo wp_json_encode( __( 'In Bundle', 'wicket-memberships' ) ); ?>;
        }
        if ( status === 'not_eligible' ) {
          return <?php echo wp_json_encode( __( 'Not Eligible', 'wicket-memberships' ) ); ?>;
        }
        return <?php echo wp_json_encode( __( 'Eligible', 'wicket-memberships' ) ); ?>;
      },

      eligibilityBadgeClass( status ) {
        return 'wicket-mship-add-member-modal__badge--' + ( status ? status.replace( /_/g, '-' ) : 'eligible' );
      },

      statusBadgeClass( rawStatus ) {
        return 'wicket-mship-add-member-modal__badge--status-' + ( rawStatus ? rawStatus.replace( /_/g, '-' ) : 'unknown' );
      },

      selectedCount() {
        return Object.values( this.checkedTiers ).filter( Boolean ).length;
      },

      // A checked tier with more than one product needs an explicit product
      // choice before the selection can be reviewed; single-product tiers
      // are pre-filled by fetchTiers() above.
      canReview() {
        const checkedTierList = this.tiers.filter( ( tier ) => this.checkedTiers[ tier.id ] );
        if ( checkedTierList.length === 0 ) {
          return false;
        }
        return checkedTierList.every( ( tier ) => {
          if ( ! Array.isArray( tier.products ) || tier.products.length <= 1 ) {
            return true;
          }
          const chosen = this.selectedTierProducts[ tier.id ];
          return chosen !== undefined && chosen !== null && chosen !== '';
        } );
      },

      goToReview() {
        if ( ! this.canReview() ) {
          return;
        }
        this.step = 'review';
      },

      backToResults() {
        this.submitError = '';
        this.step = 'results';
      },

      removeFromReview( tierId ) {
        this.checkedTiers[ tierId ] = false;
        if ( this.selectedCount() === 0 ) {
          this.step = 'results';
        }
      },

      reviewRows() {
        return this.tiers
          .filter( ( tier ) => this.checkedTiers[ tier.id ] )
          .map( ( tier ) => {
            const productIndex = this.selectedTierProducts[ tier.id ];
            const product = ( productIndex !== undefined && productIndex !== null && tier.products[ productIndex ] )
              ? tier.products[ productIndex ]
              : ( tier.products[ 0 ] || null );
            return { tier, product };
          } );
      },

      async confirmAdd() {
        const rows = this.reviewRows();
        if ( rows.length === 0 || this.submitting ) {
          return;
        }

        this.submitting = true;
        this.submitError = '';

        try {
          // Sequential, not Promise.all: add_member/mine can fail per-row
          // (e.g. a since-filled seat), and firing all requests in parallel
          // would make a partial failure impossible to attribute to a
          // specific tier in the error message.
          for ( const row of rows ) {
            const body = {
              mode: 'new',
              person_uuid: this.selectedPerson.id,
              tier_post_id: row.tier.id,
            };

            if ( row.product && row.product.product_id ) {
              body.product_id = row.product.product_id;
              if ( row.product.variation_id ) {
                body.variation_id = row.product.variation_id;
              }
            }

            const response = await fetch( this.restBase + '/bundle/' + this.bundlePostId + '/add_member/mine', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.restNonce },
              credentials: 'same-origin',
              body: JSON.stringify( body ),
            } );

            if ( ! response.ok ) {
              const errorData = await response.json().catch( () => ( {} ) );
              throw new Error( errorData.error || ( 'Request failed with status ' + response.status ) );
            }
          }

          this.closeModal();
          window.dispatchEvent( new CustomEvent( 'wicket-mship-bundle-member-added' ) );
        } catch ( err ) {
          this.submitError = err.message || <?php echo wp_json_encode( __( "We couldn't add this member. Please try again.", 'wicket-memberships' ) ); ?>;
        } finally {
          this.submitting = false;
        }
      },

      // Mirrors detail.php's own formatDate()/isoTooltip() pair — duplicated
      // rather than shared since this modal is a separate Alpine component
      // with no access to detail.php's x-data scope.
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
    };
  }
</script>
