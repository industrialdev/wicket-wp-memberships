import { __ } from "@wordpress/i18n";
import MembershipActionsDropdown from "./MembershipActionsDropdown";

/**
 * MembershipActionsSection — actions dropdown for a membership record row.
 *
 * A thin wrapper around MembershipActionsDropdown. Callers inject their
 * page-specific actions via the `actions` prop so this component works for
 * individual, organization, and membership group pages without modification.
 *
 * Data-agnostic: receives only flat props.
 *
 * @param {string}  [props.label]   - Button label. Defaults to "Membership Actions".
 * @param {Array}   props.actions   - Action objects: [{ label, onClick, disabled? }].
 */
const MembershipActionsSection = ({ label, actions = [] }) => {
  return (
    <MembershipActionsDropdown
      label={label || __("Membership Actions", "wicket-memberships")}
      actions={actions}
    />
  );
};

export default MembershipActionsSection;
