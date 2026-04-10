import SharedGracePeriodSection from "../../shared/components/GracePeriodSection";
import { findOptionByValue, getPrimaryErrorMessage } from "../utils/formUtils";

const GracePeriodSection = ({
  form,
  onChange,
  onOpenCallout,
  isEditing,
  isRecordReady,
  isDisabled,
  wcProductOptions,
  productsRequest,
  retryProducts,
}) => {
  const selectedProductId = form.late_fee_window_data.product_id;
  const selectedProductOption =
    findOptionByValue(wcProductOptions, selectedProductId) ||
    (selectedProductId && selectedProductId !== "-1"
      ? {
          label: `Saved Product | ID: ${selectedProductId}`,
          value: selectedProductId,
        }
      : null);

  return (
    <SharedGracePeriodSection
      daysCount={form.late_fee_window_data.days_count}
      disabled={isDisabled}
      isLoading={isEditing && !isRecordReady}
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
      productsRequest={{
        ...productsRequest,
        errorMessage: getPrimaryErrorMessage(
          productsRequest.error,
          "Products could not be loaded. You can retry this section without leaving the page.",
        ),
      }}
      retryProducts={retryProducts}
      selectedProductOption={selectedProductOption}
      wcProductOptions={wcProductOptions}
    />
  );
};

export default GracePeriodSection;
