import { useState } from "react";
import { __ } from "@wordpress/i18n";
import { Button, Flex, FlexBlock, __experimentalHeading as Heading } from "@wordpress/components";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import { BorderedBox, MembershipTable } from "../styled_elements";
import styled from "styled-components";

const SectionWrap = styled(BorderedBox)`
  background: #fff;
`;

/**
 * MembershipRecordRow — a single expandable row in the membership records table.
 *
 * Renders one cell per column definition in the collapsed state. When expanded,
 * renders the result of `renderExpandedContent(record)` inside the detail row.
 *
 * @param {object}    props
 * @param {object}    props.record                 - The record data object passed to each column's render fn.
 * @param {Array}     props.columns                - Column definitions: [{ label, render }].
 * @param {Function}  [props.renderExpandedContent] - Optional. Called with `record`, returns ReactNode
 *                                                    rendered inside the expanded panel.
 */
const MembershipRecordRow = ({ record, columns, renderExpandedContent }) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const colSpan = columns.length + 1;

  return (
    <>
      <tr>
        {columns.map((col, i) => (
          <td key={i} className="column-columnname">
            {col.render(record)}
          </td>
        ))}
        <td>
          <Button
            variant="primary"
            icon={isExpanded ? "minus" : "plus-alt2"}
            onClick={() => setIsExpanded((prev) => !prev)}
          />
        </td>
      </tr>
      <tr
        className="membership_details"
        style={{ display: isExpanded ? "table-row" : "none" }}
      >
        <td colSpan={colSpan}>
          {isExpanded && renderExpandedContent
            ? renderExpandedContent(record)
            : null}
        </td>
      </tr>
    </>
  );
};

/**
 * MembershipRecordsSection — shared component for membership record tables.
 *
 * Renders a titled bordered panel with one expandable row per record.
 * Columns are fully caller-defined via the `columns` prop so the table
 * structure can differ across pages without forking this component.
 *
 * Data-agnostic: receives only flat props. Adapter components in each page's
 * components/ folder handle any page-specific data mapping.
 *
 * @param {object}    props
 * @param {Array}     props.columns                - Column definitions: [{ label: string, render: (record) => ReactNode }].
 * @param {Array}     props.records                - Array of record objects passed to each column's render fn.
 * @param {boolean}   props.isLoading              - Show skeleton while data is pending.
 * @param {Function}  [props.renderExpandedContent] - Optional. Called with each record, returns ReactNode
 *                                                    rendered inside the expanded detail panel.
 */
const MembershipRecordsSection = ({ columns = [], records = [], isLoading = false, renderExpandedContent }) => {
  if (isLoading) {
    return (
      <AdminLoadingSkeleton
        label={__("Membership Records", "wicket-memberships")}
        variant="membershipTable"
      />
    );
  }

  const colSpan = columns.length + 1;

  return (
    <SectionWrap>
      <Flex align="end" justify="start" gap={5} direction={["column", "row"]}>
        <FlexBlock>
          <Heading level={4} weight="300">
            {__("Membership Records", "wicket-memberships")}
          </Heading>
        </FlexBlock>
      </Flex>

      <MembershipTable>
        <table className="widefat" cellSpacing="0">
          <thead>
            <tr>
              {columns.map((col, i) => (
                <th key={i} className="manage-column column-columnname" scope="col">
                  {col.label}
                </th>
              ))}
              <th className="check-column" />
            </tr>
          </thead>
          <tbody>
            {records.length === 0 ? (
              <tr>
                <td className="column-columnname" colSpan={colSpan}>
                  {__("No membership records yet.", "wicket-memberships")}
                </td>
              </tr>
            ) : (
              records.map((record) => (
                <MembershipRecordRow
                  key={record.ID}
                  record={record}
                  columns={columns}
                  renderExpandedContent={renderExpandedContent}
                />
              ))
            )}
          </tbody>
        </table>
      </MembershipTable>
    </SectionWrap>
  );
};

export default MembershipRecordsSection;
