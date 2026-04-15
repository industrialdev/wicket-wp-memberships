import { useState } from "react";
import { __ } from "@wordpress/i18n";
import AdminNoticeStack from "../../shared/components/AdminNoticeStack";
import AdminPageErrorBoundary from "../../shared/components/AdminPageErrorBoundary";
import { AppWrap, EditWrap } from "../../shared/styled_elements";
import { getPrimaryErrorMessage } from "../utils/formUtils";
import { useGroupMembershipBootstrap } from "../hooks/useGroupMembershipBootstrap";
import GroupMembershipForm from "./GroupMembershipForm";

const GroupMembershipPageContent = ({ postId, listUrl }) => {
  const { pageData, requestState, retryLoad } = useGroupMembershipBootstrap({ postId });

  const isLoading = requestState.status === "loading";

  const notices = [
    ...(requestState.status === "error"
      ? [
          {
            id: "load-error",
            status: "warning",
            message: getPrimaryErrorMessage(
              requestState.error,
              __("The group membership data could not be loaded. Retry to continue.", "wicket-memberships"),
            ),
            action: {
              label: __("Retry loading", "wicket-memberships"),
              onClick: retryLoad,
            },
          },
        ]
      : []),
  ];

  return (
    <>
      <AdminNoticeStack notices={notices} />
      <GroupMembershipForm pageData={pageData} isLoading={isLoading} />
    </>
  );
};

/**
 * GroupMembershipPage — top-level container for the group membership detail page.
 *
 * Owns the error boundary and renders the page heading. Mirrors the role of
 * GroupConfigPage in membership_group_configs/.
 *
 * @param {object} props
 * @param {string|number} props.postId  - WP post ID passed from the PHP mount point.
 * @param {string}        props.listUrl - URL of the group membership list page.
 */
const GroupMembershipPage = ({ postId, listUrl }) => {
  const [errorBoundaryResetKey, setErrorBoundaryResetKey] = useState(0);

  return (
    <AppWrap>
      <div className="wrap">
        <h1 className="wp-heading-inline">
          {__("Group Membership", "wicket-memberships")}
        </h1>
        <hr className="wp-header-end" />

        {listUrl && (
          <p>
            <a href={listUrl}>
              &larr; {__("Back to Group Memberships", "wicket-memberships")}
            </a>
          </p>
        )}

        <AdminPageErrorBoundary
          onReset={() => setErrorBoundaryResetKey((value) => value + 1)}
          resetKey={errorBoundaryResetKey}
        >
          <EditWrap>
            <GroupMembershipPageContent
              key={errorBoundaryResetKey}
              listUrl={listUrl}
              postId={postId}
            />
          </EditWrap>
        </AdminPageErrorBoundary>
      </div>
    </AppWrap>
  );
};

export default GroupMembershipPage;
