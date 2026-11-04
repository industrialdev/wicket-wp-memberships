import { __ } from '@wordpress/i18n';
import styled from 'styled-components';

const MembershipBundleLabel = styled.div`
  font-size: 11px;
  color: #50575E;
  line-height: 1.2;
  margin-top: 2px;
  opacity: 0.85;

  span {
    color: var(--wp-admin-theme-color);
  }
`;

const MembershipBundleBadge = ({ bundleName }) => (
  <MembershipBundleLabel title={bundleName ? `Bundle: ${bundleName}` : ''}>
    <span>* </span>{__('Part of Membership Bundle', 'wicket-memberships')}
  </MembershipBundleLabel>
);

export default MembershipBundleBadge;
