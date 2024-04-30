import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, Button, Flex, FlexItem, Modal, FlexBlock, Notice, SelectControl, Spinner, CheckboxControl, __experimentalHeading as Heading, Icon, __experimentalText as Text } from '@wordpress/components';
import styled from 'styled-components';
import { API_URL, PLUGIN_API_URL } from '../constants';
import he from 'he';
import { Wrap, ErrorsRow, BorderedBox, LabelWpStyled, SelectWpStyled, ActionRow, FormFlex, CustomDisabled } from '../styled_elements';

const MemberList = ({ memberType, wicketAdminUrl }) => {

  const [isLoading, setIsLoading] = useState(true);

  const [members, setMembers] = useState([]);

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
      setIsLoading(false);
    }).catch((error) => {
      console.error(error);
    });
  };

  useEffect(() => {

    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&type=individual
    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&filter[membership_status]=expired&filter[membership_tier]=88d6a08a-ab3c-4f01-93d7-ddf07995ab25&search=Veterinary&type=individual
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

        </div>

        <table className="wp-list-table widefat fixed striped table-view-list posts">
          <thead>
            <tr>
              <th scope="col" className="manage-column">
                { memberType === 'individual' ? __( 'Individual Member Name', 'wicket-memberships' ) : __( 'Organization Name', 'wicket-memberships' ) }
              </th>
              <th scope="col" className="manage-column">{ __( 'Status', 'wicket-memberships' ) }</th>
              <th scope="col" className="manage-column">{ __( 'Tier', 'wicket-memberships' ) }</th>
              <th scope="col" className="manage-column">{ __( 'Link to MDP', 'wicket-memberships' ) }</th>
            </tr>
          </thead>
          <tbody>
            {isLoading && (
              <tr className="alternate">
                <td className="column-columnname" colSpan={4}>
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
                  <td>{member.user.display_name}</td>
                  <td>{member.meta.membership_status}</td>
                  <td>{member.meta.membership_tier_name}</td>
                  <td>
                    {memberType === 'individual' ? (
                      <a href={`${wicketAdminUrl}/people/#`}>
                        {__('View', 'wicket-memberships')}
                        &nbsp;<Icon icon="external" />
                      </a>
                    ) : (
                      <a href={`${wicketAdminUrl}/organization/#`}>
                        {__('View', 'wicket-memberships')}
                        &nbsp;<Icon icon="external" />
                      </a>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>

			</div>
		</>
	);
};

const app = document.getElementById('member_list');
if (app) {
	createRoot(app).render(<MemberList {...app.dataset} />);
}