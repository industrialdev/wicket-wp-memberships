import { __ } from "@wordpress/i18n";
import SharedGeneralSettingsSection from "../../shared/components/GeneralSettingsSection";

const GeneralSettingsSection = ({
  form,
  onChange,
  isEditing,
  isRecordReady,
  isDisabled,
}) => (
  <SharedGeneralSettingsSection
    disabled={isDisabled}
    isLoading={isEditing && !isRecordReady}
    loadingLabel={__("Bundle Configuration Name", "wicket-memberships")}
    name={form.name}
    onNameChange={(value) =>
      onChange((currentForm) => ({ ...currentForm, name: value }))
    }
  />
);

export default GeneralSettingsSection;
