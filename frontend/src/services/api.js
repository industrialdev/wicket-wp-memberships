import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { API_URL, TIER_CPT_SLUG } from '../constants';

/**
 * Fetch Local Membership Tiers Posts
 */
export const fetchTiers = () => {
  return apiFetch({ path: addQueryArgs(`${API_URL}/${TIER_CPT_SLUG}`, { status: 'publish' }) });
}