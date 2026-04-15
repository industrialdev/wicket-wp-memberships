import { __ } from "@wordpress/i18n";
import {
  Flex,
  FlexBlock,
} from "@wordpress/components";
import DatePicker from "react-datepicker";
import styled from "styled-components";
import moment from "moment-timezone";
import {
  LabelWpStyled,
  ReactDatePickerStyledWrap,
} from "../styled_elements";
import { DEFAULT_DATE_FORMAT, PLUGIN_SETTINGS } from "../constants";

/**
 * Convert a stored ISO string to a Date object for the date picker.
 * Interprets the value in the MDP timezone and returns a plain local Date at
 * the same calendar day (react-datepicker works in local time).
 *
 * @param {string} isoString
 * @returns {Date|null}
 */
export const isoToPickerDate = (isoString) => {
  if (!isoString) return null;
  const mdpTimezone = PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || "UTC";
  const m = moment.tz(isoString, mdpTimezone);
  return new Date(m.year(), m.month(), m.date());
};

/**
 * Convert a date picker Date back to a UTC ISO string.
 * End / expiry fields are stored at end-of-day; start is at start-of-day.
 *
 * @param {Date|null} dateValue
 * @param {string}    field
 * @returns {string|null}
 */
export const pickerDateToIso = (dateValue, field) => {
  if (!dateValue) return null;
  const mdpTimezone = PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || "UTC";
  const mdpDate = moment.tz(
    [dateValue.getFullYear(), dateValue.getMonth(), dateValue.getDate()],
    mdpTimezone,
  );
  if (["membership_ends_at", "membership_expires_at"].includes(field)) {
    mdpDate.endOf("day");
  } else {
    mdpDate.startOf("day");
  }
  return mdpDate.utc().toISOString();
};

/**
 * MembershipDatesSection — presentational date pickers for start, end, and
 * expiry dates. Fully controlled: the parent owns the Date state and receives
 * changes via individual onChange callbacks.
 *
 * @param {Date|null}   props.startsAt          - Controlled start date value.
 * @param {Date|null}   props.endsAt            - Controlled end date value.
 * @param {Date|null}   props.expiresAt         - Controlled expiry date value.
 * @param {boolean}     [props.disabled]        - Disables all date pickers.
 * @param {Function}    props.onStartsAtChange  - Called with a Date when start changes.
 * @param {Function}    props.onEndsAtChange    - Called with a Date when end changes.
 * @param {Function}    props.onExpiresAtChange - Called with a Date when expiry changes.
 */
const MembershipDatesSection = ({
  startsAt = null,
  endsAt = null,
  expiresAt = null,
  disabled = false,
  onStartsAtChange,
  onEndsAtChange,
  onExpiresAtChange,
}) => {
  return (
    <Flex align="end" justify="start" gap={6} direction={["column", "row"]}>
      <FlexBlock>
        <LabelWpStyled htmlFor="membership_starts_at">
          {__("Start Date", "wicket-memberships")}
        </LabelWpStyled>
        <ReactDatePickerStyledWrap>
          <DatePicker
            aria-label={__("Start Date", "wicket-memberships")}
            name="membership_starts_at"
            dateFormat={DEFAULT_DATE_FORMAT}
            showMonthDropdown
            showYearDropdown
            dropdownMode="select"
            locale="UTC"
            disabled={disabled}
            selected={startsAt}
            onChange={onStartsAtChange}
          />
        </ReactDatePickerStyledWrap>
      </FlexBlock>

      <FlexBlock>
        <LabelWpStyled htmlFor="membership_ends_at">
          {__("End Date", "wicket-memberships")}
        </LabelWpStyled>
        <ReactDatePickerStyledWrap>
          <DatePicker
            aria-label={__("End Date", "wicket-memberships")}
            name="membership_ends_at"
            dateFormat={DEFAULT_DATE_FORMAT}
            showMonthDropdown
            showYearDropdown
            dropdownMode="select"
            disabled={disabled}
            selected={endsAt}
            onChange={onEndsAtChange}
          />
        </ReactDatePickerStyledWrap>
      </FlexBlock>

      <FlexBlock>
        <LabelWpStyled htmlFor="membership_expires_at">
          {__("Expiration Date", "wicket-memberships")}
        </LabelWpStyled>
        <ReactDatePickerStyledWrap>
          <DatePicker
            aria-label={__("Expiration Date", "wicket-memberships")}
            name="membership_expires_at"
            dateFormat={DEFAULT_DATE_FORMAT}
            showMonthDropdown
            showYearDropdown
            dropdownMode="select"
            disabled={disabled}
            selected={expiresAt}
            onChange={onExpiresAtChange}
          />
        </ReactDatePickerStyledWrap>
      </FlexBlock>
    </Flex>
  );
};

export default MembershipDatesSection;
