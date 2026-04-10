import SharedRenewalTypeSection from "../../shared/components/RenewalTypeSection";
import useResolvedOption from "../../shared/hooks/useResolvedOption";

const RenewalTypeSection = ({
  form,
  onChange,
  isEditing,
  isRecordReady,
  isDisabled,
  loadPostOptions,
}) => {
  const selectedRenewalPostId = form.group_config_data.renewal_form_page_id;

  const { option: selectedPostOption, isLoading: isLoadingPostOption } =
    useResolvedOption(selectedRenewalPostId, "post", "pages");

  return (
    <SharedRenewalTypeSection
      disabled={isDisabled}
      isLoading={isEditing && !isRecordReady}
      isLoadingValue={isLoadingPostOption}
      loadPostOptions={() => loadPostOptions("pages")}
      postTypeLabel="Page"
      onRenewalFormPostIdChange={(selected) =>
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
      renewalType={form.group_config_data.renewal_type}
      selectedPostOption={selectedPostOption}
    />
  );
};

export default RenewalTypeSection;
