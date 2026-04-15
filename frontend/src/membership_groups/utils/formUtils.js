/**
 * Utility functions for the group membership page.
 *
 * Re-exports getPrimaryErrorMessage / normalizeApiErrors from the group config
 * utils so that the group membership page can use the same error-resolution
 * helpers without duplicating logic.
 */
export { getPrimaryErrorMessage, normalizeApiErrors } from "../../membership_group_configs/utils/formUtils";
