import { __ } from "@wordpress/i18n";
import MembershipOwnerSection from "../../shared/components/MembershipOwnerSection";
import { fetchMdpPersons, updateGroupChangeOwnership } from "../../shared/services/api";

/**
 * MembershipGroupOwnerSection — group page adapter for MembershipOwnerSection.
 *
 * Maps group pageData to the flat props expected by the shared UI component and
 * injects the group-specific API call and copy.
 *
 * @param {object}       props
 * @param {object|null}  props.pageData        - Data returned by fetchGroupEditPageInfo.
 * @param {boolean}      props.isLoading       - True while page data is pending.
 * @param {Function}     props.onOwnerUpdated  - Called with new owner data after a successful save.
 */
const MembershipGroupOwnerSection = ({ pageData, isLoading, onOwnerUpdated }) => {
  if ( isLoading || ! pageData ) { return null; }

  const groupPostId = pageData.ID;
  const owner       = pageData.owner ?? null;

  const ownerOption = owner
    ? { label: owner.name, value: owner.uuid }
    : null;

  const loadOptions = (inputValue, callback) => {
    if ( inputValue.length < 3 ) { return; }

    fetchMdpPersons({ term: inputValue })
      .then((response) => {
        callback(
          response.map((person) => ({ label: person.full_name, value: person.id }))
        );
      })
      .catch((error) => {
        console.error('[MembershipGroupOwnerSection] loadOptions error', error);
      });
  };

  const handleSave = async (selectedOption) => {
    if ( ! selectedOption?.value || selectedOption.value === owner?.uuid ) {
      return {};
    }

    const response = await updateGroupChangeOwnership(groupPostId, selectedOption.value);
    if ( response?.success && onOwnerUpdated ) {
      onOwnerUpdated({ name: selectedOption.label, uuid: selectedOption.value });
    }
    return response;
  };

  return (
    <MembershipOwnerSection
      title={__('Membership Group Owner', 'wicket-memberships')}
      tooltipText={__('Represents the person responsible for managing and renewing this Membership Group.', 'wicket-memberships')}
      ownerOption={ownerOption}
      mdpLink={owner?.mdp_link ?? null}
      switchToUrl={owner?.switch_to_url ?? null}
      onLoadOptions={loadOptions}
      onSave={handleSave}
    />
  );
};

export default MembershipGroupOwnerSection;
