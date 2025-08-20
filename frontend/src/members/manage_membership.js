import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { BorderedBox, LabelWpStyled, SelectWpStyled, AsyncSelectWpStyled } from '../styled_elements';
import { Button, Modal, Icon } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import { PLUGIN_API_URL } from '../constants';
import { transferMembership } from '../services/api';

const ManageMembership = ({ membership }) => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [action, setAction] = useState(null);
  const [selectedOwner, setSelectedOwner] = useState(null);
  const [isTransferring, setIsTransferring] = useState(false);
  const [transferError, setTransferError] = useState(null);
  const [transferSuccess, setTransferSuccess] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [futureStartWarning, setFutureStartWarning] = useState(null);

  const loadMembershipOwnerOptions = (inputValue, callback) => {
    if (!inputValue || inputValue.length < 3) return;
    apiFetch({
      path: addQueryArgs(`${PLUGIN_API_URL}/mdp_person/search`, { term: inputValue }),
      method: 'POST'
    }).then((response) => {
      const options = response.map((person) => ({
        label: person.full_name,
        value: person.id
      }));
      callback(options);
    });
  };

  const options = [
    { label: __('Select Action', 'wicket-memberships'), value: '' },
    { label: __('Transfer Membership', 'wicket-memberships'), value: 'transfer' },
    { label: __('Switch Membership', 'wicket-memberships'), value: 'switch' }
  ];

  return (
    <>
      <BorderedBox>
        <div style={{ textAlign: 'left' }}>
          <div>
            <LabelWpStyled>
              {__('Membership', 'wicket-memberships')}
            </LabelWpStyled>
          </div>
          <div>
            <Button
              variant='secondary'
              onClick={() => {
                setFutureStartWarning(null);
                // Check if membership_starts_at is in the future
                const startDate = new Date(membership.data.membership_starts_at);
                const now = new Date();
                now.setHours(0, 0, 0, 0);
                if (startDate >= now || ( membership.data.membership_status !== 'active' && membership.data.membership_status !== 'Active' ) ) {
                  setFutureStartWarning(__('You cannot manage a membership that is not currently active or does not have a start date in the past.', 'wicket-memberships'));
                  return;
                }
                setIsModalOpen(true);
              }}
            >
              {__('Manage Membership', 'wicket-memberships')}
            </Button>
            {futureStartWarning && (
              <Modal
                title={__('Membership Not Started', 'wicket-memberships')}
                onRequestClose={() => setFutureStartWarning(null)}
                style={{ maxWidth: '400px', width: '100%' }}
              >
                <div style={{ color: 'black', margin: '20px 0' }}>{futureStartWarning}</div>
                <div style={{ textAlign: 'right' }}>
                  <Button variant="primary" onClick={() => setFutureStartWarning(null)}>
                    {__('OK', 'wicket-memberships')}
                  </Button>
                </div>
              </Modal>
            )}
          </div>
        </div>
      </BorderedBox>
      {isModalOpen && (
        <Modal
          title={__('Manage Membership', 'wicket-memberships')}
          onRequestClose={() => setIsModalOpen(false)}
          style={{ maxWidth: '650px', width: '100%', minHeight: '410px' }}
        >
          <div style={{ marginBottom: '16px', maxWidth: 250 }}>
            <SelectWpStyled
              value={action ? options.find(opt => opt.value === action) : options[0]}
              options={options}
              onChange={selected => setAction(selected.value)}
              isSearchable={false}
              isClearable={false}
              placeholder={__('Select Action', 'wicket-memberships')}
            />
          </div>   
          {action === 'switch' && (
            <div style={{ marginBottom: '16px' }}>
              <LabelWpStyled style={{ height: '20px' }} >
                {__('Switch Tier Action', 'wicket-memberships')}&nbsp;
                <span title={__('Choose to create an order or a new membership for tier immediately.', 'wicket-memberships')}>
                  <Icon icon='info' />
                </span>
              </LabelWpStyled>
              <div style={{ marginTop: '20px', maxWidth: 250 }}>
                <SelectWpStyled
                  options={[
                    { label: __('Create Order', 'wicket-memberships'), value: 'create_order' },
                    { label: __('Create Membership', 'wicket-memberships'), value: 'create_membership' }
                  ]}
                  value={null}
                  /*onChange={selected => alert(__('Selected: ', 'wicket-memberships') + selected.label)}*/
                  isSearchable={false}
                  isClearable={false}
                  placeholder={__('Select Option', 'wicket-memberships')}
                />

                <Button style={{ marginTop: '20px'}} variant="secondary" disabled>{__('Coming Soon', 'wicket-memberships')}</Button>
              </div>
            </div>
          )}       
          {action === 'transfer' && (
            <div style={{ marginBottom: '16px' }}>
              <LabelWpStyled style={{ height: '20px' }} >
                {__('Membership Owner', 'wicket-memberships')}&nbsp;
                <span title={__('Represents the Customer responsible for managing and Renewing the Organization Membership.', 'wicket-memberships')}>
                  <Icon icon='info' />
                </span>
              </LabelWpStyled>
              <AsyncSelectWpStyled
                id="membership_owner_id_modal"
                classNamePrefix="select"
                name="new_owner_uuid"
                defaultOptions={[]}
                loadOptions={loadMembershipOwnerOptions}
                isClearable={false}
                isSearchable={true}
                value={selectedOwner}
                onChange={option => setSelectedOwner(option)}
              />
              <div style={{ marginTop: '20px' }}>
                <Button
                  variant="primary"
                  isBusy={isTransferring}
                  disabled={!selectedOwner || isTransferring}
                  onClick={() => setShowConfirm(true)}
                >
                  {__('Transfer Membership', 'wicket-memberships')}
                </Button>
                {showConfirm && (
                  <div style={{ marginTop: '16px', background: '#fffbe5', border: '1px solid #ffe58f', padding: '16px', borderRadius: '4px' }}>
                    <div style={{ marginBottom: '12px' }}>
                      {__('I confirm that this membership should be transferred to the selected user.', 'wicket-memberships')}
                    </div>
                    <Button
                      variant="primary"
                      isBusy={isTransferring}
                      disabled={isTransferring}
                      onClick={async () => {
                        if (isTransferring) return; // Prevent double submit
                        setIsTransferring(true);
                        setTransferError(null);
                        setTransferSuccess(false);
                        try {
                          const response = await transferMembership({
                            new_owner_uuid: selectedOwner.value,
                            membership_post_id: membership.data.membership_post_id
                          });
                          setShowConfirm(false);
                          // Robustly check for response shape
                          if (!response || typeof response !== 'object') {
                            setTransferError(__('Unexpected server response. Please try again.', 'wicket-memberships'));
                            return;
                          }
                          if ('error' in response && response.error) {
                            setTransferError(response.data.error || __('Transfer failed', 'wicket-memberships'));
                            return;
                          }
                          if ('success' in response && response.success) {
                            setTransferSuccess(true);
                            if (response.success.data.redirect_url) {
                              window.open(response.success.data.redirect_url, '_blank');
                              window.location.reload();
                            }
                            return;
                          }
                          // If neither success nor error, fallback
                          setTransferError(__('Transfer failed. Please try again.', 'wicket-memberships'));
                        } catch (e) {
                          setTransferError((e && e.message) ? e.message : __('Transfer failed', 'wicket-memberships'));
                        } finally {
                          setIsTransferring(false);
                        }
                      }}
                      style={{ marginRight: '8px' }}
                    >
                      {__('Confirm', 'wicket-memberships')}
                    </Button>
                    <Button
                      variant="secondary"
                      disabled={isTransferring}
                      onClick={() => setShowConfirm(false)}
                    >
                      {__('Cancel', 'wicket-memberships')}
                    </Button>
                  </div>
                )}
                {transferError && (
                  <div style={{ color: 'red', marginTop: '10px' }}>{transferError}</div>
                )}
                {transferSuccess && (
                  <div style={{ color: 'green', marginTop: '10px' }}>{__('Membership transferred successfully!', 'wicket-memberships')}</div>
                )}
              </div>
            </div>
          )}
        </Modal>
      )}
    </>
  );
};

export default ManageMembership;
