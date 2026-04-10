import { __ } from "@wordpress/i18n";
import { Flex, FlexBlock, FlexItem, SelectControl } from "@wordpress/components";
import WicketButton from "./WicketButton";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import CalendarSeasonsTable from "./CalendarSeasonsTable";
import CycleAnniversaryFields from "./CycleAnniversaryFields";
import { BorderedBox } from "../styled_elements";

const CycleSection = ({
  cycleType,
  anniversaryData,
  calendarItems,
  disabled,
  isLoading,
  onCycleTypeChange,
  onAnniversaryDataChange,
  onAddSeason,
  onEditSeason,
}) => {
  if (isLoading) {
    return (
      <AdminLoadingSkeleton
        label={__("Cycle", "wicket-memberships")}
        variant="cycle"
      />
    );
  }

  return (
    <BorderedBox>
      <Flex align="end" direction={["column", "row"]} gap={5} justify="start">
        <FlexBlock>
          <SelectControl
            __nextHasNoMarginBottom={true}
            disabled={disabled}
            label={__("Cycle", "wicket-memberships")}
            onChange={onCycleTypeChange}
            options={[
              { label: __("Calendar", "wicket-memberships"), value: "calendar" },
              { label: __("Anniversary", "wicket-memberships"), value: "anniversary" },
            ]}
            value={cycleType}
          />
        </FlexBlock>
        {cycleType === "calendar" ? (
          <FlexItem>
            <WicketButton
              dashicon="plus-alt"
              disabled={disabled}
              onClick={onAddSeason}
              variant="secondary"
            >
              {__("Add Season", "wicket-memberships")}
            </WicketButton>
          </FlexItem>
        ) : null}
      </Flex>

      {cycleType === "anniversary" ? (
        <CycleAnniversaryFields
          alignEndDatesEnabled={anniversaryData.align_end_dates_enabled}
          alignEndDatesType={anniversaryData.align_end_dates_type}
          disabled={disabled}
          onAlignEndDatesEnabledChange={(value) =>
            onAnniversaryDataChange({ ...anniversaryData, align_end_dates_enabled: value })
          }
          onAlignEndDatesTypeChange={(value) =>
            onAnniversaryDataChange({ ...anniversaryData, align_end_dates_type: value })
          }
          onPeriodCountChange={(value) =>
            onAnniversaryDataChange({ ...anniversaryData, period_count: value })
          }
          onPeriodTypeChange={(value) =>
            onAnniversaryDataChange({ ...anniversaryData, period_type: value })
          }
          periodCount={anniversaryData.period_count}
          periodType={anniversaryData.period_type}
        />
      ) : null}

      {cycleType === "calendar" ? (
        <CalendarSeasonsTable
          disabled={disabled}
          onEditSeason={onEditSeason}
          seasons={calendarItems}
        />
      ) : null}
    </BorderedBox>
  );
};

export default CycleSection;
