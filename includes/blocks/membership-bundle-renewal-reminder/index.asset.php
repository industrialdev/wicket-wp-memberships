<?php
/**
 * Manual dependency/version manifest for index.js.
 *
 * No build step for this block (same as membership-bundles-list), so this file
 * stands in for the .asset.php that build tooling would normally generate.
 * WordPress reads it automatically when registering a script referenced via
 * block.json's "editorScript" (file:./index.js).
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

return [
  'dependencies' => [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render', 'wp-components', 'wp-i18n' ],
  'version'      => (string) filemtime( __DIR__ . '/index.js' ),
];
