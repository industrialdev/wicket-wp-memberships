import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { PLUGIN_API_URL } from '../constants';
import { Wrap, ActionRow, FormFlex, ErrorsRow, BorderedBox, SelectWpStyled, CustomDisabled, LabelWpStyled } from '../styled_elements';
import { TextControl, Spinner, Button, Flex, FlexItem, Modal, TextareaControl, FlexBlock, Notice, SelectControl, CheckboxControl, Disabled, __experimentalHeading as Heading, Icon } from '@wordpress/components';
import styled from 'styled-components';

export const EditWrap = styled.div`
	max-width: 1000px;
`;

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

const WhiteBorderedBox = styled(BorderedBox)`
  background: #fff;
`;

const MembershipTable = styled.div`
  margin-top: 20px;

  .membership_details {
    background: #F6F7F7;

    td {
      padding: 15px;
    }
  }

  td {
    vertical-align: middle;
  }

  .billing_table {
    margin-top: 15px;

    thead {
      th {
        background: #F0F0F1;
      }
    }
  }
`;

export const RecordTopInfo = styled.div`
  background: #F0F6FC;
  margin-top: 15px;
  padding: 15px;
  font-size: 14px;
`;

const MemberEdit = ({ memberType, recordId }) => {

  const [isLoading, setIsLoading] = useState(true);

  const [member, setMember] = useState(null);
  const [memberships, setMemberships] = useState(null);

  const fetchMemberships = async () => {
    setIsLoading(true);
    apiFetch({
      path: addQueryArgs(`${PLUGIN_API_URL}/membership_entity`, { entity_id: recordId }),
    }).then((response) => {
      console.log(response);

      // add "show" property to each membership
      response.forEach((membership) => {
        membership.showRow = false;
      });

      setMemberships(response);
      setIsLoading(false);
    }).catch((error) => {
      console.error(error);
    });
  }

  useEffect(() => {
    fetchMemberships();
  }, []);

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">
					{memberType === 'individual' ? __('Individual Members', 'wicket-memberships') : __('Organization Members', 'wicket-memberships')}
				</h1>
				<hr className="wp-header-end"></hr>

        <EditWrap>
          <WhiteBorderedBox>
            <Flex
              align='end'
              justify='start'
              gap={5}
              direction={[
                'column',
                'row'
              ]}
            >
              <FlexBlock>
                <Heading
                  level={3}
                >%NAME%</Heading>
              </FlexBlock>
              <FlexItem>
                <Button
                  variant='primary'
                >
                  <Icon icon='external' />&nbsp;
                  {__('View in MDP', 'wicket-memberships')}
                </Button>
              </FlexItem>
            </Flex>
            <RecordTopInfo>
              <Flex
                align='end'
                justify='start'
                gap={10}
                direction={[
                  'column',
                  'row'
                ]}
              >
                <FlexItem>
                  <strong>{__('Email:', 'wicket-memberships')}</strong> %EMAIL%
                </FlexItem>
                <FlexItem>
                  <strong>{__('Identifying Number:', 'wicket-memberships')}</strong> %ID%
                </FlexItem>
              </Flex>
              <MarginedFlex
                align='end'
                justify='start'
                gap={10}
                direction={[
                  'column',
                  'row'
                ]}
              >
                <FlexItem>
                  <strong>{__('Location:', 'wicket-memberships')}</strong> %LOCATION%
                </FlexItem>
                <FlexItem>
                  <strong>{__('Season:', 'wicket-memberships')}</strong> %SEASON%
                </FlexItem>
                <FlexItem>
                  <strong>{__('Primary Contact:', 'wicket-memberships')}</strong> %CONTACT%
                </FlexItem>
              </MarginedFlex>
            </RecordTopInfo>
          </WhiteBorderedBox>

          <WhiteBorderedBox>
            <Flex
              align='end'
              justify='start'
              gap={5}
              direction={[
                'column',
                'row'
              ]}
            >
              <FlexBlock>
                <Heading
                  level={4}
                  weight='300'
                >
                  {__('Membership Records', 'wicket-memberships')}
                </Heading>
              </FlexBlock>
              <FlexItem>
                <Button
                  variant='primary'
                >
                  <Icon icon='plus' />&nbsp;
                  {__('Add New Membership', 'wicket-memberships')}
                </Button>
              </FlexItem>
            </Flex>
            <MembershipTable>
              <table className="widefat" cellSpacing="0">
                <thead>
                  <tr>
                    <th className="manage-column column-columnname" scope="col">
                      {__('Membership Tier', 'wicket-memberships')}
                    </th>
                    <th className="manage-column column-columnname" scope="col">
                      {__('Status', 'wicket-memberships')}
                    </th>
                    <th className="manage-column column-columnname" scope="col">
                      {__('Start Date', 'wicket-memberships')}
                    </th>
                    <th className="manage-column column-columnname" scope="col">
                      {__('End Date', 'wicket-memberships')}
                    </th>
                    <th className="manage-column column-columnname" scope="col">
                      {__('Exp. Date', 'wicket-memberships')}
                    </th>
                    <th className='check-column'></th>
                  </tr>
                </thead>
                <tbody>
                  {memberships && memberships.map((membership, index) => (
                    <React.Fragment key={index}>
                      <tr
                        // className='alternate'
                      >
                        <td className="column-columnname">
                          {membership.data.membership_tier_name}
                        </td>
                        <td className="column-columnname">
                          %STATUS%
                        </td>
                        <td className="column-columnname">
                          {membership.data.membership_starts_at}
                        </td>
                        <td className="column-columnname">
                          {membership.data.membership_ends_at}
                        </td>
                        <td className="column-columnname">
                          {membership.data.membership_expires_at}
                        </td>
                        <td>
                          <Button
                            variant='primary'
                            icon={membership.showRow ? 'minus' : 'plus-alt2'}
                            onClick={() => {
                              membership.showRow = !membership.showRow;
                              setMemberships([...memberships]);
                            }}
                          >
                          </Button>
                        </td>
                      </tr>
                      <tr
                        className='membership_details'
                        style={{ display: membership.showRow ? 'table-row' : 'none' }}
                      >
                        <td colSpan={6} >
                          <Flex
                            align='end'
                            justify='start'
                            gap={6}
                            direction={[
                              'column',
                              'row'
                            ]}
                          >
                            <FlexBlock>
                              <Heading
                                level={4}
                                // weight='300'
                              >
                                {__('Billing Info', 'wicket-memberships')}
                              </Heading>
                            </FlexBlock>
                            <FlexItem>
                              {__('Subscription:', 'wicket-memberships')}&nbsp;
                              <a href={membership.subscription.link}>
                                <strong>#{membership.subscription.id}</strong>
                              </a>
                            </FlexItem>
                            <FlexItem>
                              {__('Next Payment Date:', 'wicket-memberships')} <strong>{membership.subscription.next_payment_date}</strong>
                            </FlexItem>
                          </Flex>

                          <table className="widefat billing_table" cellSpacing="0">
                            <thead>
                              <tr>
                                <th className="manage-column column-columnname" scope="col">
                                  {__('Order Number', 'wicket-memberships')}
                                </th>
                                <th className="manage-column column-columnname" scope="col">
                                  {__('Order Date', 'wicket-memberships')}
                                </th>
                                <th className="manage-column column-columnname" scope="col">
                                  {__('Order Total', 'wicket-memberships')}
                                </th>
                                <th className="manage-column column-columnname" scope="col">
                                  {__('Order Type', 'wicket-memberships')}
                                </th>
                                <th className="manage-column column-columnname" scope="col">
                                  {__('Order Status', 'wicket-memberships')}
                                </th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td className="column-columnname">
                                  <a href={membership.order.link}>
                                    #{membership.order.id}
                                  </a>
                                </td>
                                <td className="column-columnname">
                                  {membership.order.date_created}
                                </td>
                                <td className="column-columnname">
                                  {membership.order.total}
                                </td>
                                <td className="column-columnname">
                                  %ORDER_TYPE%
                                </td>
                                <td className="column-columnname">
                                  %ORDER_STATUS%
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </td>
                      </tr>
                    </React.Fragment>
                  ))}
                </tbody>
              </table>
            </MembershipTable>
          </WhiteBorderedBox>
        </EditWrap>
			</div>
		</>
	);
};

const app = document.getElementById('edit_member');
if (app) {
	createRoot(app).render(<MemberEdit {...app.dataset} />);
}