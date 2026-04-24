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
 * Fetch Membership Groups
 */
export const fetchMembershipGroups = (params = null) => {
  if (params === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_groups`, params),
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
 * Fetch Membership Groups Info
 */
export const fetchGroupsInfo = (groupIds = []) => {
  if (groupIds.length === 0) {
    return;
  }
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_group_info`, {
      filter: {
        group_id: groupIds,
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
 * Fetch group members broken down by tier for a given group post ID.
 * GET /wicket_member/v1/group/{groupPostId}/members_by_tier
 */
export const fetchGroupMembersByTier = (groupPostId) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/group/${groupPostId}/members_by_tier`,
  });
};

/**
 * Fetch Membership Group Filters
 */
export const fetchMembershipGroupFilters = () => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership_group_filters`,
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
 * Fetch all data required to populate the membership group detail/edit page.
 *
 * Maps to GET /wicket_member/v1/group/admin/get_edit_page_info?group_post_id=<id>
 *
 * @param {string|number} postId - WP post ID of the membership group.
 */
export const fetchGroupEditPageInfo = (postId) => {
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/group/admin/get_edit_page_info`, {
      group_post_id: postId,
    }),
  });
};

/**
 * Fetch available status transition options for a membership group post.
 *
 * Maps to GET /wicket_member/v1/group/admin/status_options?group_post_id=<id>
 *
 * @param {string|number} groupPostId - WP post ID of the membership group.
 */
export const fetchMembershipGroupStatuses = (groupPostId = null) => {
  if (groupPostId === null) {
    return;
  }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/group/admin/status_options`, {
      group_post_id: groupPostId,
    }),
  });
};

/**
 * Transition a membership group to a new status.
 *
 * Maps to POST /wicket_member/v1/group/admin/manage_status
 *
 * @param {string|number} groupPostId - WP post ID of the membership group.
 * @param {string}        status      - New status slug.
 */
export const updateMembershipGroupStatus = (groupPostId, status) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/group/admin/manage_status`,
    method: "POST",
    data: {
      group_post_id: groupPostId,
      status: status,
    },
  });
};

/**
 * Update editable fields on a membership group post (dates, renewal type, owner).
 *
 * Maps to POST /wicket_member/v1/membership_group_entity/{id}/update
 *
 * @param {string|number} groupPostId - WP post ID of the membership group.
 * @param {object}        data        - Fields to update.
 */
export const updateMembershipGroup = (groupPostId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership_group_entity/${groupPostId}/update`,
    method: "POST",
    data: data,
  });
};

/**
 * Change the owner of a membership group.
 *
 * Maps to POST /wicket_member/v1/group/{group_post_id}/change_owner
 *
 * @param {string|number} groupPostId   - WP post ID of the membership group.
 * @param {string}        newOwnerUuid  - MDP UUID of the new owner.
 */
export const updateGroupChangeOwnership = (groupPostId, newOwnerUuid) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/group/${groupPostId}/change_owner`,
    method: "POST",
    data: { new_owner_uuid: newOwnerUuid },
  });
};

/**
 * Create a new membership group.
 *
 * Maps to POST /wicket_member/v1/group
 *
 * @param {object} data
 * @param {string}        data.name
 * @param {number}        data.membership_group_config_id
 * @param {string}        data.org_uuid
 * @param {string}        data.owner_uuid
 * @param {string}        data.start_date  ISO 8601
 */
export const createMembershipGroup = (data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/group`,
    method: "POST",
    data,
  });
};
