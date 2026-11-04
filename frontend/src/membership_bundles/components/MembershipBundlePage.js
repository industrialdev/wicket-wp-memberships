import { useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { addQueryArgs } from "@wordpress/url";
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
  overflow: hidden;
`;

// Error codes written by the renewal order job (membership_renewal_order_creation.error_code).
const RENEWAL_ORDER_ERROR_MESSAGES = {
  subscription_not_found: __("The bundle's subscription could not be found.", "wicket-memberships"),
  "renewal-order-error": __("WooCommerce Subscriptions could not create the order.", "wicket-memberships"),
};

const isNewlyCreated = () => {
  try {
    return new URLSearchParams(window.location.search).get("new") === "1";
  } catch {
    return false;
  }
};

const MembershipBundlePageContent = ({ bundleGroupUuid, listUrl, individualMembersUrl, individualMemberEditUrl }) => {
  const [renewalOrderNotice, setRenewalOrderNotice] = useState(null);
  const dismissRenewalOrderNotice = () => setRenewalOrderNotice(null);

  // Distinct ids per state: Notice renders its children (incl. the action
  // button's hooks) inline, so reusing one instance across states crashes React.
  const handleRenewalOrderStatus = (status, claim, data) => {
    if (status === "delayed") {
      setRenewalOrderNotice({
        id: "renewal-order-delayed",
        status: "warning",
        message: __("Renewal order creation is taking longer than expected. It is still queued, and this page will keep checking.", "wicket-memberships"),
        onDismiss: dismissRenewalOrderNotice,
      });
      return;
    }

    if (status === "unknown") {
      setRenewalOrderNotice({
        id: "renewal-order-unknown",
        status: "warning",
        message: __("The renewal order status could not be confirmed.", "wicket-memberships"),
        // retryLoad is declared below; it's only read when the button is clicked.
        action: {
          label: __("Retry loading", "wicket-memberships"),
          onClick: () => {
            dismissRenewalOrderNotice();
            retryLoad();
          },
        },
        onDismiss: dismissRenewalOrderNotice,
      });
      return;
    }

    if (status === "failed") {
      console.error("[MembershipBundlePage] renewal order creation failed", claim);
      setRenewalOrderNotice({
        id: "renewal-order-failed",
        status: "error",
        message: sprintf(
          /* translators: %s: reason the renewal order job failed. */
          __("The renewal order could not be created: %s Check the WooCommerce logs for details.", "wicket-memberships"),
          RENEWAL_ORDER_ERROR_MESSAGES[claim.error_code] ?? __("An unexpected error occurred.", "wicket-memberships"),
        ),
        onDismiss: dismissRenewalOrderNotice,
      });
      return;
    }

    const orderLink = data?.orders?.find((o) => o.id === claim.order_id)?.link;
    setRenewalOrderNotice({
      id: "renewal-order-created",
      status: "success",
      message: sprintf(
        /* translators: %d: renewal order ID. */
        __("Renewal order #%d has been created.", "wicket-memberships"),
        claim.order_id,
      ),
      action: orderLink
        ? { label: __("View order", "wicket-memberships"), onClick: () => window.location.assign(orderLink) }
        : undefined,
      onDismiss: dismissRenewalOrderNotice,
    });
  };

  const { pageData, setPageData, requestState, retryLoad, watchRenewalOrderCreation, renewalProcessingMeta, renewalOrderCreationMeta } = useMembershipBundleBootstrap({
    bundleGroupUuid,
    onRenewalOrderStatus: handleRenewalOrderStatus,
  });
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

  const handleMemberAdded = (response) => {
    if (response?.mdp_link_collision) {
      const memberUrl = individualMemberEditUrl && response.person_uuid
        ? addQueryArgs(individualMemberEditUrl, { id: response.person_uuid })
        : null;

      setMemberAddedNotice({
        id: "member-added",
        status: "error",
        message: __(
          "Member added, but the membership could not be linked to its MDP record — the WordPress ID was already claimed by a different MDP record. Contact an administrator.",
          "wicket-memberships"
        ),
        action: memberUrl
          ? { label: __("View membership", "wicket-memberships"), onClick: () => window.location.assign(memberUrl) }
          : undefined,
        onDismiss: () => setMemberAddedNotice(null),
      });
      return;
    }

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

  const handleRenewalOrderQueued = () => {
    setRenewalOrderNotice({
      id: "renewal-order-queued",
      status: "info",
      message: __("Renewal order creation has been queued. This page will update when the order is ready.", "wicket-memberships"),
      onDismiss: dismissRenewalOrderNotice,
    });
    watchRenewalOrderCreation();
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
    ...(renewalOrderNotice ? [renewalOrderNotice] : []),
  ];

  return (
    <>
      <AdminNoticeStack notices={notices} />
      <ContentArea>
        <RenewalProcessingOverlay processingMeta={renewalProcessingMeta} orderCreationMeta={renewalOrderCreationMeta} />
        <MembershipBundleForm
          pageData={pageData}
          isLoading={isLoading}
          onOwnerUpdated={handleOwnerUpdated}
          individualMembersUrl={individualMembersUrl}
          onMemberAdded={handleMemberAdded}
          onBundleCancelled={handleGroupCancelled}
          onRenewalOrderQueued={handleRenewalOrderQueued}
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
 * @param {string} props.individualMemberEditUrl - Base URL of the individual member edit page (append ?id=<person_uuid>).
 */
const MembershipBundlePage = ({ bundleGroupUuid, listUrl, individualMembersUrl, individualMemberEditUrl }) => {
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
              individualMemberEditUrl={individualMemberEditUrl}
            />
          </EditWrap>
        </AdminPageErrorBoundary>
      </div>
    </AppWrap>
  );
};

export default MembershipBundlePage;
