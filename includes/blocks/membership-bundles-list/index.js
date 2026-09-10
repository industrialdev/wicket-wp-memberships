/**
 * Editor registration for wicket-memberships/membership-bundles-list.
 *
 * Plain global-`wp` script, no build step (no JSX/webpack) — matches the rest
 * of this block, which is otherwise pure PHP. This file exists only so the
 * block reliably shows up in the inserter with a live preview; it registers
 * no attributes and has nothing of its own to save (save() returns null —
 * the front end is rendered entirely by render.php via the block.json
 * "render" key, not from serialized block content).
 *
 * Dependencies (wp-blocks, wp-element, wp-block-editor, wp-server-side-render)
 * are declared in the sibling index.asset.php, which WordPress reads
 * automatically when a script is registered via block.json's "editorScript".
 */
( function ( blocks, element, blockEditor, ServerSideRender ) {
  var el = element.createElement;

  blocks.registerBlockType( 'wicket-memberships/membership-bundles-list', {
    edit: function ( props ) {
      var blockProps = blockEditor.useBlockProps( { className: 'wicket-mship-bundle-list-editor-preview' } );

      return el(
        'div',
        blockProps,
        el( ServerSideRender, {
          block: 'wicket-memberships/membership-bundles-list',
          attributes: props.attributes,
        } )
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
  window.wp.serverSideRender
);
