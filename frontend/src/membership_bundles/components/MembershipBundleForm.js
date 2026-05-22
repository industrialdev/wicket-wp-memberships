import IntroBlockSection from "./IntroBlockSection";
import MembershipRecordsSection from "./MembershipRecordsSection";

/**
 * MembershipBundleForm — form orchestrator for the membership bundle detail page.
 *
 * Renders all section components in order. Mirrors the role of BundleConfigForm
 * in membership_bundle_configs/. Each section component is a thin adapter that
 * maps pageData to flat props for the shared UI component.
 *
 * Expanded record detail content (billing info, order details, status, actions,
 * dates) is rendered inside MembershipRecordsSection via its renderExpandedContent
 * prop — matching the layout of members/edit.js.
 *
 * @param {object}       props
 * @param {object|null}  props.pageData              - Data returned by fetchBundleEditPageInfo.
 * @param {boolean}      props.isLoading             - True while page data is pending.
 * @param {Function}     props.onOwnerUpdated        - Called with new owner data after a successful ownership change.
 * @param {string}       props.individualMembersUrl  - URL of the individual members list page.
 * @param {Function}     [props.onMemberAdded]       - Called after a member is successfully added to the bundle.
 * @param {Function}     [props.onBundleCancelled]    - Called with a success message after the bundle is cancelled.
 */
const MembershipBundleForm = ({ pageData, isLoading, onOwnerUpdated, individualMembersUrl, onMemberAdded, onBundleCancelled }) => {
  return (
    <>
      <IntroBlockSection pageData={pageData} isLoading={isLoading} />
      <MembershipRecordsSection pageData={pageData} isLoading={isLoading} onOwnerUpdated={onOwnerUpdated} individualMembersUrl={individualMembersUrl} onMemberAdded={onMemberAdded} onBundleCancelled={onBundleCancelled} />
    </>
  );
};

export default MembershipBundleForm;
