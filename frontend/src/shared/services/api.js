import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import {
  API_URL,
  BASE_PLUGIN_API_URL,
  PLUGIN_API_URL,
  TIER_CPT_SLUG,
  WC_API_V3_URL,
} from "../constants";

/**
 * Fetch Local Membership Tiers Posts
 */
export const fetchTiers = () => {
  return apiFetch({
    path: addQueryArgs(`${API_URL}/${TIER_CPT_SLUG}`, {
      status: "publish",
      per_page: 99,
    }),
  });
};

/**
 * Update Membership Record
 */
export const updateMembership = (membershipId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership_entity/${membershipId}/update`,
    method: "POST",
    data: data,
  });
};

/**
 * Update Membership Status
 */
export const updateMembershipStatus = (membershipId, status) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/admin/manage_status`,
    method: "POST",
    data: {
      post_id: membershipId,
      status: status,
    },
  });
};

/**
 * Fetch Membership Records
 */
export const fetchMemberships = (recordId = null) => {
  if (recordId === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_entity`, {
      entity_id: recordId,
    }),
  });
};

/**
 * Fetch Member Info
 */
export const fetchMemberInfo = (recordId = null) => {
  if (recordId === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/admin/get_edit_page_info`, {
      entity_id: recordId,
    }),
  });
};

/**
 * Fetch Available Membership Statuses for a Membership Post
 */
export const fetchMembershipStatuses = (postId = null) => {
  if (postId === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/admin/status_options`, {
      post_id: postId,
    }),
  });
};

/**
 * Fetch Members
 */
export const fetchMembers = (params = null) => {
  if (params === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/memberships`, params),
  });
};

/**
 * Fetch Membership Bundles
 */
export const fetchMembershipBundles = (params = null) => {
  if (params === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_bundles`, params),
  });
};

/**
 * Fetch Membership Tiers Info
 */
export const fetchTiersInfo = (tierIds = []) => {
  if (tierIds.length === 0) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_tier_info`, {
      filter: {
        tier_uuid: tierIds,
      },
    }),
  });
};

/**
 * Fetch Membership Bundles Info
 */
export const fetchBundlesInfo = (bundleIds = []) => {
  if (bundleIds.length === 0) {
    return;
  }
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_bundle_info`, {
      filter: {
        bundle_id: bundleIds,
      },
    }),
  });
};

/**
 * Fetch Membership Tiers
 */
export const fetchMembershipTiers = (queryParams = {}) => {
  const url = addQueryArgs(`${PLUGIN_API_URL}/membership_tiers`, queryParams);
  return apiFetch({ path: url });
};

/**
 * Fetch MDP Persons
 */
export const fetchMdpPersons = (queryParams = {}) => {
  // ?term=
  const url = addQueryArgs(`${PLUGIN_API_URL}/mdp_person/search`, queryParams);
  return apiFetch({ path: url, method: "POST" });
};

/**
 * Search MDP organisations by name.
 *
 * POST /wicket-base/v1/search-orgs
 *
 * @param {string} searchTerm
 * @returns {Promise<Array>} Array of org objects.
 */
export const fetchSearchOrgs = (searchTerm) => {
  return apiFetch({
    path: `${BASE_PLUGIN_API_URL}/search-orgs`,
    method: "POST",
    data: {
      searchTerm,
      autocomplete: true,
      includeMembershipSummary: false,
    },
  }).then((response) => response?.data ?? []);
};

/**
 * Resolve an org UUID to its display data.
 *
 * GET /wicket_member/v1/org_data?org_uuid=<uuid>
 *
 * @param {string} orgUuid
 * @returns {Promise<{ name: string, location: string }>}
 */
export const fetchOrgByUuid = (orgUuid) => {
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/org_data`, { org_uuid: orgUuid }),
  });
};

/**
 * Fetch bundle members broken down by tier for a given bundle post ID.
 * GET /wicket_member/v1/bundle/{bundlePostId}/members_by_tier
 */
export const fetchBundleMembersByTier = (bundlePostId) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/${bundlePostId}/members_by_tier`,
  });
};

/**
 * Fetch Membership Bundle Filters
 */
export const fetchMembershipBundleFilters = () => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership_bundle_filters`,
  });
};

/**
 * Fetch Membership Filters
 */
export const fetchMembershipFilters = (memberType = null) => {
  if (memberType === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_filters`, {
      type: memberType,
    }),
  });
};

/**
 * Fetch WooCommerce Products
 */
export const fetchWcProducts = (queryParams = {}) => {
  return apiFetch({
    path: addQueryArgs(`${WC_API_V3_URL}/products`, queryParams),
  });
};

/**
 * Fetch WooCommerce Product Variations
 */
export const fetchProductVariations = (productId, queryParams = {}) => {
  return apiFetch({
    path: addQueryArgs(
      `${WC_API_V3_URL}/products/${productId}/variations`,
      queryParams,
    ),
  });
};

/**
 * Create Renewal Order for a membership bundle.
 * Uses the bundle's existing subscription — does not create a new subscription.
 */
export const createBundleRenewalOrder = (bundlePostId) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/${bundlePostId}/create_renewal_order`,
    method: "POST",
    data: {
      bundle_post_id: bundlePostId,
    },
  });
};

/**
 * Create Renewal Order
 */
export const createRenewalOrder = (membershipId, productId, variationId) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership/${membershipId}/create_renewal_order`,
    method: "POST",
    data: {
      membership_post_id: membershipId,
      product_id: productId,
      variation_id: variationId,
    },
  });
};

/**
 * Fetch all data required to populate the membership bundle detail/edit page.
 *
 * Maps to GET /wicket_member/v1/bundle/admin/get_edit_page_info?bundle_group_uuid=<uuid>
 *
 * @param {string} bundleGroupUuid - membership_bundle_group_uuid shared by all posts in the series.
 */
export const fetchBundleEditPageInfo = (bundleGroupUuid) => {
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/bundle/admin/get_edit_page_info`, {
      bundle_group_uuid: bundleGroupUuid,
    }),
  });
};

/**
 * Fetch available status transition options for a membership bundle post.
 *
 * Maps to GET /wicket_member/v1/bundle/admin/status_options?bundle_post_id=<id>
 *
 * @param {string|number} bundlePostId - WP post ID of the membership bundle.
 */
export const fetchMembershipBundleStatuses = (bundlePostId = null) => {
  if (bundlePostId === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/bundle/admin/status_options`, {
      bundle_post_id: bundlePostId,
    }),
  });
};

/**
 * Transition a membership bundle to a new status.
 *
 * Maps to POST /wicket_member/v1/bundle/admin/manage_status
 *
 * @param {string|number} bundlePostId - WP post ID of the membership bundle.
 * @param {string}        status      - New status slug.
 */
export const updateMembershipBundleStatus = (bundlePostId, status) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/admin/manage_status`,
    method: "POST",
    data: {
      bundle_post_id: bundlePostId,
      status: status,
    },
  });
};

/**
 * Update editable fields on a membership bundle post (dates, renewal type, owner).
 *
 * Maps to POST /wicket_member/v1/membership_bundle_entity/{id}/update
 *
 * @param {string|number} bundlePostId - WP post ID of the membership bundle.
 * @param {object}        data        - Fields to update.
 */
export const updateMembershipBundle = (bundlePostId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership_bundle_entity/${bundlePostId}/update`,
    method: "POST",
    data: data,
  });
};

/**
 * Change the owner of a membership bundle.
 *
 * Maps to POST /wicket_member/v1/bundle/{bundle_post_id}/change_owner
 *
 * @param {string|number} bundlePostId   - WP post ID of the membership bundle.
 * @param {string}        newOwnerUuid  - MDP UUID of the new owner.
 */
export const updateBundleChangeOwnership = (bundlePostId, newOwnerUuid) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/${bundlePostId}/change_owner`,
    method: "POST",
    data: { new_owner_uuid: newOwnerUuid },
  });
};

/**
 * Create a new membership bundle.
 *
 * Maps to POST /wicket_member/v1/bundle
 *
 * @param {object} data
 * @param {string}        data.name
 * @param {number}        data.membership_bundle_config_id
 * @param {string}        data.org_uuid
 * @param {string}        data.owner_uuid
 * @param {string}        data.start_date  ISO 8601
 */
export const createMembershipBundle = (data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle`,
    method: "POST",
    data,
  });
};

/**
 * Resolve WooCommerce product and variation names by ID.
 *
 * GET /wicket_member/v1/membership_products?ids[]=123&ids[]=456
 *
 * Accepts a mixed list of product IDs and variation IDs. Returns a flat array
 * of objects: { id, name, type, product_id, variation_id }.
 *
 * @param {number[]} ids
 */
export const fetchMembershipProducts = (ids = []) => {
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_products`, { ids }),
  });
};

/**
 * Add a member to a membership bundle.
 *
 * POST /wicket_member/v1/bundle/{bundlePostId}/add_member
 *
 * @param {number} bundlePostId
 * @param {object} data
 * @param {'new'|'existing'} data.mode
 * @param {string}  [data.person_uuid]                  — required when mode='new'
 * @param {number}  [data.tier_post_id]                 — required
 * @param {number}  [data.existing_membership_post_id]  — required when mode='existing'
 * @param {number}  [data.product_id]                   — optional; auto-resolved by backend when omitted
 */
export const addMemberToBundle = (bundlePostId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/${bundlePostId}/add_member`,
    method: "POST",
    data,
  });
};

/**
 * Remove an individual membership from a membership bundle.
 *
 * POST /wicket_member/v1/bundle/{bundlePostId}/remove_member
 *
 * @param {number} bundlePostId
 * @param {object} data
 * @param {number} data.membership_post_id          — post ID of the membership to remove
 * @param {'cancel'|'keep_as_individual'} data.mode — 'cancel' ends immediately; 'keep_as_individual' converts to standalone
 */
export const removeMemberFromBundle = (bundlePostId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/${bundlePostId}/remove_member`,
    method: "POST",
    data,
  });
};

/**
 * Move an individual membership from one membership bundle to another.
 *
 * POST /wicket_member/v1/bundle/{sourceBundlePostId}/move_individual_membership
 *
 * @param {number} sourceBundlePostId
 * @param {object} data
 * @param {number} data.membership_post_id    — post ID of the membership to move
 * @param {number} data.target_bundle_post_id  — post ID of the destination bundle
 */
export const moveIndividualMembership = (sourceBundlePostId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/${sourceBundlePostId}/move_individual_membership`,
    method: "POST",
    data,
  });
};

/**
 * Cancel a membership bundle.
 *
 * POST /wicket_member/v1/bundle/{bundlePostId}/cancel
 *
 * @param {number} bundlePostId
 * @param {object} data
 * @param {'cancel_all'|'keep_as_individual'}  data.member_handling — what to do with individual memberships
 * @param {'immediately'|'at_end_date'}        [data.timing]        — required when member_handling='cancel_all'
 */
export const cancelMembershipBundle = (bundlePostId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/bundle/${bundlePostId}/cancel`,
    method: "POST",
    data,
  });
};
