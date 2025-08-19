import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { DEFAULT_DATE_FORMAT, API_URL } from '../constants';
import { ErrorsRow, BorderedBox, ActionRow, CustomDisabled, AppWrap, LabelWpStyled, ReactDatePickerStyledWrap, AsyncSelectWpStyled, SelectWpStyled } from '../styled_elements';
import { TextControl, Tooltip, Spinner, Button, Flex, FlexItem, FlexBlock, Notice, SelectControl, __experimentalHeading as Heading, Icon, Modal } from '@wordpress/components';
import DatePicker from 'react-datepicker';
import styled from 'styled-components';
import { fetchTiers, fetchMemberships, updateMembership, fetchMembershipStatuses, updateMembershipStatus, fetchMemberInfo, fetchMdpPersons } from '../services/api';
import he from 'he';
import moment from 'moment';
import CreateRenewalOrder from './create_renewal_order';

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

const MemberEdit = ({ memberType, recordId, membershipUuid }) => {

	const renewalTypeOptions = [
		{ label: __('Inherited from Tier', 'wicket-memberships'), value: 'inherited' },
		{ label: __('Sequential Logic', 'wicket-memberships'), value: 'sequential_logic' },
		{ label: __('Renewal Form Flow', 'wicket-memberships'), value: 'form_flow' },
		{ label: __('Subscription Renewal', 'wicket-memberships'), value: 'subscription' },
		{ label: __('Current Tier', 'wicket-memberships'), value: 'current_tier' }
	];

  const [isLoading, setIsLoading] = useState(true);

  const [wpPagesOptions, setWpPagesOptions] = useState([]); // { id, name }
  const [wpTierOptions, setWpTierOptions] = useState([]); // { id, name }
  const [memberInfo, setMemberInfo] = useState(null);
  const [memberships, setMemberships] = useState([]);
  const [membershipOwnerOptions, setMembershipOwnerOptions] = useState([]);
  const [isManageStatusModalOpen, setIsManageStatusModalOpen] = useState(false);
  const [manageStatusErrors, setManageStatusErrors] = useState([]);
  const [manageStatusFormData, setManageStatusFormData] = useState({
    postId: null,
    currentStatus: '',
    newStatus: '',
    availableStatuses: []
  });

  const loadMembershipOwnerOptions = (inputValue, callback) => {
    if (  inputValue.length < 3 ) { return; }

    fetchMdpPersons({ term: inputValue })
      .then((response) => {
        const options = response.map((person) => {
          return {
            label: person.full_name,
            value: person.id
          };
        });

        callback(options);
      })
      .catch((error) => {
        console.error(error);
      });
  };

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


  /**
   * Close the Manage Status Modal
   */
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

          // update status for this membership
          setMemberships(
            memberships.map((m) => {
              if (m.ID == manageStatusFormData.postId) {
                m.data.membership_status = manageStatusFormData.newStatus;
                m.data.membership_ends_at = moment(response.membership_ends_at).format('YYYY-MM-DD');
                m.data.membership_expires_at = moment(response.membership_expires_at).format('YYYY-MM-DD');
                m.updatedNow = true;
              }
              return m;
            })
          );

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

        let tempMembershipOwnerOptions = [];

        // add addtional properties to each membership
        response.forEach((membership) => {
          membership.showRow = false;

          // if membershipUuid exists, set showRow to true
          if ( membershipUuid && membership.data.membership_wicket_uuid == membershipUuid ) {
            membership.showRow = true;
          }

          membership.updatingNow = false;
          membership.updateResult = '';

          console.log('mship ID' + membership.ID);

          // set renewal type
          if ( membership.data.renewal_type === 'form_flow' || ( /*membership.data.renewal_type === undefined &&*/ [0, false].indexOf(membership.data.membership_next_tier_form_page_id) === -1 )) {
            membership.data.renewalType = 'form_flow';
          }
          console.log('renewal_type' + membership.data.renewalType);
          if ( membership.data.renewal_type === 'sequential_logic' || ( /*membership.data.renewal_type === undefined &&*/ [0, false].indexOf(membership.data.membership_next_tier_id) === -1 && membership.data.membership_tier_post_id != membership.data.membership_next_tier_id)) {
            membership.data.renewalType = 'sequential_logic';
          }
          console.log('renewal_type' + membership.data.renewalType);
          if ( membership.data.renewal_type === 'subscription' ||  ['', 0, false].indexOf(membership.data.membership_next_tier_subscription_renewal) === -1 ) {
            membership.data.renewalType = 'subscription';
          }
          console.log('renewal_type' + membership.data.renewalType);
          if ( membership.data.renewal_type === 'current_tier' || ( /*membership.data.renewal_type === undefined &&*/ [0, false].indexOf(membership.data.membership_next_tier_id) === -1 && membership.data.membership_tier_post_id == membership.data.membership_next_tier_id && (! ['', 0, false].indexOf(membership.data.membership_next_tier_subscription_renewal) === -1))) {
            membership.data.renewalType = 'current_tier';
          }
          console.log('renewal_type' + membership.data.renewalType);

          if( membership.data.renewal_type === undefined || membership.data.renewal_type === 'inherited')  {
            if( membership.data.renewalType !== undefined) {
              membership.data.tierRenewalType = membership.data.renewalType;
            }
            membership.data.renewalType = 'inherited';
          }
          console.log('renewal_type' + membership.data.renewalType);
          if( membership.data.tierRenewalType === undefined ) {
            membership.data.tierRenewalType = 'current_tier';
          }
          console.log('tierRenewalType' + membership.data.tierRenewalType);

          // Set initial membership owner options
          tempMembershipOwnerOptions.push({
            label: membership.data.user_name,
            value: membership.data.membership_user_uuid
          });
        });

        setMembershipOwnerOptions(tempMembershipOwnerOptions);
        setMemberships(response);
        setIsLoading(false);
      }).catch((error) => {
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

  // Fetch Local WP Pages
  const getLocalWpPages = () => {
		apiFetch({ path: addQueryArgs(`${API_URL}/pages`, {
      _fields: 'id,title',
			status: 'publish',
			per_page: -1
		}) }).then((posts) => {
			let options = posts.map((post) => {
				const decodedTitle = he.decode(post.title.rendered);
				return {
					label: `${decodedTitle} | ID: ${post.id}`,
					value: post.id
				}
			});

			setWpPagesOptions(options);
		});
  }

	// Fetch Local Membership Tiers Posts
  const getWpTierOptions = () => {

    fetchTiers()
      .then((tiers) => {
        let options = tiers.map((tier) => {
          const decodedTitle = he.decode(tier.title.rendered);
          return {
            label: `${decodedTitle} | ID: ${tier.id}`,
            value: tier.id
          }
        });
  
        setWpTierOptions(options);
      })
      .catch((error) => {
        console.error(error);
      });
  }

  useEffect(() => {
    getMemberInfo();
    getMemberships();
    getLocalWpPages();
    getWpTierOptions();
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
    console.log('handleMembershipFieldChange');
    console.log(membershipId, field, value);
    setMemberships(
      memberships.map((m) => {
        if (m.ID == membershipId) {
          m.data[field] = value;
        }
        return m;
      })
    );
  }

  const getMemberInfo = () => {
    fetchMemberInfo(recordId)
      .then((response) => {
        console.log('memberInfo');
        console.log(response);
        setMemberInfo(response);
      })
      .catch((error) => {
        console.error(error);
      });
  }

  // get org name
  // this is no longer used and can be removed - org name comes from memberInfo now
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

  // get Unassigned seats count
  const getUnassignedSeats = (membership) => {
    if (memberType !== 'organization') {
      return 0;
    }

    // check if the max_assignments and active_assignments_count are numbers
    if (isNaN(parseInt(membership.max_assignments)) || isNaN(parseInt(membership.active_assignments_count))) {
      return 0;
    }

    return parseInt(membership.max_assignments) - parseInt(membership.active_assignments_count);
  }

  const getNextPaymentDate = (subscription)  => {
    if( subscription.next_payment_date == "1970-01-01" ) {
      return "N/A"
    }   else {
      return subscription.next_payment_date
    }
  }

  console.log('manageStatusFormData', manageStatusFormData);

  console.log('MEMBERSHIPS', memberships);

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
                  {memberType === 'individual' ? getIndividualName() : memberInfo === null ? '' : memberInfo.org_name}
                </Heading>
              </FlexBlock>
              <FlexItem>
                <CustomDisabled
                  isDisabled={memberInfo === null}
                >
                  <Button
                    variant='secondary'
                    href={memberInfo === null ? '' : memberInfo.mdp_link}
                    target='_blank'
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
                      <strong>{__('Email:', 'wicket-memberships')}</strong> {memberInfo === null ? '-' : memberInfo.data}
                    </FlexItem>
                    <FlexItem>
                      <strong>{__('Identifying Number:', 'wicket-memberships')}</strong> {memberInfo === null ? '-' : memberInfo.identifying_number}
                    </FlexItem>
                  </>
                }
                {memberType === 'organization' &&
                  <>
                    <FlexItem>
                      <strong>{__('Location:', 'wicket-memberships')}</strong> {memberInfo === null ? '-' : memberInfo.data}
                    </FlexItem>
                    <FlexItem>
                      <strong>{__('Identifying Number:', 'wicket-memberships')}</strong> {memberInfo === null ? '-' : memberInfo.identifying_number}
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
                      variant='secondary'
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
                        {__('ID', 'wicket-memberships')}
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
                            {membership.ID}
                          </td>
                          <td className="column-columnname">
                            {membership.data.membership_status}
                          </td>
                          <td className="column-columnname">
                            { moment(membership.data.membership_starts_at).format('YYYY-MM-DD') }
                          </td>
                          <td className="column-columnname">
                            { moment(membership.data.membership_ends_at).format('YYYY-MM-DD') }
                          </td>
                          <td className="column-columnname">
                            { moment(membership.data.membership_expires_at).format('YYYY-MM-DD') }
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
                          <td colSpan={7} >
                            {membership.subscription.id !== undefined &&
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
                                {__('Next Payment Date:', 'wicket-memberships')} <strong>{ getNextPaymentDate(membership.subscription) }</strong>
                              </FlexItem>
                            </Flex>
                            }

                            {/* Attached order information */}
                            {membership.order.id !== undefined &&
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
                                    { moment(membership.order.date_created).format('YYYY-MM-DD') }
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
                            }

                            <Flex
                              align='normal'
                              justify='start'
                              gap={6}
                              direction={[
                                'column',
                                'row'
                              ]}
                            >
                              <BorderedBox style={{ flex: '1' }} >
                                <Flex
                                  align='end'
                                  justify='start'
                                  gap={6}
                                  direction={[
                                    'column',
                                    'row'
                                  ]}
                                >
                                  <FlexBlock style={{ flex: '1' }} >
                                    <TextControl
                                      label={__('Membership Status', 'wicket-memberships')}
                                      disabled={true}
                                      __nextHasNoMarginBottom={true}
                                      value={membership.data.membership_status}
                                    />
                                  </FlexBlock>
                                  <FlexItem >
                                    <Button
                                      variant='secondary'
                                      onClick={() => {
                                        openManageStatusModalOpen(index);
                                      }}
                                    >
                                      {__('Manage Status', 'wicket-memberships')}
                                    </Button>
                                  </FlexItem>
                                </Flex>
                              </BorderedBox>

                              <CreateRenewalOrder membership={membership} />
                            </Flex>

                            <BorderedBox>
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
                                    <LabelWpStyled htmlFor="membership_starts_at">
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
                                        selected={ membership.data.membership_starts_at }
                                        onChange={(value) => {
                                          handleMembershipFieldChange(membership.ID, 'membership_starts_at', moment(value).format('YYYY-MM-DD'));
                                        }}
                                      />
                                    </ReactDatePickerStyledWrap>
                                  </FlexBlock>
                                  <FlexBlock>
                                    <LabelWpStyled htmlFor="membership_ends_at">
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
                                        selected={ membership.data.membership_ends_at }
                                        onChange={(value) => {
                                          handleMembershipFieldChange(membership.ID, 'membership_ends_at', moment(value).format('YYYY-MM-DD'));
                                        }}
                                      />
                                    </ReactDatePickerStyledWrap>
                                  </FlexBlock>
                                  <FlexBlock>
                                    <LabelWpStyled htmlFor="membership_expires_at">
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
                                        selected={ membership.data.membership_expires_at }
                                        onChange={(value) => {
                                          handleMembershipFieldChange(membership.ID, 'membership_expires_at', moment(value).format('YYYY-MM-DD'));
                                        }}
                                      />
                                    </ReactDatePickerStyledWrap>
                                  </FlexBlock>
                                </Flex>
                                <MarginedFlex>
                                  <FlexBlock>
                                    <LabelWpStyled htmlFor="renewal_type">
                                      {__('Renewal Type', 'wicket-memberships')}
                                    </LabelWpStyled>
                                    <SelectWpStyled
                                      id="renewal_type"
                                      classNamePrefix="select"
                                      name='renewal_type'
                                      value={(() => {
                                        return renewalTypeOptions.find(option => option.value == membership.data.renewalType);
                                      })()}
                                      options={renewalTypeOptions}
                                      onChange={(selected) => {
                                        handleMembershipFieldChange(membership.ID, 'renewalType', selected.value);
                                      }}
                                    />

                                { (membership.data.renewalType === 'inherited' && membership.data.tierRenewalType !== undefined ) &&
                                    <FlexItem  style={{ marginTop: '5px' }} >
                                      <small>
                                        {__('Inherited Renewal Type: ', 'wicket-memberships')} <strong>{ (renewalTypeOptions.find(option => option.value == membership.data.tierRenewalType)).label }</strong>
                                      </small>
                                    </FlexItem>
                                }

                                  </FlexBlock>
                                </MarginedFlex>

                                {/* Sequential Logic - Form Flow */}
                                {membership.data.renewalType === 'form_flow' &&
                                  <MarginedFlex>
                                    <FlexBlock>
                                      <LabelWpStyled htmlFor="next_tier_form_page_id">
                                        {__('Form Page', 'wicket-memberships')}
                                      </LabelWpStyled>
                                      <SelectWpStyled
                                        id="next_tier_form_page_id"
                                        name="next_tier_form_page_id"
                                        classNamePrefix="select"
                                        value={wpPagesOptions.find(option => option.value == membership.data.membership_next_tier_form_page_id)}
                                        isClearable={false}
                                        isSearchable={true}
                                        options={wpPagesOptions}
                                        onChange={(selected) => {
                                          handleMembershipFieldChange(membership.ID, 'membership_next_tier_form_page_id', selected.value);
                                        }}
                                      />
                                    </FlexBlock>
                                  </MarginedFlex>
                                }

                                {/* Sequential Logic - Tier ID */}
                                {membership.data.renewalType === 'sequential_logic' &&
                                  <MarginedFlex>
                                    <FlexBlock>
                                      <LabelWpStyled htmlFor="next_tier_id">
                                        {__('Sequential Tier', 'wicket-memberships')}
                                      </LabelWpStyled>
                                      <SelectWpStyled
                                        id="next_tier_id"
                                        name="next_tier_id"
                                        classNamePrefix="select"
                                        value={wpTierOptions.find(option => option.value == membership.data.membership_next_tier_id)}
                                        isClearable={false}
                                        isSearchable={true}
                                        options={wpTierOptions}
                                        onChange={(selected) => {
                                          handleMembershipFieldChange(membership.ID, 'membership_next_tier_id', selected.value);
                                        }}
                                      />
                                    </FlexBlock>
                                  </MarginedFlex>
                                }

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
                                          <strong>{membership.max_assignments}</strong>
                                        </div>
                                        <div className='box'>
                                          {__('Assigned Seats:', 'wicket-memberships')}
                                          <strong>{membership.active_assignments_count}</strong>
                                        </div>
                                        <div className='box'>
                                          {__('Unassigned:', 'wicket-memberships')}
                                          <strong>{getUnassignedSeats(membership)}</strong>
                                        </div>
                                      </SeatsBox>
                                      <div style={{ marginTop: '10px' }} >
                                        <Button
                                          href={membership.mdp_membership_link}
                                          target='_blank'
                                          variant='link'
                                        >
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
                                      <AsyncSelectWpStyled
                                        id="membership_owner_id"
                                        classNamePrefix="select"
                                        name="new_owner_uuid"
                                        value={membershipOwnerOptions.find(option => option.value === membership.data.membership_user_uuid)}
                                        defaultOptions={membershipOwnerOptions}
                                        loadOptions={loadMembershipOwnerOptions}
                                        isClearable={false}
                                        isSearchable={true}
                                        // isLoading={wcProductOptions.length === 0}
                                        onChange={selected => {
                                          handleMembershipFieldChange(membership.ID, 'membership_user_uuid', selected.value);
                                        }}
                                      />

                                      <div style={{ marginTop: '10px' }} >
                                        <Button
                                          href={membership.mdp_person_link}
                                          target='_blank'
                                          variant='link'
                                        >
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
                                      disabled={membership.updatingNow || membership.updatedNow || membership.data.membership_status.toLowerCase() == 'cancelled'}
                                      isBusy={membership.updatingNow}
                                    >
                                      {__('Update Membership', 'wicket-memberships')}
                                    </Button>
                                  </FlexItem>
                                </MarginedFlex>
                              </form>
                            </BorderedBox>
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
									<Button
                    variant="primary"
                    type='submit'
                    disabled={manageStatusFormData.newStatus === ''}
                  >
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