import SharedRenewalWindowSection from "../../shared/components/RenewalWindowSection";

const RenewalWindowSection = ({
  form,
  onChange,
  onOpenCallout,
  isEditing,
  isRecordReady,
  isDisabled,
}) => (
  <SharedRenewalWindowSection
    daysCount={form.renewal_window_data.days_count}
    disabled={isDisabled}
    isLoading={isEditing && !isRecordReady}
    onConfigureCallout={onOpenCallout}
    onDaysCountChange={(value) =>
      onChange((currentForm) => ({
        ...currentForm,
        renewal_window_data: {
          ...currentForm.renewal_window_data,
          days_count: value,
        },
      }))
    }
  />
);

export default RenewalWindowSection;
