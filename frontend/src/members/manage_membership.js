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
    { label: __('Upgrade Membership', 'wicket-memberships'), value: 'upgrade' }
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
              onClick={() => setIsModalOpen(true)}
            >
              {__('Manage Membership', 'wicket-memberships')}
            </Button>
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
                        setIsTransferring(true);
                        setTransferError(null);
                        setTransferSuccess(false);
                        try {
                          const response = await transferMembership({
                            new_owner_uuid: selectedOwner.value,
                            membership_post_id: membership.data.membership_post_id
                          });
                          setTransferSuccess(true);
                          setShowConfirm(false);
                          // Redirect to the new membership edit page
                          const wicketMembershipUuid = response.wicket_membership_uuid;
                          const userUuid = selectedOwner.value;
                          console.log(`admin.php?page=wicket_individual_member_edit&id=${userUuid}&membership_uuid=${wicketMembershipUuid}`)
                          //window.location.href = `admin.php?page=wicket_individual_member_edit&id=${userUuid}&membership_uuid=${wicketMembershipUuid}`;
                        } catch (e) {
                          setTransferError(e.message || 'Transfer failed');
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
