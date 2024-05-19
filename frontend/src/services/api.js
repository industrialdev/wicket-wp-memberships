import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { API_URL, PLUGIN_API_URL, TIER_CPT_SLUG } from '../constants';

/**
 * Fetch Local Membership Tiers Posts
 */
export const fetchTiers = () => {
  return apiFetch({
    path: addQueryArgs(`${API_URL}/${TIER_CPT_SLUG}`, { status: 'publish' })
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

export const fetchMembershipFilters = (memberType = null) => {
  if (memberType === null) { return; }

  return apiFetch({ path: addQueryArgs(`${PLUGIN_API_URL}/membership_filters`, { type: memberType }) });
};