import { useState } from "react";
import { __ } from "@wordpress/i18n";
import styled from "styled-components";
import AdminNoticeStack from "../../shared/components/AdminNoticeStack";
import AdminPageErrorBoundary from "../../shared/components/AdminPageErrorBoundary";
import { AppWrap, EditWrap } from "../../shared/styled_elements";
import { getPrimaryErrorMessage } from "../utils/formUtils";
import { useMembershipBundleBootstrap } from "../hooks/useMembershipBundleBootstrap";
import MembershipBundleForm from "./MembershipBundleForm";
import RenewalProcessingOverlay from "./RenewalProcessingOverlay";

// Positioned container so RenewalProcessingOverlay (position: absolute) is scoped
// to the bundle content area rather than the entire viewport.
const ContentArea = styled.div`
  position: relative;
`;

const isNewlyCreated = () => {
  try {
    return new URLSearchParams(window.location.search).get("new") === "1";
  } catch {
    return false;
  }
};

const MembershipBundlePageContent = ({ bundleGroupUuid, listUrl, individualMembersUrl }) => {
  const { pageData, setPageData, requestState, retryLoad, renewalProcessingMeta } = useMembershipBundleBootstrap({ bundleGroupUuid });
  const [memberAddedNotice, setMemberAddedNotice]       = useState(null);
  const [groupCancelledNotice, setGroupCancelledNotice] = useState(null);
  const [newGroupNotice, setNewGroupNotice] = useState(
    isNewlyCreated()
      ? {
          id: "new-bundle",
          status: "success",
          message: __("The membership bundle has been created successfully. Use the Bundle Members section below to begin adding members.", "wicket-memberships"),
          onDismiss: () => setNewGroupNotice(null),
        }
      : null,
  );

  const isLoading = requestState.status === "loading";

  const handleOwnerUpdated = (newOwner) => {
    setPageData((prev) => prev ? { ...prev, owner: { ...prev.owner, ...newOwner } } : prev);
  };

  const handleMemberAdded = () => {
    setMemberAddedNotice({
      id: "member-added",
      status: "success",
      message: __("Member successfully added to the bundle.", "wicket-memberships"),
      onDismiss: () => setMemberAddedNotice(null),
    });
  };

  const handleGroupCancelled = (message) => {
    setGroupCancelledNotice({
      id: "bundle-cancelled",
      status: "success",
      message: message || __("Membership bundle cancelled successfully.", "wicket-memberships"),
      onDismiss: () => setGroupCancelledNotice(null),
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
              __("The membership bundle data could not be loaded. Retry to continue.", "wicket-memberships"),
            ),
            action: {
              label: __("Retry loading", "wicket-memberships"),
              onClick: retryLoad,
            },
          },
        ]
      : []),
    ...(newGroupNotice ? [newGroupNotice] : []),
    ...(memberAddedNotice ? [memberAddedNotice] : []),
    ...(groupCancelledNotice ? [groupCancelledNotice] : []),
  ];

  return (
    <>
      <AdminNoticeStack notices={notices} />
      <ContentArea>
        <RenewalProcessingOverlay processingMeta={renewalProcessingMeta} />
        <MembershipBundleForm
          pageData={pageData}
          isLoading={isLoading}
          onOwnerUpdated={handleOwnerUpdated}
          individualMembersUrl={individualMembersUrl}
          onMemberAdded={handleMemberAdded}
          onBundleCancelled={handleGroupCancelled}
        />
      </ContentArea>
    </>
  );
};

/**
 * MembershipBundlePage — top-level container for the membership bundle detail page.
 *
 * Owns the error boundary and renders the page heading. Mirrors the role of
 * BundleConfigPage in membership_bundle_configs/.
 *
 * @param {object} props
 * @param {string} props.bundleGroupUuid      - membership_bundle_group_uuid for the series.
 * @param {string} props.listUrl              - URL of the membership bundle list page.
 * @param {string} props.individualMembersUrl - URL of the individual members list page.
 */
const MembershipBundlePage = ({ bundleGroupUuid, listUrl, individualMembersUrl }) => {
  const [errorBoundaryResetKey, setErrorBoundaryResetKey] = useState(0);

  return (
    <AppWrap>
      <div className="wrap">
        <h1 className="wp-heading-inline">
          {__("Membership Bundle", "wicket-memberships")}
        </h1>
        <hr className="wp-header-end" />

        {listUrl && (
          <p>
            <a href={listUrl}>
              &larr; {__("Back to Membership Bundles", "wicket-memberships")}
            </a>
          </p>
        )}

        <AdminPageErrorBoundary
          onReset={() => setErrorBoundaryResetKey((value) => value + 1)}
          resetKey={errorBoundaryResetKey}
        >
          <EditWrap>
            <MembershipBundlePageContent
              key={errorBoundaryResetKey}
              listUrl={listUrl}
              bundleGroupUuid={bundleGroupUuid}
              individualMembersUrl={individualMembersUrl}
            />
          </EditWrap>
        </AdminPageErrorBoundary>
      </div>
    </AppWrap>
  );
};

export default MembershipBundlePage;
