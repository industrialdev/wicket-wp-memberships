import { useState } from "react";
import { __ } from "@wordpress/i18n";
import SharedMembershipRecordsSection from "../../shared/components/MembershipRecordsSection";
import MembershipGroupRecordDetails from "./MembershipGroupRecordDetails";
import { formatDateWithTooltip } from "../../shared/constants";

const buildColumns = (pageData) => [
  {
    label: __("Membership Group Name", "wicket-memberships"),
    render: (record) => record.name,
  },
  {
    label: __("ID", "wicket-memberships"),
    render: (record) => record.ID,
  },
  {
    label: __("Config", "wicket-memberships"),
    render: () => pageData?.config_title || "—",
  },
  {
    label: __("Status", "wicket-memberships"),
    render: (record) => record.status,
  },
  {
    label: __("Start Date", "wicket-memberships"),
    render: (record) => formatDateWithTooltip(record.starts_at),
  },
  {
    label: __("End Date", "wicket-memberships"),
    render: (record) => formatDateWithTooltip(record.ends_at),
  },
  {
    label: __("Exp. Date", "wicket-memberships"),
    render: (record) => formatDateWithTooltip(record.expires_at),
  },
];

/**
 * MembershipRecordsSection — membership group page adapter for
 * the shared MembershipRecordsSection UI component.
 *
 * Defines the column layout for the group context, maps the singular group
 * membership record from pageData, and wires the expanded detail panel
 * so each row shows billing info, order details, status management,
 * actions, and date editing — matching the layout in members/edit.js.
 *
 * @param {object}       props
 * @param {object|null}  props.pageData              - Data returned by fetchGroupEditPageInfo.
 * @param {boolean}      props.isLoading             - Pass-through to the shared component.
 * @param {Function}     props.onOwnerUpdated        - Called with new owner data after a successful ownership change.
 * @param {string}       props.individualMembersUrl  - URL of the individual members list page, passed to the expanded panel.
 * @param {Function}     [props.onMemberAdded]       - Called after a member is successfully added to the group.
 */
const MembershipRecordsSection = ({ pageData, isLoading, onOwnerUpdated, individualMembersUrl, onMemberAdded }) => {
  // Keep a local copy of records so status/date changes update the collapsed
  // row summary (status badge, dates) without a full page reload.
  const [localRecords, setLocalRecords] = useState(null);

  const records = localRecords ?? (pageData?.membership_records ?? []);
  const columns = buildColumns(pageData);

  const handleRecordUpdated = (updatedRecord) => {
    setLocalRecords((prev) => {
      const base = prev ?? (pageData?.membership_records ?? []);
      return base.map((r) => (r.ID === updatedRecord.ID ? updatedRecord : r));
    });
  };

  const renderExpandedContent = (record) => (
    <MembershipGroupRecordDetails
      record={record}
      groupPageData={pageData}
      onRecordUpdated={handleRecordUpdated}
      onOwnerUpdated={onOwnerUpdated}
      individualMembersUrl={individualMembersUrl}
      onMemberAdded={onMemberAdded}
    />
  );

  return (
    <SharedMembershipRecordsSection
      columns={columns}
      records={records}
      isLoading={isLoading}
      renderExpandedContent={renderExpandedContent}
    />
  );
};

export default MembershipRecordsSection;
