import { __ } from "@wordpress/i18n";
import { createRoot } from "react-dom/client";
import { useState, useEffect } from "react";
import { addQueryArgs } from "@wordpress/url";
import { Spinner, Icon } from "@wordpress/components";
import {
  fetchMembers,
  fetchTiersInfo,
  fetchMembershipFilters,
} from "../shared/services/api";

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

const MemberList = ({ memberType, editMemberUrl }) => {
  const [isLoading, setIsLoading] = useState(true);

  const [members, setMembers] = useState([]);

  const [totalMembers, setTotalMembers] = useState(0);
  const [totalPages, setTotalPages] = useState(0);

  const [tiersInfo, setTiersInfo] = useState(null);
  const [membershipFilters, setMembershipFilters] = useState(null);

  const [activeTab, setActiveTab] = useState('all');
  const [tabCounts, setTabCounts] = useState({ all: 0, pending: 0, grace_period: 0 });

  const [searchParams, setSearchParams] = useState({
    type: memberType,
    page: 1,
    posts_per_page: 10,
    status: "",
    order_col: "post_modified",
    order_dir: "DESC",
    // filter: {
    //   membership_status: '',
    //   membership_tier: '',
    // },
    search: "",
  });

  const [tempSearchParams, setTempSearchParams] = useState(searchParams);

  // console.log(tempSearchParams);
  console.log(searchParams);

  const getMembers = (params) => {
    setIsLoading(true);

    fetchMembers(params)
      .then((response) => {
        console.log(response);

        setMembers(response.results);
        setTotalMembers(response.count);
        setTotalPages(Math.ceil(response.count / params.posts_per_page));
        setIsLoading(false);

        const tierIds = [...new Set(
          response.results.flatMap((member) =>
            (member.user.all_membership_tiers || [{ uuid: member.meta.membership_tier_uuid }]).map((t) => t.uuid)
          )
        )];
        if (tiersInfo === null) {
          getTiersInfo(tierIds);
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
    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&type=individual
    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&filter[membership_status]=expired&filter[membership_tier]=88d6a08a-ab3c-4f01-93d7-ddf07995ab25&search=Veterinary&type=individual
    getMembershipFilters();
    getMembers(searchParams);
    getTabCounts();
  }, []);

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
              const newSearchParams = {
                ...searchParams,
                filter: {
                  membership_status: tempSearchParams.filter.membership_status,
                  membership_tier: tempSearchParams.filter.membership_tier,
                },
              };
              // remove if empty filter values
              if (newSearchParams.filter.membership_status === "") {
                delete newSearchParams.filter.membership_status;
              }
              if (newSearchParams.filter.membership_tier === "") {
                delete newSearchParams.filter.membership_tier;
              }
              setSearchParams(newSearchParams);
              getMembers(newSearchParams);
            }}
          >
            <div className="alignleft actions">
              <select
                name="filter_status"
                id="filter_status"
                onChange={(e) => {
                  setTempSearchParams({
                    ...tempSearchParams,
                    filter: {
                      ...tempSearchParams.filter,
                      membership_status: e.target.value,
                    },
                  });
                }}
              >
                <option value="">{__("Status", "wicket-memberships")}</option>
                {membershipFilters !== null &&
                  membershipFilters.membership_status.map((status, index) => (
                    <option key={index} value={status.name}>
                      {status.value}
                    </option>
                  ))}
              </select>

              <select
                name="filter_tier"
                id="filter_tier"
                onChange={(e) => {
                  setTempSearchParams({
                    ...tempSearchParams,
                    filter: {
                      ...tempSearchParams.filter,
                      membership_tier: e.target.value,
                    },
                  });
                }}
              >
                <option value="">
                  {__("All Tiers", "wicket-memberships")}
                </option>
                {membershipFilters !== null &&
                  membershipFilters.tiers.map(
                    (tier, index) =>
                      getTierInfo(tier.value) !== null && (
                        <option key={index} value={tier.value}>
                          {getTierInfo(tier.value).name}
                        </option>
                      ),
                  )}
              </select>

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
              <th scope="col" className="manage-column">{ __( 'Link to MDP', 'wicket-memberships' ) }</th>
            </tr>
          </thead>
          <tbody>
            {isLoading && (
              <tr className="alternate">
                <td
                  className="column-columnname"
                  colSpan={memberType === 'organization' ? 7 : 6}
                >
                  <Spinner />
                </td>
              </tr>
            )}
            {!isLoading && members.length === 0 && (
              <tr className="alternate">
                <td className="column-columnname" colSpan={memberType === 'organization' ? 7 : 5}>
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
                      const STATUS_LABELS = {
                        active:       __('Active', 'wicket-memberships'),
                        delayed:      __('Delayed', 'wicket-memberships'),
                        grace_period: __('Grace Period', 'wicket-memberships'),
                        pending:      __('Pending', 'wicket-memberships'),
                      };
                      const allTiers = member.user.all_membership_tiers || [{ uuid: member.meta.membership_tier_uuid, status: member.meta.membership_status }];
                      const activeTiers = allTiers.filter((t) => ACTIVE_STATUSES.includes(t.status));
                      if (activeTiers.length === 0) {
                        return <span>{__('Inactive', 'wicket-memberships')}</span>;
                      }
                      return activeTiers.map((t, i) => {
                        const info = getTierInfo(t.uuid);
                        const name = info ? info.name : t.uuid;
                        const statusLabel = STATUS_LABELS[t.status] || t.status;
                        return (
                          <span key={i}>
                            {i > 0 && ', '}
                            {name} ({statusLabel})
                          </span>
                        );
                      });
                    })()}
                  </td>
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

            {/* Pagination */}
            {totalPages > 1 && (
              <span className="pagination-links">
                <button
                  className="prev-page button"
                  disabled={searchParams.page === 1}
                  onClick={() => {
                    const newSearchParams = {
                      ...searchParams,
                      page: searchParams.page - 1,
                    };
                    setSearchParams(newSearchParams);
                    getMembers(newSearchParams);
                  }}
                >
                  ‹
                </button>

                <span className="screen-reader-text">
                  {__("Current Page", "wicket-memberships")}
                </span>
                <span id="table-paging" className="paging-input">
                  &nbsp;
                  <span className="tablenav-paging-text">
                    {searchParams.page} {__("of", "wicket-memberships")}{" "}
                    <span className="total-pages">{totalPages}</span>
                  </span>
                  &nbsp;
                </span>

                <button
                  className="next-page button"
                  disabled={searchParams.page === totalPages}
                  onClick={() => {
                    const newSearchParams = {
                      ...searchParams,
                      page: searchParams.page + 1,
                    };
                    setSearchParams(newSearchParams);
                    getMembers(newSearchParams);
                  }}
                >
                  ›
                </button>
              </span>
            )}
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
