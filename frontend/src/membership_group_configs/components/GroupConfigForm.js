import { useMemo, useState } from "react";
import { __ } from "@wordpress/i18n";
import { Flex, FlexItem } from "@wordpress/components";
import WicketButton from "../../shared/components/WicketButton";
import LocalizedCalloutModal from "../../shared/components/LocalizedCalloutModal";
import SeasonConfigModal from "../../shared/components/SeasonConfigModal";
import { ActionRow } from "../../shared/styled_elements";
import {
  createEmptySeason,
  normalizeCycleData,
} from "../utils/formUtils";
import ApprovalSection from "./ApprovalSection";
import CycleSection from "./CycleSection";
import GeneralSettingsSection from "./GeneralSettingsSection";
import GracePeriodSection from "./GracePeriodSection";
import RenewalTypeSection from "./RenewalTypeSection";
import RenewalWindowSection from "./RenewalWindowSection";

const GroupConfigForm = ({
  form,
  setForm,
  onSubmit,
  isSubmitting,
  groupConfigListUrl,
  postId,
  languageCodes,
  isRecordReady,
  isEditing,
  wpPostsOptions,
  wcProductOptions,
  loadPostOptions,
  loadProductOptions,
}) => {
  const [activeCalloutModal, setActiveCalloutModal] = useState(null);
  const [seasonModalState, setSeasonModalState] = useState({
    isOpen: false,
    seasonIndex: null,
  });

  const isInteractionLocked = isSubmitting || !isRecordReady;
  const cycleData = normalizeCycleData(form.cycle_data);
  const seasonIndex = seasonModalState.seasonIndex;
  const selectedSeason =
    seasonIndex === null
      ? createEmptySeason()
      : cycleData.calendar_items[seasonIndex] || createEmptySeason();

  const activeCalloutConfig = useMemo(() => {
    if (activeCalloutModal === "renewal_window_data") {
      return {
        title: __(
          "Renewal Window - Callout Configuration",
          "wicket-memberships",
        ),
        value: form.renewal_window_data,
        onSave: (calloutData) =>
          setForm((currentForm) => ({
            ...currentForm,
            renewal_window_data: {
              ...currentForm.renewal_window_data,
              locales: calloutData.locales,
            },
          })),
      };
    }

    if (activeCalloutModal === "late_fee_window_data") {
      return {
        title: __(
          "Grace Period Window - Callout Configuration",
          "wicket-memberships",
        ),
        value: form.late_fee_window_data,
        onSave: (calloutData) =>
          setForm((currentForm) => ({
            ...currentForm,
            late_fee_window_data: {
              ...currentForm.late_fee_window_data,
              locales: calloutData.locales,
            },
          })),
      };
    }

    if (activeCalloutModal === "approval_callout_data") {
      return {
        title: __("Approval Callout Configuration", "wicket-memberships"),
        value: form.group_config_data.approval_callout_data,
        onSave: (calloutData) =>
          setForm((currentForm) => ({
            ...currentForm,
            group_config_data: {
              ...currentForm.group_config_data,
              approval_callout_data: calloutData,
            },
          })),
      };
    }

    return null;
  }, [activeCalloutModal, form, languageCodes, setForm]);

  return (
    <>
      <form onSubmit={onSubmit}>
        <GeneralSettingsSection
          form={form}
          isDisabled={isInteractionLocked}
          isEditing={isEditing}
          isRecordReady={isRecordReady}
          onChange={setForm}
        />

        <RenewalWindowSection
          form={form}
          isDisabled={isInteractionLocked}
          isEditing={isEditing}
          isRecordReady={isRecordReady}
          onChange={setForm}
          onOpenCallout={() => setActiveCalloutModal("renewal_window_data")}
        />

        <GracePeriodSection
          form={form}
          isDisabled={isInteractionLocked}
          isEditing={isEditing}
          isRecordReady={isRecordReady}
          onChange={setForm}
          onOpenCallout={() => setActiveCalloutModal("late_fee_window_data")}
          wcProductOptions={wcProductOptions}
          loadProductOptions={loadProductOptions}
        />

        <CycleSection
          form={form}
          isDisabled={isInteractionLocked}
          isEditing={isEditing}
          isRecordReady={isRecordReady}
          onChange={setForm}
          onOpenSeasonModal={(nextSeasonIndex) =>
            setSeasonModalState({
              isOpen: true,
              seasonIndex: nextSeasonIndex,
            })
          }
        />

        <ApprovalSection
          form={form}
          isDisabled={isInteractionLocked}
          isEditing={isEditing}
          isRecordReady={isRecordReady}
          onChange={setForm}
          onOpenCallout={() => setActiveCalloutModal("approval_callout_data")}
        />

        <RenewalTypeSection
          form={form}
          isDisabled={isInteractionLocked}
          isEditing={isEditing}
          isRecordReady={isRecordReady}
          onChange={setForm}
          wpPostsOptions={wpPostsOptions}
          loadPostOptions={loadPostOptions}
        />

        <ActionRow>
          <Flex align="end" direction={["column", "row"]} gap={5} justify="end">
            <FlexItem>
              <WicketButton
                disabled={isInteractionLocked}
                isBusy={isSubmitting}
                type="submit"
                variant="primary"
              >
                {isSubmitting && __("Saving now...", "wicket-memberships")}
                {!isSubmitting &&
                  (postId
                    ? __("Update Group Configuration", "wicket-memberships")
                    : __("Save Group Configuration", "wicket-memberships"))}
              </WicketButton>
            </FlexItem>
            <FlexItem>
              <WicketButton href={groupConfigListUrl} variant="tertiary">
                {__("Cancel", "wicket-memberships")}
              </WicketButton>
            </FlexItem>
          </Flex>
        </ActionRow>
      </form>

      {activeCalloutConfig ? (
        <LocalizedCalloutModal
          isOpen={Boolean(activeCalloutConfig)}
          languageCodes={languageCodes}
          onClose={() => setActiveCalloutModal(null)}
          onSave={activeCalloutConfig.onSave}
          title={activeCalloutConfig.title}
          value={activeCalloutConfig.value}
        />
      ) : null}

      <SeasonConfigModal
        initialSeason={selectedSeason}
        isOpen={seasonModalState.isOpen}
        onClose={() =>
          setSeasonModalState({
            isOpen: false,
            seasonIndex: null,
          })
        }
        onDelete={() =>
          setForm((currentForm) => ({
            ...currentForm,
            cycle_data: {
              ...currentForm.cycle_data,
              calendar_items: normalizeCycleData(
                currentForm.cycle_data,
              ).calendar_items.filter((_, index) => index !== seasonIndex),
            },
          }))
        }
        onSave={(season) =>
          setForm((currentForm) => {
            const currentCycleData = normalizeCycleData(currentForm.cycle_data);

            if (seasonIndex === null) {
              return {
                ...currentForm,
                cycle_data: {
                  ...currentCycleData,
                  calendar_items: [...currentCycleData.calendar_items, season],
                },
              };
            }

            return {
              ...currentForm,
              cycle_data: {
                ...currentCycleData,
                calendar_items: currentCycleData.calendar_items.map(
                  (existingSeason, index) =>
                    index === seasonIndex ? season : existingSeason,
                ),
              },
            };
          })
        }
        seasonIndex={seasonIndex}
      />
    </>
  );
};

export default GroupConfigForm;
