import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { addQueryArgs } from "@wordpress/url";
import styled from "styled-components";
import { fetchGroupMembersByTier } from "../../shared/services/api";
import AdminLoadingSkeleton from "../../shared/components/AdminLoadingSkeleton";

const SectionHeader = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
`;

const SectionTitle = styled.strong`
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
`;

const MembersTable = styled.table`
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  border: 1px solid #c3c4c7;
  background: white;

  th {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #c3c4c7;
    background: #e1e1e1;
  }

  td {
    padding: 10px;
    border-bottom: 1px solid #c3c4c7;
    vertical-align: middle;
  }

  tbody tr:last-child td {
    border-bottom: none;
  }

  a {
    text-decoration: underline;
  }
`;

const ManageLink = styled.div`
  margin-top: 10px;
  margin-bottom: 20px;
  
  a {
    text-decoration: underline;
  }
`;

/**
 * GroupMembersSection — displays a group's member count broken down by tier.
 *
 * Fetches from GET /wicket_member/v1/group/{postId}/members_by_tier.
 * Each tier row links to the individual members page filtered by group + tier.
 * "Manage Group Members" links to the individual members page filtered by group only.
 *
 * Renders without its own wrapper — intended to be placed inside an existing
 * bordered container (e.g. MembershipDetailsForm's BorderedBox).
 *
 * @param {object}       props
 * @param {object|null}  props.pageData              - Data returned by fetchGroupEditPageInfo. Must include `ID`.
 * @param {boolean}      [props.isLoading]           - True while page data is pending.
 * @param {string}       props.individualMembersUrl  - Base URL of the individual members list page.
 */
const GroupMembersSection = ({ pageData, isLoading, individualMembersUrl }) => {
  const [tierData, setTierData] = useState(null);
  const [isFetching, setIsFetching] = useState(false);

  useEffect(() => {
    if (!pageData?.ID) {
      return;
    }
    setIsFetching(true);
    fetchGroupMembersByTier(pageData.ID)
      .then((data) => {
        setTierData(data);
      })
      .catch(() => {
        setTierData(null);
      })
      .finally(() => {
        setIsFetching(false);
      });
  }, [pageData?.ID]);

  if (isLoading || isFetching) {
    return (
      <AdminLoadingSkeleton
        label={__("Loading group members…", "wicket-memberships")}
        variant="compact"
      />
    );
  }

  const tiers = tierData?.tiers ?? [];
  const totalMembers = tierData?.total_members ?? 0;

  const manageUrl = individualMembersUrl
    ? addQueryArgs(individualMembersUrl, { filter_group_id: pageData?.ID })
    : "";

  return (
    <div>
      <SectionHeader>
        <SectionTitle>{__("Group Members", "wicket-memberships")}</SectionTitle>
        <span>
          {__("Total Members:", "wicket-memberships")} <strong>{totalMembers}</strong>
        </span>
      </SectionHeader>

      {tiers.length > 0 && (
        <MembersTable>
          <colgroup>
            <col style={{ width: "50%" }} />
            <col style={{ width: "25%" }} />
            <col style={{ width: "25%" }} />
          </colgroup>
          <thead>
            <tr>
              <th>{__("Membership Tier", "wicket-memberships")}</th>
              <th>{__("Member Count", "wicket-memberships")}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {tiers.map((tier) => {
              const viewUrl = individualMembersUrl
                ? addQueryArgs(individualMembersUrl, {
                    filter_group_id: pageData?.ID,
                    filter_tier_name: tier.tier_name,
                  })
                : "";
              return (
                <tr key={tier.tier_name}>
                  <td>{tier.tier_name}</td>
                  <td>{tier.member_count}</td>
                  <td>
                    {viewUrl && (
                      <a href={viewUrl}>{__("View members", "wicket-memberships")}</a>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </MembersTable>
      )}

      {manageUrl && (
        <ManageLink>
          <a href={manageUrl}>{__("Manage Group Members", "wicket-memberships")}</a>
        </ManageLink>
      )}
    </div>
  );
};

export default GroupMembersSection;
