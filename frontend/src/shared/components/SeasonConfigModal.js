import { useEffect, useState } from "react";
import { __ } from "@wordpress/i18n";
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
import { DEFAULT_DATE_FORMAT } from "../constants";
import {
  ActionRow,
  AppWrap,
  ErrorsRow,
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

const formatDate = (date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(
    date.getDate(),
  ).padStart(2, "0")}`;

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
                  onChange={(date) =>
                    setTempSeason((currentSeason) => ({
                      ...currentSeason,
                      start_date: date ? formatDate(date) : "",
                    }))
                  }
                  selected={
                    tempSeason.start_date ? new Date(tempSeason.start_date) : null
                  }
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
                  onChange={(date) =>
                    setTempSeason((currentSeason) => ({
                      ...currentSeason,
                      end_date: date ? formatDate(date) : "",
                    }))
                  }
                  selected={tempSeason.end_date ? new Date(tempSeason.end_date) : null}
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
