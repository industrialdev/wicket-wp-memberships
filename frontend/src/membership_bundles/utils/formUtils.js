/**
 * Utility functions for the membership bundle page.
 *
 * Re-exports getPrimaryErrorMessage / normalizeApiErrors from the bundle config
 * utils so that the membership bundle page can use the same error-resolution
 * helpers without duplicating logic.
 */
export { getPrimaryErrorMessage, normalizeApiErrors } from "../../membership_bundle_configs/utils/formUtils";
