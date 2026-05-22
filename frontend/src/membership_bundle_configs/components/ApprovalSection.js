import { __ } from "@wordpress/i18n";
import ApprovalSettingsSection from "../../shared/components/ApprovalSettingsSection";

const ApprovalSection = ({
  form,
  onChange,
  onOpenCallout,
  isEditing,
  isRecordReady,
  isDisabled,
}) => {
  return (
    <ApprovalSettingsSection
      approvalEmailRecipient={form.bundle_config_data.approval_email_recipient}
      approvalRequired={form.bundle_config_data.approval_required}
      disabled={isDisabled}
      isLoading={isEditing && !isRecordReady}
      loadingLabel={__("Approval", "wicket-memberships")}
      onApprovalEmailRecipientChange={(value) =>
        onChange((currentForm) => ({
          ...currentForm,
          bundle_config_data: {
            ...currentForm.bundle_config_data,
            approval_email_recipient: value,
          },
        }))
      }
      onApprovalRequiredChange={(value) =>
        onChange((currentForm) => ({
          ...currentForm,
          bundle_config_data: {
            ...currentForm.bundle_config_data,
            approval_required: value,
          },
        }))
      }
      onConfigureCallout={onOpenCallout}
    />
  );
};

export default ApprovalSection;
