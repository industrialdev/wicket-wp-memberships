<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

/**
 * Lookup helpers for WooCommerce REST API key access to the membership CPT endpoints.
 *
 * Owns every WooCommerce-key concern for the allowlist feature: enumerating the keys an
 * administrator can choose from, reading the saved allowlist, and resolving the credential on the
 * current request back to a key_id. Kept separate from Membership_Post_Types so that class keeps a
 * single responsibility.
 *
 * This class registers no hooks and performs no authentication of its own — it only answers
 * questions. WooCommerce remains the component that validates credentials.
 *
 * Every method is static and side-effect free. The filter that will consume these (Step 4) runs
 * 3+ times per request, so nothing here may mutate WooCommerce state.
 *
 * @see docs/local/WOOCOMMERCE_API_KEY_AUTH_FOR_MEMBERSHIP_CPTS.md  Design and rationale.
 *
 * @since 1.0.0
 */
class Api_Key_Access {

  /**
   * Key within the `wicket_membership_plugin_options` option array holding the allowlist.
   *
   * Exposed as a constant so the settings field (Step 2) and the filter (Step 4) cannot drift
   * apart on the spelling.
   *
   * @var string
   */
  const OPTION_KEY = 'wicket_mship_api_allowed_keys';

  /**
   * Prefix every WooCommerce consumer key carries.
   *
   * Used only as a cheap early-out before hashing and querying — it is NOT an access control.
   * The allowlist is the access control. Without this test, every Application Password request to
   * a membership route would pointlessly hash a WP username and query the key table.
   *
   * @var string
   */
  const CONSUMER_KEY_PREFIX = 'ck_';

  /**
   * Per-request memo for get_allowed_key_ids(), keyed by nothing (one allowlist per request).
   *
   * Null means "not yet resolved". See get_allowed_key_ids() for why this exists.
   *
   * @var array<int, int>|null
   */
  private static $allowed_key_ids_cache = null;

  /**
   * Whether WooCommerce is loaded far enough for any of this to be meaningful.
   *
   * Tests for `wc_api_hash()` specifically rather than a WooCommerce class, because that function
   * is the one piece of WooCommerce this class actually calls. If it is missing there is no key
   * table to read and no way to hash a credential, so every public method degrades to "nothing".
   *
   * @return bool  True when WooCommerce's API-key helpers are available.
   */
  private static function woocommerce_available() {
    return function_exists( 'wc_api_hash' );
  }

  /**
   * Fully-qualified name of the WooCommerce API keys table.
   *
   * @global \wpdb  $wpdb  WordPress database abstraction object.
   *
   * @return string  Prefixed table name, e.g. `wp_woocommerce_api_keys`.
   */
  private static function keys_table() {
    global $wpdb;

    return $wpdb->prefix . 'woocommerce_api_keys';
  }

  /**
   * List the WooCommerce REST API keys an administrator can choose from.
   *
   * Deliberately never selects `consumer_key` (the stored hash) or `consumer_secret` — this data
   * is bound for an admin screen and key material must not reach the browser.
   *
   * The `description` column is the human-visible "name" in WooCommerce, but it is free text,
   * editable and non-unique, so `key_id` is the identifier callers should store. Owner details are
   * returned alongside so the settings UI can show which user a key resolves to: an allowlisted key
   * owned by a non-admin is rejected later by the `manage_options` gate, and that needs to be
   * visible at selection time rather than discovered as a 401.
   *
   * @global \wpdb  $wpdb  WordPress database abstraction object.
   *
   * @return array<int, array{key_id:int, user_id:int, description:string, permissions:string, truncated_key:string, owner_name:string, owner_login:string, owner_roles:string[]}>
   *         One entry per key, ordered by description. Empty array when WooCommerce is inactive.
   */
  public static function get_available_keys() {
    if ( ! self::woocommerce_available() ) {
      return array();
    }

    global $wpdb;

    // Column list is explicit and excludes consumer_key / consumer_secret by design.
    $rows = $wpdb->get_results(
      'SELECT key_id, user_id, description, permissions, truncated_key
         FROM ' . self::keys_table() . '
        ORDER BY description ASC'
    );

    if ( empty( $rows ) ) {
      return array();
    }

    $keys = array();

    foreach ( $rows as $row ) {
      $owner = get_userdata( (int) $row->user_id );

      $keys[] = array(
        'key_id'        => (int) $row->key_id,
        'user_id'       => (int) $row->user_id,
        // WooCommerce leaves description empty when the admin doesn't type one.
        'description'   => (string) $row->description,
        'permissions'   => (string) $row->permissions,
        'truncated_key' => (string) $row->truncated_key,
        // Owner may be false if the WP user was deleted but the key row survived.
        'owner_name'    => $owner ? $owner->display_name : '',
        'owner_login'   => $owner ? $owner->user_login : '',
        'owner_roles'   => $owner ? (array) $owner->roles : array(),
      );
    }

    return $keys;
  }

  /**
   * The saved allowlist of key_ids, filtered down to keys that still exist.
   *
   * WooCommerce fires no action when a key is revoked, so the stored option accumulates IDs for
   * keys that are long gone. Intersecting against the live table on every read means a revoked key
   * can never grant access, and a future key that happened to reuse the ID could not inherit the
   * old approval.
   *
   * Memoised for the duration of the request: the filter that consumes this (Step 4) is invoked
   * 3+ times per request via WooCommerce's is_request_to_rest_api(), and this would otherwise
   * repeat the same query each time.
   *
   * @global \wpdb  $wpdb  WordPress database abstraction object.
   *
   * @return array<int, int>  Approved key_ids that currently exist. Empty array means the feature
   *                          is effectively off, which is the safe default.
   */
  public static function get_allowed_key_ids() {
    if ( null !== self::$allowed_key_ids_cache ) {
      return self::$allowed_key_ids_cache;
    }

    if ( ! self::woocommerce_available() ) {
      self::$allowed_key_ids_cache = array();

      return self::$allowed_key_ids_cache;
    }

    $options = get_option( 'wicket_membership_plugin_options' );
    $saved   = isset( $options[ self::OPTION_KEY ] ) ? (array) $options[ self::OPTION_KEY ] : array();

    // absint() then drop zeros so a stray empty value can never be read as key_id 0.
    $saved = array_values( array_filter( array_map( 'absint', $saved ) ) );

    if ( empty( $saved ) ) {
      self::$allowed_key_ids_cache = array();

      return self::$allowed_key_ids_cache;
    }

    global $wpdb;

    // Placeholders are built from the count of already-absint'ed values, so the IN list is safe.
    $placeholders = implode( ',', array_fill( 0, count( $saved ), '%d' ) );

    $live = $wpdb->get_col(
      $wpdb->prepare(
        'SELECT key_id FROM ' . self::keys_table() . " WHERE key_id IN ( {$placeholders} )",
        $saved
      )
    );

    self::$allowed_key_ids_cache = array_values( array_map( 'absint', (array) $live ) );

    return self::$allowed_key_ids_cache;
  }

  /**
   * The WooCommerce consumer key presented on the current request, if any.
   *
   * Mirrors WooCommerce's own credential precedence in perform_basic_authentication()
   * (`class-wc-rest-authentication.php:187-197`) exactly, including its requirement that the
   * key and secret arrive together: query-string credentials win, and the Basic header is used
   * only when the query string did not supply a complete pair. Diverging from that would risk
   * authorising one key while WooCommerce authenticates a different one.
   *
   * OAuth 1.0a is intentionally not consulted — Basic over TLS is the only supported transport.
   *
   * @return string|null  The raw consumer key, or null when the request presents none.
   */
  public static function get_request_consumer_key() {
    $consumer_key = '';

    // WooCommerce only accepts query-string credentials when BOTH halves are present.
    if ( ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ) {
      $consumer_key = sanitize_text_field( wp_unslash( $_GET['consumer_key'] ) );
    }

    // Falls back to full Basic auth, again only when both halves are present.
    if ( '' === $consumer_key && ! empty( $_SERVER['PHP_AUTH_USER'] ) && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
      $consumer_key = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
    }

    if ( '' === $consumer_key ) {
      return null;
    }

    // Cheap early-out: skip hashing and querying for credentials that cannot be WooCommerce keys
    // (an Application Password sends a WP username in this same slot).
    if ( 0 !== strpos( $consumer_key, self::CONSUMER_KEY_PREFIX ) ) {
      return null;
    }

    return $consumer_key;
  }

  /**
   * Resolve the current request's consumer key to its key_id.
   *
   * Hashes with wc_api_hash() — the same transform WooCommerce applies before its own lookup, so
   * the stored hash matches — and reads only the primary key back.
   *
   * @global \wpdb  $wpdb  WordPress database abstraction object.
   *
   * @return int|null  The key_id, or null when no key is presented or it matches no stored key.
   */
  public static function get_key_id_for_request() {
    if ( ! self::woocommerce_available() ) {
      return null;
    }

    $consumer_key = self::get_request_consumer_key();

    if ( null === $consumer_key ) {
      return null;
    }

    global $wpdb;

    $key_id = $wpdb->get_var(
      $wpdb->prepare(
        'SELECT key_id FROM ' . self::keys_table() . ' WHERE consumer_key = %s',
        wc_api_hash( $consumer_key )
      )
    );

    return null === $key_id ? null : (int) $key_id;
  }

  /**
   * Whether the current request carries OAuth 1.0a parameters.
   *
   * Used to refuse OAuth outright: Basic over TLS is the only supported transport, and without
   * this check a caller could pass an allowlisted key in the query string, fail Basic for want of
   * a secret, and still be authenticated by WooCommerce's OAuth fallback.
   *
   * Deliberately does NOT call WC_REST_Authentication::get_oauth_parameters(). That method looks
   * like the obvious helper and is public, but it calls set_error() when OAuth parameters are only
   * partially present (`class-wc-rest-authentication.php:334`), which nulls WooCommerce's resolved
   * user and plants a 401. Since the consuming filter runs several times per request, calling it
   * would corrupt authentication for requests unrelated to memberships — including WooCommerce's
   * own routes.
   *
   * Checks the same three sources WooCommerce merges (`:286-298`); `$_REQUEST` alone would miss
   * the Authorization header form.
   *
   * @see \Wicket_Memberships\Api_Key_Access::get_request_consumer_key()  Basic-credential counterpart.
   *
   * @return bool  True when the request looks like an OAuth 1.0a attempt.
   */
  public static function request_has_oauth_params() {
    if ( ! empty( $_GET['oauth_consumer_key'] ) || ! empty( $_POST['oauth_consumer_key'] ) ) {
      return true;
    }

    $header = self::get_authorization_header();

    // Matches WooCommerce's own test in parse_header() — a Basic header begins "Basic ", so it
    // cannot be mistaken for an OAuth one.
    return 'OAuth ' === substr( $header, 0, 6 );
  }

  /**
   * Read the Authorization header.
   *
   * Prefers WooCommerce's own getter, which is public and pure (it only reads
   * `$_SERVER['HTTP_AUTHORIZATION']` and getallheaders()), so header handling stays identical to
   * the code we are gating. Falls back to reading the superglobal directly when WooCommerce is not
   * loaded, which keeps this class usable without a hard dependency.
   *
   * @return string  Header value, or an empty string when absent.
   */
  private static function get_authorization_header() {
    if ( class_exists( '\WC_REST_Authentication' ) ) {
      return (string) \WC_REST_Authentication::instance()->get_authorization_header();
    }

    if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
      return (string) wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
    }

    return '';
  }
}
