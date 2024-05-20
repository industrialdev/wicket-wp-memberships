import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import { useState, useEffect } from 'react';
import { DEFAULT_DATE_FORMAT } from '../constants';
import { ErrorsRow, BorderedBox, ActionRow, CustomDisabled, AppWrap, LabelWpStyled, ReactDatePickerStyledWrap } from '../styled_elements';
import { TextControl, Tooltip, Spinner, Button, Flex, FlexItem, FlexBlock, Notice, SelectControl, __experimentalHeading as Heading, Icon, Modal } from '@wordpress/components';
import DatePicker from 'react-datepicker';
import styled from 'styled-components';
import { fetchTiers, fetchMemberships, updateMembership, fetchMembershipStatuses, updateMembershipStatus } from '../services/api';
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

const SeatsBox = styled.div`
  border: 1px solid #949494;
  display: flex;

  .box {
    flex: 1;
    background: white;
    padding: 11px 15px;
    font-size: 13px;
    color: #50575E;

    strong {
      margin-left: 3px;
    }

    &.disabled {
      background: #e1e1e1;
    }
  }
`;

const RecordTopInfo = styled.div`
  background: #F0F6FC;
  margin-top: 15px;
  padding: 15px;
  font-size: 14px;
`;

const MemberEdit = ({ memberType, recordId }) => {

  const [isLoading, setIsLoading] = useState(true);

  const [memberships, setMemberships] = useState([]);
  const [tiers, setTiers] = useState([]);
  const [isManageStatusModalOpen, setIsManageStatusModalOpen] = useState(false);
  const [manageStatusErrors, setManageStatusErrors] = useState([]);
  const [manageStatusFormData, setManageStatusFormData] = useState({
    postId: null,
    currentStatus: '',
    newStatus: '',
    availableStatuses: []
  });

  const openManageStatusModalOpen = (membershipIndex) => {
    setManageStatusFormData({
      postId: memberships[membershipIndex].data.membership_post_id,
      currentStatus: memberships[membershipIndex].data.membership_status,
      newStatus: '',
      availableStatuses: []
    });
    getMembershipStatuses(membershipIndex);
    setIsManageStatusModalOpen(true);
  }

  const closeManageStatusModalOpen = () => {
    setIsManageStatusModalOpen(false);
  }

  const getMembershipStatusOptions = () => {
    let statuses = Object.keys(manageStatusFormData.availableStatuses).map((status) => {
      return {
        label: manageStatusFormData.availableStatuses[status].name,
        value: manageStatusFormData.availableStatuses[status].slug
      };
    });

    // prepend the empty option
    statuses.unshift({
      label: __('Select Status', 'wicket-memberships'),
      value: ''
    });

    return statuses;
  }

  const handleManageStatusModalSubmit = (event) => {
    event.preventDefault();

    updateMembershipStatus(manageStatusFormData.postId, manageStatusFormData.newStatus)
      .then((response) => {
        if (response.success) {
          closeManageStatusModalOpen();
        } else {
          console.log(response);
          setManageStatusErrors([response.error]);
        }
      })
      .catch((error) => {
        console.error(error);
      });
  }

  const getMemberships = () => {
    setIsLoading(true);

    fetchMemberships(recordId)
      .then((response) => {
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

  const getMembershipStatuses = (membershipIndex) => {

    const membership = memberships[membershipIndex];

    fetchMembershipStatuses(membership.data.membership_post_id)
      .then((response) => {
        setManageStatusFormData({
          postId: membership.data.membership_post_id,
          currentStatus: membership.data.membership_status,
          newStatus: '',
          availableStatuses: response
        });
      })
      .catch((error) => {
        console.error(error);
      });
  }

  useEffect(() => {
    getMemberships();
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

    // remove empty values
    Object.keys(data).forEach((key) => {
      if (data[key] === '') {
        delete data[key];
      }
    });

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

  // get org name
  const getOrgName = () => {
    if (memberType !== 'organization' || memberships.length === 0 ) {
      return '';
    }

    return memberships[0].data.org_name;
  }

  // get individual user name
  const getIndividualName = () => {
    if (memberType !== 'individual' || memberships.length === 0 ) {
      return '';
    }

    return memberships[0].data.user_name;
  }

  // get individual email
  const getIndividualEmail = () => {
    if (memberType !== 'individual' || memberships.length === 0 ) {
      return '';
    }

    return memberships[0].data.user_email;
  }

  // get individual id
  const getIndividualId = () => {
    if (memberType !== 'individual' || memberships.length === 0 ) {
      return '';
    }

    return memberships[0].data.user_id;
  }

  console.log('TIERS', tiers);
  console.log('manageStatusFormData', manageStatusFormData);

	return (
		<AppWrap>
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
                >
                  {memberType === 'individual' ? getIndividualName() : getOrgName()}
                </Heading>
              </FlexBlock>
              <FlexItem>
                <CustomDisabled>
                  <Button
                    variant='primary'
                  >
                    <Icon icon='external' />&nbsp;
                    {__('View in MDP', 'wicket-memberships')}
                  </Button>
                </CustomDisabled>
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
                {memberType === 'individual' &&
                  <>
                    <FlexItem>
                      <strong>{__('Email:', 'wicket-memberships')}</strong> {getIndividualEmail()}
                    </FlexItem>
                    <FlexItem>
                      <strong>{__('Identifying Number:', 'wicket-memberships')}</strong> {getIndividualId()}
                    </FlexItem>
                  </>
                }
                {memberType === 'organization' &&
                  <>
                    <FlexItem>
                      <strong>{__('Location:', 'wicket-memberships')}</strong> %LOCATION%
                    </FlexItem>
                    <FlexItem>
                      <strong>{__('Identifying Number:', 'wicket-memberships')}</strong> %CONTACT%
                    </FlexItem>
                  </>
                }
              </Flex>
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
                  <CustomDisabled>
                    <Button
                      variant='primary'
                    >
                      <Icon icon='plus' />&nbsp;
                      {__('Add New Membership', 'wicket-memberships')}
                    </Button>
                  </CustomDisabled>
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
                    {memberships.map((membership, index) => (
                      <React.Fragment key={index}>
                        <tr
                          // className='alternate'
                        >
                          <td className="column-columnname">
                            {membership.data.membership_tier_name}
                          </td>
                          <td className="column-columnname">
                            {membership.data.membership_status}
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
                                <a
                                  target='_blank'
                                  href={membership.subscription.link}
                                >
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
                                    {__('Order Status', 'wicket-memberships')}
                                  </th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr>
                                  <td className="column-columnname">
                                    <a
                                      target='_blank'
                                      href={membership.order.link}
                                    >
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
                                    {membership.order.status}
                                  </td>
                                </tr>
                              </tbody>
                            </table>

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
                                  label={__('Membership Status', 'wicket-memberships')}
                                  disabled={true}
                                  __nextHasNoMarginBottom={true}
                                  value={membership.data.membership_status}
                                />
                              </FlexBlock>
                              <FlexBlock>
                                <Button
                                  variant='primary'
                                  onClick={() => {
                                    openManageStatusModalOpen(index);
                                  }}
                                >
                                  {__('Manage Status', 'wicket-memberships')}
                                </Button>
                              </FlexBlock>
                            </MarginedFlex>

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
                                  <LabelWpStyled htmlFor="mdp_tier">
                                    {__('Start Date', 'wicket-memberships')}
                                  </LabelWpStyled>
                                  <ReactDatePickerStyledWrap>
                                    <DatePicker
                                      aria-label={__('Start Date', 'wicket-memberships')}
                                      name='membership_starts_at'
                                      dateFormat={DEFAULT_DATE_FORMAT}
                                      showMonthDropdown
                                      showYearDropdown
                                      dropdownMode="select"
                                      selected={ moment(membership.data.membership_starts_at).format('YYYY-MM-DD') }
                                      onChange={(value) => {
                                        console.log(value);
                                        handleMembershipFieldChange(membership.ID, 'membership_starts_at', moment(value).format('YYYY-MM-DD'));
                                      }}
                                    />
                                  </ReactDatePickerStyledWrap>
                                </FlexBlock>
                                <FlexBlock>
                                  <LabelWpStyled htmlFor="mdp_tier">
                                    {__('End Date', 'wicket-memberships')}
                                  </LabelWpStyled>
                                  <ReactDatePickerStyledWrap>
                                    <DatePicker
                                      aria-label={__('End Date', 'wicket-memberships')}
                                      name='membership_ends_at'
                                      dateFormat={DEFAULT_DATE_FORMAT}
                                      showMonthDropdown
                                      showYearDropdown
                                      dropdownMode="select"
                                      selected={ moment(membership.data.membership_ends_at).format('YYYY-MM-DD') }
                                      onChange={(value) => {
                                        handleMembershipFieldChange(membership.ID, 'membership_ends_at', moment(value).format('YYYY-MM-DD'));
                                      }}
                                    />
                                  </ReactDatePickerStyledWrap>
                                </FlexBlock>
                                <FlexBlock>


                                <LabelWpStyled htmlFor="mdp_tier">
                                    {__('Expiration Date', 'wicket-memberships')}
                                  </LabelWpStyled>
                                  <ReactDatePickerStyledWrap>
                                    <DatePicker
                                      aria-label={__('Expiration Date', 'wicket-memberships')}
                                      name='membership_expires_at'
                                      dateFormat={DEFAULT_DATE_FORMAT}
                                      showMonthDropdown
                                      showYearDropdown
                                      dropdownMode="select"
                                      selected={ moment(membership.data.membership_expires_at).format('YYYY-MM-DD') }
                                      onChange={(value) => {
                                        handleMembershipFieldChange(membership.ID, 'membership_expires_at', moment(value).format('YYYY-MM-DD'));
                                      }}
                                    />
                                  </ReactDatePickerStyledWrap>
                                </FlexBlock>
                              </MarginedFlex>

                              {memberType === 'organization' && (
                                <MarginedFlex
                                  align='start'
                                  justify='start'
                                  gap={6}
                                  direction={[
                                    'column',
                                    'row'
                                  ]}
                                  style={{
                                    marginBottom: '30px'
                                  }}
                                >
                                  <FlexBlock
                                    style={{
                                      flexGrow: 2
                                    }}
                                  >
                                    <LabelWpStyled
                                      style={{ height: '20px' }}
                                    >
                                      {__('Seats', 'wicket-memberships')}
                                    </LabelWpStyled>
                                    <SeatsBox>
                                      <div className='box disabled'>
                                        {__('Total Seats:', 'wicket-memberships')}
                                        <strong>%i%</strong>
                                      </div>
                                      <div className='box'>
                                        {__('Assigned Seats:', 'wicket-memberships')}
                                        <strong>%i%</strong>
                                      </div>
                                      <div className='box'>
                                        {__('Unassigned:', 'wicket-memberships')}
                                        <strong>%i%</strong>
                                      </div>
                                    </SeatsBox>
                                    <div style={{ marginTop: '10px' }} >
                                      <Button variant='link'>
                                        {__('Manage Seats in MDP', 'wicket-memberships')}
                                      </Button>
                                      &nbsp;<Icon icon='external' style={{ color: 'var(--wp-admin-theme-color)' }} />
                                    </div>
                                  </FlexBlock>
                                  <FlexBlock>
                                    <LabelWpStyled style={{ height: '20px' }} >
                                      {__('Membership Owner', 'wicket-memberships')}&nbsp;
                                      <Tooltip
                                        text="Represents the Customer responsible for managing and Renewing the Organization Membership."
                                      >
                                        <div><Icon icon='info' /></div>
                                      </Tooltip>
                                    </LabelWpStyled>
                                    <TextControl
                                      disabled={true}
                                      value={'%name%'}
                                    />
                                    <div style={{ marginTop: '10px' }} >
                                      <Button variant='link'>
                                        {__('View in MDP', 'wicket-memberships')}
                                      </Button>
                                      &nbsp;<Icon icon='external' style={{ color: 'var(--wp-admin-theme-color)' }} />
                                    </div>
                                  </FlexBlock>
                                </MarginedFlex>
                              )}

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

			{/* "Manage Status" Modal */}
			{isManageStatusModalOpen && (
				<Modal
					title={__('Change Status', 'wicket-memberships')}
					onRequestClose={closeManageStatusModalOpen}
					style={
						{
							maxWidth: '840px',
							width: '100%'
						}
					}
				>
					<form onSubmit={handleManageStatusModalSubmit}>

						{manageStatusErrors.length > 0 && (
							<ErrorsRow>
								{manageStatusErrors.map((errorMessage, index) => (
									<Notice isDismissible={false} key={index} status="warning">{errorMessage}</Notice>
								))}
							</ErrorsRow>
						)}

              <MarginedFlex
                align='end'
                justify='start'
                gap={5}
                direction={[
                  'column',
                  'row'
                ]}
              >
							<FlexBlock>
								<TextControl
									label={__('Current Status', 'wicket-memberships')}
                  disabled={true}
                  style={{
                    backgroundColor: '#F6F7F7'
                  }}
									value={manageStatusFormData.currentStatus}
                  __nextHasNoMarginBottom={true}
								/>
							</FlexBlock>
              <FlexItem>
                <div
                  style={{
                    fontWeight: 500,
                    marginBottom: '5px'
                  }}
                >
                  {__('To', 'wicket-memberships')}
                </div>
              </FlexItem>
              <FlexBlock>
                <SelectControl
                  label={__('New Status', 'wicket-memberships')}
                  value={manageStatusFormData.newStatus}
                  onChange={(value) => {
                    setManageStatusFormData({
                      ...manageStatusFormData,
                      newStatus: value
                    });
                  }}
                  options={
                    getMembershipStatusOptions()
                  }
                  __nextHasNoMarginBottom={true}
                />
              </FlexBlock>
						</MarginedFlex>

						<ActionRow>
							<Flex
								align='end'
								gap={5}
								direction={[
									'column',
									'row'
								]}
							>
								<FlexItem>
									<Button variant="primary" type='submit'>
										{__('Update Status', 'wicket-memberships')}
									</Button>
								</FlexItem>
							</Flex>

						</ActionRow>
					</form>
				</Modal>
			)}

		</AppWrap>
	);
};

const app = document.getElementById('edit_member');
if (app) {
	createRoot(app).render(<MemberEdit {...app.dataset} />);
}