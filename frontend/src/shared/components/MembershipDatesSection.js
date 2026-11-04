import { __ } from "@wordpress/i18n";
import { Flex } from "@wordpress/components";
import moment from "moment-timezone";
import { PLUGIN_SETTINGS } from "../constants";
import MembershipDatePicker from "./MembershipDatePicker";

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
      <MembershipDatePicker
        name="membership_starts_at"
        label={__("Start Date", "wicket-memberships")}
        value={startsAt}
        disabled={disabled}
        onChange={onStartsAtChange}
      />
      <MembershipDatePicker
        name="membership_ends_at"
        label={__("End Date", "wicket-memberships")}
        value={endsAt}
        disabled={disabled}
        onChange={onEndsAtChange}
      />
      <MembershipDatePicker
        name="membership_expires_at"
        label={__("Expiration Date", "wicket-memberships")}
        value={expiresAt}
        disabled={disabled}
        onChange={onExpiresAtChange}
      />
    </Flex>
  );
};

export default MembershipDatesSection;
