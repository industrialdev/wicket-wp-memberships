import { __ } from "@wordpress/i18n";
import { useState } from "@wordpress/element";
import { Flex } from "@wordpress/components";
import styled from "styled-components";
import GroupMembersSection from "./GroupMembersSection";
import AddMemberToGroupModal from "./AddMemberToGroupModal";
import CancelMembershipGroupModal from "./CancelMembershipGroupModal";
import MembershipBillingInfoSection from "../../shared/components/MembershipBillingInfoSection";
import MembershipOrderDetailsSection from "../../shared/components/MembershipOrderDetailsSection";
import MembershipStatusSection from "../../shared/components/MembershipStatusSection";
import MembershipActionsSection from "../../shared/components/MembershipActionsSection";
import MembershipDetailsForm from "../../shared/components/MembershipDetailsForm";
import {
  fetchMdpPersons,
  fetchMembershipGroupStatuses,
  updateGroupChangeOwnership,
  updateMembershipGroupStatus,
  updateMembershipGroup,
} from "../../shared/services/api";

const DetailsWrap = styled.div`
  padding: 4px 0;
`;

/**
 * MembershipGroupRecordDetails — expanded panel content for a single group
 * membership record row.
 *
 * Rendered inside the detail <tr> of MembershipRecordsSection when the user
 * expands a row. Wires the shared section components to the group-specific API
 * functions, mirroring the expanded row layout from members/edit.js:
 *
 *  1. Billing Info   — subscription ID + next payment date
 *  2. Order Details  — order record table
 *  3. Status row     — manage status alongside the actions dropdown
 *  4. Dates          — start / end / expiry date pickers + save
 *  5. Renewal Type   — renewal type selector with conditional sub-fields
 *
 * `record` is the membership group record entry from pageData.membership_records
 * (the shape returned by Group_Admin_Controller::get_group_edit_page_info).
 *
 * @param {object}   props
 * @param {object}   props.record                - Single membership record from pageData.membership_records.
 * @param {object}   props.groupPageData         - Full pageData for the group (subscription_id etc.).
 * @param {Function} props.onRecordUpdated       - Called with the updated record after a save.
 * @param {Function} props.onOwnerUpdated        - Called with new owner data after a successful ownership change.
 * @param {string}   props.individualMembersUrl  - URL of the individual members list page for group member links.
 * @param {Function} [props.onGroupCancelled]    - Called with a success message after the group is cancelled.
 */
const MembershipGroupRecordDetails = ({ record, groupPageData, onRecordUpdated, onOwnerUpdated, individualMembersUrl, onMemberAdded, onGroupCancelled }) => {
  const [isAddMemberOpen, setIsAddMemberOpen] = useState(false);
  const [isCancelGroupOpen, setIsCancelGroupOpen] = useState(false);
  const [memberRefreshKey, setMemberRefreshKey] = useState(0);

  // Group-level subscription and order data is shared across all records until
  // the API is enriched to return per-record billing data.
  // TODO: Switch to per-record subscription/order data once
  // Group_Admin_Controller::get_group_edit_page_info() enriches individual
  // record entries with their own billing info. See TODO.md.
  const subscriptionId = groupPageData?.subscription?.id ?? null;
  // TODO: Replace mock subscription link and next_payment_date with real values
  // once Group_Admin_Controller::get_group_edit_page_info() is enriched with
  // live WooCommerce subscription data. See TODO.md.
  const subscriptionLink = groupPageData?.subscription?.link ?? null;
  const nextPaymentDate = groupPageData?.subscription?.next_payment_date ?? null;
  const orders = groupPageData?.orders ?? null;
  const configRenewalType = groupPageData?.config_renewal_type || null;

  const groupPostId = groupPageData?.ID ?? null;
  const owner       = groupPageData?.owner ?? null;
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
        console.error('[MembershipGroupRecordDetails] loadOwnerOptions error', error);
      });
  };

  const handleOwnerSave = (selectedOption) => {
    if ( ! selectedOption?.value || selectedOption.value === owner?.uuid ) {
      return Promise.resolve({});
    }

    return updateGroupChangeOwnership(groupPostId, selectedOption.value);
  };

  const isCancelled = record.status?.toLowerCase() === "cancelled";

  const handleStatusUpdated = (_postId, newStatus) => {
    if (onRecordUpdated) {
      onRecordUpdated({
        ...record,
        status: groupPageData?.statuses?.[newStatus]?.name ?? newStatus,
      });
    }
  };

  const handleSave = ({ renewalType, nextTierFormPageId, nextTierId, ...datepayload }) =>
    updateMembershipGroup(record.ID, {
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
          fetchStatuses={fetchMembershipGroupStatuses}
          updateStatus={updateMembershipGroupStatus}
          onStatusUpdated={handleStatusUpdated}
          onCancelIntercept={() => setIsCancelGroupOpen(true)}
        />

        <MembershipActionsSection
          actions={[
            {
              label: __("Add Member to Group", "wicket-memberships"),
              onClick: () => setIsAddMemberOpen(true),
            },
          ]}
        />
      </Flex>

      <CancelMembershipGroupModal
        isOpen={isCancelGroupOpen}
        groupPostId={groupPostId}
        onRequestClose={() => setIsCancelGroupOpen(false)}
        onSuccess={(message) => {
          setIsCancelGroupOpen(false);
          if (onRecordUpdated) {
            onRecordUpdated({ ...record, status: "cancelled" });
          }
          if (onGroupCancelled) {
            onGroupCancelled(message);
          }
        }}
      />

      <AddMemberToGroupModal
        isOpen={isAddMemberOpen}
        groupPostId={groupPostId}
        onRequestClose={() => setIsAddMemberOpen(false)}
        onSuccess={() => {
          setIsAddMemberOpen(false);
          setMemberRefreshKey((k) => k + 1);
          if (onMemberAdded) onMemberAdded();
        }}
      />

      <MembershipDetailsForm
        dates={{
          starts_at: record.starts_at,
          ends_at: record.ends_at,
          expires_at: record.expires_at,
        }}
        renewalType={record?.renewal_type || "inherited"}
        tierRenewalType={configRenewalType}
        nextTierFormPageId={record?.next_tier_form_page_id ?? null}
        nextTierId={record?.next_tier_id ?? null}
        disabled={isCancelled}
        onSave={handleSave}
        onSaved={handleSaved}
        ownerOption={ownerOption}
        ownerMdpLink={ownerMdpLink}
        ownerSwitchToUrl={ownerSwitchToUrl}
        onLoadOwnerOptions={loadOwnerOptions}
        onOwnerSave={handleOwnerSave}
        onOwnerUpdated={onOwnerUpdated}
        renderExtra={() => (
          <GroupMembersSection
            pageData={groupPageData}
            individualMembersUrl={individualMembersUrl}
            refreshKey={memberRefreshKey}
          />
        )}
      />
    </DetailsWrap>
  );
};

export default MembershipGroupRecordDetails;
