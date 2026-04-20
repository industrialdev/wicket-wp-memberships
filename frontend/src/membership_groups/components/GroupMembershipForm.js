import IntroBlockSection from "./IntroBlockSection";
import MembershipRecordsSection from "./MembershipRecordsSection";

/**
 * GroupMembershipForm — form orchestrator for the group membership detail page.
 *
 * Renders all section components in order. Mirrors the role of GroupConfigForm
 * in membership_group_configs/. Each section component is a thin adapter that
 * maps pageData to flat props for the shared UI component.
 *
 * Expanded record detail content (billing info, order details, status, actions,
 * dates) is rendered inside MembershipRecordsSection via its renderExpandedContent
 * prop — matching the layout of members/edit.js.
 *
 * @param {object}       props
 * @param {object|null}  props.pageData              - Data returned by fetchGroupEditPageInfo.
 * @param {boolean}      props.isLoading             - True while page data is pending.
 * @param {Function}     props.onOwnerUpdated        - Called with new owner data after a successful ownership change.
 * @param {string}       props.individualMembersUrl  - URL of the individual members list page.
 */
const GroupMembershipForm = ({ pageData, isLoading, onOwnerUpdated, individualMembersUrl }) => {
  return (
    <>
      <IntroBlockSection pageData={pageData} isLoading={isLoading} />
      <MembershipRecordsSection pageData={pageData} isLoading={isLoading} onOwnerUpdated={onOwnerUpdated} individualMembersUrl={individualMembersUrl} />
    </>
  );
};

export default GroupMembershipForm;
