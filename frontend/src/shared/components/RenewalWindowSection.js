import { __ } from "@wordpress/i18n";
import { Flex, FlexBlock, FlexItem, TextControl } from "@wordpress/components";
import WicketButton from "./WicketButton";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import { BorderedBox } from "../styled_elements";

const RenewalWindowSection = ({
  daysCount,
  disabled,
  isLoading,
  onDaysCountChange,
  onConfigureCallout,
}) => {
  if (isLoading) {
    return (
      <AdminLoadingSkeleton
        label={__("Renewal Window", "wicket-memberships")}
        variant="fieldWithAction"
      />
    );
  }

  return (
    <BorderedBox>
      <Flex align="end" direction={["column", "row"]} gap={5} justify="start">
        <FlexBlock>
          <TextControl
            __nextHasNoMarginBottom={true}
            disabled={disabled}
            label={__("Renewal Window (Days)", "wicket-memberships")}
            min="1"
            onChange={onDaysCountChange}
            type="number"
            value={daysCount}
          />
        </FlexBlock>
        <FlexItem>
          <WicketButton
            dashicon="screenoptions"
            disabled={disabled}
            onClick={onConfigureCallout}
            variant="secondary"
          >
            {__("Callout Configuration", "wicket-memberships")}
          </WicketButton>
        </FlexItem>
      </Flex>
    </BorderedBox>
  );
};

export default RenewalWindowSection;
