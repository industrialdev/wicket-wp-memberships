/**
 * Editor registration for wicket-memberships/membership-bundle-coverage-callout.
 *
 * Plain global-`wp` script, no build step — same approach as the
 * membership-bundles-list block. The front end is rendered entirely by
 * render.php via block.json's "render" key; this file only provides the
 * sidebar fields (block attributes) and a live preview.
 *
 * Fields:
 * - heading / message: wording with placeholders. Left empty, render.php uses
 *   the default wording (shown as the field placeholder here).
 * - siteName: value for {site_name}; empty = the WordPress site title.
 * - siteContactLink: URL or mailto: for {site_name_contact_link}, which renders
 *   the site name as a link.
 * {organization_name} and {bundle_owner_name} differ per member, so they are
 * always filled from the member's bundle and are not editable here.
 *
 * The preview uses ServerSideRender, which renders as the editing user. Most
 * editors are not covered by a membership bundle, so render.php returns
 * nothing for them — EmptyResponsePlaceholder shows a description instead.
 */
( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
  var el = element.createElement;
  var __ = i18n.__;

  var DEFAULT_HEADING = __( '{organization_name} is currently paying for your membership.', 'wicket-memberships' );
  var DEFAULT_MESSAGE = __( 'If you no longer work here, please contact {site_name_contact_link} or {bundle_owner_name} to update this.', 'wicket-memberships' );
  var PLACEHOLDER_HELP = __(
    'Variables: {organization_name}, {bundle_owner_name} (filled from the member\'s bundle), {site_name}, {site_name_contact_link} (site name as a link). Leave empty to use the default wording.',
    'wicket-memberships'
  );

  function EmptyPlaceholder() {
    return el(
      'div',
      { className: 'wicket-mship-bundle-coverage-callout-editor-placeholder' },
      el( 'strong', null, __( 'Membership Bundle Coverage Callout', 'wicket-memberships' ) ),
      el(
        'p',
        null,
        __(
          'Shown only to members whose membership is paid for by a membership bundle. Edit the wording in the block settings sidebar.',
          'wicket-memberships'
        )
      )
    );
  }

  blocks.registerBlockType( 'wicket-memberships/membership-bundle-coverage-callout', {
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
            { title: __( 'Callout wording', 'wicket-memberships' ), initialOpen: true },
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
              help: PLACEHOLDER_HELP,
              onChange: bind( 'message' ),
            } )
          ),
          el(
            components.PanelBody,
            { title: __( 'Site contact', 'wicket-memberships' ), initialOpen: true },
            el( components.TextControl, {
              label: __( 'Site name', 'wicket-memberships' ),
              value: attributes.siteName,
              help: __( 'Used for {site_name} and {site_name_contact_link}. Leave empty to use the site title.', 'wicket-memberships' ),
              onChange: bind( 'siteName' ),
            } ),
            el( components.TextControl, {
              label: __( 'Site contact link', 'wicket-memberships' ),
              value: attributes.siteContactLink,
              placeholder: 'mailto:membership@example.com',
              help: __( 'A URL or mailto: address. {site_name_contact_link} links the site name here; without a link it shows the site name as plain text.', 'wicket-memberships' ),
              onChange: bind( 'siteContactLink' ),
            } )
          )
        ),
        el(
          'div',
          blockProps,
          el( ServerSideRender, {
            block: 'wicket-memberships/membership-bundle-coverage-callout',
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
