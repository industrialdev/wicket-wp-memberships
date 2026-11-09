import { __ } from "@wordpress/i18n";
import {
  CheckboxControl,
  Flex,
  FlexBlock,
  FlexItem,
  SelectControl,
  TextControl,
} from "@wordpress/components";
import { BorderedBox, CustomDisabled, FormFlex } from "../styled_elements";

const CycleAnniversaryFields = ({
  periodCount,
  periodType,
  alignEndDatesEnabled,
  alignEndDatesType,
  disabled,
  onPeriodCountChange,
  onPeriodTypeChange,
  onAlignEndDatesEnabledChange,
  onAlignEndDatesTypeChange,
}) => (
  <>
    <FormFlex align="end" direction={["column", "row"]} gap={5}>
      <FlexBlock>
        <TextControl
          __nextHasNoMarginBottom={true}
          disabled={disabled}
          label={__("Membership Period", "wicket-memberships")}
          min="1"
          onChange={onPeriodCountChange}
          type="number"
          value={periodCount}
        />
      </FlexBlock>
      <FlexBlock>
        <SelectControl
          __nextHasNoMarginBottom={true}
          disabled={disabled}
          label=""
          onChange={onPeriodTypeChange}
          options={[
            { label: __("Year", "wicket-memberships"), value: "year" },
            { label: __("Month", "wicket-memberships"), value: "month" },
            { label: __("Week", "wicket-memberships"), value: "week" },
          ]}
          value={periodType}
        />
      </FlexBlock>
    </FormFlex>

    <BorderedBox>
      <Flex align="end" direction={["column", "row"]} gap={5} justify="start">
        <FlexItem>
          <CheckboxControl
            __nextHasNoMarginBottom={true}
            checked={alignEndDatesEnabled}
            disabled={disabled}
            label={__("Align End Dates", "wicket-memberships")}
            onChange={onAlignEndDatesEnabledChange}
          />
        </FlexItem>
        <FlexBlock>
          <CustomDisabled isDisabled={!alignEndDatesEnabled}>
            <SelectControl
              __nextHasNoMarginBottom={true}
              disabled={disabled || !alignEndDatesEnabled}
              label={__("Align by", "wicket-memberships")}
              onChange={onAlignEndDatesTypeChange}
              options={[
                {
                  label: __("First Day of Month", "wicket-memberships"),
                  value: "first-day-of-month",
                },
                {
                  label: __("15th of Month", "wicket-memberships"),
                  value: "15th-of-month",
                },
                {
                  label: __("Last Day of Month", "wicket-memberships"),
                  value: "last-day-of-month",
                },
              ]}
              value={alignEndDatesType}
            />
          </CustomDisabled>
        </FlexBlock>
      </Flex>
    </BorderedBox>
  </>
);

export default CycleAnniversaryFields;
