import { __ } from "@wordpress/i18n";
import {
  Flex,
  FlexBlock,
  FlexItem,
  TextControl,
} from "@wordpress/components";
import WicketButton from "./WicketButton";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import ModalPostSelector from "./ModalPostSelector";
import { BorderedBox } from "../styled_elements";

const GracePeriodSection = ({
  daysCount,
  selectedProductOption,
  disabled,
  isLoading,
  isLoadingValue,
  onDaysCountChange,
  onProductChange,
  onConfigureCallout,
  loadProductOptions,
  showProduct = true,
}) => {
  if (isLoading) {
    return (
      <AdminLoadingSkeleton
        label={__("Grace Period Window", "wicket-memberships")}
        variant="multiField"
      />
    );
  }

  return (
    <BorderedBox>
      <Flex align="end" direction={["column", "row"]} gap={5}>
        <FlexBlock>
          <TextControl
            __nextHasNoMarginBottom={true}
            disabled={disabled}
            label={__("Grace Period Window (Days)", "wicket-memberships")}
            min="0"
            onChange={onDaysCountChange}
            type="number"
            value={daysCount}
          />
        </FlexBlock>
        {showProduct && (
          <FlexBlock>
            <ModalPostSelector
              id="late_fee_product_id"
              label={__("Product", "wicket-memberships")}
              placeholder={__("Select a product…", "wicket-memberships")}
              modalTitle={__("Select a Product", "wicket-memberships")}
              value={selectedProductOption}
              onChange={onProductChange}
              disabled={disabled}
              isLoadingValue={isLoadingValue}
              loadOptions={loadProductOptions}
              columnLabels={{
                id: __("ID", "wicket-memberships"),
                name: __("Product Name", "wicket-memberships"),
              }}
            />
          </FlexBlock>
        )}
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

export default GracePeriodSection;
