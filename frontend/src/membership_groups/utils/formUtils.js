/**
 * Utility functions for the membership group page.
 *
 * Re-exports getPrimaryErrorMessage / normalizeApiErrors from the group config
 * utils so that the membership group page can use the same error-resolution
 * helpers without duplicating logic.
 */
export { getPrimaryErrorMessage, normalizeApiErrors } from "../../membership_group_configs/utils/formUtils";
