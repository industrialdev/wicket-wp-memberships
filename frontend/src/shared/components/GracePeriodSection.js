import { __ } from "@wordpress/i18n";
import {
  Flex,
  FlexBlock,
  FlexItem,
  Notice,
  TextControl,
} from "@wordpress/components";
import WicketButton from "./WicketButton";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import { BorderedBox, LabelWpStyled, SelectWpStyled } from "../styled_elements";

const GracePeriodSection = ({
  daysCount,
  selectedProductOption,
  wcProductOptions,
  productsRequest,
  disabled,
  isLoading,
  onDaysCountChange,
  onProductChange,
  onConfigureCallout,
  retryProducts,
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
      {productsRequest.status === "error" ? (
        <Notice isDismissible={false} status="warning">
          <div>
            {productsRequest.errorMessage ||
              __(
                "Products could not be loaded. You can retry this section without leaving the page.",
                "wicket-memberships",
              )}
          </div>
          <div>
            <WicketButton onClick={retryProducts} variant="link">
              {__("Retry products", "wicket-memberships")}
            </WicketButton>
          </div>
        </Notice>
      ) : null}

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
        <FlexBlock>
          <LabelWpStyled htmlFor="late_fee_product_id">
            {__("Product", "wicket-memberships")}
          </LabelWpStyled>
          <SelectWpStyled
            classNamePrefix="select"
            id="late_fee_product_id"
            isClearable={true}
            isDisabled={disabled || productsRequest.status === "error"}
            isLoading={productsRequest.status === "loading"}
            isSearchable={true}
            onChange={onProductChange}
            options={wcProductOptions}
            value={selectedProductOption}
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

export default GracePeriodSection;
