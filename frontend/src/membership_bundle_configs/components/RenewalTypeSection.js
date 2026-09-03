import { __ } from "@wordpress/i18n";
import SharedRenewalTypeSection from "../../shared/components/RenewalTypeSection";
import useResolvedOption from "../../shared/hooks/useResolvedOption";

// confirmation_renewal is bundle-config-only — not a valid Membership_Tier
// renewal_type — so it's added here rather than to the shared component's
// base options list.
const BUNDLE_ONLY_RENEWAL_TYPE_OPTIONS = [
  { label: __("Confirmation Renewal", "wicket-memberships"), value: "confirmation_renewal" },
];

const RenewalTypeSection = ({
  form,
  onChange,
  isEditing,
  isRecordReady,
  isDisabled,
  loadPostOptions,
}) => {
  const selectedRenewalPostId = form.bundle_config_data.renewal_form_page_id;

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
          bundle_config_data: {
            ...currentForm.bundle_config_data,
            renewal_form_page_id: selected ? selected.value : "",
          },
        }))
      }
      onRenewalTypeChange={(value) =>
        onChange((currentForm) => ({
          ...currentForm,
          bundle_config_data: {
            ...currentForm.bundle_config_data,
            renewal_type: value,
            renewal_form_page_id: "",
          },
        }))
      }
      renewalType={form.bundle_config_data.renewal_type}
      selectedPostOption={selectedPostOption}
      extraOptions={BUNDLE_ONLY_RENEWAL_TYPE_OPTIONS}
    />
  );
};

export default RenewalTypeSection;
