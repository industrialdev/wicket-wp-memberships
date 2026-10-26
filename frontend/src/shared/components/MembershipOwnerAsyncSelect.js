import { useCallback, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { AsyncSelectWpStyled } from "../styled_elements";

/**
 * MembershipOwnerAsyncSelect — debounced async select for choosing a membership
 * owner by MDP person UUID.
 *
 * Fully controlled: the parent owns the selected value and receives changes via
 * onChange. Debounce is handled internally so callers supply a plain
 * `(inputValue, callback) => void` load function.
 *
 * @param {object|null} props.value        - Controlled selected option: { label, value }.
 * @param {object[]}    [props.defaultOptions] - Options shown before the user types.
 * @param {Function}    props.onLoadOptions - `(inputValue, callback) => void`.
 * @param {Function}    props.onChange     - Called with the selected option on change.
 */
const MembershipOwnerAsyncSelect = ({
  value = null,
  defaultOptions = [],
  onLoadOptions,
  onChange,
}) => {
  const debounceTimer = useRef(null);
  const debouncedLoadOptions = useCallback(
    (inputValue, callback) => {
      if (debounceTimer.current) { clearTimeout(debounceTimer.current); }
      debounceTimer.current = setTimeout(() => {
        onLoadOptions(inputValue, callback);
      }, 300);
    },
    [onLoadOptions],
  );

  return (
    <AsyncSelectWpStyled
      id="membership_owner_id"
      classNamePrefix="select"
      name="membership_owner_uuid"
      value={value}
      defaultOptions={defaultOptions}
      loadOptions={debouncedLoadOptions}
      isClearable={false}
      isSearchable={true}
      noOptionsMessage={({ inputValue }) =>
        inputValue.length < 3
          ? __("Type at least 3 characters to search…", "wicket-memberships")
          : __("No results found.", "wicket-memberships")
      }
      onChange={onChange}
    />
  );
};

export default MembershipOwnerAsyncSelect;
