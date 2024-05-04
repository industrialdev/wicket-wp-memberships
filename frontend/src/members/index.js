import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { Spinner, Icon } from '@wordpress/components';
import { PLUGIN_API_URL } from '../constants';

const MemberList = ({ memberType, editMemberUrl }) => {

  const [isLoading, setIsLoading] = useState(true);

  const [members, setMembers] = useState([]);

  const [totalMembers, setTotalMembers] = useState(0);
  const [totalPages, setTotalPages] = useState(0);

  const [tiersInfo, setTiersInfo] = useState(null);
  const [membershipFilters, setMembershipFilters] = useState(null);

  const [searchParams, setSearchParams] = useState({
    type: memberType,
    page: 1,
    posts_per_page: 10,
    status: '',
    order_col: 'start_date',
    order_dir: 'ASC',
    // filter: {
    //   membership_status: '',
    //   membership_tier: '',
    // },
    search: '',
  });

  const [tempSearchParams, setTempSearchParams] = useState(searchParams);

  // console.log(tempSearchParams);
  console.log(searchParams);

  // fetch members
  const fetchMembers = (params) => {
    setIsLoading(true);
    apiFetch({
      path: addQueryArgs(`${PLUGIN_API_URL}/memberships`, params),
    }).then((response) => {
      console.log(response);

      setMembers(response.results);
      setTotalMembers(response.count);
      setTotalPages(Math.ceil(response.count / params.posts_per_page));
      setIsLoading(false);

      const tierIds = response.results.map((member) => member.meta.membership_tier_uuid);
      fetchTiersInfo(tierIds);
    }).catch((error) => {
      console.error(error);
    });
  };

  const fetchTiersInfo = (tierIds) => {
    if ( tierIds.length === 0 ) { return }

    apiFetch({ path: addQueryArgs(`${PLUGIN_API_URL}/membership_tier_info`, {
      filter: {
        tier_uuid: tierIds
      },
    }) }).then((tiersInfo) => {
      setTiersInfo(tiersInfo);
		}).catch((error) => {
      console.log('Tiers Info Error:');
      console.log(error);
		});
  }

  const fetchMembershipFilters = () => {
    apiFetch({ path: addQueryArgs(`${PLUGIN_API_URL}/membership_filters`, { type: memberType }) }).then((filters) => {
      setMembershipFilters(filters);
    }).catch((error) => {
      console.error(error);
    });
  };

  const getTierInfo = (tierId) => {
    if ( tiersInfo === null ) { return null }

    if ( ! tiersInfo.hasOwnProperty('tier_data') || ! tiersInfo.tier_data.hasOwnProperty(tierId) ) {
      return null;
    }

    return tiersInfo.tier_data[tierId];
  };

  useEffect(() => {
    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&type=individual
    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&filter[membership_status]=expired&filter[membership_tier]=88d6a08a-ab3c-4f01-93d7-ddf07995ab25&search=Veterinary&type=individual
    fetchMembershipFilters();
    fetchMembers(searchParams);
  }, []);

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">
					{memberType === 'individual' ? __('Individual Members', 'wicket-memberships') : __('Organization Members', 'wicket-memberships')}
				</h1>
				<hr className="wp-header-end"></hr>

        <form
          onSubmit={
            (e) => {
              e.preventDefault();
              const newSearchParams = {
                ...searchParams,
                search: tempSearchParams.search,
              };
              setSearchParams(newSearchParams);
              fetchMembers(newSearchParams);
            }
          }
        >
          <p className="search-box">
            <label className="screen-reader-text" htmlFor="post-search-input">
              {__('Search Member', 'wicket-memberships')}
            </label>
            <input
              type="search"
              id="post-search-input"
              value={tempSearchParams.search}
              onChange={(e) => setTempSearchParams({ ...tempSearchParams, search: e.target.value })}
            />
            <input
              type="submit"
              className="button"
              value={__('Search Member', 'wicket-memberships')}
            />
          </p>
        </form>

        <div className="tablenav top">
          <form
            onSubmit={
              (e) => {
                e.preventDefault();
                const newSearchParams = {
                  ...searchParams,
                  filter: {
                    membership_status: tempSearchParams.filter.membership_status,
                    membership_tier: tempSearchParams.filter.membership_tier,
                  }
                };
                // remove if empty filter values
                if (newSearchParams.filter.membership_status === '') {
                  delete newSearchParams.filter.membership_status;
                }
                if (newSearchParams.filter.membership_tier === '') {
                  delete newSearchParams.filter.membership_tier;
                }
                setSearchParams(newSearchParams);
                fetchMembers(newSearchParams);
              }
            }
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
                <option value="">{__('Status', 'wicket-memberships')}</option>
                {membershipFilters !== null && membershipFilters.membership_status.map((status, index) => (
                  <option key={index} value={status.name}>{status.value}</option>
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
                <option value="">{__('All Tiers', 'wicket-memberships')}</option>
                {membershipFilters !== null && membershipFilters.tiers.map((tier, index) => (
                  <option key={index} value={tier.value}>
                    {getTierInfo(tier.value) !== null && getTierInfo(tier.value).name}
                    {getTierInfo(tier.value) === null && __('Loading...', 'wicket-memberships')}
                  </option>
                ))}
              </select>

              <input type="submit" id="post-query-submit" className="button" value={__('Filter', 'wicket-memberships')} />
            </div>
          </form>
        </div>

        <table className="wp-list-table widefat fixed striped table-view-list posts">
          <thead>
            <tr>
              { memberType === 'organization' && (
                <>
                  <th scope="col" className="manage-column">
                    { __('Organization Name', 'wicket-memberships') }
                  </th>
                  <th scope="col" className="manage-column">{ __('Location', 'wicket-memberships') }</th>
                </>
              )}
              <th scope="col" className="manage-column">
                { memberType === 'individual' ? __( 'Individual Member Name', 'wicket-memberships' ) : __( 'Contact', 'wicket-memberships' ) }
              </th>
              <th scope="col" className="manage-column">{ __( 'Status', 'wicket-memberships' ) }</th>
              <th scope="col" className="manage-column">{ __( 'Tier', 'wicket-memberships' ) }</th>
              <th scope="col" className="manage-column">{ __( 'Link to MDP', 'wicket-memberships' ) }</th>
            </tr>
          </thead>
          <tbody>
            {isLoading && (
              <tr className="alternate">
                <td
                  className="column-columnname"
                  colSpan={memberType === 'organization' ? 6 : 4}
                >
                  <Spinner />
                </td>
              </tr>
            )}
            {!isLoading && members.length === 0 && (
              <tr className="alternate">
                <td className="column-columnname" colSpan={4}>
                  { __( 'No members found.', 'wicket-memberships' ) }
                </td>
              </tr>
            )}
            {!isLoading && members.length > 0 && (
              members.map((member, index) => (
                <tr key={index}>
                  { memberType === 'organization' && (
                    <>
                      <td>
                        <strong>
                          <a href={addQueryArgs(editMemberUrl, { id: member.ID })}
                            className='row-title'
                          >{member.meta.org_name}</a>
                        </strong>

                        <div class="row-actions">
                          <span class="edit">
                            <a href={addQueryArgs(editMemberUrl, { id: member.ID })} aria-label={ __('Edit', 'wicket-memberships') }>
                              { __('Edit', 'wicket-memberships') }
                            </a>
                          </span>
                        </div>
                      </td>
                      <td>
                        {member.meta.org_location}
                      </td>
                    </>
                  )}
                  <td>
                    {memberType === 'individual' && (
                      <>
                        <strong>
                          <a href={addQueryArgs(editMemberUrl, { id: member.ID })}
                            className='row-title'
                          >{member.user.display_name}</a>
                        </strong>
                        <div class="row-actions">
                          <span class="edit">
                            <a href={addQueryArgs(editMemberUrl, { id: member.ID })} aria-label={ __('Edit', 'wicket-memberships') }>
                              { __('Edit', 'wicket-memberships') }
                            </a>
                          </span>
                        </div>
                      </>
                    )}
                    {memberType === 'organization' && (
                      <>
                        {member.user.display_name}
                      </>
                    )}
                  </td>
                  <td>
                    <span style={{
                          color: (member.meta.membership_status === 'active' ? 'green' : ''),
                          textTransform: 'capitalize'
                        }}>
                      { member.meta.membership_status }
                    </span>
                  </td>
                  <td>
                    {tiersInfo === null && <Spinner />}
                    {getTierInfo(member.meta.membership_tier_uuid) !== null && getTierInfo(member.meta.membership_tier_uuid).name}
                  </td>
                  <td>
                    <a
                      target="_blank"
                      href={member.user.mdp_link}
                    >
                      {__('View', 'wicket-memberships')}
                      &nbsp;<Icon icon="external" />
                    </a>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>

        <div className="tablenav bottom">
          <div className="tablenav-pages">
            <span className="displaying-num">
              {totalMembers} {__('items', 'wicket-memberships')}
            </span>

            {/* Pagination */}
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
                  fetchMembers(newSearchParams);
                }}
              >‹</button>

              <span className="screen-reader-text">{__('Current Page', 'wicket-memberships')}</span>
              <span id="table-paging" className="paging-input">
                &nbsp;
                <span className="tablenav-paging-text">{searchParams.page} {__('of', 'wicket-memberships')} <span className="total-pages">{totalPages}</span></span>
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
                  fetchMembers(newSearchParams);
                }}
              >›</button>

            </span>

          </div>
          <br className="clear" />
        </div>

			</div>
		</>
	);
};

const app = document.getElementById('member_list');
if (app) {
	createRoot(app).render(<MemberList {...app.dataset} />);
}