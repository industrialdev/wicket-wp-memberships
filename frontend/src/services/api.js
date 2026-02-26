import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { API_URL, PLUGIN_API_URL, TIER_CPT_SLUG, WC_API_V3_URL } from '../constants';

/**
 * Fetch Local Membership Tiers Posts
 */
export const fetchTiers = () => {
  return apiFetch({
    path: addQueryArgs(`${API_URL}/${TIER_CPT_SLUG}`, { status: 'publish', per_page: 99 })
  });
}

/**
 * Update Membership Record
 */
export const updateMembership = (membershipId, data) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership_entity/${membershipId}/update`,
    method: 'POST',
    data: data
  });
}

/**
 * Update Membership Status
 */
export const updateMembershipStatus = (membershipId, status) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/admin/manage_status`,
    method: 'POST',
    data: {
      post_id: membershipId,
      status: status
    }
  });
}

/**
 * Fetch Membership Records
 */
export const fetchMemberships = (recordId = null) => {
  if (recordId === null) { return; }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_entity`, { entity_id: recordId }),
  });
}

/**
 * Fetch Member Info
 */
export const fetchMemberInfo = (recordId = null) => {
  if (recordId === null) { return; }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/admin/get_edit_page_info`, { entity_id: recordId })
  });
}

/**
 * Fetch Available Membership Statuses for a Membership Post
 */
export const fetchMembershipStatuses = (postId = null) => {
  if (postId === null) { return; }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/admin/status_options`, { post_id: postId })
  });
}

/**
 * Fetch Members
 */
export const fetchMembers = (params = null) => {
  if (params === null) { return; }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/memberships`, params),
  })
}

/**
 * Fetch Membership Tiers Info
 */
export const fetchTiersInfo = (tierIds = []) => {
  if ( tierIds.length === 0 ) { return }

  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership_tier_info`, {
      filter: {
        tier_uuid: tierIds
      },
    })
  })
}

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
  return apiFetch({ path: url, method: 'POST' });
};

/**
 * Fetch Membership Filters
 */
export const fetchMembershipFilters = (memberType = null) => {
  if (memberType === null) { return; }

  return apiFetch({ path: addQueryArgs(`${PLUGIN_API_URL}/membership_filters`, { type: memberType }) });
};

/**
 * Fetch WooCommerce Products
 */
export const fetchWcProducts = (queryParams = {}) => {
  return apiFetch({ path:
    addQueryArgs(`${WC_API_V3_URL}/products`, queryParams)
  });
};

/**
 * Fetch WooCommerce Product Variations
 */
export const fetchProductVariations = (productId, queryParams = {}) => {
  return apiFetch({ path:
    addQueryArgs(`${WC_API_V3_URL}/products/${productId}/variations`, queryParams)
  });
}

/**
 * Create Renewal Order
 */
export const createRenewalOrder = (membershipId, productId, variationId) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership/${membershipId}/create_renewal_order`,
    method: 'POST',
    data: {
      membership_post_id: membershipId,
      product_id: productId,
      variation_id: variationId
    }
  });
}

/**
 * Transfer Membership
 */
export const transferMembership = ({ new_owner_uuid, membership_post_id }) => {
  return apiFetch({
    path: `${PLUGIN_API_URL}/membership/${membership_post_id}/transfer_membership`,
    method: 'POST',
    data: {
      new_owner_uuid
    }
  });
}

/**
 * Switch Membership Product
 */
export const switchMembership = (membershipId, switchPostID, switchType) => {
  if (!membershipId || !switchPostID || !switchType) return Promise.reject('Missing membershipId, switchPostID, or switchType');
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/membership/${membershipId}/switch_membership`, { switch_post_id: switchPostID, switch_type: switchType }),
    method: 'POST',
  });
};
