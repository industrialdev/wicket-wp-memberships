import { __ } from "@wordpress/i18n";
import {
  CheckboxControl,
  Flex,
  FlexBlock,
  FlexItem,
  TextControl,
} from "@wordpress/components";
import WicketButton from "./WicketButton";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import { BorderedBox, CustomDisabled } from "../styled_elements";

const ApprovalSettingsSection = ({
  approvalRequired,
  approvalEmailRecipient,
  disabled = false,
  isLoading = false,
  onApprovalRequiredChange,
  onApprovalEmailRecipientChange,
  onConfigureCallout,
  loadingLabel,
}) => {
  if (isLoading) {
    return (
      <div style={{ marginTop: "15px" }}>
        <AdminLoadingSkeleton
          label={loadingLabel || __("Approval", "wicket-memberships")}
          variant="multiField"
        />
      </div>
    );
  }

  return (
    <div style={{ marginTop: "15px" }}>
      <BorderedBox>
        <Flex align="end" direction={["column", "row"]} gap={5} justify="start">
          <FlexItem>
            <CheckboxControl
              __nextHasNoMarginBottom={true}
              checked={approvalRequired}
              disabled={disabled}
              label={__("Approval Required", "wicket-memberships")}
              onChange={onApprovalRequiredChange}
            />
          </FlexItem>
          <FlexBlock>
            <CustomDisabled isDisabled={!approvalRequired}>
              <TextControl
                __nextHasNoMarginBottom={true}
                disabled={disabled || !approvalRequired}
                label={__("Approval Email Recipient", "wicket-memberships")}
                onChange={onApprovalEmailRecipientChange}
                type="email"
                value={approvalEmailRecipient}
              />
            </CustomDisabled>
          </FlexBlock>
          <FlexItem>
            <WicketButton
              dashicon="screenoptions"
              disabled={disabled || !approvalRequired}
              onClick={onConfigureCallout}
              variant="secondary"
            >
              {__("Callout Configuration", "wicket-memberships")}
            </WicketButton>
          </FlexItem>
        </Flex>
      </BorderedBox>
    </div>
  );
};

export default ApprovalSettingsSection;
