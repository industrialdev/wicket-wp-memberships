import SharedRenewalTypeSection from "../../shared/components/RenewalTypeSection";
import { findOptionByValue, getPrimaryErrorMessage } from "../utils/formUtils";

const RenewalTypeSection = ({
  form,
  onChange,
  isEditing,
  isRecordReady,
  isDisabled,
  wpPagesOptions,
  pagesRequest,
  retryPages,
}) => {
  const selectedRenewalPageId = form.group_config_data.renewal_form_page_id;
  const selectedPageOption =
    findOptionByValue(wpPagesOptions, selectedRenewalPageId) ||
    (selectedRenewalPageId
      ? {
          label: `Saved Page | ID: ${selectedRenewalPageId}`,
          value: selectedRenewalPageId,
        }
      : null);

  return (
    <SharedRenewalTypeSection
      disabled={isDisabled}
      isLoading={isEditing && !isRecordReady}
      onRenewalFormPageIdChange={(selected) =>
        onChange((currentForm) => ({
          ...currentForm,
          group_config_data: {
            ...currentForm.group_config_data,
            renewal_form_page_id: selected ? selected.value : "",
          },
        }))
      }
      onRenewalTypeChange={(value) =>
        onChange((currentForm) => ({
          ...currentForm,
          group_config_data: {
            ...currentForm.group_config_data,
            renewal_type: value,
            renewal_form_page_id: "",
          },
        }))
      }
      pagesRequest={{
        ...pagesRequest,
        errorMessage: getPrimaryErrorMessage(
          pagesRequest.error,
          "Pages could not be loaded. You can retry this section without leaving the page.",
        ),
      }}
      renewalType={form.group_config_data.renewal_type}
      retryPages={retryPages}
      selectedPageOption={selectedPageOption}
      wpPagesOptions={wpPagesOptions}
    />
  );
};

export default RenewalTypeSection;
