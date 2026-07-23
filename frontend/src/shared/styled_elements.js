import styled, { createGlobalStyle } from "styled-components";
import { Flex, Disabled, Modal } from "@wordpress/components";
import Select from "react-select";
import AsyncSelect from "react-select/async";
import ReactDatePickerCss from "!!raw-loader!react-datepicker/dist/react-datepicker.css";

const datePickerStyleRules = `
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

export const AppWrap = styled.div`
  ${datePickerStyleRules}
`;

// Mount once on any page/modal whose DatePicker uses popperContainer to portal its
// popper to document.body — that portaled node lives outside AppWrap's DOM subtree,
// so AppWrap's scoped rules above never reach it without this global copy.
// (createGlobalStyle is safe to mount more than once across the app.)
export const GlobalDatePickerStyle = createGlobalStyle`
  ${datePickerStyleRules}
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

// menuPortalTarget renders the option list into document.body via a portal, so it
// escapes any clipped/scrollable ancestor (e.g. a WicketModal) instead of being cut off.
// styles.menuPortal keeps the portaled menu above WP admin's own modal/overlay z-indexes.
const selectPortalProps = {
  menuPortalTarget: typeof document !== "undefined" ? document.body : null,
  styles: {
    menuPortal: (base) => ({ ...base, zIndex: 100000 }),
  },
};

export const SelectWpStyled = styled(Select).attrs(selectPortalProps)`
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

export const AsyncSelectWpStyled = styled(AsyncSelect).attrs(selectPortalProps)`
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

// Date pickers (react-datepicker withPortal) and react-select dropdowns
// (SelectWpStyled/AsyncSelectWpStyled menuPortalTarget) render their popups via a
// portal to document.body, so they no longer need this modal to disable its own
// scroll/clipping. Leaving overflow at its @wordpress/components default lets the
// modal cap its own height and scroll its content on short viewports.
//
// $fillHeight makes .components-modal__frame/.components-modal__content a real flex
// column with a definite height (the frame's own display:flex has no flex-direction,
// so flex:1 on .components-modal__content sizes it in the wrong axis by default and
// its height stays auto, only ever clipped by the frame's overflow:hidden rather than
// shrinking a child to fit). Opt in via the $fillHeight prop for modals with content
// that needs to compress and internally scroll instead of clipping — e.g.
// ModalPostSelector's post-picker table. Plain form modals (SeasonConfigModal,
// manage_products.js) should NOT set this — they want their natural auto-height.
export const ModalStyled = styled(Modal)`
  ${({ $fillHeight }) =>
    $fillHeight &&
    `
      &.components-modal__frame {
        display: flex;
        flex-direction: column;
      }

      .components-modal__content {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
      }
    `}
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
