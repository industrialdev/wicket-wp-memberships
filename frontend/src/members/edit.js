import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { PLUGIN_API_URL } from '../constants';
import { Wrap, ActionRow, FormFlex, ErrorsRow, BorderedBox, SelectWpStyled, CustomDisabled, LabelWpStyled } from '../styled_elements';
import { TextControl, Spinner, Button, Flex, FlexItem, Modal, TextareaControl, FlexBlock, Notice, SelectControl, CheckboxControl, Disabled, __experimentalHeading as Heading, Icon } from '@wordpress/components';
import styled from 'styled-components';
import { fetchTiers, updateMembership } from '../services/api';
import he from 'he';
import moment from 'moment';

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
  const [tiers, setTiers] = useState([]);

  const fetchMemberships = () => {
    setIsLoading(true);
    apiFetch({
      path: addQueryArgs(`${PLUGIN_API_URL}/membership_entity`, { entity_id: recordId }),
    }).then((response) => {
      console.log(response);

      // add addtional properties to each membership
      response.forEach((membership) => {
        membership.showRow = false;
        membership.updatingNow = false;
        membership.updateResult = '';
      });

      setMemberships(response);
      setIsLoading(false);
    }).catch((error) => {
      console.error(error);
    });
  }

  const getTiers = () => {
    fetchTiers()
      .then((response) => {
        setTiers(response);
      })
      .catch((error) => {
        console.error(error);
      });
  }

  useEffect(() => {
    fetchMemberships();
    getTiers();
  }, []);


  const handleUpdateMembership = (event) => {
    event.preventDefault();
    // get all form data and send it to the API
    const form = event.target;
    const formData = new FormData(form);
    const data = {};
    const membershipId = form.dataset.membershipId;
    console.log(form);

    if ( memberships.find((m) => m.ID == membershipId).updatingNow ) {
      return;
    }

    for (let [key, value] of formData.entries()) {
      data[key] = value;
    }

    // set updating flag
    setMemberships(
      memberships.map((m) => {
        if (m.ID == membershipId) {
          m.updatingNow = true;
        }
        return m;
      })
    );

    updateMembership(membershipId, data)
      .then((response) => {
        console.log(response);
        let updateMessage = '';

        if (response.success) {
          updateMessage = response.success;
        } else {
          updateMessage = response.error;
        }

        // set updating flag
        setMemberships(
          memberships.map((m) => {
            if (m.ID == membershipId) {
              m.updatingNow = false;
              m.updateResult = updateMessage;
            }
            return m;
          })
        );
      })
      .catch((error) => {
        console.log(error);
      });
  }

  // on membership field change
  const handleMembershipFieldChange = (membershipId, field, value) => {
    setMemberships(
      memberships.map((m) => {
        if (m.ID == membershipId) {
          m.data[field] = value;
        }
        return m;
      })
    );
  }

  console.log('TIERS', tiers);

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
              {/* <MarginedFlex
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
              </MarginedFlex> */}
            </RecordTopInfo>
          </WhiteBorderedBox>

          {isLoading && <Spinner />}
          {!isLoading && (
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
              {/* Membership List */}
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
                        {/* Membership Details */}
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

                            {/* Attached order information */}
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

                            {/* Membership update form */}
                            {membership.updateResult.length > 0 && (
                              <ErrorsRow>
                                <Notice
                                  isDismissible={true}
                                  onDismiss={() => {
                                    setMemberships(
                                      memberships.map((m) => {
                                        if (m.ID == membership.ID) {
                                          m.updateResult = '';
                                        }
                                        return m;
                                      })
                                    );
                                  }}
                                  status="info">{membership.updateResult}</Notice>
                              </ErrorsRow>
                            )}
                            <form
                              data-membership-id={membership.ID}
                              onSubmit={handleUpdateMembership}
                            >
                              <MarginedFlex
                                align='end'
                                justify='start'
                                gap={6}
                                direction={[
                                  'column',
                                  'row'
                                ]}
                              >
                                <FlexBlock>
                                  <SelectControl
                                    label={__('Membership Status', 'wicket-memberships')}
                                    value={''}
                                    options={[
                                      { label: 'Status 1', value: 1 },
                                    ]}
                                  />
                                </FlexBlock>
                                <FlexBlock>
                                  <SelectControl
                                    label={__('Renew as', 'wicket-memberships')}
                                    name='membership_next_tier_id'
                                    value={membership.data.membership_next_tier_id}
                                    disabled={tiers.length === 0}
                                    onChange={(value) => {
                                      handleMembershipFieldChange(membership.ID, 'membership_next_tier_id', value);
                                    }}
                                    options={tiers.map((tier) => {
                                      return {
                                        label: he.decode(tier.title.rendered),
                                        value: tier.id
                                      };
                                    })}
                                  />
                                </FlexBlock>
                              </MarginedFlex>

                              <MarginedFlex
                                align='end'
                                justify='start'
                                gap={6}
                                direction={[
                                  'column',
                                  'row'
                                ]}
                              >
                                <FlexBlock>
                                  <TextControl
                                    label={__('Start Date', 'wicket-memberships')}
                                    name='membership_starts_at'
                                    value={moment(membership.data.membership_starts_at).format('YYYY-MM-DD')}
                                    type="date"
                                    onChange={(value) => {
                                      handleMembershipFieldChange(membership.ID, 'membership_starts_at', value);
                                    }}
                                  />
                                </FlexBlock>
                                <FlexBlock>
                                  <TextControl
                                    label={__('End Date', 'wicket-memberships')}
                                    name='membership_ends_at'
                                    value={moment(membership.data.membership_ends_at).format('YYYY-MM-DD')}
                                    type="date"
                                    onChange={(value) => {
                                      handleMembershipFieldChange(membership.ID, 'membership_ends_at', value);
                                    }}
                                  />
                                </FlexBlock>
                                <FlexBlock>
                                  <TextControl
                                    label={__('Expiration Date', 'wicket-memberships')}
                                    name='membership_expires_at'
                                    value={moment(membership.data.membership_expires_at).format('YYYY-MM-DD')}
                                    type="date"
                                    onChange={(value) => {
                                      handleMembershipFieldChange(membership.ID, 'membership_expires_at', value);
                                    }}
                                  />
                                </FlexBlock>
                              </MarginedFlex>

                              <MarginedFlex
                                align='end'
                                justify='start'
                                gap={6}
                                direction={[
                                  'column',
                                  'row'
                                ]}
                              >
                                <FlexItem>
                                  <Button
                                    variant='primary'
                                    type='submit'
                                    disabled={membership.updatingNow}
                                    isBusy={membership.updatingNow}
                                  >
                                    {__('Update Membership', 'wicket-memberships')}
                                  </Button>
                                </FlexItem>
                              </MarginedFlex>
                            </form>
                          </td>
                        </tr>
                      </React.Fragment>
                    ))}
                  </tbody>
                </table>
              </MembershipTable>
            </WhiteBorderedBox>
          )}
        </EditWrap>
			</div>
		</>
	);
};

const app = document.getElementById('edit_member');
if (app) {
	createRoot(app).render(<MemberEdit {...app.dataset} />);
}