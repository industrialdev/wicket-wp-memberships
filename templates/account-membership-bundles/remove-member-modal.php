<?php
/**
 * Membership Bundle - Remove Member Modal
 *
 * Server-rendered PHP + Alpine.js confirmation modal for removing an
 * individual membership from a bundle, opened via the "Remove" button in
 * detail.php's members table.
 *
 * Included by detail.php, which has already verified the requester is
 * logged in, Alpine.js is available, and $bundle_post_id/$block_config are
 * set. Do not include this file directly from anywhere else without the
 * same guards.
 *
 * Decoupled from detail.php's own Alpine component the same way
 * add-member-modal.php is (see that file's header comment): opened via a
 * global window event carrying the membership post ID/first name/last
 * name/email/tier name/bundle end date in event.detail, rather than shared
 * x-data. The bundle end date is passed through from detail.php's own
 * already-loaded bundle entity rather than re-fetched here — this modal has
 * no other need for a network round trip before the member even confirms
 * anything. On a successful removal, this dispatches
 * `wicket-mship-bundle-member-removed` on window; detail.php listens for it
 * to refresh the members table and tier summary.
 *
 * Unlike the admin-side RemoveFromMembershipBundleModal.js
 * (frontend/src/members/RemoveFromMembershipBundleModal.js), this
 * member-facing version offers only one outcome — the membership is kept
 * as a standalone individual membership through the bundle's current end
 * date — with no "cancel immediately" choice, per the approved design.
 * It still calls the member-scoped counterpart of the same endpoint that
 * component uses, always with mode "keep_as_individual":
 *   - POST /wicket_member/v1/bundle/{id}/remove_member/mine
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

/**
 * @var int   $bundle_post_id Set by detail.php before requiring this file.
 * @var array $block_config   Set by detail.php before requiring this file — reused
 *                             as-is (restBase/restNonce/bundlePostId already match).
 */
?>
<div
  class="wicket-mship-remove-member-modal"
  x-data="wicketRemoveMemberModal(<?php echo esc_attr( wp_json_encode( $block_config ) ); ?>)"
  x-on:wicket-mship-open-remove-member-modal.window="openModal($event.detail)"
  x-show="open"
  x-cloak
  style="display: none;"
  role="dialog"
  aria-modal="true"
  :aria-label="<?php echo esc_attr( wp_json_encode( __( 'Remove member from bundle', 'wicket-memberships' ) ) ); ?>"
  x-on:keydown.escape.window="closeModal()"
>
  <div class="wicket-mship-remove-member-modal__overlay"></div>

  <div class="wicket-mship-remove-member-modal__panel">
    <div class="wicket-mship-remove-member-modal__header">
      <h2 class="text-heading-xs" x-text="titleText()"></h2>

      <button type="button" class="wicket-mship-remove-member-modal__close" @click="closeModal()" :disabled="submitting">
        <?php esc_html_e( 'Close', 'wicket-memberships' ); ?>
        <span aria-hidden="true">✕</span>
      </button>
    </div>

    <div class="wicket-mship-remove-member-modal__body">
      <div class="wicket-mship-remove-member-modal__summary-card">
        <div class="wicket-mship-remove-member-modal__summary-field">
          <p class="wicket-mship-remove-member-modal__summary-label"><?php esc_html_e( 'Member', 'wicket-memberships' ); ?></p>
          <p class="wicket-mship-remove-member-modal__summary-value" x-text="fullName()"></p>
        </div>
        <div class="wicket-mship-remove-member-modal__summary-field">
          <p class="wicket-mship-remove-member-modal__summary-label"><?php esc_html_e( 'Email', 'wicket-memberships' ); ?></p>
          <p class="wicket-mship-remove-member-modal__summary-value" x-text="email"></p>
        </div>
        <div class="wicket-mship-remove-member-modal__summary-field">
          <p class="wicket-mship-remove-member-modal__summary-label"><?php esc_html_e( 'Tier', 'wicket-memberships' ); ?></p>
          <p class="wicket-mship-remove-member-modal__summary-value" x-text="tierName"></p>
        </div>
      </div>

      <template x-if="error">
        <p class="wicket-mship-remove-member-modal__error" x-text="error"></p>
      </template>

      <p class="wicket-mship-remove-member-modal__description" x-text="confirmText()"></p>

      <div class="wicket-mship-remove-member-modal__footer">
        <?php
        get_component( 'button', [
          'variant' => 'secondary',
          'size'    => 'sm',
          'label'   => __( 'Cancel', 'wicket-memberships' ),
          'type'    => 'button',
          'atts'    => [
            'x-on:click="closeModal()"',
            ':disabled="submitting"',
          ],
        ] );
        get_component( 'button', [
          'variant'     => 'primary',
          'size'        => 'sm',
          'label'       => '',
          'type'        => 'button',
          'prefix_icon' => 'fa-solid fa-trash',
          'classes'     => [ 'wicket-mship-remove-member-modal__confirm-btn' ],
          'atts'        => [
            'x-on:click="confirmRemove()"',
            ':disabled="submitting"',
            'x-text' => sprintf(
              'submitting ? %s : %s',
              wp_json_encode( __( 'Removing…', 'wicket-memberships' ) ),
              wp_json_encode( __( 'Remove Membership', 'wicket-memberships' ) )
            ),
          ],
        ] );
        ?>
      </div>
    </div>
  </div>
</div>

<script>
  // Global Alpine component factory — mirrors the conventions established in
  // add-member-modal.php's wicketAddMemberModal() and detail.php's own
  // wicketMembershipBundleDetail().
  function wicketRemoveMemberModal( config ) {
    return {
      restBase: config.restBase,
      restNonce: config.restNonce,
      bundlePostId: config.bundlePostId,
      mdpTimezone: config.mdpTimezone,

      // Passed in via the opening event's detail (see openModal()) rather
      // than fetched here — detail.php already has this from its own
      // bundle entity load, so no second request is needed just to fill in
      // the "kept as individual until <date>" copy below.
      bundleEndsAt: null,

      open: false,
      membershipPostId: null,
      firstName: '',
      lastName: '',
      email: '',
      tierName: '',
      error: '',
      submitting: false,

      openModal( detail ) {
        this.membershipPostId = detail?.membershipPostId ?? null;
        this.firstName = detail?.firstName ?? '';
        this.lastName = detail?.lastName ?? '';
        this.email = detail?.email ?? '';
        this.tierName = detail?.tierName ?? '';
        this.bundleEndsAt = detail?.bundleEndsAt ?? null;
        this.error = '';
        this.submitting = false;
        this.open = true;
      },

      closeModal() {
        if ( this.submitting ) {
          return;
        }
        this.open = false;
      },

      fullName() {
        return ( this.firstName + ' ' + this.lastName ).trim() || '—';
      },

      titleText() {
        const name = this.fullName();
        const suffix = this.tierName ? ' – ' + this.tierName : '';
        return <?php echo wp_json_encode( __( 'Remove', 'wicket-memberships' ) ); ?> + ' ' + name + suffix + ' ' + <?php echo wp_json_encode( __( 'from your bundle?', 'wicket-memberships' ) ); ?>;
      },

      confirmText() {
        const name = this.fullName();
        const until = this.bundleEndsAt
          ? this.formatDate( this.bundleEndsAt )
          : <?php echo wp_json_encode( __( 'the bundle\'s current end date', 'wicket-memberships' ) ); ?>;
        return <?php echo wp_json_encode( __( 'Removing', 'wicket-memberships' ) ); ?> + ' ' + name + ' ' +
          <?php echo wp_json_encode( __( 'from your bundle will continue their membership as an individual membership until', 'wicket-memberships' ) ); ?> +
          ' ' + until + '.';
      },

      async confirmRemove() {
        if ( ! this.membershipPostId || this.submitting ) {
          return;
        }

        this.submitting = true;
        this.error = '';

        try {
          const response = await fetch( this.restBase + '/bundle/' + this.bundlePostId + '/remove_member/mine', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.restNonce },
            credentials: 'same-origin',
            body: JSON.stringify( {
              membership_post_id: this.membershipPostId,
              mode: 'keep_as_individual',
            } ),
          } );

          if ( ! response.ok ) {
            const errorData = await response.json().catch( () => ( {} ) );
            throw new Error( errorData.error || ( 'Request failed with status ' + response.status ) );
          }

          this.open = false;
          window.dispatchEvent( new CustomEvent( 'wicket-mship-bundle-member-removed' ) );
        } catch ( err ) {
          this.error = err.message || <?php echo wp_json_encode( __( "We couldn't remove this member. Please try again.", 'wicket-memberships' ) ); ?>;
        } finally {
          this.submitting = false;
        }
      },

      // Mirrors detail.php's own formatDate() — duplicated rather than shared
      // since this modal is a separate Alpine component with no access to
      // detail.php's x-data scope (same rationale as add-member-modal.php's
      // own copy).
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
