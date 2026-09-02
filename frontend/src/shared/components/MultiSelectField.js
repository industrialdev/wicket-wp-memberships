import { FlexBlock } from "@wordpress/components";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import { LabelWpStyled, MultiSelectWpStyled } from "../styled_elements";

/**
 * Standard labeled multi-select field, built on MultiSelectWpStyled
 * (react-select in isMulti mode). Handles the label, optional help text,
 * loading skeleton, and value/onChange normalization (option objects in,
 * plain value array out) so a caller only supplies data — no react-select
 * plumbing per usage.
 *
 * `options` and `selectedValues` are plain value arrays (e.g. post IDs), not
 * option objects — this component owns the {label,value} <-> value mapping
 * internally via `options` (each entry: { label, value }).
 */
const MultiSelectField = ({
  id,
  label,
  helpText,
  options,
  selectedValues,
  onChange,
  isDisabled,
  isLoading,
  isLoadingOptions,
  placeholder,
}) => {
  if (isLoading) {
    return <AdminLoadingSkeleton label={label} variant="singleField" />;
  }

  const selectedOptions = options.filter((option) =>
    selectedValues.includes(option.value),
  );

  return (
    <FlexBlock>
      {label ? (
        <LabelWpStyled htmlFor={id}>{label}</LabelWpStyled>
      ) : null}
      <MultiSelectWpStyled
        classNamePrefix="select"
        id={id}
        isDisabled={isDisabled}
        isLoading={isLoadingOptions}
        onChange={(selected) =>
          onChange((selected || []).map((option) => option.value))
        }
        options={options}
        placeholder={placeholder}
        value={selectedOptions}
      />
      {helpText ? (
        <p style={{ fontSize: "12px", color: "#757575", marginTop: "6px" }}>
          {helpText}
        </p>
      ) : null}
    </FlexBlock>
  );
};

export default MultiSelectField;
