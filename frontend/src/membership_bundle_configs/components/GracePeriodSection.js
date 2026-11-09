import SharedGracePeriodSection from "../../shared/components/GracePeriodSection";
import useResolvedOption from "../../shared/hooks/useResolvedOption";

const GracePeriodSection = ({
  form,
  onChange,
  onOpenCallout,
  isEditing,
  isRecordReady,
  isDisabled,
  loadProductOptions,
}) => {
  const selectedProductId = form.late_fee_window_data.product_id;

  const { option: selectedProductOption, isLoading: isLoadingProductOption } =
    useResolvedOption(
      selectedProductId && selectedProductId !== "-1" ? selectedProductId : null,
      "product",
    );

  return (
    <SharedGracePeriodSection
      daysCount={form.late_fee_window_data.days_count}
      disabled={isDisabled}
      isLoading={isEditing && !isRecordReady}
      isLoadingValue={isLoadingProductOption}
      loadProductOptions={loadProductOptions}
      onConfigureCallout={onOpenCallout}
      onDaysCountChange={(value) =>
        onChange((currentForm) => ({
          ...currentForm,
          late_fee_window_data: {
            ...currentForm.late_fee_window_data,
            days_count: value,
          },
        }))
      }
      onProductChange={(selected) =>
        onChange((currentForm) => ({
          ...currentForm,
          late_fee_window_data: {
            ...currentForm.late_fee_window_data,
            product_id: selected ? selected.value : "-1",
          },
        }))
      }
      selectedProductOption={selectedProductOption}
      showProduct={false}
    />
  );
};

export default GracePeriodSection;
