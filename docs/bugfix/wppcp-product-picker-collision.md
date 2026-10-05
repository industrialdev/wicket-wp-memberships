---
title: "WP Private Content Plus Collision — Admin Product & Page Pickers"
audience: [developer]
php_class: Membership_WP_REST_Controller
source_files:
  - "includes/Membership_WP_REST_Controller.php"
  - "frontend/src/services/api.js"
ticket: WWID-1763
branch: bugfix/WWID-1763-tier-assigned-products-display-hidden
---

# WP Private Content Plus Collision — Admin Product & Page Pickers

Products assigned to a membership tier disappeared from the tier edit page's product
picker, and products already assigned to *other* tiers reappeared as selectable. Root
cause is a collision with the **WP Private Content Plus** (WPCP) plugin, not a bug in
this plugin's own logic. WPCP's front-end visibility filtering reaches into every REST
request, including staff-only admin screens.

The fix moves this plugin's product and variation lookups off WooCommerce's
`/wc/v3/products` routes and onto plugin-namespaced endpoints that suppress WPCP for
those queries only.

---

## Cause

WPCP hooks `pre_get_posts` globally:

```
wp-private-content-plus/functions.php:278  exclude_restricted_posts_from_search()
wp-private-content-plus/functions.php:330  add_filter( 'pre_get_posts', ... )
```

Its entry condition looks like it only targets search queries:

```php
if ( ( $query->is_search && ! is_admin() && $query->is_main_query() ) ||
     ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $query->query_vars['s'] ) ) ) {
```

The second branch is the problem. `s` is in the key list that
`WP_Query::fill_query_vars()` initialises to an empty string, and `pre_get_posts` fires
*after* `parse_query()`. So `isset( $query->query_vars['s'] )` is **always true**. The
handler therefore runs on every `WP_Query` in a REST context — no search term required,
no route restriction, admin screens included.

Once inside, it collects every post carrying `_wppcp_post_page_visibility` of `member`,
`role`, or `users` (queried with `post_type => 'any'`) and applies them as an exclusion:

```
wp-private-content-plus/functions.php:326  $query->set( 'post__not_in', $restricted_posts );
```

That single line produces two distinct failure modes.

### Failure mode 1 — restricted products vanish

Any product with a restriction set is excluded from the query, so it never appears in the
picker. The tier still references it in `tier_data`; the UI just cannot show or re-select
it.

### Failure mode 2 — the picker's exclusion list is silently discarded

`$query->set( 'post__not_in', ... )` **overwrites rather than merges**. WooCommerce maps
its REST `exclude` parameter onto that same `post__not_in` key. The pickers use `exclude`
to hide products already assigned to another tier, so WPCP's assignment throws that list
away.

Consequence worth internalising: **a single restricted post of any type, anywhere on the
site, breaks the exclusion list for all product pickers.** It does not need to be a
product, and it fires even when zero products are restricted.

### Why this reaches CPTs, not just posts and pages

WPCP registers its restriction metabox on every registered post type except a short skip
list:

```
wp-private-content-plus/classes/class-wppcp-private-posts-pages.php:36
$skipped_types = array( 'attachment', 'revision', 'nav_menu_item',
                        'wppcp_private_block', 'wppcp_group', 'wppcp_fproduct_tabs' );
```

`product`, `wicket_mship_tier`, `wicket_mship_config`, and `wicket_membership` are all
absent from that list, so every one of them can carry a restriction. This is why the
problem is not confined to the page pickers where it was first seen.

---

## Effect (observed)

| Screen | Failure mode 1 | Failure mode 2 |
|---|---|---|
| Tier edit — product picker (`membership_tiers/manage_products.js`) | Yes | Yes — passes `exclude: productsInUse` |
| Tier edit — variation picker | Yes | Yes — passes `exclude: productVariationsInUse` |
| Config edit (`membership_configs/edit.js`) | Yes | No — never passes `exclude` |
| Member edit → switch membership (`members/switch_membership.js`) | Yes | No — never passes `exclude` |
| Member edit → create renewal order (`members/create_renewal_order.js`) | Yes | No — never passes `exclude` |

Failure mode 1 affected **all four screens**; they were silently missing restricted
products. Only the tier edit page was exposed to failure mode 2, which is why the ticket
was filed against it — the resurfacing of other tiers' products is far more visible than
an absence.

---

## The repair

Two new endpoints on `wicket_member/v1`, both capability-gated by
`permissions_check_read()` (`includes/Membership_WP_REST_Controller.php:829`, requires
`WICKET_MEMBERSHIPS_CAPABILITY`):

| Route | Method | Returns |
|---|---|---|
| `GET /wicket_member/v1/wc_products_all` | `get_all_wc_products()` :690 | `[{ id, name, type }]` |
| `GET /wicket_member/v1/wc_product_variations/{id}` | `get_wc_product_variations()` :752 | `[{ id, name }]` |

Both suppress WPCP for the duration of their own query using WPCP's own documented escape
hatch — the same one WPCP uses internally to avoid recursing on its own lookup:

```php
add_filter( 'disable_restriction_checks', '__return_true' );
$products = wc_get_products( $args );
remove_filter( 'disable_restriction_checks', '__return_true' );
```

Supporting changes:

- `parse_exclude_ids()` (:652) normalises `exclude` from either a comma-separated string
  (products) or an array (variations), dropping zeros so a trailing comma cannot become
  post ID `0`.
- `parse_per_page_param()` / `parse_status_param()` normalise `per_page` into a
  `wc_get_products()` `limit` (-1 = all, non-positive falls back to `/wc/v3`'s default of 10)
  and default `status` to `publish` so an omitted parameter cannot widen results to drafts.
- `frontend/src/services/api.js` — `fetchWcProducts()` and `fetchProductVariations()`
  repointed at the new routes. **The route is the only thing that changed:** `status` and
  `per_page` are still forwarded by the callers and still honoured server-side.

### Deliberately not changed: pagination

An earlier revision of this fix hardcoded `status => publish` and `limit => -1`, dropping
`per_page` on the floor. That silently lifted the 100-row cap on every picker.

It was reverted. Removing the cap was not required to fix the WPCP collision, so it was
unrelated scope riding along inside a repair — exactly the kind of change that makes a fix
interfere with working functionality. The endpoints now consume both parameters and remain
a drop-in replacement for `/wc/v3/products`.

If the 100-row cap is a genuine problem for large catalogues, that is a separate ticket
with its own testing, not a side effect of this one.

### Why an endpoint and not a filter on `/wc/v3/products`

A dispatch-level filter on the WooCommerce route cannot distinguish this plugin's admin
requests from any other consumer of that route. It would relax WPCP restrictions for
WooCommerce itself and every other caller on the site — a visibility regression well
outside this ticket. Keeping the bypass on our own namespace confines it to requests this
plugin's admin screens make.

Precedent: `get_all_wp_pages()` (:611) already applies this identical workaround to the
renewal form page picker.

---

## Blast radius of the repair

The two API helpers are shared, so repointing them moved **all four screens** at once,
not just the tier edit page named in the ticket.

**Intended and beneficial.** Failure mode 1 affected all four, so config edit, switch
membership, and create renewal order are fixed as a side effect rather than merely
disturbed.

**One regression introduced and fixed.** `get_wc_product_variations()` initially returned
`[{ id }]` only, on the assumption that the pickers render just the ID. True for the tier
edit page; false for two others:

- `members/switch_membership.js:207,216`
- `members/create_renewal_order.js:353`

Both label options with `` `${variation.name} (#${variation.id})` ``. WooCommerce's v3
variations controller supplied that field
(`class-wc-rest-product-variations-controller.php:160`), so dropping it rendered every
option as `undefined (#123)`. The regression was present in the committed
`frontend/build/member_edit.js`.

Resolved server-side by returning `name` from the same call WooCommerce itself uses, so
the labels are byte-identical to the pre-change output:

```php
'name' => wc_get_formatted_variation( $variation, true, false, false ),
```

No frontend rebuild was required — the fix is PHP-only, and the accompanying `api.js`
change is comment-only (the build strips comments).

**No behavioural change beyond visibility.** `status` and `per_page` are honoured, so row
counts and post-status filtering are identical to the `/wc/v3` behaviour. The only intended
difference is that restricted products now appear and the picker's `exclude` list survives.

**Frontend rebuild required.** Because `api.js` forwards the two parameters, the change is
not comment-only: `frontend/build/` was rebuilt via `npm run build`. `member_edit.js`,
`membership_tier_create.js`, and `membership_config_create.js` are all affected.

---

## Verification performed

- WPCP's `disable_restriction_checks` filter confirmed present and honoured at
  `functions.php:284`, gated on `doing_action( 'pre_get_posts' )`.
- `s` confirmed always set via `WP_Query::fill_query_vars()`, establishing that the
  handler runs on all REST queries rather than only searches.
- `post__not_in` overwrite confirmed at `functions.php:326`.
- WPCP metabox confirmed registered for all post types bar the skip list, establishing
  that products and this plugin's CPTs can all carry restrictions.
- Every field consumed from both endpoints audited across all call sites: `product.id`,
  `product.name`, `product.type`, `variation.id`, `variation.name` — all present in the
  new payloads. (`product.max_seats`, `product.product_id`, `product.variation_id` come
  from stored tier config, not these endpoints.)
- `php -l` clean on the modified controller.

---

## Regression coverage

Lives in the stack QA suite, not this repo:
`qa/tests/WordPress/Memberships/wpcp-product-visibility.pest.php`.

Run with `composer test:wordpress:memberships` from `qa/`. Must be the **WordPress** suite —
Unit is Brain Monkey, where `pre_get_posts` and `REST_REQUEST` do not exist, so the bug is
unreproducible by construction.

| Test | Guards |
|---|---|
| returns products WPCP hides | Failure mode 1 — the reported bug |
| still hides that product from `wc/v3/products` | Canary: proves WPCP is actually filtering |
| keeps excluding in-use products when a restricted post exists | Failure mode 2 |
| returns the id, name and type each picker reads | Fields the four React pickers consume |
| returns variations with a name, not just an id | The `undefined (#123)` regression |
| honours `per_page` instead of always returning everything | Keeps the pagination revert reverted |

### Three harness prerequisites, or the tests are worthless

Each of these silently produces a suite that passes against the *unfixed* code. The first two
are handled by `memberships_enable_wpcp()` in
`qa/tests/WordPress/Memberships/support/bootstrap.php`.

1. **Activating WPCP is not enough.** It wires itself entirely from `plugins_loaded` and only
   requires `functions.php` — which registers the `pre_get_posts` handler — from inside that
   callback. A test activates it long after `plugins_loaded` fired, so `active_plugins` gets set
   and nothing else: `is_plugin_active()` returns true while none of WPCP's hooks exist.
   `wppcp_plugin_init()` must be invoked directly. Measured before/after:
   `functions.php loaded=false → true`, `pre_get_posts hooked=false → true`.
2. **`REST_REQUEST` must be defined manually.** `rest_do_request()` does not define it;
   WordPress only does so when serving a real HTTP REST request. It is a process-wide constant,
   which is why the helper is opt-in and deliberately excluded from
   `memberships_boot_wordpress()`.
3. **Assert ID membership, never counts or positions.** The runtime SQLite database is only
   reset by `test:wordpress:prepare`, and the two endpoints return rows in different orders.

### Verified non-vacuous by mutation

The fix was deliberately broken and the suite re-run, twice:

| Mutation applied to the fix | Result |
|---|---|
| WPCP bypass removed from `get_all_wc_products()` | *returns products WPCP hides* and *keeps excluding in-use products* fail; other four pass |
| `name` dropped from the variation payload | *returns variations with a name* fails; other five pass |

The `/wc/v3` canary passed under both mutations, which is its purpose — it fails only if WPCP
stops filtering in the runtime, signalling that the other tests have gone vacuous rather than
letting them pass against a broken endpoint.

### Incidental findings from writing them

- The WordPress suite shares one PHP process and already ran close to the default 128M;
  dispatching REST requests pulls in the WooCommerce REST controllers plus WPCP's class graph
  and exceeds it. Raised inside the test file. The durable fix is a `memory_limit` on the base
  command in `qa/orchestration/run-pest-suite.php`, which currently sets one only for coverage
  runs — the next REST-based test file will hit this too.
- `memberships_teardown()` exhausts memory in this suite. No existing test uses it; the
  established idiom is `memberships_save_snapshot()` / `memberships_restore_snapshot()`, which
  also gives per-test isolation.
- Unrelated latent theme bug: `wicket-wp-theme/custom/api-rate-limiting.php:85` reads
  `$_SERVER['HTTP_SEC_FETCH_SITE']` unguarded, warning on any CLI REST request. The test file
  supplies the header as realistic request context rather than fixing another repo. Deserves
  its own ticket.

### Not covered

- **The four React pickers themselves.** They are the actual consumers, but there is no JS test
  runner in `frontend/` (only `build` and `start`), and `qa`'s Browser suite covers
  AccountCentre, GravityForms and GuestCheckout only — there is no Memberships browser suite.
  Coverage stops at the HTTP contract those pickers depend on.
- **WPCP's site-lockdown and global-restriction branches, for the *product* endpoints.** Both are
  covered against `wp_pages_all` (see the pages suite below), but the handler applies them to
  every REST-context query regardless of post type, so an enabled global restriction would empty
  the product pickers too. The bypass already protects them; nothing asserts it. Cheap to add by
  reusing `memberships_set_wppcp_options()` from the pages file.

---

## The pages endpoint — same collision, worse failure mode

`get_all_wp_pages()` (`GET /wicket_member/v1/wp_pages_all`, :611) predates this ticket and
applies the identical bypass for the renewal-form page pickers. It is *not* simply the products
case with a different post type; the differences matter for testing.

Consumers — both call the route inline via `apiFetch`, with **no helper in
`frontend/src/services/api.js`**, so unlike the product path there is no single chokepoint to
change:

- `frontend/src/membership_tiers/edit.js:254`
- `frontend/src/members/edit.js:304`

Both read `page.id` and `page.title.rendered`, the latter through `he.decode()`.

**The payload shape is load-bearing in a way the products payload is not.**
`he.decode(page.title.rendered)` throws a `TypeError` on `undefined`. Where trimming the
variation payload produced a cosmetic `undefined (#123)` label, trimming `title.rendered` here
takes the whole picker down with an exception. The nested `title: { rendered }` shape is not
decoration.

**Three vectors reach pages, where products has one and a half:**

1. `pre_get_posts` meta-based exclusion — the same failure mode 1.
2. `rest_prepare_page` (`wp-private-content-plus/functions.php:271`) returns a
   `rest_forbidden` **403** for a restricted page. Core `/wp/v2/pages` is subject to it;
   `get_all_wp_pages()` builds its own array from `get_posts()` and never runs
   `prepare_item_for_response`, so it bypasses this structurally rather than via the filter.
3. **Site lockdown and global page restriction** — two branches that run *before* the meta
   query (`functions.php:292-304`), keyed off the `wppcp_options` option:

   | Setting | Effect on any REST-context query |
   |---|---|
   | `site_lockdown.lockdown_status = 'enabled'` | `post__in` forced to `lockdown_allowed_posts`, or `array(0)` when empty |
   | `global_page_restriction.restrict_all_pages_status = '1'` | `post__in` forced to `array(0)` |

   Either one empties the picker completely. Both sit behind the same
   `disable_restriction_checks` guard, so the existing bypass already covers them.

   **These are not page-specific despite the option name.** The handler applies them to every
   query in a REST context regardless of post type, so an enabled global restriction would
   empty the *product* pickers too. Neither branch is covered by the product tests above.

Failure mode 2 does **not** apply: `get_all_wp_pages()` accepts no parameters at all, so there
is no `exclude` list for WPCP to clobber. It also takes no `status` or `per_page` — `publish`
and `numberposts => -1` are hardcoded, and no consumer passes anything. The unbounded query is
pre-existing, not introduced.

### Regression coverage — pages endpoint

`qa/tests/WordPress/Memberships/wpcp-page-visibility.pest.php`. Reuses
`memberships_enable_wpcp()` unchanged. Pages need no factory —
`wp_insert_post( [ 'post_type' => 'page' ] )` is sufficient.

| Test | Guards |
|---|---|
| returns pages WPCP hides | Failure mode 1 for pages |
| still hides that page from `wp/v2/pages` | Canary: proves WPCP is filtering |
| returns id and a rendered title string | `he.decode()` throws on `undefined`, taking both pickers down |
| survives WPCP global page restriction | `post__in => array(0)` empties the picker outright |
| survives WPCP site lockdown | Same, via the lockdown branch |
| does not leak the WPCP bypass to later queries | `remove_filter()` actually restores state |
| returns only published pages | Drafts stay out if the hardcoded status is loosened |

The lockdown and global-restriction tests write `wppcp_options`; the
`memberships_restore_snapshot()` idiom reverts it per test, so no bespoke option handling is
needed. The two core-route contrast assertions request `_fields=id`, which avoids rendering page
content — see the incidental findings below.

### The leak test, added to both suites

*does not leak the WPCP bypass to later queries* now exists in the products file too. It was the
one case verifying the `remove_filter()` half of the pattern: call the plugin endpoint, then call
the core route, and assert the restricted item is *still* hidden. Without it, a dropped
`remove_filter()` would leave `disable_restriction_checks` attached for the remainder of the
request and quietly relax WPCP for every later consumer — precisely the outcome that choosing an
endpoint over a dispatch filter was meant to prevent.

### Verified non-vacuous by mutation — pages and leak

| Mutation applied to the fix | Result |
|---|---|
| WPCP bypass removed from `get_all_wp_pages()` | 4 of 7 pages tests fail (hides / global restriction / lockdown / leak sanity); canary, shape and published-only correctly unaffected |
| `remove_filter()` only, dropped from `get_all_wp_pages()` | Canary + both lockdown contrasts + leak test fail; *returns pages WPCP hides* still passes |
| `remove_filter()` only, dropped from `get_all_wc_products()` | Canary + leak test fail; other five pass |

The last two mutations produce a **discriminating signature**: removing the whole bypass fails
the "hides" test while the canary passes; leaking the bypass does the exact opposite. A reader
diagnosing a red suite can tell the two apart from the failure pattern alone.

### A harness bug the canary caught

Worth recording, because it is the failure mode these canaries exist for. Adding the pages file
broke the *products* canary. Cause: the pages file's `afterAll` calls
`memberships_disable_wpcp()`, which detaches the `pre_get_posts` handler; the products file then
calls `memberships_enable_wpcp()`, but `WP_Private_Content_Plus()` is a singleton, so
`wppcp_plugin_init()` returned the existing instance without re-including `functions.php` or
re-registering the handler. WPCP was inert for the entire products file.

Every WPCP assertion in that file would have passed against unfixed code. The canary failed
instead, which is the whole point of shipping one. `memberships_enable_wpcp()` now re-attaches
the handler explicitly when it finds it detached, making the helper idempotent and independent
of file ordering.

### Further incidental findings

- A third pre-existing theme bug: `wicket-wp-theme/custom/blocks.php:172` passes `null` to
  `str_contains()` when a block has no `blockName`, which fires whenever `/wp/v2/pages` renders
  page content. The tests pass `_fields=id` to the core route — they only assert on IDs, and
  skipping content rendering is both quieter and faster. Same class of latent bug as the
  `HTTP_SEC_FETCH_SITE` one above; both deserve a ticket against the theme.
- Shared helpers (`memberships_rest_get()`, `memberships_rest_ids()`,
  `memberships_wpcp_restrict()`) live in `support/bootstrap.php` rather than in either test file.
  `pest.php` `require_once`s that bootstrap for WordPress Memberships runs, so declaring them in
  two test files is a redeclaration fatal.

---

## Known remaining exposure — not addressed by this change

The bypass is per-callsite. Three callsites now carry it: `get_all_wp_pages()`,
`get_all_wc_products()`, `get_wc_product_variations()`. The underlying WPCP behaviour
affects *every* `WP_Query` made during a REST request, so other paths remain structurally
exposed and were not audited as part of this ticket:

- **`fetchTiers()`** (`api.js:8`) requests `/wp/v2/wicket_mship_tier` — a core WP REST
  route, which runs through `WP_Query` and therefore through WPCP. A tier post with a
  restriction set would vanish from the tier picker. Confirmed reachable: `wicket_mship_tier`
  is not in WPCP's skip list. Not observed in the wild; no `exclude` is passed, so only
  failure mode 1 applies.
- **`Membership_Tier.php`** calls `get_posts()` in roughly eight places (:41, :71, :104,
  :141, :175, :207, :689). Any of these executing inside a REST request inherits the same
  filtering.

Because the same root cause has now required three separate one-off bypasses, a shared
helper that runs an arbitrary query with WPCP suppressed would be the better shape if a
fourth is needed — still scoped to this plugin's own routes, which remains the correct
boundary.

---

## References

- Asana: [WWID-1763](https://app.asana.com/1/1138832104141584/project/1215225770793728/task/1215840563654232)
- [Membership_WP_REST_Controller class reference](../engineering/Class-Membership_WP_REST_Controller.md)
