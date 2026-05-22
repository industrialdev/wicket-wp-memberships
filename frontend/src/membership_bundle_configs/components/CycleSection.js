import SharedCycleSection from "../../shared/components/CycleSection";
import { normalizeCycleData } from "../../shared/cycleUtils";

const CycleSection = ({
  form,
  onChange,
  onOpenSeasonModal,
  isEditing,
  isRecordReady,
  isDisabled,
}) => {
  const cycleData = normalizeCycleData(form.cycle_data);

  return (
    <SharedCycleSection
      anniversaryData={cycleData.anniversary_data}
      calendarItems={cycleData.calendar_items}
      cycleType={cycleData.cycle_type}
      disabled={isDisabled}
      isLoading={isEditing && !isRecordReady}
      onAddSeason={() => onOpenSeasonModal(null)}
      onAnniversaryDataChange={(anniversaryData) =>
        onChange((currentForm) => ({
          ...currentForm,
          cycle_data: {
            ...normalizeCycleData(currentForm.cycle_data),
            anniversary_data: anniversaryData,
          },
        }))
      }
      onCycleTypeChange={(value) =>
        onChange((currentForm) => ({
          ...currentForm,
          cycle_data: {
            ...normalizeCycleData(currentForm.cycle_data),
            cycle_type: value,
          },
        }))
      }
      onEditSeason={(index) => onOpenSeasonModal(index)}
    />
  );
};

export default CycleSection;
