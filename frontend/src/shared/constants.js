import moment from "moment-timezone";

export const API_URL = "/wp/v2";
export const WC_API_V3_URL = "/wc/v3";
export const PLUGIN_API_URL = "/wicket_member/v1";
export const TIER_CPT_SLUG = "wicket_mship_tier";
export const DEFAULT_DATE_FORMAT = "yyyy-MM-dd";
export const WC_PRODUCT_TYPES = ["subscription", "variable-subscription"];
export const PLUGIN_SETTINGS = wicketMembershipsSettings;
export const WP_ADMIN_URL = wicketMembershipsSettings.adminUrl;

/**
 * Format a numeric price value as a localized currency string using the
 * currency code from wicketMembershipsSettings (falls back to "USD").
 *
 * @param {string|number|null} price
 * @returns {string}
 */
export const formatCurrency = (price) => {
  if (price === undefined || price === null || price === "") return "—";
  const num = parseFloat(price);
  if (isNaN(num)) return price;
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency: PLUGIN_SETTINGS.currency ?? "USD",
  }).format(num);
};

/**
 * Format an ISO date string as YYYY-MM-DD with the full ISO 8601 string
 * (in MDP timezone) shown on hover via a title attribute.
 *
 * @param {string} isoString
 * @returns {JSX.Element|string}
 */
export const formatDateWithTooltip = (isoString) => {
  if (!isoString) return "";
  const mdpTimezone = PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || "UTC";
  const m = moment.tz(isoString, mdpTimezone);
  const dateDisplay = m.format("YYYY-MM-DD");
  const isoFull = m.format(); // ISO 8601 with timezone offset, e.g. 2026-10-05T00:00:00-04:00
  return <span title={isoFull}>{dateDisplay}</span>;
};
