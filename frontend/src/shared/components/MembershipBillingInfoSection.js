import { __ } from "@wordpress/i18n";
import {
  Flex,
  FlexBlock,
  FlexItem,
  __experimentalHeading as Heading,
} from "@wordpress/components";
import { formatDateWithTooltip } from "../constants";

/**
 * MembershipBillingInfoSection — billing summary block rendered inside an
 * expanded membership record row.
 *
 * Displays the subscription ID (linked) and next payment date.
 *
 * Data-agnostic: receives only flat props. Callers are responsible for mapping
 * their page-specific data shapes to these props.
 *
 * @param {string|number|null}  props.subscriptionId    - WooCommerce subscription ID.
 * @param {string|null}         props.subscriptionLink  - Admin edit URL for the subscription.
 * @param {string|null}         props.nextPaymentDate   - Next payment date string.
 */
const MembershipBillingInfoSection = ({
  subscriptionId = null,
  subscriptionLink = null,
  nextPaymentDate = null,
}) => {
  return (
    <Flex align="end" justify="start" gap={6} direction={["column", "row"]}>
      <FlexBlock>
        <Heading level={4}>{__("Billing Info", "wicket-memberships")}</Heading>
      </FlexBlock>

      <FlexItem>
        {__("Subscription:", "wicket-memberships")}&nbsp;
        {subscriptionLink ? (
          <a target="_blank" href={subscriptionLink} rel="noreferrer">
            <strong>#{subscriptionId}</strong>
          </a>
        ) : (
          <strong>#{subscriptionId}</strong>
        )}
      </FlexItem>

      <FlexItem>
        {__("Next Payment Date:", "wicket-memberships")}&nbsp;
        <strong>{formatDateWithTooltip(nextPaymentDate)}</strong>
      </FlexItem>
    </Flex>
  );
};

export default MembershipBillingInfoSection;
