<?php
namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Api_Key_Access;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Membership_Post_Types {

  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';
  private $membership_bundle_cpt_slug = '';
  private $membership_bundle_config_cpt_slug = '';


  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
    $this->membership_bundle_cpt_slug = Helper::get_membership_bundle_cpt_slug();
    $this->membership_bundle_config_cpt_slug = Helper::get_membership_bundle_config_cpt_slug();
    add_action('init', [ $this, 'register_membership_post_type' ]);
    add_action('init', [ $this, 'register_membership_config_post_type' ]);
    add_action('init', [ $this, 'register_membership_tier_post_type' ]);
    add_action('init', [ $this, 'register_membership_bundle_post_type' ]);
    add_action('init', [ $this, 'register_membership_bundle_config_post_type' ]);

    // Register the fields to the REST API and validate the data
    add_action('rest_api_init', [ $this, 'register_membership_config_cpt_fields' ]);
    add_action('rest_api_init', [ $this, 'register_membership_tier_cpt_fields' ]);
    add_action('rest_api_init', [ $this, 'register_membership_bundle_config_cpt_fields' ]);

    // Gate the membership CPT REST endpoints to authenticated administrators
    add_filter('rest_pre_dispatch', [ $this, 'restrict_membership_rest_access' ], 10, 3);

    // Let WooCommerce authenticate approved API keys on those same endpoints. Does nothing until
    // a key is selected on the settings page, and nothing if WooCommerce is inactive.
    add_filter('woocommerce_rest_is_request_to_rest_api', [ $this, 'allow_woocommerce_key_auth' ]);
  }

  /**
   * The wp/v2 REST base routes for all four membership post types.
   *
   * Single source of truth for "which routes are ours", used by the capability gate. The API key
   * route list is derived from it, so a route can never be key-reachable without being gated.
   *
   * @see is_protected_rest_route()  Matcher for the parsed-route form.
   * @see get_api_key_rest_bases()   The subset WooCommerce API keys may reach.
   *
   * @return string[]  Collection routes, e.g. `/wp/v2/wicket_membership`.
   */
  private function get_protected_rest_bases() {
    return array(
      '/wp/v2/' . $this->membership_cpt_slug,
      '/wp/v2/' . $this->membership_config_cpt_slug,
      '/wp/v2/' . $this->membership_tier_cpt_slug,
      // Bundle config was left off this list when it was originally added — same
      // show_in_rest=>true CPT shape as the other three, same unauthenticated exposure.
      '/wp/v2/' . $this->membership_bundle_config_cpt_slug,
    );
  }

  /**
   * The wp/v2 REST base routes that allowlisted WooCommerce API keys may reach.
   *
   * Bundle data is excluded: a bundle is a composite of the bundle post, its owner, subscription,
   * config and member records, so exposing it to keys is a separate requirement.
   *
   * @see atlas://proposals/feature-membership-bundle-api-key-access.md
   * @see is_api_key_rest_request_uri()  The only consumer.
   *
   * @return string[]  Collection routes, e.g. `/wp/v2/wicket_membership`.
   */
  private function get_api_key_rest_bases() {
    return array_values( array_diff(
      $this->get_protected_rest_bases(),
      array( '/wp/v2/' . $this->membership_bundle_config_cpt_slug )
    ) );
  }

  /**
   * Whether a parsed REST route targets one of the membership post types.
   *
   * Matches the collection route, or any sub-route beneath it (e.g. `/<base>/123`), so a single
   * record is protected as tightly as the list. The match uses the same rules as WP_REST_Server:
   * it ignores letter case, and `$` also matches before a final newline. An exact string compare
   * let `/wp/v2/WICKET_MEMBERSHIP` and `/wp/v2/wicket_membership%0A` past the gate, while the
   * server still dispatched them to the membership handlers.
   *
   * @param  string         $route  Route as reported by WP_REST_Request::get_route(), e.g. `/wp/v2/foo`.
   * @param  string[]|null  $bases  Base routes to match; defaults to get_protected_rest_bases().
   *
   * @return bool  True when the route belongs to one of the base routes.
   */
  private function is_protected_rest_route( $route, $bases = null ) {
    if ( ! is_string( $route ) || '' === $route ) {
      return false;
    }

    foreach ( null === $bases ? $this->get_protected_rest_bases() : $bases as $base ) {
      // Same delimiters and flags as WP_REST_Server::match_request_to_handler(): `i`, and no `D`.
      if ( preg_match( '@^' . preg_quote( $base, '@' ) . '(?:/.*)?$@i', $route ) ) {
        return true;
      }
    }

    return false;
  }

  /**
   * The REST route WordPress dispatches for the current request.
   *
   * Needed because WooCommerce's `woocommerce_rest_is_request_to_rest_api` filter receives no
   * arguments and has no WP_REST_Request to ask.
   *
   * Returns a route only for a request that WordPress serves as REST, read from the `rest_route`
   * query var that WordPress dispatches. WooCommerce authenticates on `determine_current_user` for
   * every request, but the key's read/write limit and restrict_membership_rest_access() run only on
   * REST dispatch. A route returned for any other request (admin-ajax, admin-post, or a `/wp-json/`
   * path on a plain-permalink site) would log the key in as its owner with no limit.
   *
   * On the first `determine_current_user` call, WordPress has not parsed the request yet, so the
   * route is empty. WooCommerce runs authenticate() again from `rest_authentication_errors`, inside
   * REST dispatch, when the route is known.
   *
   * wp_is_rest_endpoint() includes wp_is_serving_rest_request() and is also true during an internal
   * rest_do_request(). That adds no route: WordPress sets the `rest_route` query var only on a
   * request it serves as REST.
   *
   * @return string  Route with a leading slash, e.g. `/wp/v2/wicket_membership`; empty string when
   *                 the request is not a REST request.
   */
  private function get_requested_rest_route() {
    global $wp;

    if ( ! wp_is_rest_endpoint() || ! ( $wp instanceof \WP ) || empty( $wp->query_vars['rest_route'] ) || ! is_string( $wp->query_vars['rest_route'] ) ) {
      return '';
    }

    // Only compared, never output. Sanitizing could make it differ from the dispatched route.
    return '/' . ltrim( $wp->query_vars['rest_route'], '/' );
  }

  /**
   * Whether the current request URI targets a route that WooCommerce API keys may reach.
   *
   * URI-based counterpart to is_protected_rest_route(), for callers that have no WP_REST_Request.
   *
   * @see get_api_key_rest_bases()
   *
   * @return bool  True when the raw request URI resolves to a key-reachable membership route.
   */
  private function is_api_key_rest_request_uri() {
    return $this->is_protected_rest_route( $this->get_requested_rest_route(), $this->get_api_key_rest_bases() );
  }

  /**
   * Let WooCommerce authenticate an approved API key on the membership CPT endpoints.
   *
   * WooCommerce refuses to authenticate anything outside its own namespaces: authenticate()
   * returns early unless is_request_to_rest_api() is true, which only matches URIs containing
   * `wp-json/wc/` or `wp-json/wc-`. A consumer key sent to `/wp/v2/wicket_membership` is therefore
   * never even inspected, and the request reaches restrict_membership_rest_access() as an
   * anonymous user. This filter is the sole seam WooCommerce provides for widening that.
   *
   * Returning true here does not grant access — it only lets WooCommerce *attempt* authentication.
   * The key's secret, its read/read_write scope, and the caller's `manage_options` capability are
   * all still enforced downstream, unchanged.
   *
   * Conditions are ordered cheapest-first; only the last touches the database. The filter runs on
   * most requests (WooCommerce calls it from `determine_current_user`), so the common path must
   * exit on a superglobal read.
   *
   * Never returns a hard false: the incoming value is passed through on every failure, so
   * WooCommerce's own `true` for genuine `wc/` routes survives untouched.
   *
   * @see \Wicket_Memberships\Api_Key_Access                     Key lookup and credential parsing.
   * @see restrict_membership_rest_access()                      The capability gate this feeds.
   * @see atlas://packages/wicket-wp-memberships/engineering/membership-cpt-rest-access.md  The full contract.
   *
   * @param  bool  $is_wc_request  WooCommerce's own determination for this request.
   *
   * @return bool  True to let WooCommerce authenticate this request; otherwise $is_wc_request.
   */
  public function allow_woocommerce_key_auth( $is_wc_request ) {
    // Already one of WooCommerce's own routes — nothing to add, and we must never downgrade it.
    if ( $is_wc_request ) {
      return $is_wc_request;
    }

    // 1. SSL only. WooCommerce would otherwise fall through to OAuth 1.0a over plain HTTP; by
    // failing here the request never reaches that branch at all.
    if ( ! is_ssl() ) {
      return $is_wc_request;
    }

    // 2. A WooCommerce-shaped credential is present. Cheap early-out that keeps Application
    // Password requests (which occupy the same Basic header slot) off the rest of this path.
    if ( null === Api_Key_Access::get_request_consumer_key() ) {
      return $is_wc_request;
    }

    // 3. The request targets a key-reachable membership route (bundle data excluded).
    if ( ! $this->is_api_key_rest_request_uri() ) {
      return $is_wc_request;
    }

    // 4. Refuse OAuth outright — Basic over TLS is the only supported transport.
    if ( Api_Key_Access::request_has_oauth_params() ) {
      return $is_wc_request;
    }

    // 5. The key is on the administrator's allowlist. Only this step queries.
    $key_id = Api_Key_Access::get_key_id_for_request();

    if ( null === $key_id || ! in_array( $key_id, Api_Key_Access::get_allowed_key_ids(), true ) ) {
      return $is_wc_request;
    }

    return true;
  }

  /**
   * Restrict the auto-generated wp/v2 REST endpoints for the membership post
   * types to authenticated administrators.
   *
   * Covers all four membership post types (membership records, configs, tiers and bundle
   * configs, all registered with 'show_in_rest' => true), both their collection routes and
   * single-record sub-routes.
   *
   * Every caller must be logged in and hold 'manage_options'. A request that presents a
   * WooCommerce API key must also use an allowlisted key on a key-reachable route, and may only
   * read; this holds however WooCommerce came to authenticate the key. OAuth-signed requests are
   * refused, because the key in them cannot be checked.
   *
   * @see get_protected_rest_bases()  Shared route list, also used by the URI-based matcher.
   * @see get_api_key_rest_bases()    The subset WooCommerce API keys may reach.
   *
   * @param mixed            $result  Response to replace the requested version with, or null.
   * @param \WP_REST_Server  $server  Server instance.
   * @param \WP_REST_Request $request Request used to generate the response.
   * @return mixed
   */
  public function restrict_membership_rest_access( $result, $server, $request ) {
    // An earlier hook already produced a response/error; leave it untouched.
    if ( ! empty( $result ) ) {
      return $result;
    }

    $route = $request->get_route();

    if ( ! $this->is_protected_rest_route( $route ) ) {
      return $result;
    }

    // get_key_id_for_request() finds only Basic and query-string keys. WooCommerce also accepts
    // OAuth-signed keys on a wc/ URI, and a `rest_route` override could bring one here unchecked.
    if ( Api_Key_Access::request_has_oauth_params() ) {
      return new WP_Error(
        'rest_forbidden',
        __( 'This API key has no access to this endpoint.', 'wicket-memberships' ),
        array( 'status' => rest_authorization_required_code() )
      );
    }

    // A WooCommerce key passes only when it is allowlisted and the route is key-reachable. This
    // check sees the dispatched route. allow_woocommerce_key_auth() sees only the request URI, and
    // WooCommerce authenticates any key on a wc/ URI, so a `rest_route` override could otherwise
    // reach these routes with any admin-owned key. Keys only read: a wp/v2 write skips the
    // plugin's own membership logic (MDP sync, status changes, subscription links).
    $key_id = Api_Key_Access::get_key_id_for_request();

    if ( null !== $key_id && (
      ! in_array( $key_id, Api_Key_Access::get_allowed_key_ids(), true )
      || ! $this->is_protected_rest_route( $route, $this->get_api_key_rest_bases() )
      || ! in_array( $request->get_method(), array( 'GET', 'HEAD' ), true )
    ) ) {
      return new WP_Error(
        'rest_forbidden',
        __( 'This API key has no access to this endpoint.', 'wicket-memberships' ),
        array( 'status' => rest_authorization_required_code() )
      );
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
      return new WP_Error(
        'rest_forbidden',
        __( 'You must be an authenticated administrator to access this endpoint.', 'wicket-memberships' ),
        array( 'status' => rest_authorization_required_code() )
      );
    }

    return $result;
  }

  /**
   * Register and validate rest fields for the membership config post type
   */
  public function register_membership_config_cpt_fields() {
    // Renewal Window Data
    $field = 'renewal_window_data';

    register_rest_field(
      $this->membership_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Renewal Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The renewal window data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( intval( $value['days_count'] ) < 1 ) {
                $errors->add( 'rest_invalid_param_days_count', __( 'The renewal window days count must be greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // Validate locales to be not empty
              $language_codes = Helper::get_wp_languages_iso();
              $locales_valid = true;
              foreach ( $language_codes as $language_code ) {
                if ( empty( $value['locales'][ $language_code ]['callout_header'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_content'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_button_label'] ) ) {
                  $locales_valid = false;
                }
              }

              if ( $locales_valid === false ) {
                $errors->add( 'rest_invalid_param_locales', __( 'The renewal window callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'days_count'           => array(
              'type'        => 'integer',
              'description' => 'The number of days before the end of the membership that the renewal window starts',
            ),
            'locales' => array(
              'type'        => 'object',
              'description' => 'Localized renewal window callout data',
              'properties'  => array(
                'type'        => 'object',
                'properties'  => array(
                  'callout_header'       => array(
                    'type'        => 'string',
                    'description' => 'The localized header for the renewal window callout',
                  ),
                  'callout_content'      => array(
                    'type'        => 'string',
                    'description' => 'The localized content for the renewal window callout',
                  ),
                  'callout_button_label' => array(
                    'type'        => 'string',
                    'description' => 'The localized label for the renewal window callout button',
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

    // Late Fee Window Data
    $field = 'late_fee_window_data';

    register_rest_field(
      $this->membership_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Late Fee Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The grace period window data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( ! isset( $value['days_count'] ) || intval( $value['days_count'] ) < 0 ) {
                $errors->add( 'rest_invalid_param_days_count', __( 'The grace period window days count must be equal or greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( isset( $value['product_id'] ) && intval( $value['product_id'] ) > 0 ) {
                $wc_product = new \WC_Product( $value['product_id'] );
                if ( $wc_product->exists() === false ) {
                  $errors->add( 'rest_invalid_param_product_id', __( 'The grace period window product must be a valid product.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // Validate locales to be not empty
              $language_codes = Helper::get_wp_languages_iso();
              $locales_valid = true;
              foreach ( $language_codes as $language_code ) {
                if ( empty( $value['locales'][ $language_code ]['callout_header'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_content'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_button_label'] ) ) {
                  $locales_valid = false;
                }
              }

              if ( $locales_valid === false ) {
                $errors->add( 'rest_invalid_param_locales', __( 'The grace period window callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'days_count' => array(
              'type' => 'integer',
            ),
            'product_id' => array(
              'type' => 'integer',
            ),
            'locales' => array(
              'type'        => 'object',
              'description' => 'Localized late fee window callout data',
              'properties'  => array(
                'type'        => 'object',
                'properties'  => array(
                  'callout_header'       => array(
                    'type'        => 'string',
                    'description' => 'The localized header for the late fee window callout',
                  ),
                  'callout_content'      => array(
                    'type'        => 'string',
                    'description' => 'The localized content for the late fee window callout',
                  ),
                  'callout_button_label' => array(
                    'type'        => 'string',
                    'description' => 'The localized label for the late fee window callout button',
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

    // Cycle Data
    $field = 'cycle_data';

    register_rest_field(
      $this->membership_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Late Fee Window Data',
          'arg_options' => [
            'sanitize_callback' => function( $value ) {
              if ( is_array( $value ) && ( $value['cycle_type'] ?? null ) === 'calendar' && is_array( $value['calendar_items'] ?? null ) ) {
                $value['calendar_items'] = $this->normalize_calendar_season_dates( $value['calendar_items'] );
              }
              return $value;
            },
            'validate_callback' => function( $value ) {
              $errors = new WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The cycle data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( ! in_array( $value['cycle_type'], [ 'calendar', 'anniversary' ] ) ) {
                $errors->add( 'rest_invalid_param_cycle_type', __( 'The cycle type must be either calendar or anniversary.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $value['cycle_type'] === 'anniversary' ) {
                if ( intval( $value['anniversary_data']['period_count'] ) < 1 ) {
                  $errors->add( 'rest_invalid_param_anniversary_period_count', __( 'The anniversary period count must be greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! in_array( $value['anniversary_data']['period_type'], [ 'year', 'month', 'week' ] ) ) {
                  $errors->add( 'rest_invalid_param_anniversary_period_type', __( 'The anniversary period type must be year, month, or week.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( $value['anniversary_data']['align_end_dates_enabled'] === true ) {
                  if ( ! in_array( $value['anniversary_data']['align_end_dates_type'], [ 'first-day-of-month', '15th-of-month', 'last-day-of-month' ] ) ) {
                    $errors->add( 'rest_invalid_param_anniversary_align_end_dates_type', __( 'The anniversary align end dates type must be first-day-of-month, 15th-of-month, or last-day-of-month.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }
              }

              if ( $value['cycle_type'] === 'calendar' ) {

                if ( ! is_array( $value['calendar_items'] ) ) {
                  $errors->add( 'rest_invalid_param_calendar_items', __( 'The calendar items must be an array.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                // Normalize dates for validation so legacy/partial season data
                // (e.g. bare Y-m-d dates missing a time component) is checked
                // against the same day boundaries that will actually be stored.
                $value['calendar_items'] = $this->normalize_calendar_season_dates( $value['calendar_items'] );

                if ( count( $value['calendar_items'] ) < 1 ) {
                  $errors->add( 'rest_invalid_param_calendar_items', __( 'At least one season item must be defined.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                $active_seasons = array_filter( $value['calendar_items'], function( $item ) {
                  return $item['active'] === true;
                });

                if ( count( $active_seasons ) < 1 ) {
                  $errors->add( 'rest_invalid_param_calendar_active_seasons', __( 'At least one season must be active.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                // Validate each season item
                foreach ( $value['calendar_items'] as $item ) {
                  if ( empty( $item['season_name'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_season_name', __( 'The calendar season name must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( empty( $item['start_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_start_date', __( 'The calendar start date must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( empty( $item['end_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_end_date', __( 'The calendar end date must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( strtotime( $item['start_date'] ) > strtotime( $item['end_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season start date must be before the end date.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }

                // Validate that the season start right after the previous season
                $seasons = $value['calendar_items'];
                foreach ( $seasons as $i => $season) {
                  // if next season does not exist, break
                  if ( $i + 1 >= count( $seasons ) ) {
                    break;
                  }

                  $next_season_start = strtotime( $seasons[ $i + 1 ]['start_date'] );
                  $season_end = strtotime( $season['end_date'] );

                  if ( $next_season_start < $season_end || $next_season_start > $season_end + DAY_IN_SECONDS ) {
                    $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season dates must be within one day of each other.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }

                // Validate that the season dates do not overlap
                $season_overlaps = false;
                foreach ( $value['calendar_items'] as $key => $season ) {
                  $season_start = strtotime( $season['start_date'] );
                  $season_end = strtotime( $season['end_date'] );

                  foreach ( $value['calendar_items'] as $inner_key => $inner_season ) {
                    if ( $key === $inner_key ) {
                      continue;
                    }

                    $inner_season_start = strtotime( $inner_season['start_date'] );
                    $inner_season_end = strtotime( $inner_season['end_date'] );

                    if ( $season_start >= $inner_season_start && $season_start <= $inner_season_end ) {
                      $season_overlaps = true;
                    }

                    if ( $season_end >= $inner_season_start && $season_end <= $inner_season_end ) {
                      $season_overlaps = true;
                    }
                  }
                }

                if ( $season_overlaps ) {
                  $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season dates must not overlap.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

              }

              // Proration prices a join against the season, so it only exists on calendar cycles.
              // A missing key means off, which keeps configs saved before proration at full price.
              if ( isset( $value['proration'] ) ) {
                if ( ! is_array( $value['proration'] ) ) {
                  $errors->add( 'rest_invalid_param_proration', __( 'The proration data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
                } else {
                  $proration_enabled = $value['proration']['enabled'] ?? false;

                  if ( ! is_bool( $proration_enabled ) ) {
                    $errors->add( 'rest_invalid_param_proration_enabled', __( 'Proration enabled must be true or false.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( $proration_enabled === true ) {
                    if ( $value['cycle_type'] !== 'calendar' ) {
                      $errors->add( 'rest_invalid_param_proration_cycle_type', __( 'Proration is only available for calendar cycles.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }

                    if ( ! in_array( $value['proration']['rate'] ?? '', [ 'daily', 'weekly', 'monthly', 'quarterly', 'semi_annually', 'annually' ], true ) ) {
                      $errors->add( 'rest_invalid_param_proration_rate', __( 'The proration rate must be daily, weekly, monthly, quarterly, semi-annually, or annually.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }
                  }
                }
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'cycle_type' => array(
              'type' => 'string', // calendar/anniversary
            ),
            'anniversary_data' => array(
              'type' => 'object',
              'properties' => array(
                'period_count' => array(
                  'type' => 'integer',
                ),
                'period_type' => array(
                  'type' => 'string', // year/month/week
                ),
                'align_end_dates_enabled' => array(
                  'type' => 'boolean',
                ),
                'align_end_dates_type' => array(
                  'type' => 'string', // first-day-of-month | 15th-of-month | last-day-of-month
                ),
              ),
            ),
            'calendar_items' => array(
              'type' => 'array',
              'properties' => array(
                'season_name' => array(
                  'type' => 'string',
                ),
                'active' => array(
                  'type' => 'boolean',
                ),
                'start_date' => array(
                  'type' => 'string',
                ),
                'end_date' => array(
                  'type' => 'string',
                ),
              ),
            ),
            'proration' => array(
              'type' => 'object',
              'properties' => array(
                'enabled' => array(
                  'type' => 'boolean',
                ),
                'rate' => array(
                  'type' => 'string', // daily/weekly/monthly/quarterly/semi_annually/annually
                ),
              ),
            ),
          ),
        ),
      )
    );
  }

  /**
   * Register rest fields for the membership tier post type
   */
  public function register_membership_tier_cpt_fields() {
    // Tier Data
    $field = 'tier_data';

    register_rest_field(
      $this->membership_tier_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Tier Additional Data',
          'arg_options' => [
            'validate_callback' => function( $value, $request, $key ) {
              $errors = new WP_Error();

              $tier_post_id = $request->get_param('id');

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The tier data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( is_bool( $value['approval_required'] ) === false ) {
                $errors->add( 'rest_invalid_param_approval_required', __( 'The approval required value must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // if approval required, then approval email recipient must not be empty and must be a valid email
              if ( $value['approval_required'] === true || $value['renew_approval_required'] === true ) {
                if ( empty( $value['approval_email_recipient'] ) ) {
                  $errors->add( 'rest_invalid_param_approval_email_recipient', __( 'The approval email recipient must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! is_email( $value['approval_email_recipient'] ) ) {
                  $errors->add( 'rest_invalid_param_approval_email_recipient', __( 'The approval email recipient must be a valid email.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                // validate locales to be not empty
                $locales_valid = true;
                $language_codes = Helper::get_wp_languages_iso();
                foreach ( $language_codes as $language_code ) {
                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_header'] ) ) {
                    $locales_valid = false;
                  }

                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_content'] ) ) {
                    $locales_valid = false;
                  }

                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_button_label'] ) ) {
                    $locales_valid = false;
                  }
                }

                if ( $locales_valid === false ) {
                  $errors->add( 'rest_invalid_param_approval_callout_data', __( 'The approval callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( empty( $value['mdp_tier_name'] ) ) {
                $errors->add( 'rest_invalid_param_mdp_tier_name', __( 'The MDP Tier Name must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['mdp_tier_uuid'] ) ) {
                $errors->add( 'rest_invalid_param_mdp_tier_uuid', __( 'The MDP Tier UUID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['renewal_type'] ) ) {
                $errors->add( 'rest_invalid_param_renewal_type', __( 'The Renewal Type must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $value['renewal_type'] === 'current_tier' ) {
                if ( ! empty( $value['next_tier_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must be empty for current tier renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $value['renewal_type'] === 'current_tier' || $value['renewal_type'] === 'sequential_logic' ) {
                if ( ! empty( $value['next_tier_form_page_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_form_page_id', __( 'The Next Tier Form Page ID must be empty for current tier or sequential logic renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $value['renewal_type'] === 'sequential_logic' ) {
                if ( empty( $value['next_tier_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must not be empty for sequential logic renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $value['renewal_type'] === 'form_flow' ) {
                if ( empty( $value['next_tier_form_page_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_form_page_id', __( 'The Next Tier Form Page ID must not be empty for form flow renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! empty( $value['next_tier_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must be empty for form flow renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // if ( empty( $value['next_tier_id'] ) ) {
              //   $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              // }

              if ( empty( $value['config_id'] ) ) {
                $errors->add( 'rest_invalid_param_config_id', __( 'The Membership Config Post ID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // if config_id is not a valid Config Post ID
              $config_post = get_post( $value['config_id'] );

              if ( ! $config_post || $config_post->post_type !== $this->membership_config_cpt_slug ) {
                $errors->add( 'rest_invalid_param_config_id', __( 'The Membership Config Post ID must be a valid post ID.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // only allow 'individual' or 'organization' type
              if ( ! in_array( $value['type'], [ 'individual', 'organization' ] ) ) {
                $errors->add( 'rest_invalid_param_type', __( 'The tier type must be either individual or organization.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // at least one product is required for all tier types
              if ( count( $value['product_data'] ) < 1 ) {
                $errors->add( 'rest_invalid_param_product_data', __( 'At least one product is required.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( count( $value['product_data'] ) > 0 ) {
                foreach ( $value['product_data'] as $product ) {
                  $wc_product = wc_get_product( $product['product_id'] );

                  // each product object must have a product_id, variation_id, and max_seats
                  foreach ( [ 'product_id', 'variation_id', 'max_seats' ] as $key ) {
                    if ( ! array_key_exists( $key, $product ) ) {
                      $errors->add( 'rest_invalid_param_product_data', __( 'The product data must have a ' . $key . ' key.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }
                  }

                  // each product object must be "variable-subscription" or "subscription"
                  if ( ! $wc_product->is_type( 'variable-subscription' ) && ! $wc_product->is_type( 'subscription' ) ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'All products must be either variable-subscription or subscription.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  // dissalow products with max_seats less than -1
                  if ( intval( $product['max_seats'] ) < -1 ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'Max seats must be greater than or equal to -1.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  // product_id must be unique for all tiers
                  if ( $tier_post_id === null ) {
                    if ( Membership_Tier::get_tier_by_product_id( $product['product_id'] ) !== false ) {
                      //$errors->add( 'rest_invalid_param_product_data', __( 'Product IDs must be unique for all tiers.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }
                  } else {
                    // if we are editing a tier, then we need to exclude the current tier product id from the list
                    $tier = new Membership_Tier( $tier_post_id );
                    $tier_product_ids = $tier->get_product_ids();

                    if ( Membership_Tier::get_tier_by_product_id( $product['product_id'] ) !== false && ! in_array( $product['product_id'], $tier_product_ids ) ) {
                      //$errors->add( 'rest_invalid_param_product_data', __( 'Product IDs must be unique for all tiers.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }
                  }

                  // return error if wc product is variable-subscription and variation_id is not set
                  if ( $wc_product->is_type( 'variable-subscription' ) && empty( $product['variation_id'] ) ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'Variation ID must be set for variable subscription products.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  // A missing prorate key reads as false. The flag stays valid while the config has
                  // proration off; Membership_Tier::is_product_prorated() ignores it then.
                  if ( array_key_exists( 'prorate', $product ) && ! is_bool( $product['prorate'] ) ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'Prorate must be true or false.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                }

                // Validate if all product variations are unique
                $product_variations = array_map( function( $product ) {
                  return $product['variation_id'];
                }, $value['product_data'] );

                // Remove empty values
                $product_variations = array_filter( $product_variations );

                if ( count( $product_variations ) !== count( array_unique( $product_variations ) ) ) {
                  $errors->add( 'rest_invalid_param_product_data', __( 'Variation IDs must be unique for all products.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // if individual type, then max_seats must be -1 for all products
              if ( $value['type'] === 'individual' ) {
                foreach ( $value['product_data'] as $product ) {
                  if ( intval( $product['max_seats'] ) !== -1 ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'Max seats must be -1 for individual tier types.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }
              }

              // if type is organization and seat type is per_seat, max 1 product is allowed
              if ( $value['type'] === 'organization' && $value['seat_type'] === 'per_seat' && count( $value['product_data'] ) > 99 ) {
                $errors->add( 'rest_invalid_param_product_data', __( 'Only one product is allowed for organization tier types.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // if type is organization and seat type is per_seat, max_seats must be -1
              if ( $value['type'] === 'organization' && $value['seat_type'] === 'per_seat' && isset( $value['product_data'][0] ) ) {
                if ( intval( $value['product_data'][0]['max_seats'] ) !== -1 ) {
                  $errors->add( 'rest_invalid_param_product_data', __( 'Max seats must be -1 for organization tier types with "per_seat" type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // if type is organization and seat type is per_range_of_seats, product id cannot be same
              if ( $value['type'] === 'organization' && $value['seat_type'] === 'per_range_of_seats' && count( $value['product_data'] ) > 1 ) {
                $product_ids = array_map( function( $product ) {
                  return $product['product_id'];
                }, $value['product_data'] );

                if ( count( $product_ids ) !== count( array_unique( $product_ids ) ) ) {
                  //$errors->add( 'rest_invalid_param_product_data', __( 'Product IDs must be unique for organization tier types with "per_range_of_seats" type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'approval_required' => array(
              'type'        => 'boolean',
              'description' => 'Approval Required',
            ),
            'renew_approval_required' => array(
              'type'        => 'boolean',
              'description' => 'Renew Approval Required',
            ),
            'approval_email_recipient' => array(
              'type'        => 'strong',
              'description' => 'Approval Email Recipient',
            ),
            'mdp_tier_name' => array(
              'type'        => 'string',
              'description' => 'MPD Tier Name',
            ),
            'mdp_tier_uuid' => array(
              'type'        => 'string',
              'description' => 'MPD Tier UUID',
            ),
            'next_tier_id' => array(
              'type'        => 'integer',
              'description' => 'Next Tier ID',
            ),
            'next_tier_form_page_id' => array(
              'type'        => 'integer',
              'description' => 'Next Tier Form Page ID',
            ),
            'config_id' => array(
              'type'        => 'integer',
              'description' => 'Membership Config Post ID',
            ),
            'renewal_type' => array(
              'type'        => 'string',
              'description' => 'Renewal Type',
            ),
            'type' => array(
              'type'        => 'string',
              'description' => 'Tier Type',
            ),
            'seat_type' => array(
              'type'        => 'string',
              'description' => 'Seat Type',
            ),
            'product_data' => array(
              'type'        => 'array',
              'description' => 'Product Data',
              'properties' => array(
                'product_id' => array(
                  'type' => 'integer',
                ),
                'variation_id' => array(
                  'type' => 'integer',
                ),
                'max_seats' => array(
                  'type' => 'integer',
                ),
                'prorate' => array(
                  'type' => 'boolean',
                ),
              ),
            ),
            'approval_callout_data' => array(
              'type'        => 'object',
              'description' => 'Approval Callout Data',
              'properties'  => array(
                'locales' => array(
                  'type'        => 'object',
                  'description' => 'Localized approval callout data',
                  'properties'  => array(
                    'type'        => 'object',
                    'properties'  => array(
                      'callout_header'       => array(
                        'type'        => 'string',
                        'description' => 'The localized header for the approval callout',
                      ),
                      'callout_content'      => array(
                        'type'        => 'string',
                        'description' => 'The localized content for the approval callout',
                      ),
                      'callout_button_label' => array(
                        'type'        => 'string',
                        'description' => 'The localized label for the approval callout button',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

  }

  /**
   * Register and validate REST fields for the membership bundle config post type.
   * Fields: renewal_window_data, late_fee_window_data, cycle_data, bundle_config_data.
   * Follows the same pattern as register_membership_config_cpt_fields() and register_membership_tier_cpt_fields().
   */
  public function register_membership_bundle_config_cpt_fields() {
    // Renewal Window Data
    $field = 'renewal_window_data';

    register_rest_field(
      $this->membership_bundle_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Renewal Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new \WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The renewal window data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
                return $errors;
              }

              if ( ! isset( $value['days_count'] ) || intval( $value['days_count'] ) < 1 ) {
                $errors->add( 'rest_invalid_param_days_count', __( 'The renewal window days count must be greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              $language_codes = Helper::get_wp_languages_iso();
              $locales_valid  = true;
              foreach ( $language_codes as $language_code ) {
                if ( empty( $value['locales'][ $language_code ]['callout_header'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_content'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_button_label'] ) ) {
                  $locales_valid = false;
                }
              }

              if ( $locales_valid === false ) {
                $errors->add( 'rest_invalid_param_locales', __( 'The renewal window callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'days_count' => array(
              'type'        => 'integer',
              'description' => 'The number of days before the end of the membership that the renewal window starts',
            ),
            'locales'    => array(
              'type'        => 'object',
              'description' => 'Localized renewal window callout data',
              'properties'  => array(
                'type'       => 'object',
                'properties' => array(
                  'callout_header'       => array(
                    'type'        => 'string',
                    'description' => 'The localized header for the renewal window callout',
                  ),
                  'callout_content'      => array(
                    'type'        => 'string',
                    'description' => 'The localized content for the renewal window callout',
                  ),
                  'callout_button_label' => array(
                    'type'        => 'string',
                    'description' => 'The localized label for the renewal window callout button',
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

    // Late Fee Window Data (Grace Period)
    $field = 'late_fee_window_data';

    register_rest_field(
      $this->membership_bundle_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Late Fee Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new \WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The grace period window data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
                return $errors;
              }

              if ( ! isset( $value['days_count'] ) || intval( $value['days_count'] ) < 0 ) {
                $errors->add( 'rest_invalid_param_days_count', __( 'The grace period window days count must be equal or greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              $language_codes = Helper::get_wp_languages_iso();
              $locales_valid  = true;
              foreach ( $language_codes as $language_code ) {
                if ( empty( $value['locales'][ $language_code ]['callout_header'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_content'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_button_label'] ) ) {
                  $locales_valid = false;
                }
              }

              if ( $locales_valid === false ) {
                $errors->add( 'rest_invalid_param_locales', __( 'The grace period window callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'days_count' => array(
              'type' => 'integer',
            ),
            'locales'    => array(
              'type'        => 'object',
              'description' => 'Localized late fee window callout data',
              'properties'  => array(
                'type'       => 'object',
                'properties' => array(
                  'callout_header'       => array(
                    'type'        => 'string',
                    'description' => 'The localized header for the late fee window callout',
                  ),
                  'callout_content'      => array(
                    'type'        => 'string',
                    'description' => 'The localized content for the late fee window callout',
                  ),
                  'callout_button_label' => array(
                    'type'        => 'string',
                    'description' => 'The localized label for the late fee window callout button',
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

    // Cycle Data
    $field = 'cycle_data';

    register_rest_field(
      $this->membership_bundle_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Cycle Data',
          'arg_options' => [
            'sanitize_callback' => function( $value ) {
              if ( is_array( $value ) && ( $value['cycle_type'] ?? null ) === 'calendar' && is_array( $value['calendar_items'] ?? null ) ) {
                $value['calendar_items'] = $this->normalize_calendar_season_dates( $value['calendar_items'] );
              }
              return $value;
            },
            'validate_callback' => function( $value ) {
              $errors = new \WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The cycle data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
                return $errors;
              }

              if ( ! in_array( $value['cycle_type'], [ 'calendar', 'anniversary' ] ) ) {
                $errors->add( 'rest_invalid_param_cycle_type', __( 'The cycle type must be either calendar or anniversary.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $value['cycle_type'] === 'anniversary' ) {
                if ( intval( $value['anniversary_data']['period_count'] ) < 1 ) {
                  $errors->add( 'rest_invalid_param_anniversary_period_count', __( 'The anniversary period count must be greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! in_array( $value['anniversary_data']['period_type'], [ 'year', 'month', 'week' ] ) ) {
                  $errors->add( 'rest_invalid_param_anniversary_period_type', __( 'The anniversary period type must be year, month, or week.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( $value['anniversary_data']['align_end_dates_enabled'] === true ) {
                  if ( ! in_array( $value['anniversary_data']['align_end_dates_type'], [ 'first-day-of-month', '15th-of-month', 'last-day-of-month' ] ) ) {
                    $errors->add( 'rest_invalid_param_anniversary_align_end_dates_type', __( 'The anniversary align end dates type must be first-day-of-month, 15th-of-month, or last-day-of-month.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }
              }

              if ( $value['cycle_type'] === 'calendar' ) {
                if ( ! is_array( $value['calendar_items'] ) ) {
                  $errors->add( 'rest_invalid_param_calendar_items', __( 'The calendar items must be an array.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                // Normalize dates for validation so legacy/partial season data
                // (e.g. bare Y-m-d dates missing a time component) is checked
                // against the same day boundaries that will actually be stored.
                $value['calendar_items'] = $this->normalize_calendar_season_dates( $value['calendar_items'] );

                if ( count( $value['calendar_items'] ) < 1 ) {
                  $errors->add( 'rest_invalid_param_calendar_items', __( 'At least one season item must be defined.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                $active_seasons = array_filter( $value['calendar_items'], function( $item ) {
                  return $item['active'] === true;
                });

                if ( count( $active_seasons ) < 1 ) {
                  $errors->add( 'rest_invalid_param_calendar_active_seasons', __( 'At least one season must be active.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                foreach ( $value['calendar_items'] as $item ) {
                  if ( empty( $item['season_name'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_season_name', __( 'The calendar season name must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( empty( $item['start_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_start_date', __( 'The calendar start date must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( empty( $item['end_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_end_date', __( 'The calendar end date must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( strtotime( $item['start_date'] ) > strtotime( $item['end_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season start date must be before the end date.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }

                $seasons = $value['calendar_items'];
                foreach ( $seasons as $i => $season ) {
                  if ( $i + 1 >= count( $seasons ) ) {
                    break;
                  }

                  $next_season_start = strtotime( $seasons[ $i + 1 ]['start_date'] );
                  $season_end        = strtotime( $season['end_date'] );

                  if ( $next_season_start < $season_end || $next_season_start > $season_end + DAY_IN_SECONDS ) {
                    $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season dates must be within one day of each other.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }

                $season_overlaps = false;
                foreach ( $value['calendar_items'] as $key => $season ) {
                  $season_start = strtotime( $season['start_date'] );
                  $season_end   = strtotime( $season['end_date'] );

                  foreach ( $value['calendar_items'] as $inner_key => $inner_season ) {
                    if ( $key === $inner_key ) {
                      continue;
                    }

                    $inner_season_start = strtotime( $inner_season['start_date'] );
                    $inner_season_end   = strtotime( $inner_season['end_date'] );

                    if ( $season_start >= $inner_season_start && $season_start <= $inner_season_end ) {
                      $season_overlaps = true;
                    }

                    if ( $season_end >= $inner_season_start && $season_end <= $inner_season_end ) {
                      $season_overlaps = true;
                    }
                  }
                }

                if ( $season_overlaps ) {
                  $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season dates must not overlap.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'cycle_type'      => array(
              'type' => 'string', // calendar | anniversary
            ),
            'anniversary_data' => array(
              'type'       => 'object',
              'properties' => array(
                'period_count'            => array(
                  'type' => 'integer',
                ),
                'period_type'             => array(
                  'type' => 'string', // year | month | week
                ),
                'align_end_dates_enabled' => array(
                  'type' => 'boolean',
                ),
                'align_end_dates_type'    => array(
                  'type' => 'string', // first-day-of-month | 15th-of-month | last-day-of-month
                ),
              ),
            ),
            'calendar_items'  => array(
              'type'       => 'array',
              'properties' => array(
                'season_name' => array(
                  'type' => 'string',
                ),
                'active'      => array(
                  'type' => 'boolean',
                ),
                'start_date'  => array(
                  'type' => 'string',
                ),
                'end_date'    => array(
                  'type' => 'string',
                ),
              ),
            ),
          ),
        ),
      )
    );

    // Bundle Config Data (renewal type, approval settings)
    $field = 'bundle_config_data';

    register_rest_field(
      $this->membership_bundle_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Bundle Config Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new \WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The bundle config data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
                return $errors;
              }

              if ( empty( $value['renewal_type'] ) || ! in_array( $value['renewal_type'], [ 'subscription', 'form_page' ] ) ) {
                $errors->add( 'rest_invalid_param_renewal_type', __( 'The renewal type must be subscription or form_page.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $value['renewal_type'] === 'form_page' ) {
                if ( empty( $value['renewal_form_page_id'] ) ) {
                  $errors->add( 'rest_invalid_param_renewal_form_page_id', __( 'The renewal form page ID must not be empty for form_page renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                } else {
                  $form_page = get_post( intval( $value['renewal_form_page_id'] ) );
                  if ( ! $form_page || $form_page->post_status === 'trash' ) {
                    $errors->add( 'rest_invalid_param_renewal_form_page_id', __( 'The renewal form page ID must be a valid post ID.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }
              }

              if ( is_bool( $value['approval_required'] ) === false ) {
                $errors->add( 'rest_invalid_param_approval_required', __( 'The approval required value must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              $approval_required = ! empty( $value['approval_required'] );
              if ( $approval_required ) {
                if ( empty( $value['approval_email_recipient'] ) ) {
                  $errors->add( 'rest_invalid_param_approval_email_recipient', __( 'The approval email recipient must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! empty( $value['approval_email_recipient'] ) && ! is_email( $value['approval_email_recipient'] ) ) {
                  $errors->add( 'rest_invalid_param_approval_email_recipient', __( 'The approval email recipient must be a valid email.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                $language_codes = Helper::get_wp_languages_iso();
                $locales_valid  = true;
                foreach ( $language_codes as $language_code ) {
                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_header'] ) ) {
                    $locales_valid = false;
                  }

                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_content'] ) ) {
                    $locales_valid = false;
                  }

                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_button_label'] ) ) {
                    $locales_valid = false;
                  }
                }

                if ( $locales_valid === false ) {
                  $errors->add( 'rest_invalid_param_approval_callout_data', __( 'The approval callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( isset( $value['eligible_tier_ids'] ) ) {
                if ( ! is_array( $value['eligible_tier_ids'] ) ) {
                  $errors->add( 'rest_invalid_param_eligible_tier_ids', __( 'The eligible tier IDs must be an array.', 'wicket-memberships' ), array( 'status' => 400 ) );
                } else {
                  // Reject non-integer entries outright rather than silently coercing them
                  // (e.g. "foo" -> 0) — a phantom tier ID 0 in the stored config is worse
                  // than a rejected save, since it would silently restrict eligibility to
                  // a tier that doesn't exist.
                  foreach ( $value['eligible_tier_ids'] as $tier_id ) {
                    if ( ! is_numeric( $tier_id ) || (string) (int) $tier_id !== (string) $tier_id ) {
                      $errors->add( 'rest_invalid_param_eligible_tier_ids', __( 'Each eligible tier ID must be an integer post ID.', 'wicket-memberships' ), array( 'status' => 400 ) );
                      break;
                    }
                  }
                }
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'renewal_type'             => array(
              'type'        => 'string',
              'description' => 'Renewal Type: subscription | form_page',
            ),
            'eligible_tier_ids'        => array(
              'type'        => 'array',
              'items'       => array(
                'type' => 'integer',
              ),
              'description' => 'Post IDs of individual Membership_Tier posts eligible for this bundle config. Empty means all active individual tiers are eligible.',
            ),
            'renewal_form_page_id'     => array(
              'type'        => 'integer',
              'description' => 'Renewal Form Page Post ID (required when renewal_type is form_page)',
            ),
            'approval_required'        => array(
              'type'        => 'integer',
              'description' => 'Whether approval is required: 1 | 0',
            ),
            'grant_owner_assignment'   => array(
              'type'        => 'integer',
              'description' => 'Whether to grant owner assignment: 1 | 0',
            ),
            'approval_email_recipient' => array(
              'type'        => 'string',
              'description' => 'Email address to notify when approval is required',
            ),
            'approval_callout_data'    => array(
              'type'        => 'object',
              'description' => 'Approval Callout Data',
              'properties'  => array(
                'locales' => array(
                  'type'        => 'object',
                  'description' => 'Localized approval callout data',
                  'properties'  => array(
                    'type'       => 'object',
                    'properties' => array(
                      'callout_header'       => array(
                        'type'        => 'string',
                        'description' => 'The localized header for the approval callout',
                      ),
                      'callout_content'      => array(
                        'type'        => 'string',
                        'description' => 'The localized content for the approval callout',
                      ),
                      'callout_button_label' => array(
                        'type'        => 'string',
                        'description' => 'The localized label for the approval callout button',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );
  }

  /**
   * Create the membership post type
   */
  public function register_membership_post_type() {
    $supports = array(
      'custom-fields',
    );

    $labels = array(
      'name' => _x('Memberships', 'plural', 'wicket-memberships' ),
    );

    $membership_menu_item = false;
    if( ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      $membership_menu_item = WICKET_MEMBERSHIP_PLUGIN_SLUG;
    }

    $args = array(
      'supports' => $supports,
      'labels' => $labels,
      'description'        => __( 'Records of the Wicket Memberships', 'wicket-memberships' ),
      'public'             => false,
      'publicly_queryable' => false,
      'show_ui'            => true,
      'show_in_menu'       => $membership_menu_item,
      'query_var'          => true,
      'capability_type'    => 'post',
      'map_meta_cap'       => true, //permissions same as 'posts'
      'has_archive'        => true,
      'hierarchical'       => false,
      'menu_position'      => null,
      'show_in_rest'       => true,
    );

    register_post_type($this->membership_cpt_slug, $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The status of this membership record', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      =>  true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_status', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'The UserID owns this membership record', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'user_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The UUID in wicket of this membership record', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );
    register_post_meta($this->membership_cpt_slug, 'membership_wicket_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The start date membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_starts_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The end date membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_ends_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The expiry date of the membership in wordpress.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_expires_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The early renew date of the membership in wordpress.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_early_renew_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Person or Org membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_type', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Org Name.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'org_name', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Org UUID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'org_uuid', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'Org max seats.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'org_seats', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'MDP Membership ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_uuid', $args);

    /** Tier Info */

    $args = array(
      'type'              => 'string',
      'description'       => __( 'MDP Tier UUID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_tier_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Tier Name.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_tier_name', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'Sequential Tier WP Post_ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_next_tier_id', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'Renewal Form WP Page_ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_next_form_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Order ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_parent_order_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Subscription ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_subscription_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Product ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_product_id', $args);
  }

  /**
   * Create the Wicket Membership Config post type
   */
  public function register_membership_config_post_type() {
    $labels = array(
      'name'                  => _x( 'Membership Configs', 'Post Type General Name', 'wicket-memberships' ),
      'singular_name'         => _x( 'Membership Config', 'Post Type Singular Name', 'wicket-memberships' ),
      'menu_name'             => __( 'Post Types', 'wicket-memberships' ),
      'name_admin_bar'        => __( 'Post Type', 'wicket-memberships' ),
      'archives'              => __( 'Item Archives', 'wicket-memberships' ),
      'attributes'            => __( 'Item Attributes', 'wicket-memberships' ),
      'parent_item_colon'     => __( 'Parent Item:', 'wicket-memberships' ),
      'all_items'             => __( 'Membership Configs', 'wicket-memberships' ),
      'add_new_item'          => __( 'Add New Item', 'wicket-memberships' ),
      'add_new'               => __( 'Add New', 'wicket-memberships' ),
      'new_item'              => __( 'New Item', 'wicket-memberships' ),
      'edit_item'             => __( 'Edit Item', 'wicket-memberships' ),
      'update_item'           => __( 'Update Item', 'wicket-memberships' ),
      'view_item'             => __( 'View Item', 'wicket-memberships' ),
      'view_items'            => __( 'View Items', 'wicket-memberships' ),
      'search_items'          => __( 'Search Item', 'wicket-memberships' ),
      'not_found'             => __( 'Not found', 'wicket-memberships' ),
      'not_found_in_trash'    => __( 'Not found in Trash', 'wicket-memberships' ),
      'featured_image'        => __( 'Featured Image', 'wicket-memberships' ),
      'set_featured_image'    => __( 'Set featured image', 'wicket-memberships' ),
      'remove_featured_image' => __( 'Remove featured image', 'wicket-memberships' ),
      'use_featured_image'    => __( 'Use as featured image', 'wicket-memberships' ),
      'insert_into_item'      => __( 'Insert into item', 'wicket-memberships' ),
      'uploaded_to_this_item' => __( 'Uploaded to this item', 'wicket-memberships' ),
      'items_list'            => __( 'Items list', 'wicket-memberships' ),
      'items_list_navigation' => __( 'Items list navigation', 'wicket-memberships' ),
      'filter_items_list'     => __( 'Filter items list', 'wicket-memberships' ),
    );

    $args = array(
      'label'                 => __( 'Membership Config', 'wicket-memberships' ),
      'description'           => __( 'Membership Configurations are defined here', 'wicket-memberships' ),
      'labels'                => $labels,
      'supports'              => array( 'title', 'custom-fields' ),
      'hierarchical'          => false,
      'public'                => false,
      'show_ui'               => true,
      'show_in_menu'          => WICKET_MEMBERSHIP_PLUGIN_SLUG,
      'menu_position'         => 5,
      'show_in_admin_bar'     => true,
      'show_in_nav_menus'     => true,
      'can_export'            => true,
      'has_archive'           => true,
      'exclude_from_search'   => false,
      'publicly_queryable'    => false,
      'capability_type'       => 'page',
      'show_in_rest'          => true,
    );

    register_post_type( $this->membership_config_cpt_slug, $args );

    add_action('rest_api_init', function () {
      register_rest_field($this->membership_config_cpt_slug, 'multi_tier_renewal', [
          'get_callback'    => function ($object) {
              return get_post_meta($object['id'], 'multi_tier_renewal', true);
          },
          'update_callback' => function ($value, $object) {
              return update_post_meta($object->ID, 'multi_tier_renewal', $value);
          },
          'schema' => [
              'description' => 'Is multi tier renewal',
              'context'     => ['view', 'edit'],
          ],
      ]);
    });
  }

    /**
   * Create the Wicket Membership Tier post type
   */
  public function register_membership_tier_post_type() {
    $labels = array(
      'name'                  => _x( 'Membership Tiers', 'Post Type General Name', 'wicket-memberships' ),
      'singular_name'         => _x( 'Membership Tiers', 'Post Type Singular Name', 'wicket-memberships' ),
      'menu_name'             => __( 'Post Types', 'wicket-memberships' ),
      'name_admin_bar'        => __( 'Post Type', 'wicket-memberships' ),
      'archives'              => __( 'Item Archives', 'wicket-memberships' ),
      'attributes'            => __( 'Item Attributes', 'wicket-memberships' ),
      'parent_item_colon'     => __( 'Parent Item:', 'wicket-memberships' ),
      'all_items'             => __( 'Membership Tiers', 'wicket-memberships' ),
      'add_new_item'          => __( 'Add New Item', 'wicket-memberships' ),
      'add_new'               => __( 'Add New', 'wicket-memberships' ),
      'new_item'              => __( 'New Item', 'wicket-memberships' ),
      'edit_item'             => __( 'Edit Item', 'wicket-memberships' ),
      'update_item'           => __( 'Update Item', 'wicket-memberships' ),
      'view_item'             => __( 'View Item', 'wicket-memberships' ),
      'view_items'            => __( 'View Items', 'wicket-memberships' ),
      'search_items'          => __( 'Search Item', 'wicket-memberships' ),
      'not_found'             => __( 'Not found', 'wicket-memberships' ),
      'not_found_in_trash'    => __( 'Not found in Trash', 'wicket-memberships' ),
      'featured_image'        => __( 'Featured Image', 'wicket-memberships' ),
      'set_featured_image'    => __( 'Set featured image', 'wicket-memberships' ),
      'remove_featured_image' => __( 'Remove featured image', 'wicket-memberships' ),
      'use_featured_image'    => __( 'Use as featured image', 'wicket-memberships' ),
      'insert_into_item'      => __( 'Insert into item', 'wicket-memberships' ),
      'uploaded_to_this_item' => __( 'Uploaded to this item', 'wicket-memberships' ),
      'items_list'            => __( 'Items list', 'wicket-memberships' ),
      'items_list_navigation' => __( 'Items list navigation', 'wicket-memberships' ),
      'filter_items_list'     => __( 'Filter items list', 'wicket-memberships' ),
    );

    $args = array(
      'label'                 => __( 'Membership Tiers', 'wicket-memberships' ),
      'description'           => __( 'Membership Tiers are defined here', 'wicket-memberships' ),
      'labels'                => $labels,
      'supports'              => array( 'title', 'custom-fields' ),
      'hierarchical'          => false,
      'public'                => false,
      'show_ui'               => true,
      'show_in_menu'          => WICKET_MEMBERSHIP_PLUGIN_SLUG,
      'menu_position'         => 5,
      'show_in_admin_bar'     => true,
      'show_in_nav_menus'     => true,
      'can_export'            => true,
      'has_archive'           => true,
      'exclude_from_search'   => false,
      'publicly_queryable'    => false,
      'capability_type'       => 'page',
      'show_in_rest'          => true
    );

    register_post_type( $this->membership_tier_cpt_slug, $args );

    add_action('rest_api_init', function () {
      register_rest_field($this->membership_tier_cpt_slug, 'membership_tier_slug', [
          'get_callback'    => function ($object) {
              return get_post_meta($object['id'], 'membership_tier_slug', true);
          },
          'update_callback' => function ($value, $object) {
              return update_post_meta($object->ID, 'membership_tier_slug', $value);
          },
          'schema' => [
              'description' => 'membership_tier_slug',
              'context'     => ['view', 'edit'],
          ],
      ]);
    });
  }

  /**
   * Create the Wicket Membership Bundle post type
   */
  public function register_membership_bundle_post_type() {
    $labels = array(
      'name'                  => _x( 'Membership Bundles', 'Post Type General Name', 'wicket-memberships' ),
      'singular_name'         => _x( 'Membership Bundle', 'Post Type Singular Name', 'wicket-memberships' ),
      'menu_name'             => __( 'Post Types', 'wicket-memberships' ),
      'name_admin_bar'        => __( 'Post Type', 'wicket-memberships' ),
      'archives'              => __( 'Item Archives', 'wicket-memberships' ),
      'attributes'            => __( 'Item Attributes', 'wicket-memberships' ),
      'parent_item_colon'     => __( 'Parent Item:', 'wicket-memberships' ),
      'all_items'             => __( 'Membership Bundles', 'wicket-memberships' ),
      'add_new_item'          => __( 'Add New Item', 'wicket-memberships' ),
      'add_new'               => __( 'Add New', 'wicket-memberships' ),
      'new_item'              => __( 'New Item', 'wicket-memberships' ),
      'edit_item'             => __( 'Edit Item', 'wicket-memberships' ),
      'update_item'           => __( 'Update Item', 'wicket-memberships' ),
      'view_item'             => __( 'View Item', 'wicket-memberships' ),
      'view_items'            => __( 'View Items', 'wicket-memberships' ),
      'search_items'          => __( 'Search Item', 'wicket-memberships' ),
      'not_found'             => __( 'Not found', 'wicket-memberships' ),
      'not_found_in_trash'    => __( 'Not found in Trash', 'wicket-memberships' ),
      'featured_image'        => __( 'Featured Image', 'wicket-memberships' ),
      'set_featured_image'    => __( 'Set featured image', 'wicket-memberships' ),
      'remove_featured_image' => __( 'Remove featured image', 'wicket-memberships' ),
      'use_featured_image'    => __( 'Use as featured image', 'wicket-memberships' ),
      'insert_into_item'      => __( 'Insert into item', 'wicket-memberships' ),
      'uploaded_to_this_item' => __( 'Uploaded to this item', 'wicket-memberships' ),
      'items_list'            => __( 'Items list', 'wicket-memberships' ),
      'items_list_navigation' => __( 'Items list navigation', 'wicket-memberships' ),
      'filter_items_list'     => __( 'Filter items list', 'wicket-memberships' ),
    );

    $args = array(
      'label'               => __( 'Membership Bundle', 'wicket-memberships' ),
      'description'         => __( 'Membership Bundles are defined here', 'wicket-memberships' ),
      'labels'              => $labels,
      'supports'            => array( 'title', 'custom-fields' ),
      'hierarchical'        => false,
      'public'              => false,
      'show_ui'             => true,
      'show_in_menu'        => false,
      'menu_position'       => 5,
      'show_in_admin_bar'   => true,
      'show_in_nav_menus'   => true,
      'can_export'          => true,
      'has_archive'         => true,
      'exclude_from_search' => false,
      'publicly_queryable'  => false,
      'capability_type'     => 'page',
      'show_in_rest'        => true,
    );

    register_post_type( $this->membership_bundle_cpt_slug, $args );
  }

  /**
   * Create the Wicket Membership Bundle Config post type
   */
  public function register_membership_bundle_config_post_type() {
    $labels = array(
      'name'                  => _x( 'Membership Bundle Configs', 'Post Type General Name', 'wicket-memberships' ),
      'singular_name'         => _x( 'Membership Bundle Config', 'Post Type Singular Name', 'wicket-memberships' ),
      'menu_name'             => __( 'Post Types', 'wicket-memberships' ),
      'name_admin_bar'        => __( 'Post Type', 'wicket-memberships' ),
      'archives'              => __( 'Item Archives', 'wicket-memberships' ),
      'attributes'            => __( 'Item Attributes', 'wicket-memberships' ),
      'parent_item_colon'     => __( 'Parent Item:', 'wicket-memberships' ),
      'all_items'             => __( 'Membership Bundle Configs', 'wicket-memberships' ),
      'add_new_item'          => __( 'Add New Item', 'wicket-memberships' ),
      'add_new'               => __( 'Add New', 'wicket-memberships' ),
      'new_item'              => __( 'New Item', 'wicket-memberships' ),
      'edit_item'             => __( 'Edit Item', 'wicket-memberships' ),
      'update_item'           => __( 'Update Item', 'wicket-memberships' ),
      'view_item'             => __( 'View Item', 'wicket-memberships' ),
      'view_items'            => __( 'View Items', 'wicket-memberships' ),
      'search_items'          => __( 'Search Item', 'wicket-memberships' ),
      'not_found'             => __( 'Not found', 'wicket-memberships' ),
      'not_found_in_trash'    => __( 'Not found in Trash', 'wicket-memberships' ),
      'featured_image'        => __( 'Featured Image', 'wicket-memberships' ),
      'set_featured_image'    => __( 'Set featured image', 'wicket-memberships' ),
      'remove_featured_image' => __( 'Remove featured image', 'wicket-memberships' ),
      'use_featured_image'    => __( 'Use as featured image', 'wicket-memberships' ),
      'insert_into_item'      => __( 'Insert into item', 'wicket-memberships' ),
      'uploaded_to_this_item' => __( 'Uploaded to this item', 'wicket-memberships' ),
      'items_list'            => __( 'Items list', 'wicket-memberships' ),
      'items_list_navigation' => __( 'Items list navigation', 'wicket-memberships' ),
      'filter_items_list'     => __( 'Filter items list', 'wicket-memberships' ),
    );

    $args = array(
      'label'               => __( 'Membership Bundle Config', 'wicket-memberships' ),
      'description'         => __( 'Membership Bundle Configurations are defined here', 'wicket-memberships' ),
      'labels'              => $labels,
      'supports'            => array( 'title', 'custom-fields' ),
      'hierarchical'        => false,
      'public'              => false,
      'show_ui'             => true,
      'show_in_menu'        => false,
      'menu_position'       => 5,
      'show_in_admin_bar'   => true,
      'show_in_nav_menus'   => true,
      'can_export'          => true,
      'has_archive'         => true,
      'exclude_from_search' => false,
      'publicly_queryable'  => false,
      'capability_type'     => 'page',
      'show_in_rest'        => true,
    );

    register_post_type( $this->membership_bundle_config_cpt_slug, $args );
  }

  /**
   * Normalize calendar season start/end dates to the start/end of day in the
   * MDP timezone (converted to UTC), so partial or legacy season data (e.g.
   * bare Y-m-d dates with no time component) always ends up with consistent
   * day boundaries. Without this, a season saved before this convention
   * existed keeps a bare date forever, since the admin UI only re-derives a
   * season's dates when that specific season's date picker is touched.
   *
   * @param array $calendar_items List of season items with start_date/end_date.
   * @return array Season items with normalized start_date/end_date.
   */
  private function normalize_calendar_season_dates( $calendar_items ) {
    if ( ! is_array( $calendar_items ) ) {
      return $calendar_items;
    }

    foreach ( $calendar_items as $key => $item ) {
      if ( ! empty( $item['start_date'] ) ) {
        try {
          $calendar_items[ $key ]['start_date'] = Utilities::get_mdp_day_start( $item['start_date'] )->format( 'c' );
        } catch ( \InvalidArgumentException $e ) {
          // Leave the raw, unparseable value in place. The validate_callback's
          // strtotime()/empty() checks below will reject it with a normal 400
          // instead of this normalization step throwing a fatal 500.
        }
      }

      if ( ! empty( $item['end_date'] ) ) {
        try {
          $calendar_items[ $key ]['end_date'] = Utilities::get_mdp_day_end( $item['end_date'] )->format( 'c' );
        } catch ( \InvalidArgumentException $e ) {
          // Same rationale as start_date above.
        }
      }
    }

    return $calendar_items;
  }

}