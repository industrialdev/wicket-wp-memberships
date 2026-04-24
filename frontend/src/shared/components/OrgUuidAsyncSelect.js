import { useCallback, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { AsyncSelectWpStyled } from "../styled_elements";

/**
 * OrgUuidAsyncSelect — debounced async select for choosing an MDP organisation.
 *
 * Fully controlled: the parent owns the selected value and receives changes via
 * onChange. The selected option's `value` is the org UUID; `label` is the org name.
 * Debounce is handled internally so callers supply a plain
 * `(inputValue, callback) => void` load function.
 *
 * @param {object|null} props.value        - Controlled selected option: { label, value }.
 * @param {object[]}    [props.defaultOptions] - Options shown before the user types.
 * @param {Function}    props.onLoadOptions - `(inputValue, callback) => void`.
 * @param {Function}    props.onChange     - Called with the selected option on change.
 */
const OrgUuidAsyncSelect = ({
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
      id="org_uuid_selector"
      classNamePrefix="select"
      name="org_uuid"
      value={value}
      defaultOptions={defaultOptions}
      loadOptions={debouncedLoadOptions}
      isClearable={true}
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

export default OrgUuidAsyncSelect;
