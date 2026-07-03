<?php
/**
 * WP-CLI command for syncing membership tiers from the external Wicket MDP.
 *
 * @package Wicket
 */

namespace Wicket_Memberships\CLI;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Membership_Tier;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Creates local `wicket_mship_tier` posts from the tiers defined in the external MDP.
 *
 * Registered as `wp wicket-mship tier <subcommand>`. Reads the authoritative tier list
 * from the MDP (via the base plugin's `get_individual_memberships()` helper) and reconciles
 * it against local tier posts, creating any that are missing and linking them by
 * `mdp_tier_uuid`. Product/renewal configuration is intentionally left to the admin UI —
 * see docs/engineering/wp-cli-tier-sync-plan.md.
 *
 * @since 1.0.121
 */
class Tier_Sync_Command {

  /**
   * MDP page size used when paginating the `memberships` endpoint.
   *
   * The endpoint paginates JSON:API style; we request an explicit page size and follow
   * `links.next` until it is null. 100 comfortably exceeds real-world tier counts, keeping
   * this to a single round-trip in practice while remaining correct if it ever paginates.
   *
   * @var int
   */
  private const MDP_PAGE_SIZE = 100;

  /**
   * Hard cap on pages fetched, as a defensive guard against an unexpected pagination loop.
   *
   * @var int
   */
  private const MDP_MAX_PAGES = 100;

  /**
   * Create local membership tiers from the external MDP tiers.
   *
   * For each MDP tier: if a local tier already links to its UUID it is skipped (its name is
   * refreshed if it drifted); otherwise a new `wicket_mship_tier` post is created linked by
   * `mdp_tier_uuid` and attached to the config given by --config-id. Newly created tiers
   * have no products — product selection is completed afterwards in the admin UI.
   *
   * ## OPTIONS
   *
   * --config-id=<post_id>
   * : The `wicket_mship_config` post ID to attach to every created tier. Required because
   * the MDP does not supply a config, and a tier is invalid without one. Environment
   * specific — the same config has different IDs across sites.
   *
   * [--type=<type>]
   * : Only sync tiers of this MDP type.
   * ---
   * default: all
   * options:
   *   - all
   *   - individual
   *   - organization
   * ---
   *
   * [--dry-run]
   * : Compute and print the plan without writing anything. Recommended first run.
   *
   * [--yes]
   * : Skip the interactive confirmation before writing.
   *
   * [--format=<format>]
   * : Output format for the results table.
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - csv
   * ---
   *
   * ## EXAMPLES
   *
   *     # Preview what would be created against config post 123.
   *     $ wp wicket-mship tier sync --config-id=123 --dry-run
   *
   *     # Create the missing tiers (prompts for confirmation).
   *     $ wp wicket-mship tier sync --config-id=123
   *
   *     # Sync only organization tiers, no prompt.
   *     $ wp wicket-mship tier sync --config-id=123 --type=organization --yes
   *
   * @param array<int, string>    $args        Positional args (unused).
   * @param array<string, string> $assoc_args  Associative args from the flags above.
   *
   * @return void
   */
  public function sync( $args, $assoc_args ) {
    $dry_run   = Utils\get_flag_value( $assoc_args, 'dry-run', false );
    $type      = Utils\get_flag_value( $assoc_args, 'type', 'all' );
    $format    = Utils\get_flag_value( $assoc_args, 'format', 'table' );
    $config_id = (int) Utils\get_flag_value( $assoc_args, 'config-id', 0 );

    // Fail before any MDP work if the config target is invalid — a tier cannot be saved
    // without a valid config, so there is no point fetching tiers we could not create.
    $this->assert_valid_config( $config_id );

    // Confirm the MDP is reachable up front; creating tiers with a dead client would
    // produce half-linked posts.
    if ( ! $this->mdp_client_available() ) {
      WP_CLI::error( 'MDP API client is not available (check Wicket settings/connectivity). Aborting.' );
    }

    WP_CLI::log( 'Fetching membership tiers from the MDP...' );
    $mdp_tiers = $this->get_mdp_tiers();

    if ( empty( $mdp_tiers ) ) {
      WP_CLI::warning( 'No tiers returned from the MDP. Nothing to do.' );
      return;
    }

    // Client-side type filter (the MDP `memberships` endpoint returns both types together).
    if ( 'all' !== $type ) {
      $mdp_tiers = array_values( array_filter( $mdp_tiers, static function ( $tier ) use ( $type ) {
        return ( $tier['attributes']['type'] ?? '' ) === $type;
      } ) );
    }

    // Build the reconciliation plan: create / skip / update, one row per MDP tier.
    $plan = array();
    foreach ( $mdp_tiers as $mdp_tier ) {
      $uuid    = $mdp_tier['id'] ?? '';
      $name    = $mdp_tier['attributes']['name_en'] ?? '';
      $mdp_typ = $mdp_tier['attributes']['type'] ?? '';

      if ( empty( $uuid ) ) {
        continue; // Skip malformed entries with no UUID to link against.
      }

      $existing_id = Membership_Tier::get_tier_id_by_wicket_uuid( $uuid );

      if ( false === $existing_id ) {
        $action = 'create';
      } else {
        // Existing tier: only a name drift is actionable (update policy = name only).
        $existing_name = ( new Membership_Tier( $existing_id ) )->get_mdp_tier_name();
        $action        = ( $existing_name !== $name ) ? 'update' : 'skip';
      }

      $plan[] = array(
        'action'  => $action,
        'type'    => $mdp_typ,
        'name'    => $name,
        'uuid'    => $uuid,
        'post_id' => $existing_id ?: '',
        'mdp'     => $mdp_tier,
      );
    }

    $to_create = array_filter( $plan, static fn( $row ) => 'create' === $row['action'] );
    $to_update = array_filter( $plan, static fn( $row ) => 'update' === $row['action'] );

    // Show the plan first so a dry run is genuinely informative.
    $this->print_results( $plan, $format );
    WP_CLI::log( sprintf(
      'Plan: %d to create, %d to update (name), %d unchanged.',
      count( $to_create ),
      count( $to_update ),
      count( $plan ) - count( $to_create ) - count( $to_update )
    ) );

    if ( $dry_run ) {
      WP_CLI::success( 'Dry run complete — no changes written.' );
      return;
    }

    if ( empty( $to_create ) && empty( $to_update ) ) {
      WP_CLI::success( 'Local tiers already in sync with the MDP.' );
      return;
    }

    // Gate the write behind an explicit confirmation unless --yes was passed.
    WP_CLI::confirm(
      sprintf( 'Create %d and update %d local tier(s)?', count( $to_create ), count( $to_update ) ),
      $assoc_args
    );

    $created = 0;
    $updated = 0;
    $errors  = 0;

    foreach ( $plan as $row ) {
      if ( 'create' === $row['action'] ) {
        $post_id = $this->create_tier( $row['mdp'], $config_id );

        if ( is_wp_error( $post_id ) ) {
          WP_CLI::warning( sprintf( 'Failed to create "%s": %s', $row['name'], $post_id->get_error_message() ) );
          ++$errors;
          continue;
        }

        ++$created;
        WP_CLI::log( sprintf( 'Created tier #%d "%s" (%s).', $post_id, $row['name'], $row['uuid'] ) );
      } elseif ( 'update' === $row['action'] ) {
        $this->refresh_tier_name( (int) $row['post_id'], $row['name'] );
        ++$updated;
        WP_CLI::log( sprintf( 'Updated name on tier #%d to "%s".', $row['post_id'], $row['name'] ) );
      }
    }

    WP_CLI::success( sprintf( 'Done. Created %d, updated %d, errors %d.', $created, $updated, $errors ) );
  }

  /**
   * List membership tiers from either the MDP or the local site.
   *
   * A read-only helper to preview the MDP source list before syncing and to diff local
   * tiers afterward. Writes nothing.
   *
   * ## OPTIONS
   *
   * [--source=<source>]
   * : Where to read tiers from.
   * ---
   * default: mdp
   * options:
   *   - mdp
   *   - local
   * ---
   *
   * [--format=<format>]
   * : Output format.
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - csv
   *   - count
   *   - ids
   * ---
   *
   * ## EXAMPLES
   *
   *     $ wp wicket-mship tier list --source=mdp
   *     $ wp wicket-mship tier list --source=local --format=json
   *
   * @subcommand list
   *
   * @param array<int, string>    $args        Positional args (unused).
   * @param array<string, string> $assoc_args  Associative args from the flags above.
   *
   * @return void
   */
  public function list( $args, $assoc_args ) {
    $source = Utils\get_flag_value( $assoc_args, 'source', 'mdp' );
    $format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

    if ( 'local' === $source ) {
      $rows = $this->get_local_tier_rows();
    } else {
      if ( ! $this->mdp_client_available() ) {
        WP_CLI::error( 'MDP API client is not available (check Wicket settings/connectivity).' );
      }
      $rows = array();
      foreach ( $this->get_mdp_tiers() as $tier ) {
        $rows[] = array(
          'uuid' => $tier['id'] ?? '',
          'name' => $tier['attributes']['name_en'] ?? '',
          'type' => $tier['attributes']['type'] ?? '',
          'slug' => $tier['attributes']['slug'] ?? '',
        );
      }
    }

    if ( empty( $rows ) ) {
      WP_CLI::warning( 'No tiers found.' );
      return;
    }

    Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
  }

  /**
   * Validate that the given ID is a real membership config post, or stop with an error.
   *
   * Mirrors the guard the REST tier-save layer applies (`get_post()` + post-type check),
   * so the CLI cannot attach a tier to a non-config post.
   *
   * @param int $config_id  Candidate config post ID.
   *
   * @return void
   */
  private function assert_valid_config( $config_id ) {
    if ( $config_id <= 0 ) {
      WP_CLI::error( '--config-id is required and must be a positive post ID.' );
    }

    $post = get_post( $config_id );

    if ( ! $post || Helper::get_membership_config_cpt_slug() !== $post->post_type ) {
      WP_CLI::error( sprintf( '--config-id %d is not a valid %s post.', $config_id, Helper::get_membership_config_cpt_slug() ) );
    }
  }

  /**
   * Determine whether a live MDP API client is available.
   *
   * @return bool True when `wicket_api_client()` returns a usable client.
   */
  private function mdp_client_available() {
    // The base plugin exposes the client factory; it returns false when the SDK is missing
    // or the connection/authorization fails.
    return function_exists( 'wicket_api_client' ) && (bool) wicket_api_client();
  }

  /**
   * Fetch all membership tiers from the MDP, following pagination to completion.
   *
   * Uses the base plugin's `get_individual_memberships()` helper (GET `memberships`). The
   * endpoint returns both individual and organization tiers together and paginates JSON:API
   * style, so we request an explicit page size and follow `links.next` until it is null.
   *
   * @return array<int, array<string, mixed>>  Flat list of MDP tier resource objects.
   */
  private function get_mdp_tiers() {
    if ( ! function_exists( 'get_individual_memberships' ) ) {
      WP_CLI::error( 'Base plugin helper get_individual_memberships() is unavailable.' );
    }

    $tiers = array();
    $page  = 1;

    do {
      $response = get_individual_memberships( '', array(
        'sort' => '-category_weight',
        'page' => array(
          'size'   => self::MDP_PAGE_SIZE,
          'number' => $page,
        ),
      ) );

      // The helper swallows exceptions and can return null on failure.
      if ( empty( $response['data'] ) ) {
        break;
      }

      foreach ( $response['data'] as $item ) {
        $tiers[] = $item;
      }

      // Continue only while the MDP advertises a next page.
      $has_next = ! empty( $response['links']['next'] );
      ++$page;
    } while ( $has_next && $page <= self::MDP_MAX_PAGES );

    return $tiers;
  }

  /**
   * Create a `wicket_mship_tier` post from an MDP tier resource.
   *
   * Writes a minimal, valid `tier_data` blob linked by `mdp_tier_uuid` and attached to the
   * given config. `product_data` is intentionally empty — products are added later in the
   * admin UI (a placeholder product id would fatal the tier admin, which loads each product
   * via `wc_get_product()` unguarded). `tier_data` is passed through `meta_input` so it is
   * persisted before `save_post_wicket_mship_tier` fires, letting the existing slug hook
   * (`Helper::add_slug_on_mship_tier_create`) resolve `membership_tier_slug` correctly.
   *
   * @param array<string, mixed> $mdp_tier   MDP tier resource object (`id` + `attributes`).
   * @param int                  $config_id  Config post ID to attach.
   *
   * @return int|\WP_Error  New post ID on success, WP_Error on failure.
   */
  private function create_tier( $mdp_tier, $config_id ) {
    $attributes = $mdp_tier['attributes'] ?? array();
    $tier_data  = $this->build_tier_data( $mdp_tier, $config_id );

    return wp_insert_post( array(
      'post_type'   => Helper::get_membership_tier_cpt_slug(),
      'post_title'  => $tier_data['mdp_tier_name'],
      'post_status' => 'publish',
      'meta_input'  => array(
        'tier_data' => $tier_data,
        // Pre-seed the slug from the data we already hold, so it is correct even if the
        // save_post hook's own lookup fails; the hook may re-set it to the same value.
        'membership_tier_slug' => $attributes['slug'] ?? '',
      ),
    ), true );
  }

  /**
   * Build the minimal `tier_data` array for a new tier from an MDP tier resource.
   *
   * @param array<string, mixed> $mdp_tier   MDP tier resource object.
   * @param int                  $config_id  Config post ID to attach.
   *
   * @return array<string, mixed>  The `tier_data` meta value.
   */
  private function build_tier_data( $mdp_tier, $config_id ) {
    $attributes = $mdp_tier['attributes'] ?? array();
    $type       = ( 'organization' === ( $attributes['type'] ?? '' ) ) ? 'organization' : 'individual';

    $tier_data = array(
      'mdp_tier_uuid' => (string) ( $mdp_tier['id'] ?? '' ),
      'mdp_tier_name' => (string) ( $attributes['name_en'] ?? '' ),
      'config_id'     => (int) $config_id,
      'type'          => $type,
      // Sensible, benign default; the actual renewal path is configured in the admin UI.
      'renewal_type'  => 'subscription',
      // MDP 'approval' enum maps to the tier's approval flag; default off when not required.
      'approval_required' => ( 'approval_required' === ( $attributes['approval'] ?? '' ) ) ? 1 : 0,
      // No products yet — the admin selects the WooCommerce subscription product later.
      'product_data'  => array(),
    );

    // Organization tiers carry a seat type derived from the MDP assignment model:
    // unlimited_assignments=true -> range buckets; false -> per-seat (qty-driven).
    if ( 'organization' === $type ) {
      $tier_data['seat_type'] = ! empty( $attributes['unlimited_assignments'] ) ? 'per_range_of_seats' : 'per_seat';
    }

    return $tier_data;
  }

  /**
   * Refresh only the `mdp_tier_name` on an existing tier (update policy = name only).
   *
   * @param int    $post_id  Existing tier post ID.
   * @param string $name     New MDP name (`name_en`).
   *
   * @return void
   */
  private function refresh_tier_name( $post_id, $name ) {
    $tier = new Membership_Tier( $post_id );

    if ( ! is_array( $tier->tier_data ) ) {
      return; // Malformed tier; leave untouched rather than risk clobbering it.
    }

    $tier_data                  = $tier->tier_data;
    $tier_data['mdp_tier_name'] = (string) $name;
    $tier->update_tier_data( $tier_data );
  }

  /**
   * Build display rows for local tier posts.
   *
   * @return array<int, array<string, mixed>>  One row per local tier.
   */
  private function get_local_tier_rows() {
    $posts = get_posts( array(
      'post_type'      => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
      'post_status'    => 'any',
    ) );

    $rows = array();
    foreach ( $posts as $post ) {
      $tier = new Membership_Tier( $post->ID );
      $rows[] = array(
        'post_id' => $post->ID,
        'name'    => $tier->get_mdp_tier_name(),
        'type'    => $tier->get_tier_type(),
        'uuid'    => $tier->get_mdp_tier_uuid(),
      );
    }

    return $rows;
  }

  /**
   * Print the reconciliation plan/results table (drops the internal `mdp` payload column).
   *
   * @param array<int, array<string, mixed>> $plan    Plan rows.
   * @param string                           $format  WP-CLI output format.
   *
   * @return void
   */
  private function print_results( $plan, $format ) {
    $rows = array_map( static function ( $row ) {
      return array(
        'action'  => $row['action'],
        'type'    => $row['type'],
        'name'    => $row['name'],
        'uuid'    => $row['uuid'],
        'post_id' => $row['post_id'],
      );
    }, $plan );

    Utils\format_items( $format, $rows, array( 'action', 'type', 'name', 'uuid', 'post_id' ) );
  }
}
