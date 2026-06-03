import { __ } from "@wordpress/i18n";
import { useState } from "@wordpress/element";
import { Flex } from "@wordpress/components";
import styled from "styled-components";
import BundleMembersSection from "./BundleMembersSection";
import AddMemberToBundleModal from "./AddMemberToBundleModal";
import CancelMembershipBundleModal from "./CancelMembershipBundleModal";
import CreateBundleRenewalOrderModal from "./CreateBundleRenewalOrderModal";
import MembershipBillingInfoSection from "../../shared/components/MembershipBillingInfoSection";
import MembershipOrderDetailsSection from "../../shared/components/MembershipOrderDetailsSection";
import MembershipStatusSection from "../../shared/components/MembershipStatusSection";
import MembershipActionsSection from "../../shared/components/MembershipActionsSection";
import MembershipDetailsForm from "../../shared/components/MembershipDetailsForm";
import {
  fetchMdpPersons,
  fetchMembershipBundleStatuses,
  updateBundleChangeOwnership,
  updateMembershipBundleStatus,
  updateMembershipBundle,
} from "../../shared/services/api";
import { BUNDLE_RENEWAL_TYPE_OPTIONS } from "../../shared/components/MembershipRenewalTypeSection";

const DetailsWrap = styled.div`
  padding: 4px 0;
`;

/**
 * MembershipBundleRecordDetails — expanded panel content for a single bundle
 * membership record row.
 *
 * Rendered inside the detail <tr> of MembershipRecordsSection when the user
 * expands a row. Wires the shared section components to the bundle-specific API
 * functions, mirroring the expanded row layout from members/edit.js:
 *
 *  1. Billing Info   — subscription ID + next payment date
 *  2. Order Details  — order record table
 *  3. Status row     — manage status alongside the actions dropdown
 *  4. Dates          — start / end / expiry date pickers + save
 *  5. Renewal Type   — renewal type selector with conditional sub-fields
 *
 * `record` is the membership bundle record entry from pageData.membership_records
 * (the shape returned by Group_Admin_Controller::get_group_edit_page_info).
 *
 * @param {object}   props
 * @param {object}   props.record                - Single membership record from pageData.membership_records.
 * @param {object}   props.bundlePageData         - Full pageData for the bundle (subscription_id etc.).
 * @param {Function} props.onRecordUpdated       - Called with the updated record after a save.
 * @param {Function} props.onOwnerUpdated        - Called with new owner data after a successful ownership change.
 * @param {string}   props.individualMembersUrl  - URL of the individual members list page for bundle member links.
 * @param {Function} [props.onBundleCancelled]    - Called with a success message after the bundle is cancelled.
 */
const MembershipBundleRecordDetails = ({ record, bundlePageData, onRecordUpdated, onOwnerUpdated, individualMembersUrl, onMemberAdded, onBundleCancelled }) => {
  const [isAddMemberOpen, setIsAddMemberOpen] = useState(false);
  const [isCancelGroupOpen, setIsCancelGroupOpen] = useState(false);
  const [isCreateRenewalOrderOpen, setIsCreateRenewalOrderOpen] = useState(false);
  const [memberRefreshKey, setMemberRefreshKey] = useState(0);

  // Group-level subscription and order data is shared across all records until
  // the API is enriched to return per-record billing data.
  // TODO: Switch to per-record subscription/order data once
  // Group_Admin_Controller::get_group_edit_page_info() enriches individual
  // record entries with their own billing info. See TODO.md.
  const subscriptionId = bundlePageData?.subscription?.id ?? null;
  // TODO: Replace mock subscription link and next_payment_date with real values
  // once Group_Admin_Controller::get_group_edit_page_info() is enriched with
  // live WooCommerce subscription data. See TODO.md.
  const subscriptionLink = bundlePageData?.subscription?.link ?? null;
  const nextPaymentDate = bundlePageData?.subscription?.next_payment_date ?? null;
  const orders = bundlePageData?.orders ?? null;
  const configRenewalType = bundlePageData?.config_renewal_type || null;

  // Map config renewal type to the select option value used in the form.
  // Config stores 'form_page' but the select uses 'form_flow'.
  const CONFIG_TO_RENEWAL_TYPE = { form_page: 'form_flow', subscription: 'subscription' };
  const defaultRenewalType = configRenewalType ? ( CONFIG_TO_RENEWAL_TYPE[ configRenewalType ] ?? null ) : null;

  const bundlePostId = bundlePageData?.ID ?? null;
  const owner       = bundlePageData?.owner ?? null;
  const ownerOption = owner ? { label: owner.name, value: owner.uuid } : null;
  const ownerMdpLink = owner?.mdp_link ?? null;
  const ownerSwitchToUrl = owner?.switch_to_url ?? null;

  const loadOwnerOptions = (inputValue, callback) => {
    if ( inputValue.length < 3 ) { return; }
    fetchMdpPersons({ term: inputValue })
      .then((response) => {
        callback(
          response.map((person) => ({ label: person.full_name, value: person.id }))
        );
      })
      .catch((error) => {
        console.error('[MembershipBundleRecordDetails] loadOwnerOptions error', error);
      });
  };

  const handleOwnerSave = (selectedOption) => {
    if ( ! selectedOption?.value || selectedOption.value === owner?.uuid ) {
      return Promise.resolve({});
    }

    return updateBundleChangeOwnership(bundlePostId, selectedOption.value);
  };

  const isCancelled = record.status?.toLowerCase() === "cancelled";

  const handleStatusUpdated = (_postId, newStatus) => {
    if (onRecordUpdated) {
      onRecordUpdated({
        ...record,
        status: bundlePageData?.statuses?.[newStatus]?.name ?? newStatus,
      });
    }
  };

  const handleSave = ({ renewalType, nextTierFormPageId, nextTierId, ...datepayload }) =>
    updateMembershipBundle(record.ID, {
      group_post_id: record.ID,
      renewal_type: renewalType,
      next_tier_form_page_id: nextTierFormPageId,
      next_tier_id: nextTierId,
      ...datepayload,
    });

  const handleSaved = (updated) => {
    if (onRecordUpdated) {
      onRecordUpdated({
        ...record,
        starts_at: updated.starts_at ?? record.starts_at,
        ends_at: updated.ends_at ?? record.ends_at,
        expires_at: updated.expires_at ?? record.expires_at,
        renewal_type: updated.renewalType ?? record.renewal_type,
        next_tier_form_page_id: updated.nextTierFormPageId ?? record.next_tier_form_page_id,
        next_tier_id: updated.nextTierId ?? record.next_tier_id,
      });
    }
  };

  return (
    <DetailsWrap>
      <MembershipBillingInfoSection
        subscriptionId={subscriptionId}
        subscriptionLink={subscriptionLink}
        nextPaymentDate={nextPaymentDate}
      />

      <MembershipOrderDetailsSection orders={orders} />

      <Flex align="normal" justify="start" gap={6} direction={["column", "row"]}>
        <MembershipStatusSection
          postId={record.ID}
          currentStatus={record.status}
          fetchStatuses={fetchMembershipBundleStatuses}
          updateStatus={updateMembershipBundleStatus}
          onStatusUpdated={handleStatusUpdated}
          onCancelIntercept={() => setIsCancelGroupOpen(true)}
        />

        <MembershipActionsSection
          actions={[
            {
              label: __("Add Member to Bundle", "wicket-memberships"),
              onClick: () => setIsAddMemberOpen(true),
            },
            {
              label: __("Create Renewal Order", "wicket-memberships"),
              onClick: () => setIsCreateRenewalOrderOpen(true),
              disabled: isCancelled,
            },
          ]}
        />
      </Flex>

      <CancelMembershipBundleModal
        isOpen={isCancelGroupOpen}
        bundlePostId={bundlePostId}
        onRequestClose={() => setIsCancelGroupOpen(false)}
        onSuccess={(message) => {
          setIsCancelGroupOpen(false);
          if (onRecordUpdated) {
            onRecordUpdated({ ...record, status: "cancelled" });
          }
          if (onBundleCancelled) {
            onBundleCancelled(message);
          }
        }}
      />

      <AddMemberToBundleModal
        isOpen={isAddMemberOpen}
        bundlePostId={bundlePostId}
        onRequestClose={() => setIsAddMemberOpen(false)}
        onSuccess={() => {
          setIsAddMemberOpen(false);
          setMemberRefreshKey((k) => k + 1);
          if (onMemberAdded) onMemberAdded();
        }}
      />

      <CreateBundleRenewalOrderModal
        isOpen={isCreateRenewalOrderOpen}
        bundlePostId={bundlePostId}
        onRequestClose={() => setIsCreateRenewalOrderOpen(false)}
        onSuccess={() => setIsCreateRenewalOrderOpen(false)}
      />

      <MembershipDetailsForm
        dates={{
          starts_at: record.starts_at,
          ends_at: record.ends_at,
          expires_at: record.expires_at,
        }}
        renewalType={record?.renewal_type || defaultRenewalType || null}
        tierRenewalType={configRenewalType}
        nextTierFormPageId={record?.next_tier_form_page_id ?? null}
        nextTierId={record?.next_tier_id ?? null}
        disabled={isCancelled}
        allowedRenewalTypes={BUNDLE_RENEWAL_TYPE_OPTIONS}
        onSave={handleSave}
        onSaved={handleSaved}
        ownerOption={ownerOption}
        ownerMdpLink={ownerMdpLink}
        ownerSwitchToUrl={ownerSwitchToUrl}
        onLoadOwnerOptions={loadOwnerOptions}
        onOwnerSave={handleOwnerSave}
        onOwnerUpdated={onOwnerUpdated}
        submitLabel={__("Update Membership Bundle", "wicket-memberships")}
        renderExtra={() => (
          <BundleMembersSection
            pageData={bundlePageData}
            bundlePostId={record.ID}
            individualMembersUrl={individualMembersUrl}
            refreshKey={memberRefreshKey}
          />
        )}
      />
    </DetailsWrap>
  );
};

export default MembershipBundleRecordDetails;
