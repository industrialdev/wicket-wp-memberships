import { __ } from '@wordpress/i18n';
import { Button, Flex, FlexItem, FlexBlock } from '@wordpress/components';
import styled from 'styled-components';
import { BorderedBox } from '../shared/styled_elements';
import { formatDateWithTooltip } from '../shared/constants';

const Wrap = styled.div``;

const WhiteBox = styled(BorderedBox)`
  background: #fff;
`;

const GroupTitle = styled.div`
  font-size: 15px;
  font-weight: 600;
`;

const GroupMeta = styled.div`
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

const GroupMembershipDetails = ({ membership }) => {
  // TODO: Replace '#' with the real group membership MDP link once group MDP sync is implemented.
  const mdpLink = '#';

  const manageGroupUrl = membership.group_edit_url;

  return (
    <Wrap>
      <WhiteBox>
        <Flex align='start' justify='space-between' gap={4}>
          <FlexBlock>
            <GroupTitle>{membership.group_name || __('Group Membership', 'wicket-memberships')}</GroupTitle>
          </FlexBlock>
          <FlexItem>
            {/* TODO: Enable once group MDP sync is implemented — link destination TBD */}
            <a className='disabled-link' href={mdpLink} target='_blank' rel='noreferrer' style={{ textDecoration: 'underline' }}>
              {__('View in MDP', 'wicket-memberships')}
            </a>
          </FlexItem>
        </Flex>

        <GroupMeta>
          <div>
            <strong>{__('ID:', 'wicket-memberships')}</strong>
            {membership.group_id ?? '—'}
          </div>
          <div>
            <strong>{__('Group Administrator:', 'wicket-memberships')}</strong>
            {membership.group_admin_name || '—'}
          </div>
          <div>
            <strong>{__('Group Membership Start Date:', 'wicket-memberships')}</strong>
            {membership.group_starts_at ? formatDateWithTooltip(membership.group_starts_at) : '—'}
          </div>
        </GroupMeta>

        <ManageLink>
          {/* TODO: Wire to manage group membership page once routing is defined */}
          <a href={manageGroupUrl}>
            {__('Manage Group Membership', 'wicket-memberships')}
          </a>
        </ManageLink>
      </WhiteBox>

      <ActionsRow>
        {/* TODO: Implement Move to Another Group action */}
        <Button variant='secondary' className='button-red' disabled>
          {__('Move to Another Group', 'wicket-memberships')}
        </Button>
        {/* TODO: Implement Remove from Group action */}
        <Button variant='secondary' className='button-red' disabled>
          {__('Remove from Group', 'wicket-memberships')}
        </Button>
      </ActionsRow>
    </Wrap>
  );
};

export default GroupMembershipDetails;
