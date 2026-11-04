import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { __ } from "@wordpress/i18n";
import moment from "moment-timezone";
import {
  Flex,
  FlexBlock,
  FlexItem,
  Icon,
  Notice,
  SelectControl,
  TextControl,
} from "@wordpress/components";
import WicketButton from "./WicketButton";
import DatePicker from "react-datepicker";
import { DEFAULT_DATE_FORMAT, PLUGIN_SETTINGS } from "../constants";
import {
  ActionRow,
  AppWrap,
  ErrorsRow,
  GlobalDatePickerStyle,
  LabelWpStyled,
  ReactDatePickerStyledWrap,
} from "../styled_elements";
import WicketModal from "./WicketModal";

const createEmptySeason = () => ({
  season_name: "",
  active: true,
  start_date: "",
  end_date: "",
});

// The modal (.components-modal__frame / __content) clips its own overflow, so a
// normally-positioned popper gets cut off at the modal's edge. Render it into
// document.body instead — GlobalDatePickerStyle makes sure the portaled node
// still picks up react-datepicker's CSS despite living outside AppWrap.
const PopperPortal = ({ children }) => {
  if (typeof document === "undefined") return children;
  return createPortal(
    <div style={{ position: "relative", zIndex: 999999 }}>{children}</div>,
    document.body,
  );
};

const mdpTimezone = PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || "UTC";

// Converts a calendar-day Date from the picker into a full UTC ISO string in the
// MDP timezone — start of day for start_date, end of day for end_date. Matches
// the individual/organization membership config's convertSeasonDate() so a
// season's end date is inclusive of its whole last day rather than starting at
// midnight of that day.
const convertSeasonDate = (dateValue, isEndDate = false) => {
  if (!dateValue) return "";
  const m = moment.tz(
    [dateValue.getFullYear(), dateValue.getMonth(), dateValue.getDate()],
    mdpTimezone,
  );
  return isEndDate ? m.endOf("day").utc().toISOString() : m.startOf("day").utc().toISOString();
};

// Converts a stored ISO string back into a plain calendar-day Date for the picker
// (react-datepicker works in local time, so we take the MDP-timezone calendar
// date and construct a local Date at midnight on that same day).
const getSeasonDatePickerValue = (isoString) => {
  if (!isoString) return null;
  const m = moment.tz(isoString, mdpTimezone);
  return new Date(m.year(), m.month(), m.date());
};

const SeasonConfigModal = ({
  isOpen,
  seasonIndex,
  initialSeason,
  onClose,
  onSave,
  onDelete,
}) => {
  const [tempSeason, setTempSeason] = useState(initialSeason || createEmptySeason());
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    setTempSeason(initialSeason || createEmptySeason());
    setErrors({});
  }, [initialSeason, isOpen]);

  const validateSeason = () => {
    const nextErrors = {};

    if (!tempSeason.season_name) {
      nextErrors.seasonName = __(
        "Season Name is required",
        "wicket-memberships",
      );
    }

    if (!tempSeason.start_date) {
      nextErrors.seasonStartDate = __(
        "Season Start Date is required",
        "wicket-memberships",
      );
    }

    if (!tempSeason.end_date) {
      nextErrors.seasonEndDate = __(
        "Season End Date is required",
        "wicket-memberships",
      );
    }

    if (tempSeason.start_date && tempSeason.end_date) {
      if (new Date(tempSeason.start_date) > new Date(tempSeason.end_date)) {
        nextErrors.seasonEndDate = __(
          "Season End Date must be greater than Start Date",
          "wicket-memberships",
        );
      }
    }

    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const handleSubmit = (event) => {
    event.preventDefault();

    if (!validateSeason()) {
      return;
    }

    onSave(tempSeason);
    onClose();
  };

  return (
    <WicketModal
      isOpen={isOpen}
      onRequestClose={onClose}
      title={
        seasonIndex === null
          ? __("Add Season", "wicket-memberships")
          : __("Edit Season", "wicket-memberships")
      }
    >
      <GlobalDatePickerStyle />
      <AppWrap>
        <form onSubmit={handleSubmit}>
          {Object.keys(errors).length > 0 ? (
            <ErrorsRow>
              {Object.keys(errors).map((key) => (
                <Notice isDismissible={false} key={key} status="warning">
                  {errors[key]}
                </Notice>
              ))}
            </ErrorsRow>
          ) : null}

          <TextControl
            label={__("Season Name", "wicket-memberships")}
            onChange={(value) =>
              setTempSeason((currentSeason) => ({
                ...currentSeason,
                season_name: value,
              }))
            }
            value={tempSeason.season_name}
          />

          <SelectControl
            label={__("Status", "wicket-memberships")}
            onChange={(value) =>
              setTempSeason((currentSeason) => ({
                ...currentSeason,
                active: value === "true",
              }))
            }
            options={[
              { label: __("Active", "wicket-memberships"), value: "true" },
              { label: __("Inactive", "wicket-memberships"), value: "false" },
            ]}
            value={tempSeason.active ? "true" : "false"}
          />

          <Flex align="start" gap={4}>
            <FlexBlock>
              <ReactDatePickerStyledWrap>
                <LabelWpStyled>{__("Start Date", "wicket-memberships")}</LabelWpStyled>
                <DatePicker
                  dateFormat={DEFAULT_DATE_FORMAT}
                  dropdownMode="select"
                  popperContainer={PopperPortal}
                  onChange={(date) =>
                    setTempSeason((currentSeason) => ({
                      ...currentSeason,
                      start_date: convertSeasonDate(date, false),
                    }))
                  }
                  selected={getSeasonDatePickerValue(tempSeason.start_date)}
                  showMonthDropdown
                  showYearDropdown
                />
              </ReactDatePickerStyledWrap>
            </FlexBlock>

            <FlexBlock>
              <ReactDatePickerStyledWrap>
                <LabelWpStyled>{__("End Date", "wicket-memberships")}</LabelWpStyled>
                <DatePicker
                  dateFormat={DEFAULT_DATE_FORMAT}
                  dropdownMode="select"
                  popperContainer={PopperPortal}
                  onChange={(date) =>
                    setTempSeason((currentSeason) => ({
                      ...currentSeason,
                      end_date: convertSeasonDate(date, true),
                    }))
                  }
                  selected={getSeasonDatePickerValue(tempSeason.end_date)}
                  showMonthDropdown
                  showYearDropdown
                />
              </ReactDatePickerStyledWrap>
            </FlexBlock>
          </Flex>

          <ActionRow>
            <Flex justify="space-between">
              <FlexItem>
                {seasonIndex !== null ? (
                  <WicketButton
                    isDestructive
                    onClick={() => {
                      onDelete();
                      onClose();
                    }}
                    variant="secondary"
                  >
                    <Icon icon="archive" />
                    &nbsp;
                    {__("Archive", "wicket-memberships")}
                  </WicketButton>
                ) : null}
              </FlexItem>
              <FlexItem>
                <WicketButton type="submit" variant="primary">
                  {seasonIndex === null
                    ? __("Add Season", "wicket-memberships")
                    : __("Update Season", "wicket-memberships")}
                </WicketButton>
              </FlexItem>
            </Flex>
          </ActionRow>
        </form>
      </AppWrap>
    </WicketModal>
  );
};

export default SeasonConfigModal;
