import { useState } from "@wordpress/element";
import { __ } from '@wordpress/i18n';
import { Button, Flex, FlexItem, FlexBlock } from '@wordpress/components';
import { moveTo, cancelCircleFilled } from '@wordpress/icons';
import styled from 'styled-components';
import { BorderedBox } from '../shared/styled_elements';
import { formatDateWithTooltip } from '../shared/constants';
import RemoveFromMembershipBundleModal from './RemoveFromMembershipBundleModal';
import MoveToMembershipBundleModal from './MoveToMembershipBundleModal';

const Wrap = styled.div``;

const WhiteBox = styled(BorderedBox)`
  background: #fff;
`;

const BundleTitle = styled.div`
  font-size: 15px;
  font-weight: 600;
`;

const BundleMeta = styled.div`
  display: flex;
  gap: 50px;
  margin-top: 10px;
  padding: 15px;
  background: #F6F7F7;
  font-size: 13px;
  color: #50575E;
  flex-wrap: wrap;

  strong {
    margin-right: 4px;
  }
`;

const ManageLink = styled.div`
  margin-top: 14px;
  font-size: 13px;

  a {
    color: var(--wp-admin-theme-color);
    text-decoration: underline;

    &.disabled-link {
      color: #b32d2e;
      pointer-events: none;
      opacity: 0.7;
      cursor: not-allowed;
    }
  }
`;

const ActionsRow = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 12px;

  .button-red {
    border-color: #b32d2e !important;
    color: #b32d2e !important;
    background: #fff !important;
    box-shadow: inset 0 0 0 1px #b32d2e !important;

    &:hover {
      background: #fcf0f1 !important;
    }
  }
`;

const MembershipBundleDetails = ({ membership, onSuccess }) => {
  const [removeModalOpen, setRemoveModalOpen] = useState(false);
  const [moveModalOpen, setMoveModalOpen]     = useState(false);
  const canRemove = ['pending', 'active', 'delayed'].includes(membership.data?.membership_status_slug);
  const canMove   = canRemove;

  // TODO: Replace '#' with the real membership bundle MDP link once bundle MDP sync is implemented.
  const mdpLink = '#';

  const manageBundleUrl = membership.bundle_edit_url;

  return (
    <Wrap>
      <WhiteBox>
        <Flex align='start' justify='space-between' gap={4}>
          <FlexBlock>
            <BundleTitle>{membership.bundle_name || __('Membership Bundle', 'wicket-memberships')}</BundleTitle>
          </FlexBlock>
          <FlexItem>
            {/* TODO: Enable once bundle MDP sync is implemented — link destination TBD */}
            <a className='disabled-link' href={mdpLink} target='_blank' rel='noreferrer' style={{ textDecoration: 'underline' }}>
              {__('View in MDP', 'wicket-memberships')}
            </a>
          </FlexItem>
        </Flex>

        <BundleMeta>
          <div>
            <strong>{__('ID:', 'wicket-memberships')}</strong>
            {membership.bundle_id ?? '—'}
          </div>
          <div>
            <strong>{__('Bundle Administrator:', 'wicket-memberships')}</strong>
            {membership.bundle_admin_name || '—'}
          </div>
          <div>
            <strong>{__('Membership Bundle Start Date:', 'wicket-memberships')}</strong>
            {membership.bundle_starts_at ? formatDateWithTooltip(membership.bundle_starts_at) : '—'}
          </div>
        </BundleMeta>

        <ManageLink>
          {/* TODO: Wire to manage membership bundle page once routing is defined */}
          <a href={manageBundleUrl}>
            {__('Manage Membership Bundle', 'wicket-memberships')}
          </a>
        </ManageLink>
      </WhiteBox>

      <ActionsRow>
        <Button variant='secondary' icon={moveTo} onClick={() => setMoveModalOpen(true)} disabled={!canMove}>
          {__('Move to Another Bundle', 'wicket-memberships')}
        </Button>
        <Button variant='secondary' icon={cancelCircleFilled} onClick={() => setRemoveModalOpen(true)} disabled={!canRemove}>
          {__('Remove from Bundle', 'wicket-memberships')}
        </Button>
      </ActionsRow>

      <RemoveFromMembershipBundleModal
        isOpen={removeModalOpen}
        membershipPostId={membership.data?.membership_post_id}
        bundlePostId={membership.bundle_id}
        bundleEndDate={membership.bundle_ends_at}
        onRequestClose={() => setRemoveModalOpen(false)}
        onSuccess={(message) => {
          setRemoveModalOpen(false);
          if (onSuccess) onSuccess(message);
        }}
      />

      <MoveToMembershipBundleModal
        isOpen={moveModalOpen}
        membershipPostId={membership.data?.membership_post_id}
        sourceBundlePostId={membership.bundle_id}
        onRequestClose={() => setMoveModalOpen(false)}
        onSuccess={(message) => {
          setMoveModalOpen(false);
          if (onSuccess) onSuccess(message);
        }}
      />
    </Wrap>
  );
};

export default MembershipBundleDetails;
