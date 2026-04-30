import { __ } from "@wordpress/i18n";
import { createRoot } from "react-dom/client";
import { useState, useEffect } from "react";
import { addQueryArgs } from "@wordpress/url";
import { Spinner, Icon } from "@wordpress/components";
import {
  fetchMembers,
  fetchTiersInfo,
  fetchGroupsInfo,
  fetchMembershipFilters,
} from "../shared/services/api";
import { SelectWpStyled } from "../shared/styled_elements";
import Pagination from "../shared/components/Pagination";
import { getPagedFromUrl, updatePageInUrl } from "../shared/utils/pagination";

const SortableHeader = ({ label, col, currentCol, currentDir, onSort }) => {
  const isActive = currentCol === col;
  const className = `manage-column sortable ${isActive ? `sorted ${currentDir === 'ASC' ? 'asc' : 'desc'}` : ''}`;

  return (
    <th scope="col" className={className}>
      <a href="#" onClick={(e) => { e.preventDefault(); onSort(col); }}>
        <span>{label}</span>
        <span className="sorting-indicators">
          <span className="sorting-indicator asc" aria-hidden="true"></span>
          <span className="sorting-indicator desc" aria-hidden="true"></span>
        </span>
      </a>
    </th>
  );
};

const MemberList = ({ memberType, editMemberUrl, filterGroupId, filterTierUuid }) => {
  const [isLoading, setIsLoading] = useState(true);

  const [members, setMembers] = useState([]);

  const [totalMembers, setTotalMembers] = useState(0);
  const [totalPages, setTotalPages] = useState(0);

  const [tiersInfo, setTiersInfo] = useState(null);
  const [groupsInfo, setGroupsInfo] = useState(null);
  const [membershipFilters, setMembershipFilters] = useState(null);

  const [activeTab, setActiveTab] = useState('all');
  const [tabCounts, setTabCounts] = useState({ all: 0, pending: 0, grace_period: 0 });

  const initialFilter = {};
  if (filterGroupId) initialFilter.membership_group_id = filterGroupId;
  if (filterTierUuid) initialFilter.membership_tier = filterTierUuid;

  const [searchParams, setSearchParams] = useState({
    type: memberType,
    page: getPagedFromUrl(),
    posts_per_page: 10,
    status: "",
    order_col: "post_modified",
    order_dir: "DESC",
    search: "",
    ...(Object.keys(initialFilter).length ? { filter: initialFilter } : {}),
  });

  const [tempSearchParams, setTempSearchParams] = useState(searchParams);
  const [tempStatus, setTempStatus] = useState(null);
  const [tempTier, setTempTier] = useState(null);
  const [tempGroup, setTempGroup] = useState(null);

  // console.log(tempSearchParams);
  console.log(searchParams);

  const getMembers = (params) => {
    setIsLoading(true);

    fetchMembers(params)
      .then((response) => {
        console.log(response);

        const computedTotalPages = Math.ceil(response.count / params.posts_per_page);
        setMembers(response.results);
        setTotalMembers(response.count);
        setTotalPages(computedTotalPages);
        setIsLoading(false);

        if (params.page > computedTotalPages && computedTotalPages > 0) {
          const fixed = { ...params, page: 1 };
          setSearchParams(fixed);
          updatePageInUrl(1);
          getMembers(fixed);
          return;
        }

        const tierIds = [...new Set(
          response.results.flatMap((member) =>
            (member.user.all_membership_tiers || [{ uuid: member.meta.membership_tier_uuid }]).map((t) => t.uuid)
          )
        )];
        if (tiersInfo === null) {
          getTiersInfo(tierIds);
        }

        const groupIds = [...new Set(
          response.results
            .flatMap((member) => member.user.all_membership_groups || (member.meta.membership_group_id ? [Number(member.meta.membership_group_id)] : []))
            .filter(Boolean)
            .map(Number)
        )];
        if (groupsInfo === null) {
          getGroupsInfo(groupIds);
        }
      })
      .catch((error) => {
        console.error(error);
      });
  };

  const getTiersInfo = (tierIds) => {
    if (tierIds.length === 0) {
      return;
    }

    fetchTiersInfo(tierIds)
      .then((tiersInfo) => {
        setTiersInfo(tiersInfo);
      })
      .catch((error) => {
        console.log("Tiers Info Error:");
        console.log(error);
      });
  };

  const getGroupsInfo = (groupIds) => {
    if (groupIds.length === 0) {
      setGroupsInfo({});
      return;
    }
    fetchGroupsInfo(groupIds)
      .then((info) => setGroupsInfo(info))
      .catch((error) => {
        console.log("Groups Info Error:", error);
        setGroupsInfo({});
      });
  };

  const getGroupInfo = (groupId) => {
    if (!groupsInfo || !groupsInfo.group_data) return null;
    return groupsInfo.group_data[String(groupId)] ?? null;
  };

  const getMembershipFilters = () => {
    fetchMembershipFilters(memberType)
      .then((filters) => {
        setMembershipFilters(filters);
      })
      .catch((error) => {
        console.error(error);
      });
  };

  const getTabCounts = () => {
    fetchMembers({ type: memberType, page: 1, posts_per_page: 1 })
      .then((r) => setTabCounts(prev => ({ ...prev, all: r.count })))
      .catch(console.error);

    fetchMembers({ type: memberType, page: 1, posts_per_page: 1, filter: { membership_status: 'pending' } })
      .then((r) => setTabCounts(prev => ({ ...prev, pending: r.count })))
      .catch(console.error);

    fetchMembers({ type: memberType, page: 1, posts_per_page: 1, filter: { membership_status: 'grace_period' } })
      .then((r) => setTabCounts(prev => ({ ...prev, grace_period: r.count })))
      .catch(console.error);
  };

  const handleSort = (col) => {
    const newDir = searchParams.order_col === col && searchParams.order_dir === 'ASC' ? 'DESC' : 'ASC';
    const newSearchParams = { ...searchParams, order_col: col, order_dir: newDir, page: 1 };
    setSearchParams(newSearchParams);
    updatePageInUrl(1);
    getMembers(newSearchParams);
  };

  const handleTabClick = (tab) => {
    setActiveTab(tab);
    const newSearchParams = { ...searchParams, page: 1 };
    if (tab === 'all') {
      delete newSearchParams.filter;
    } else {
      newSearchParams.filter = { membership_status: tab };
    }
    setSearchParams(newSearchParams);
    updatePageInUrl(1);
    getMembers(newSearchParams);
  };

  const getTierInfo = (tierId) => {
    if (tiersInfo === null) {
      return null;
    }

    if (
      !tiersInfo.hasOwnProperty("tier_data") ||
      !tiersInfo.tier_data.hasOwnProperty(tierId)
    ) {
      return null;
    }

    return tiersInfo.tier_data[tierId];
  };

  useEffect(() => {
    getMembershipFilters();
    getMembers(searchParams);
    getTabCounts();
  }, []);

  // Seed filter dropdowns from prop-driven initial values once filter options load.
  useEffect(() => {
    if (membershipFilters === null) return;
    if (filterGroupId && membershipFilters.groups) {
      const match = membershipFilters.groups.find((g) => String(g.value) === String(filterGroupId));
      if (match) setTempGroup({ value: match.value, label: match.label });
    }
  }, [membershipFilters]);

  // Tier seeding depends on tiersInfo which loads after the first members fetch.
  useEffect(() => {
    if (membershipFilters === null || tiersInfo === null || tempTier !== null) return;
    if (filterTierUuid && membershipFilters.tiers) {
      const match = membershipFilters.tiers.find((t) => t.value === filterTierUuid);
      if (match) {
        const info = getTierInfo(match.value);
        if (info) setTempTier({ value: match.value, label: info.name });
      }
    }
  }, [membershipFilters, tiersInfo]);

  return (
    <>
      <div className="wrap">
        <h1 className="wp-heading-inline">
          {memberType === "individual"
            ? __("Individual Members", "wicket-memberships")
            : __("Organization Members", "wicket-memberships")}
        </h1>
        <hr className="wp-header-end"></hr>

        <ul className="subsubsub">
          <li className="all">
            <a
              href="#"
              onClick={(e) => { e.preventDefault(); handleTabClick('all'); }}
              className={activeTab === 'all' ? 'current' : ''}
              aria-current={activeTab === 'all' ? 'page' : undefined}
            >
              {__('All', 'wicket-memberships')} <span className="count">({tabCounts.all})</span>
            </a> |
          </li>
          <li className="pending">
            <a
              href="#"
              onClick={(e) => { e.preventDefault(); handleTabClick('pending'); }}
              className={activeTab === 'pending' ? 'current' : ''}
              aria-current={activeTab === 'pending' ? 'page' : undefined}
            >
              {__('Pending', 'wicket-memberships')} <span className="count">({tabCounts.pending})</span>
            </a> |
          </li>
          <li className="grace_period">
            <a
              href="#"
              onClick={(e) => { e.preventDefault(); handleTabClick('grace_period'); }}
              className={activeTab === 'grace_period' ? 'current' : ''}
              aria-current={activeTab === 'grace_period' ? 'page' : undefined}
            >
              {__('Grace Period', 'wicket-memberships')} <span className="count">({tabCounts.grace_period})</span>
            </a>
          </li>
        </ul>

        <form
          onSubmit={
            (e) => {
              e.preventDefault();
              const newSearchParams = {
                ...searchParams,
                search: tempSearchParams.search,
                page: 1,
              };
              setSearchParams(newSearchParams);
              updatePageInUrl(1);
              getMembers(newSearchParams);
            }
          }
        >
          <p className="search-box">
            <label className="screen-reader-text" htmlFor="post-search-input">
              {__("Search Member", "wicket-memberships")}
            </label>
            <input
              type="search"
              id="post-search-input"
              value={tempSearchParams.search}
              onChange={(e) =>
                setTempSearchParams({
                  ...tempSearchParams,
                  search: e.target.value,
                })
              }
            />
            <input
              type="submit"
              className="button"
              value={__("Search Member", "wicket-memberships")}
            />
          </p>
        </form>

        <div className="tablenav top">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              const newSearchParams = { ...searchParams, page: 1 };
              const filter = {};
              if (tempStatus !== null) filter.membership_status = tempStatus.value;
              if (tempTier !== null) filter.membership_tier = tempTier.value;
              if (tempGroup !== null) filter.membership_group_id = tempGroup.value;
              if (Object.keys(filter).length) {
                newSearchParams.filter = filter;
              } else {
                delete newSearchParams.filter;
              }
              setSearchParams(newSearchParams);
              updatePageInUrl(1);
              getMembers(newSearchParams);
            }}
          >
            <div className="alignleft actions" style={{ display: "flex", alignItems: "center", gap: 8 }}>
              <SelectWpStyled
                inputId="filter_status"
                classNamePrefix="select"
                isClearable
                placeholder={__("All Statuses", "wicket-memberships")}
                value={tempStatus}
                onChange={(option) => setTempStatus(option)}
                options={
                  membershipFilters !== null
                    ? membershipFilters.membership_status.map((status) => ({
                        value: status.name,
                        label: status.value,
                      }))
                    : []
                }
                styles={{
                  container: (base) => ({ ...base, minWidth: 180 }),
                  control: (base) => ({ ...base, minHeight: 30, height: 30 }),
                  valueContainer: (base) => ({ ...base, height: 30, padding: "0 8px" }),
                  indicatorsContainer: (base) => ({ ...base, height: 30 }),
                }}
              />
              <SelectWpStyled
                inputId="filter_tier"
                classNamePrefix="select"
                isClearable
                placeholder={__("All Tiers", "wicket-memberships")}
                value={tempTier}
                onChange={(option) => setTempTier(option)}
                options={
                  membershipFilters !== null
                    ? membershipFilters.tiers
                        .filter((tier) => getTierInfo(tier.value) !== null)
                        .map((tier) => ({
                          value: tier.value,
                          label: getTierInfo(tier.value).name,
                        }))
                    : []
                }
                styles={{
                  container: (base) => ({ ...base, minWidth: 180 }),
                  control: (base) => ({ ...base, minHeight: 30, height: 30 }),
                  valueContainer: (base) => ({ ...base, height: 30, padding: "0 8px" }),
                  indicatorsContainer: (base) => ({ ...base, height: 30 }),
                }}
              />
              {memberType === "individual" && (
                <SelectWpStyled
                  inputId="filter_group"
                  classNamePrefix="select"
                  isClearable
                  placeholder={__("All Groups", "wicket-memberships")}
                  value={tempGroup}
                  onChange={(option) => setTempGroup(option)}
                  options={
                    membershipFilters !== null && membershipFilters.groups
                      ? membershipFilters.groups.map((group) => ({
                          value: group.value,
                          label: group.label,
                        }))
                      : []
                  }
                  styles={{
                    container: (base) => ({ ...base, minWidth: 180 }),
                    control: (base) => ({ ...base, minHeight: 30, height: 30 }),
                    valueContainer: (base) => ({ ...base, height: 30, padding: "0 8px" }),
                    indicatorsContainer: (base) => ({ ...base, height: 30 }),
                  }}
                />
              )}
              <input
                type="submit"
                id="post-query-submit"
                className="button"
                value={__("Filter", "wicket-memberships")}
              />
            </div>
          </form>
        </div>

        <table className="wp-list-table widefat fixed striped table-view-list posts">
          <thead>
            <tr>
              {memberType === "organization" && (
                <>
                  <SortableHeader
                    label={ __('Organization Name', 'wicket-memberships') }
                    col="org_name"
                    currentCol={searchParams.order_col}
                    currentDir={searchParams.order_dir}
                    onSort={handleSort}
                  />
                  <SortableHeader
                    label={ __('Location', 'wicket-memberships') }
                    col="org_location"
                    currentCol={searchParams.order_col}
                    currentDir={searchParams.order_dir}
                    onSort={handleSort}
                  />
                </>
              )}
              { memberType === 'individual' && (
                <>
                  <SortableHeader
                    label={ __( 'First Name', 'wicket-memberships' ) }
                    col="user_name"
                    currentCol={searchParams.order_col}
                    currentDir={searchParams.order_dir}
                    onSort={handleSort}
                  />
                  <SortableHeader
                    label={ __( 'Last Name', 'wicket-memberships' ) }
                    col="user_last_name"
                    currentCol={searchParams.order_col}
                    currentDir={searchParams.order_dir}
                    onSort={handleSort}
                  />
                </>
              )}
              { memberType === 'organization' && (
                <SortableHeader
                  label={ __( 'Contact', 'wicket-memberships' ) }
                  col="user_name"
                  currentCol={searchParams.order_col}
                  currentDir={searchParams.order_dir}
                  onSort={handleSort}
                />
              )}
              <SortableHeader
                label={ memberType === 'individual' ? __( 'Email', 'wicket-memberships' ) : __( 'Contact Email', 'wicket-memberships' ) }
                col="user_email"
                currentCol={searchParams.order_col}
                currentDir={searchParams.order_dir}
                onSort={handleSort}
              />
              <SortableHeader
                label={ __( 'Last Updated', 'wicket-memberships' ) }
                col="post_modified"
                currentCol={searchParams.order_col}
                currentDir={searchParams.order_dir}
                onSort={handleSort}
              />
              <th scope="col" className="manage-column">{ __( 'Tier(s)', 'wicket-memberships' ) }</th>
              { memberType === 'individual' && (
                <th scope="col" className="manage-column">{ __( 'Group(s)', 'wicket-memberships' ) }</th>
              )}
              <th scope="col" className="manage-column">{ __( 'Link to MDP', 'wicket-memberships' ) }</th>
            </tr>
          </thead>
          <tbody>
            {isLoading && (
              <tr className="alternate">
                <td
                  className="column-columnname"
                  colSpan={memberType === 'organization' ? 7 : 7}
                >
                  <Spinner />
                </td>
              </tr>
            )}
            {!isLoading && members.length === 0 && (
              <tr className="alternate">
                <td className="column-columnname" colSpan={memberType === 'organization' ? 7 : 6}>
                  { __( 'No members found.', 'wicket-memberships' ) }
                </td>
              </tr>
            )}
            {!isLoading &&
              members.length > 0 &&
              members.map((member, index) => (
                <tr key={index}>
                  {memberType === "organization" && (
                    <>
                      <td>
                        <strong>
                          <a
                            href={addQueryArgs(editMemberUrl, {
                              id: member.meta.org_uuid,
                            })}
                            className="row-title"
                          >
                            {member.meta.org_name}
                          </a>
                        </strong>

                        <div className="row-actions">
                          <span className="edit">
                            <a
                              href={addQueryArgs(editMemberUrl, {
                                id: member.meta.org_uuid,
                              })}
                              aria-label={__("Edit", "wicket-memberships")}
                            >
                              {__("Edit", "wicket-memberships")}
                            </a>
                          </span>
                        </div>
                      </td>
                      <td>{member.meta.org_location}</td>
                    </>
                  )}
                  {memberType === 'individual' && (
                    <>
                      <td>
                        <strong>
                          <a href={addQueryArgs(editMemberUrl, { id: member.user.user_login })}
                            className='row-title'
                          >{member.user.first_name}</a>
                        </strong>
                        <div className="row-actions">
                          <span className="edit">
                            <a
                              href={addQueryArgs(editMemberUrl, {
                                id: member.user.user_login,
                              })}
                              aria-label={__("Edit", "wicket-memberships")}
                            >
                              {__("Edit", "wicket-memberships")}
                            </a>
                          </span>
                        </div>
                      </td>
                      <td>
                        <strong>
                          <a href={addQueryArgs(editMemberUrl, { id: member.user.user_login })}
                            className='row-title'
                          >{member.user.last_name}</a>
                        </strong>
                        <div className="row-actions">
                          <span className="edit">
                            <a
                              href={addQueryArgs(editMemberUrl, {
                                id: member.user.user_login,
                              })}
                              aria-label={__("Edit", "wicket-memberships")}
                            >
                              {__("Edit", "wicket-memberships")}
                            </a>
                          </span>
                        </div>
                      </td>
                    </>
                  )}
                  {memberType === 'organization' && (
                    <td>
                      {member.user.display_name}
                    </td>
                  )}
                  <td>
                    { member.user.user_email }
                  </td>
                  <td>
                    { member.post_modified ? moment(member.post_modified).format('MMMM D, YYYY') : '-' }
                  </td>
                  <td>
                    {tiersInfo === null && <Spinner />}
                    {tiersInfo !== null && (() => {
                      const ACTIVE_STATUSES = ['active', 'delayed', 'grace_period', 'pending'];
                      const allTiers = member.user.all_membership_tiers || [{ uuid: member.meta.membership_tier_uuid, status: member.meta.membership_status }];
                      const activeTiers = allTiers.filter((t) => ACTIVE_STATUSES.includes(t.status));
                      if (activeTiers.length === 0) {
                        return <span>{__('Inactive', 'wicket-memberships')}</span>;
                      }
                      return activeTiers.map((t, i) => {
                        const info = getTierInfo(t.uuid);
                        const name = info ? info.name : t.uuid;
                        return (
                          <span key={i}>
                            {i > 0 && ', '}
                            {name}
                          </span>
                        );
                      });
                    })()}
                  </td>
                  { memberType === 'individual' && (
                    <td>
                      {groupsInfo === null && <Spinner />}
                      {groupsInfo !== null && (() => {
                        const allGroups = member.user.all_membership_groups || (member.meta.membership_group_id ? [Number(member.meta.membership_group_id)] : []);
                        if (allGroups.length === 0) return <span>—</span>;
                        return allGroups.map((groupId, i) => {
                          const info = getGroupInfo(groupId);
                          return (
                            <span key={i}>
                              {i > 0 && ', '}
                              {info ? info.name : '—'}
                            </span>
                          );
                        });
                      })()}
                    </td>
                  )}
                  <td>
                    <a target="_blank" href={member.user.mdp_link}>
                      {__("View", "wicket-memberships")}
                      &nbsp;
                      <Icon icon="external" />
                    </a>
                  </td>
                </tr>
              ))}
          </tbody>
        </table>

        <div className="tablenav bottom">
          <div className="tablenav-pages">
            <span className="displaying-num">
              {totalMembers} {__("items", "wicket-memberships")}
            </span>
            <Pagination
              currentPage={searchParams.page}
              totalPages={totalPages}
              onPageChange={(page) => {
                const newSearchParams = { ...searchParams, page };
                setSearchParams(newSearchParams);
                updatePageInUrl(page);
                getMembers(newSearchParams);
              }}
            />
          </div>
          <br className="clear" />
        </div>
      </div>
    </>
  );
};

const app = document.getElementById("member_list");
if (app) {
  createRoot(app).render(<MemberList {...app.dataset} />);
}
