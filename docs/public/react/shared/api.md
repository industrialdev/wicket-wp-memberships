---
title: API Service Layer
---

# API Service Layer

All HTTP calls in the plugin frontend go through `shared/services/api.js`. Every function uses `@wordpress/api-fetch` under the hood and returns a Promise that resolves to the parsed response body. URL query strings are assembled with `@wordpress/url`'s `addQueryArgs`.

## API URL Constants

Three namespaced base paths are exported from `shared/constants.js` and used internally by this module.

| Constant | Value | Used for |
|---|---|---|
| `API_URL` | `/wp/v2` | WordPress core REST API (e.g. fetching CPT posts) |
| `PLUGIN_API_URL` | `/wicket_member/v1` | All Wicket Memberships plugin endpoints |
| `BASE_PLUGIN_API_URL` | `/wicket-base/v1` | Wicket Base Plugin shared endpoints (e.g. org search) |
| `WC_API_V3_URL` | `/wc/v3` | WooCommerce REST API v3 |

`WC_API_V3_URL` is imported in `api.js` but `BASE_PLUGIN_API_URL` is also used there for org lookup calls that route through the base plugin rather than the memberships plugin.

---

## Quick Reference

| Function | Method | Endpoint | Returns |
|---|---|---|---|
| `fetchMembers` | GET | `/wicket_member/v1/memberships` | Paginated membership record list |
| `fetchMemberInfo` | GET | `/wicket_member/v1/admin/get_edit_page_info` | Member edit page data |
| `fetchMemberships` | GET | `/wicket_member/v1/membership_entity` | Membership entity objects for an entity ID |
| `updateMembership` | POST | `/wicket_member/v1/membership_entity/<id>/update` | Updated membership object |
| `updateMembershipStatus` | POST | `/wicket_member/v1/admin/manage_status` | Status transition response |
| `fetchMembershipStatuses` | GET | `/wicket_member/v1/admin/status_options` | Available status transition options |
| `fetchMembershipFilters` | GET | `/wicket_member/v1/membership_filters` | Available filter options for the members list |
| `createRenewalOrder` | POST | `/wicket_member/v1/membership/<id>/create_renewal_order` | New renewal order data |
| `fetchMembershipBundles` | GET | `/wicket_member/v1/membership_bundles` | Paginated bundle list |
| `fetchMembershipBundleFilters` | GET | `/wicket_member/v1/membership_bundle_filters` | Available filter options for the bundles list |
| `fetchBundleEditPageInfo` | GET | `/wicket_member/v1/bundle/admin/get_edit_page_info` | Bundle edit page data |
| `fetchMembershipBundleStatuses` | GET | `/wicket_member/v1/bundle/admin/status_options` | Available bundle status transition options |
| `updateMembershipBundleStatus` | POST | `/wicket_member/v1/bundle/admin/manage_status` | Status transition response |
| `updateMembershipBundle` | POST | `/wicket_member/v1/membership_bundle_entity/<id>/update` | Updated bundle object |
| `createMembershipBundle` | POST | `/wicket_member/v1/bundle` | Newly created bundle object |
| `createBundleRenewalOrder` | POST | `/wicket_member/v1/bundle/<id>/create_renewal_order` | New renewal order data |
| `fetchBundleMembersByTier` | GET | `/wicket_member/v1/bundle/<id>/members_by_tier` | Bundle members keyed by tier |
| `addMemberToBundle` | POST | `/wicket_member/v1/bundle/<id>/add_member` | New or linked membership record |
| `removeMemberFromBundle` | POST | `/wicket_member/v1/bundle/<id>/remove_member` | Operation result |
| `moveIndividualMembership` | POST | `/wicket_member/v1/bundle/<id>/move_individual_membership` | Operation result |
| `cancelMembershipBundle` | POST | `/wicket_member/v1/bundle/<id>/cancel` | Cancellation result |
| `updateBundleChangeOwnership` | POST | `/wicket_member/v1/bundle/<id>/change_owner` | Updated bundle object |
| `fetchTiers` | GET | `/wp/v2/wicket_mship_tier` | WordPress post objects for all published tiers |
| `fetchMembershipTiers` | GET | `/wicket_member/v1/membership_tiers` | Tier objects |
| `fetchTiersInfo` | GET | `/wicket_member/v1/membership_tier_info` | Tier info objects for given UUIDs |
| `fetchWcProducts` | GET | `/wc/v3/products` | WooCommerce product objects |
| `fetchProductVariations` | GET | `/wc/v3/products/<id>/variations` | WooCommerce variation objects |
| `fetchMembershipProducts` | GET | `/wicket_member/v1/membership_products` | Product/variation display name objects |
| `fetchBundlesInfo` | GET | `/wicket_member/v1/membership_bundle_info` | Bundle info objects for given IDs |
| `fetchMdpPersons` | POST | `/wicket_member/v1/mdp_person/search` | MDP person objects |
| `fetchSearchOrgs` | POST | `/wicket-base/v1/search-orgs` | Org objects matching the search term |
| `fetchOrgByUuid` | GET | `/wicket_member/v1/org_data` | Org display data object |

---

## Members

### `fetchMembers(params)`

Fetches a paginated list of membership records.

| Name | Type | Required | Description |
|---|---|---|---|
| `params` | `object` | Yes | Query parameters forwarded to the endpoint (e.g. `page`, `per_page`, filters) |

**Endpoint:** `GET /wicket_member/v1/memberships`

**Returns:** Promise resolving to an array of membership record objects.

---

### `fetchMemberInfo(recordId)`

Fetches all data needed to populate the individual member edit page.

| Name | Type | Required | Description |
|---|---|---|---|
| `recordId` | `string\|number` | Yes | MDP entity/person UUID or WP user ID used to scope the query |

**Endpoint:** `GET /wicket_member/v1/admin/get_edit_page_info?entity_id=<recordId>`

**Returns:** Promise resolving to an object containing the member's edit page data.

---

### `fetchMemberships(recordId)`

Fetches all membership entity posts for a given entity ID.

| Name | Type | Required | Description |
|---|---|---|---|
| `recordId` | `string\|number` | Yes | MDP entity ID to filter by |

**Endpoint:** `GET /wicket_member/v1/membership_entity?entity_id=<recordId>`

**Returns:** Promise resolving to an array of membership entity objects.

---

### `updateMembership(membershipId, data)`

Updates editable fields on a membership record.

| Name | Type | Required | Description |
|---|---|---|---|
| `membershipId` | `string\|number` | Yes | WP post ID of the membership |
| `data` | `object` | Yes | Fields to update |

**Endpoint:** `POST /wicket_member/v1/membership_entity/<membershipId>/update`

**Returns:** Promise resolving to the updated membership object.

---

### `updateMembershipStatus(membershipId, status)`

Transitions a membership to a new status.

| Name | Type | Required | Description |
|---|---|---|---|
| `membershipId` | `string\|number` | Yes | WP post ID of the membership |
| `status` | `string` | Yes | Target status slug (see membership status vocabulary) |

**Endpoint:** `POST /wicket_member/v1/admin/manage_status`

**Returns:** Promise resolving to the response from the status transition.

---

### `fetchMembershipStatuses(postId)`

Fetches the list of valid status transitions available for a given membership post.

| Name | Type | Required | Description |
|---|---|---|---|
| `postId` | `string\|number` | Yes | WP post ID of the membership |

**Endpoint:** `GET /wicket_member/v1/admin/status_options?post_id=<postId>`

**Returns:** Promise resolving to an array of available status option objects.

---

### `fetchMembershipFilters(memberType)`

Fetches filter options (e.g. tier list, status list) for the memberships list view.

| Name | Type | Required | Description |
|---|---|---|---|
| `memberType` | `string` | Yes | Member type context for which to return filter options |

**Endpoint:** `GET /wicket_member/v1/membership_filters?type=<memberType>`

**Returns:** Promise resolving to an object containing available filter options.

---

### `createRenewalOrder(membershipId, productId, variationId)`

Creates a renewal order for an individual membership.

| Name | Type | Required | Description |
|---|---|---|---|
| `membershipId` | `string\|number` | Yes | WP post ID of the membership |
| `productId` | `number` | Yes | WooCommerce product ID to attach to the renewal |
| `variationId` | `number` | Yes | WooCommerce variation ID; pass `0` if not a variable product |

**Endpoint:** `POST /wicket_member/v1/membership/<membershipId>/create_renewal_order`

**Returns:** Promise resolving to the new renewal order data.

---

## Bundles

### `fetchMembershipBundles(params)`

Fetches a paginated list of membership bundle posts.

| Name | Type | Required | Description |
|---|---|---|---|
| `params` | `object` | Yes | Query parameters forwarded to the endpoint |

**Endpoint:** `GET /wicket_member/v1/membership_bundles`

**Returns:** Promise resolving to an array of membership bundle objects.

---

### `fetchMembershipBundleFilters()`

Fetches filter options for the membership bundles list view. Takes no parameters.

**Endpoint:** `GET /wicket_member/v1/membership_bundle_filters`

**Returns:** Promise resolving to an object containing available filter options.

---

### `fetchBundleEditPageInfo(bundleGroupUuid)`

Fetches all data required to populate the membership bundle detail/edit page.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundleGroupUuid` | `string` | Yes | The `membership_bundle_group_uuid` shared by all posts in the bundle series |

**Endpoint:** `GET /wicket_member/v1/bundle/admin/get_edit_page_info?bundle_group_uuid=<uuid>`

**Returns:** Promise resolving to the full bundle edit page data object.

---

### `fetchMembershipBundleStatuses(bundlePostId)`

Fetches available status transition options for a membership bundle post.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `string\|number` | Yes | WP post ID of the membership bundle |

**Endpoint:** `GET /wicket_member/v1/bundle/admin/status_options?bundle_post_id=<id>`

**Returns:** Promise resolving to an array of available status option objects.

---

### `updateMembershipBundleStatus(bundlePostId, status)`

Transitions a membership bundle to a new status.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `string\|number` | Yes | WP post ID of the membership bundle |
| `status` | `string` | Yes | Target status slug |

**Endpoint:** `POST /wicket_member/v1/bundle/admin/manage_status`

**Returns:** Promise resolving to the status transition response.

---

### `updateMembershipBundle(bundlePostId, data)`

Updates editable fields on a membership bundle post (dates, renewal type, owner, etc.).

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `string\|number` | Yes | WP post ID of the membership bundle |
| `data` | `object` | Yes | Fields to update |

**Endpoint:** `POST /wicket_member/v1/membership_bundle_entity/<bundlePostId>/update`

**Returns:** Promise resolving to the updated bundle object.

---

### `createMembershipBundle(data)`

Creates a new membership bundle.

| Name | Type | Required | Description |
|---|---|---|---|
| `data.name` | `string` | Yes | Display name for the bundle |
| `data.membership_bundle_config_id` | `number` | Yes | WP post ID of the bundle config to apply |
| `data.org_uuid` | `string` | Yes | MDP UUID of the organisation the bundle belongs to |
| `data.owner_uuid` | `string` | Yes | MDP UUID of the person who owns the bundle |
| `data.start_date` | `string` | Yes | ISO 8601 start date |

**Endpoint:** `POST /wicket_member/v1/bundle`

**Returns:** Promise resolving to the newly created bundle object.

---

### `createBundleRenewalOrder(bundlePostId)`

Creates a renewal order against the bundle's existing WooCommerce subscription. Does not create a new subscription.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle |

**Endpoint:** `POST /wicket_member/v1/bundle/<bundlePostId>/create_renewal_order`

**Returns:** Promise resolving to the new renewal order data.

---

### `fetchBundleMembersByTier(bundlePostId)`

Fetches bundle member records broken down by tier.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `string\|number` | Yes | WP post ID of the membership bundle |

**Endpoint:** `GET /wicket_member/v1/bundle/<bundlePostId>/members_by_tier`

**Returns:** Promise resolving to an object keyed by tier with arrays of member records.

---

### `addMemberToBundle(bundlePostId, data)`

Adds a member to a membership bundle.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle |
| `data.mode` | `'new'\|'existing'` | Yes | Whether to create a new membership or link an existing one |
| `data.tier_post_id` | `number` | Yes | WP post ID of the tier the member should be placed in |
| `data.person_uuid` | `string` | When `mode='new'` | MDP UUID of the person to add |
| `data.existing_membership_post_id` | `number` | When `mode='existing'` | Post ID of the existing membership to link |
| `data.product_id` | `number` | No | WooCommerce product ID; auto-resolved by the backend when omitted |

**Endpoint:** `POST /wicket_member/v1/bundle/<bundlePostId>/add_member`

**Returns:** Promise resolving to the new or linked membership record.

---

### `removeMemberFromBundle(bundlePostId, data)`

Removes an individual membership from a membership bundle.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle |
| `data.membership_post_id` | `number` | Yes | Post ID of the membership to remove |
| `data.mode` | `'cancel'\|'keep_as_individual'` | Yes | `'cancel'` ends the membership immediately; `'keep_as_individual'` converts it to a standalone membership |

**Endpoint:** `POST /wicket_member/v1/bundle/<bundlePostId>/remove_member`

**Returns:** Promise resolving to the operation result.

---

### `moveIndividualMembership(sourceBundlePostId, data)`

Moves an individual membership from one membership bundle to another.

| Name | Type | Required | Description |
|---|---|---|---|
| `sourceBundlePostId` | `number` | Yes | WP post ID of the source membership bundle |
| `data.membership_post_id` | `number` | Yes | Post ID of the membership to move |
| `data.target_bundle_post_id` | `number` | Yes | Post ID of the destination bundle |

**Endpoint:** `POST /wicket_member/v1/bundle/<sourceBundlePostId>/move_individual_membership`

**Returns:** Promise resolving to the operation result.

---

### `cancelMembershipBundle(bundlePostId, data)`

Cancels a membership bundle and specifies how to handle its individual memberships.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle |
| `data.member_handling` | `'cancel_all'\|'keep_as_individual'` | Yes | What to do with the individual memberships on the bundle |
| `data.timing` | `'immediately'\|'at_end_date'` | When `member_handling='cancel_all'` | When to apply the cancellation |

**Endpoint:** `POST /wicket_member/v1/bundle/<bundlePostId>/cancel`

**Returns:** Promise resolving to the cancellation result.

---

### `updateBundleChangeOwnership(bundlePostId, newOwnerUuid)`

Changes the owner of a membership bundle to a different MDP person.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundlePostId` | `string\|number` | Yes | WP post ID of the membership bundle |
| `newOwnerUuid` | `string` | Yes | MDP UUID of the new owner |

**Endpoint:** `POST /wicket_member/v1/bundle/<bundlePostId>/change_owner`

**Returns:** Promise resolving to the updated bundle object.

---

## Bundle Configs

There are no dedicated bundle config fetch functions in this module — bundle config data is returned as part of `fetchBundleEditPageInfo`. Bundle config date calculation is handled by a separate REST endpoint documented in [Bundle Config Dates](/membership-bundles/endpoints/bundle-config-dates).

---

## Tiers

### `fetchTiers()`

Fetches all published membership tier CPT posts via the WordPress core REST API. Takes no parameters.

**Endpoint:** `GET /wp/v2/wicket_mship_tier?status=publish&per_page=99`

**Returns:** Promise resolving to an array of WordPress post objects for membership tiers.

---

### `fetchMembershipTiers(queryParams)`

Fetches membership tiers through the plugin's own REST endpoint, supporting richer filtering.

| Name | Type | Required | Description |
|---|---|---|---|
| `queryParams` | `object` | No | Query parameters to filter the results |

**Endpoint:** `GET /wicket_member/v1/membership_tiers`

**Returns:** Promise resolving to an array of tier objects.

---

### `fetchTiersInfo(tierIds)`

Resolves a list of tier UUIDs to their full tier info objects.

| Name | Type | Required | Description |
|---|---|---|---|
| `tierIds` | `string[]` | Yes | Array of MDP tier UUIDs to look up |

**Endpoint:** `GET /wicket_member/v1/membership_tier_info?filter[tier_uuid][]=...`

**Returns:** Promise resolving to an array of tier info objects.

---

## Products

### `fetchWcProducts(queryParams)`

Fetches WooCommerce products via the WC REST API v3.

| Name | Type | Required | Description |
|---|---|---|---|
| `queryParams` | `object` | No | Query parameters (e.g. `type`, `per_page`, `search`) |

**Endpoint:** `GET /wc/v3/products`

**Returns:** Promise resolving to an array of WooCommerce product objects.

---

### `fetchProductVariations(productId, queryParams)`

Fetches variations for a specific WooCommerce variable product.

| Name | Type | Required | Description |
|---|---|---|---|
| `productId` | `number` | Yes | WooCommerce product ID |
| `queryParams` | `object` | No | Additional query parameters |

**Endpoint:** `GET /wc/v3/products/<productId>/variations`

**Returns:** Promise resolving to an array of WooCommerce variation objects.

---

### `fetchMembershipProducts(ids)`

Resolves a mixed list of WooCommerce product IDs and variation IDs to their display names.

| Name | Type | Required | Description |
|---|---|---|---|
| `ids` | `number[]` | Yes | Array of product or variation IDs to resolve |

**Endpoint:** `GET /wicket_member/v1/membership_products?ids[]=...`

**Returns:** Promise resolving to a flat array of objects with shape `{ id, name, type, product_id, variation_id }`.

:::details Example

```js
const products = await fetchMembershipProducts([123, 456]);
// [
//   { id: 123, name: 'Annual Membership', type: 'subscription', product_id: 123, variation_id: null },
//   { id: 456, name: 'Monthly — Small Org', type: 'variation', product_id: 400, variation_id: 456 },
// ]
```

:::

---

## Bundles Info

### `fetchBundlesInfo(bundleIds)`

Resolves a list of bundle post IDs to their bundle info objects.

| Name | Type | Required | Description |
|---|---|---|---|
| `bundleIds` | `string[]\|number[]` | Yes | Array of bundle post IDs to look up |

**Endpoint:** `GET /wicket_member/v1/membership_bundle_info?filter[bundle_id][]=...`

**Returns:** Promise resolving to an array of bundle info objects.

---

## MDP / Org Lookups

### `fetchMdpPersons(queryParams)`

Searches MDP persons by name or other criteria.

| Name | Type | Required | Description |
|---|---|---|---|
| `queryParams` | `object` | No | Query parameters; typically `{ term: 'search string' }` |

**Endpoint:** `POST /wicket_member/v1/mdp_person/search`

**Returns:** Promise resolving to an array of MDP person objects.

---

### `fetchSearchOrgs(searchTerm)`

Searches MDP organisations by name using the base plugin's search endpoint.

| Name | Type | Required | Description |
|---|---|---|---|
| `searchTerm` | `string` | Yes | The name fragment to search for |

**Endpoint:** `POST /wicket-base/v1/search-orgs`

Posts `{ searchTerm, autocomplete: true, includeMembershipSummary: false }`.

**Returns:** Promise resolving to an array of org objects (extracted from `response.data`). Returns an empty array when the response contains no `data` field.

:::details Example

```js
const orgs = await fetchSearchOrgs('Acme');
// [ { uuid: '...', name: 'Acme Corp', ... }, ... ]
```

:::

---

### `fetchOrgByUuid(orgUuid)`

Resolves an org UUID to its display data.

| Name | Type | Required | Description |
|---|---|---|---|
| `orgUuid` | `string` | Yes | MDP UUID of the organisation |

**Endpoint:** `GET /wicket_member/v1/org_data?org_uuid=<uuid>`

**Returns:** Promise resolving to an object with at minimum `{ name, location }`.

---

## Adding a New API Function

All new functions follow the same pattern: import the relevant base URL constant from `constants.js`, build the path, call `apiFetch`, and return the promise directly.

:::details Pattern
```js
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { PLUGIN_API_URL } from '../constants';

/**
 * One-line description of what this fetches.
 *
 * @param {number} bundlePostId
 * @returns {Promise<Object>}
 */
export const fetchSomething = ( bundlePostId ) => {
  return apiFetch( {
    path: addQueryArgs( `${ PLUGIN_API_URL }/your-endpoint`, { bundle_post_id: bundlePostId } ),
  } );
};
```
:::

For `POST` / mutation calls, pass `method` and `data`:

:::details POST pattern
```js
export const updateSomething = ( bundlePostId, payload ) => {
  return apiFetch( {
    path:   `${ PLUGIN_API_URL }/your-endpoint/${ bundlePostId }/update`,
    method: 'POST',
    data:   payload,
  } );
};
```
:::

Use `PLUGIN_API_URL` for all memberships plugin endpoints, `BASE_PLUGIN_API_URL` for base plugin calls (org/person lookups), `WC_API_V3_URL` for WooCommerce, and `API_URL` for WordPress core.
