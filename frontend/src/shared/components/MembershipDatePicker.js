import { __ } from "@wordpress/i18n";
import { FlexBlock } from "@wordpress/components";
import DatePicker from "react-datepicker";
import { LabelWpStyled, ReactDatePickerStyledWrap } from "../styled_elements";
import { DEFAULT_DATE_FORMAT } from "../constants";

/**
 * MembershipDatePicker — a single labelled date picker field.
 *
 * @param {string}      props.name      - Input name attribute and aria-label key.
 * @param {string}      props.label     - Visible label text.
 * @param {Date|null}   props.value     - Controlled date value.
 * @param {boolean}     [props.disabled] - Disables the picker.
 * @param {Function}    props.onChange  - Called with a Date when the value changes.
 */
const MembershipDatePicker = ({
  name,
  label,
  value = null,
  disabled = false,
  onChange,
}) => {
  return (
    <FlexBlock>
      <LabelWpStyled htmlFor={name}>{label}</LabelWpStyled>
      <ReactDatePickerStyledWrap>
        <DatePicker
          aria-label={label}
          name={name}
          dateFormat={DEFAULT_DATE_FORMAT}
          showMonthDropdown
          showYearDropdown
          dropdownMode="select"
          disabled={disabled}
          selected={value}
          onChange={onChange}
        />
      </ReactDatePickerStyledWrap>
    </FlexBlock>
  );
};

export default MembershipDatePicker;
