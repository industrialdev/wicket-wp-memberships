import { __ } from '@wordpress/i18n';
import styled from 'styled-components';

const MembershipGroupLabel = styled.div`
  font-size: 11px;
  color: #50575E;
  line-height: 1.2;
  margin-top: 2px;
  opacity: 0.85;

  span {
    color: var(--wp-admin-theme-color);
  }
`;

const MembershipGroupBadge = ({ groupName }) => (
  <MembershipGroupLabel title={groupName ? `Group: ${groupName}` : ''}>
    <span>* </span>{__('Part of Membership Group', 'wicket-memberships')}
  </MembershipGroupLabel>
);

export default MembershipGroupBadge;
