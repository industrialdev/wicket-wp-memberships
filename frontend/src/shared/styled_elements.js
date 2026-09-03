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

// react-select's menu (SelectWpStyled/MultiSelectWpStyled/AsyncSelectWpStyled,
// all via menuPortalTarget: document.body) renders outside any styled
// component's own DOM subtree, so option-row rules scoped inside those
// components' template literals never reach it — same portal-escape problem
// GlobalDatePickerStyle solves for the date picker's popper above. Mount
// once on any page/modal using one of the Select* components.
export const GlobalSelectMenuStyle = createGlobalStyle`
  .select__option {
    cursor: pointer;
  }
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

export const SectionTitle = styled.h3`
  font-size: 13px;
  font-weight: 600;
  margin: 0 0 12px;
  padding: 0;
  line-height: 1.4;
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
`;

// Single-select and multi-select need different .select__control/
// __value-container/__input sizing (a multi-select's control must grow to
// fit wrapped tag pills; a fixed 30px height clips them), so this is a
// dedicated styled component rather than a shared class toggled by isMulti
// on SelectWpStyled — every current SelectWpStyled usage in this plugin is
// single-select only, so keeping it single-purpose avoids new dead CSS
// paths on every existing picker.
export const MultiSelectWpStyled = styled(Select).attrs({
  ...selectPortalProps,
  isMulti: true,
})`
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
    cursor: pointer;
  }

  .select__input {
    min-height: 22px;
    box-shadow: none !important;
  }

  .select__value-container {
    padding: 3px 8px;
    gap: 4px;
  }

  // react-select's default multi-value pill, restyled to match this admin's
  // grey/blue palette instead of the library's default light-blue theme.
  .select__multi-value {
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    margin: 2px 0;
  }

  .select__multi-value__label {
    padding: 3px 6px;
    font-size: 13px;
    line-height: 1;
    cursor: default;
  }

  .select__multi-value__remove {
    display: flex;
    align-items: center;
    padding: 0 6px;
    border-radius: 0 3px 3px 0;
    cursor: pointer;

    &:hover {
      background: #d63638;
      color: #fff;
    }
  }

  .select__dropdown-indicator,
  .select__clear-indicator {
    cursor: pointer;
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
