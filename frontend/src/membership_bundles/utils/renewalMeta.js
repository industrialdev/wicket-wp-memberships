export const parseMetaJson = (raw) => {
  if (!raw) return null;
  try {
    return typeof raw === "string" ? JSON.parse(raw) : raw;
  } catch {
    return null;
  }
};

/**
 * Returns the parsed membership_renewal_processing object if the renewal is
 * still in progress (meta present and no completed_at), otherwise null.
 *
 * @param {object|null} data - pageData from fetchBundleEditPageInfo
 * @returns {object|null}
 */
export const getRenewalProcessingMeta = (data) => {
  const parsed = parseMetaJson(data?.meta?.membership_renewal_processing);
  // completed_at presence means the batch finished — overlay should dismiss.
  return parsed?.completed_at ? null : parsed;
};

/**
 * Returns the parsed membership_renewal_order_creation object while the order
 * is still queued/creating (meta present and no completed_at/failed_at),
 * otherwise null. This phase precedes membership_renewal_processing — it
 * exists before the renewal order (and any member-processing meta) does.
 *
 * @param {object|null} data - pageData from fetchBundleEditPageInfo
 * @returns {object|null}
 */
export const getRenewalOrderCreationMeta = (data) => {
  const parsed = parseMetaJson(data?.meta?.membership_renewal_order_creation);
  return parsed?.completed_at || parsed?.failed_at ? null : parsed;
};
