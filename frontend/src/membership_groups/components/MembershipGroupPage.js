import { useState } from "react";
import { __ } from "@wordpress/i18n";
import AdminNoticeStack from "../../shared/components/AdminNoticeStack";
import AdminPageErrorBoundary from "../../shared/components/AdminPageErrorBoundary";
import { AppWrap, EditWrap } from "../../shared/styled_elements";
import { getPrimaryErrorMessage } from "../utils/formUtils";
import { useMembershipGroupBootstrap } from "../hooks/useMembershipGroupBootstrap";
import MembershipGroupForm from "./MembershipGroupForm";

const MembershipGroupPageContent = ({ postId, listUrl, individualMembersUrl }) => {
  const { pageData, setPageData, requestState, retryLoad } = useMembershipGroupBootstrap({ postId });
  const [memberAddedNotice, setMemberAddedNotice] = useState(null);

  const isLoading = requestState.status === "loading";

  const handleOwnerUpdated = (newOwner) => {
    setPageData((prev) => prev ? { ...prev, owner: { ...prev.owner, ...newOwner } } : prev);
  };

  const handleMemberAdded = () => {
    setMemberAddedNotice({
      id: "member-added",
      status: "success",
      message: __("Member successfully added to the group.", "wicket-memberships"),
      onDismiss: () => setMemberAddedNotice(null),
    });
  };

  const notices = [
    ...(requestState.status === "error"
      ? [
          {
            id: "load-error",
            status: "warning",
            message: getPrimaryErrorMessage(
              requestState.error,
              __("The membership group data could not be loaded. Retry to continue.", "wicket-memberships"),
            ),
            action: {
              label: __("Retry loading", "wicket-memberships"),
              onClick: retryLoad,
            },
          },
        ]
      : []),
    ...(memberAddedNotice ? [memberAddedNotice] : []),
  ];

  return (
    <>
      <AdminNoticeStack notices={notices} />
      <MembershipGroupForm
        pageData={pageData}
        isLoading={isLoading}
        onOwnerUpdated={handleOwnerUpdated}
        individualMembersUrl={individualMembersUrl}
        onMemberAdded={handleMemberAdded}
      />
    </>
  );
};

/**
 * MembershipGroupPage — top-level container for the membership group detail page.
 *
 * Owns the error boundary and renders the page heading. Mirrors the role of
 * GroupConfigPage in membership_group_configs/.
 *
 * @param {object} props
 * @param {string|number} props.postId               - WP post ID passed from the PHP mount point.
 * @param {string}        props.listUrl              - URL of the membership group list page.
 * @param {string}        props.individualMembersUrl - URL of the individual members list page.
 */
const MembershipGroupPage = ({ postId, listUrl, individualMembersUrl }) => {
  const [errorBoundaryResetKey, setErrorBoundaryResetKey] = useState(0);

  return (
    <AppWrap>
      <div className="wrap">
        <h1 className="wp-heading-inline">
          {__("Membership Group", "wicket-memberships")}
        </h1>
        <hr className="wp-header-end" />

        {listUrl && (
          <p>
            <a href={listUrl}>
              &larr; {__("Back to Membership Groups", "wicket-memberships")}
            </a>
          </p>
        )}

        <AdminPageErrorBoundary
          onReset={() => setErrorBoundaryResetKey((value) => value + 1)}
          resetKey={errorBoundaryResetKey}
        >
          <EditWrap>
            <MembershipGroupPageContent
              key={errorBoundaryResetKey}
              listUrl={listUrl}
              postId={postId}
              individualMembersUrl={individualMembersUrl}
            />
          </EditWrap>
        </AdminPageErrorBoundary>
      </div>
    </AppWrap>
  );
};

export default MembershipGroupPage;
