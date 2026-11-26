import { WC_PRODUCT_TYPES } from '../constants';
import { fetchWcProducts, fetchTiers } from './api';

/**
 * Fetch the WC subscription products a membership switch may target.
 *
 * Shared by the admin member-edit switch modal and the tier editor's self-serve switch target
 * picker so both apply one same-family rule. Two filters are applied:
 *   1. same-family — only products/variations referenced by tiers whose type matches membershipType
 *      (an org membership sees org-tier products, an individual sees individual-tier products);
 *   2. self-exclusion — the source tier's own products, since a switch must move to a different one.
 *
 * Self-exclusion is split by granularity so a variable parent shared across tiers is not lost:
 *   - simple subscription   -> drop the parent product from the picker entirely
 *   - variable subscription -> keep the parent (other variations may belong to other tiers) and
 *                              return its variation id in excludedVariationIds for the caller to
 *                              hide in the variation picker.
 *
 * @param {Object} params
 * @param {string} params.membershipType   'individual' | 'organization'. Falls back to individual
 *                                         tiers when unknown, matching the modal's prior behaviour.
 * @param {number|string} params.currentTierPostId  Source tier post id to exclude; falsy = no exclusion.
 *
 * @returns {Promise<{options: Array, excludedVariationIds: Set}>} Product options
 *          ({ label, value, type }) and the variation ids the caller must hide.
 */
export const fetchSwitchTargetProducts = async ({ membershipType, currentTierPostId }) => {
	// Fetch candidate WC subscription products and all tiers in parallel.
	const [productResults, tiers] = await Promise.all([
		Promise.all(
			WC_PRODUCT_TYPES.map((type) =>
				fetchWcProducts({ status: 'publish', per_page: 100, type })
			)
		),
		fetchTiers({ per_page: 100 }),
	]);

	// Build the set of product + variation ids referenced by tiers of the SAME type as the source
	// membership/tier (individual or organization) — the same-family rule.
	const allowedIds = new Set();
	(Array.isArray(tiers) ? tiers : [])
		.filter((tier) => {
			const tierType = tier.tier_data && tier.tier_data.type;
			// Restrict to the source's own type; fall back to individual only if type unknown.
			return membershipType ? tierType === membershipType : tierType === 'individual';
		})
		.forEach((tier) => {
			const productData = (tier.tier_data && tier.tier_data.product_data) || [];
			productData.forEach((pd) => {
				if (pd.product_id) { allowedIds.add(pd.product_id); }
				if (pd.variation_id) { allowedIds.add(pd.variation_id); }
			});
		});

	// Products/variations belonging to the SOURCE tier — a switch must move to a different product,
	// so these must not be offered even though they are tier-linked.
	const excludedProductIds = new Set();
	const excludedVariationIds = new Set();
	(Array.isArray(tiers) ? tiers : [])
		// String() compare: the caller's tier id may be a string while the tier REST id is numeric.
		.filter((tier) => currentTierPostId && String(tier.id) === String(currentTierPostId))
		.forEach((tier) => {
			const productData = (tier.tier_data && tier.tier_data.product_data) || [];
			productData.forEach((pd) => {
				if (pd.variation_id) {
					excludedVariationIds.add(pd.variation_id);
				} else if (pd.product_id) {
					excludedProductIds.add(pd.product_id);
				}
			});
		});

	// Keep only tier-linked products, minus any simple product that IS the source tier.
	const options = productResults
		.flat()
		.filter((product) => allowedIds.has(product.id) && !excludedProductIds.has(product.id))
		.map((product) => ({
			label: `${product.name} | ID: ${product.id}`,
			value: product.id,
			type: product.type,
		}));

	return { options, excludedVariationIds };
};
