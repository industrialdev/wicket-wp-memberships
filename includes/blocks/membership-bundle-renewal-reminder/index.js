/**
 * Editor registration for wicket-memberships/membership-bundle-renewal-reminder.
 *
 * Plain global-`wp` script, no build step — same approach as the other
 * membership bundle blocks. The front end is rendered entirely by render.php
 * via block.json's "render" key; this file only provides the sidebar fields
 * (block attributes) and a live preview.
 *
 * Fields:
 * - heading / message / buttonLabel: wording. Left empty, render.php uses the
 *   default wording, which is pluralised and switches to "overdue" wording
 *   when any bundle is in its grace period. Custom wording is used for both
 *   states; {bundle_count} is replaced with the number of bundles due.
 * - showBundleList: list each due bundle (name, status, date) in the card.
 *
 * The button always links to the "Manage Membership Bundles" page set in
 * Settings > Wicket Memberships > Membership Bundles.
 *
 * The preview uses ServerSideRender, which renders as the editing user. Most
 * editors own no bundle that is due, so render.php returns nothing for them —
 * EmptyResponsePlaceholder shows a description instead.
 */
( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
  var el = element.createElement;
  var __ = i18n.__;

  var DEFAULT_HEADING = __( 'You have {bundle_count} membership bundles due for renewal.', 'wicket-memberships' );
  var DEFAULT_MESSAGE = __( 'Review the members in each bundle and renew it from the Manage Group Membership page.', 'wicket-memberships' );
  var DEFAULT_BUTTON = __( 'Review bundles', 'wicket-memberships' );
  var WORDING_HELP = __(
    'Variable: {bundle_count} (number of bundles due). Leave empty to use the default wording, which adapts to one or several bundles and to overdue (grace period) renewals.',
    'wicket-memberships'
  );

  function EmptyPlaceholder() {
    return el(
      'div',
      { className: 'wicket-mship-bundle-renewal-reminder-editor-placeholder' },
      el( 'strong', null, __( 'Membership Bundle Renewal Reminder', 'wicket-memberships' ) ),
      el(
        'p',
        null,
        __(
          'Shown only to bundle owners with one or more membership bundles due for renewal. Links to the Manage Membership Bundles page. Edit the wording in the block settings sidebar.',
          'wicket-memberships'
        )
      )
    );
  }

  blocks.registerBlockType( 'wicket-memberships/membership-bundle-renewal-reminder', {
    edit: function ( props ) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = blockEditor.useBlockProps();

      function bind( key ) {
        return function ( value ) {
          var update = {};
          update[ key ] = value;
          setAttributes( update );
        };
      }

      return el(
        element.Fragment,
        null,
        el(
          blockEditor.InspectorControls,
          null,
          el(
            components.PanelBody,
            { title: __( 'Reminder wording', 'wicket-memberships' ), initialOpen: true },
            el( components.TextControl, {
              label: __( 'Heading', 'wicket-memberships' ),
              value: attributes.heading,
              placeholder: DEFAULT_HEADING,
              onChange: bind( 'heading' ),
            } ),
            el( components.TextareaControl, {
              label: __( 'Message', 'wicket-memberships' ),
              value: attributes.message,
              placeholder: DEFAULT_MESSAGE,
              help: WORDING_HELP,
              onChange: bind( 'message' ),
            } ),
            el( components.TextControl, {
              label: __( 'Button label', 'wicket-memberships' ),
              value: attributes.buttonLabel,
              placeholder: DEFAULT_BUTTON,
              help: __( 'Links to the Manage Membership Bundles page (Settings > Wicket Memberships > Membership Bundles).', 'wicket-memberships' ),
              onChange: bind( 'buttonLabel' ),
            } )
          ),
          el(
            components.PanelBody,
            { title: __( 'Display', 'wicket-memberships' ), initialOpen: true },
            el( components.ToggleControl, {
              label: __( 'List the bundles that are due', 'wicket-memberships' ),
              help: __( 'Shows each bundle with its status and renewal date, linked to its detail view.', 'wicket-memberships' ),
              checked: !! attributes.showBundleList,
              onChange: bind( 'showBundleList' ),
            } )
          )
        ),
        el(
          'div',
          blockProps,
          el( ServerSideRender, {
            block: 'wicket-memberships/membership-bundle-renewal-reminder',
            attributes: attributes,
            EmptyResponsePlaceholder: EmptyPlaceholder,
          } )
        )
      );
    },
    save: function () {
      // Fully dynamic block — front end always comes from render.php.
      return null;
    },
  } );
} )(
  window.wp.blocks,
  window.wp.element,
  window.wp.blockEditor,
  window.wp.components,
  window.wp.serverSideRender,
  window.wp.i18n
);
