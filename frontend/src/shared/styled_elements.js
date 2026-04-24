import styled from "styled-components";
import { Flex, Disabled, Modal } from "@wordpress/components";
import Select from "react-select";
import AsyncSelect from "react-select/async";
import ReactDatePickerCss from "!!raw-loader!react-datepicker/dist/react-datepicker.css";

export const AppWrap = styled.div`
  ${ReactDatePickerCss}

  .react-datepicker__current-month {
    display: none;
  }

  .react-datepicker__year-dropdown-container--select select,
  .react-datepicker__month-dropdown-container--select select {
    font-size: 13px;
    font-weight: 500;
    padding: 0;
    min-height: auto;
    appearance: auto;
    background: white;
  }

  .react-datepicker__year-dropdown-container--select,
  .react-datepicker__month-dropdown-container--select {
    margin: 0 4px;
  }
`;

export const CustomDisabled = styled(Disabled)`
  opacity: 0.5;
`;

export const Wrap = styled.div`
  max-width: 600px;
`;

export const EditWrap = styled.div`
  max-width: 1000px;
`;

export const ActionRow = styled.div`
  margin-top: 30px;
`;

export const FormFlex = styled(Flex)`
  margin-top: 15px;

  @media screen and (max-width: 767px) {
    align-items: normal !important;
  }
`;

export const ErrorsRow = styled.div`
  padding: 10px 0;
  // margin-left: -15px;
`;

export const BorderedBox = styled.div`
  border: 1px solid #c3c4c7;
  padding: 15px;
  margin-top: 15px;
`;

export const SelectWpStyled = styled(Select)`
  .select__input-container {
    margin: 0;
    padding: 0;
  }

  .select__dropdown-indicator {
    padding: 0 4px;
  }

  .select__control {
    border: 1px solid #949494;
    border-radius: 2px;
    min-height: 30px;
    height: 30px;
  }

  .select__input {
    min-height: 30px;
    box-shadow: none !important;
  }

  .select__value-container {
    padding: 0 8px;
  }

  .select__value-container--is-multi {
    padding: 6px 8px;
  }
`;

export const AsyncSelectWpStyled = styled(AsyncSelect)`
  .select__input-container {
    margin: 0;
    padding: 0;
  }

  .select__dropdown-indicator {
    padding: 0 4px;
  }

  .select__control {
    border: 1px solid #949494;
    border-radius: 2px;
    min-height: 30px;
    height: 30px;
  }

  .select__input {
    min-height: 30px;
    box-shadow: none !important;
  }

  .select__value-container {
    padding: 0 8px;
  }

  .select__value-container--is-multi {
    padding: 6px 8px;
  }
`;

export const ReactDatePickerStyledWrap = styled.div`
  position: relative;

  .react-datepicker-wrapper {
    width: 100%;
  }

  .react-datepicker-popper {
    z-index: 21;
  }

  .react-datepicker__input-container input {
    border: 1px solid #949494;
    border-radius: 2px;
    min-height: 30px;
    height: 30px;
    padding: 0 42px 0 8px;
    margin-bottom: calc(8px);
    width: 100%;
    line-height: 28px;
  }

  .membership-date-picker__adornment {
    position: absolute;
    top: 1px;
    right: 1px;
    bottom: calc(8px + 1px);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 6px;
    pointer-events: none;
    color: #50575e;
    min-width: 22px;
    justify-content: flex-end;
  }

  .membership-date-picker__divider {
    width: 1px;
    height: 18px;
    background: #dcdcde;
  }

  .membership-date-picker__icon {
    font-size: 16px;
    width: 16px;
    height: 16px;
  }
`;

export const LabelWpStyled = styled.label`
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.4;
  text-transform: uppercase;
  margin-bottom: 8px;
  padding: 0px;
`;

export const ModalStyled = styled(Modal)`
  &,
  .components-modal__content {
    overflow: visible;
  }
`;

export const RecordTopInfo = styled.div`
  background: #F0F6FC;
  margin-top: 15px;
  padding: 15px;
  font-size: 14px;
`;

export const MembershipTable = styled.div`
  margin-top: 20px;

  .membership_details {
    background: #F6F7F7;

    > td {
      padding: 15px;
    }
  }

  td {
    vertical-align: middle;
  }

  .billing_table {
    margin-top: 15px;

    thead {
      th {
        background: #F0F0F1;
      }
    }
  }
`;
