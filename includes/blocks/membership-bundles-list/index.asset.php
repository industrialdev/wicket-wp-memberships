<?php
/**
 * Manual dependency/version manifest for index.js.
 *
 * There is no build step (webpack/@wordpress/scripts) for this block, so this
 * file stands in for the .asset.php that build tooling would normally
 * generate. WordPress reads it automatically when registering a script
 * referenced via block.json's "editorScript" (file:./index.js) — see
 * WP_Block_Type::register_block_script_handle().
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

return [
  'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ],
  'version'      => (string) filemtime( __DIR__ . '/index.js' ),
];
