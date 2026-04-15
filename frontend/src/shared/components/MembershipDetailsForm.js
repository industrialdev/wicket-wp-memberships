import { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import {
  Button,
  FlexItem,
  Flex,
  Notice,
} from "@wordpress/components";
import styled from "styled-components";
import { BorderedBox, ErrorsRow } from "../styled_elements";
import MembershipDatesSection, {
  isoToPickerDate,
  pickerDateToIso,
} from "./MembershipDatesSection";
import MembershipRenewalTypeSection from "./MembershipRenewalTypeSection";

const MarginedFlex = styled(Flex)`
  margin-top: 15px;
`;

/**
 * MembershipDetailsForm — combined form for membership dates and renewal type.
 *
 * Owns all local state for the date pickers and renewal type fields. Submits
 * everything in a single "Update Membership" button click by calling `onSave`
 * with the merged payload.
 *
 * @param {object|null}  props.dates              - Initial date values: { starts_at, ends_at, expires_at }.
 * @param {string|null}  props.renewalType        - Current renewal type value.
 * @param {string|null}  [props.tierRenewalType]  - Renewal type inherited from tier/config (hint label).
 * @param {number|null}  [props.nextTierFormPageId] - Current form page ID (for form_flow).
 * @param {number|null}  [props.nextTierId]       - Current next tier ID (for sequential_logic).
 * @param {boolean}      [props.disabled]         - Disables all inputs and the save button.
 * @param {Function}     props.onSave             - Called with merged payload:
 *                                                  { membership_starts_at, membership_ends_at,
 *                                                    membership_expires_at, renewalType,
 *                                                    nextTierFormPageId, nextTierId }
 *                                                  Must return a Promise<{ success?, error? }>.
 * @param {Function}     [props.onSaved]          - Called after a successful save with updated values.
 */
const MembershipDetailsForm = ({
  dates = null,
  renewalType: initialRenewalType = null,
  tierRenewalType = null,
  nextTierFormPageId: initialNextTierFormPageId = null,
  nextTierId: initialNextTierId = null,
  disabled = false,
  onSave,
  onSaved,
}) => {
  const [startsAt, setStartsAt] = useState(null);
  const [endsAt, setEndsAt] = useState(null);
  const [expiresAt, setExpiresAt] = useState(null);
  const [renewalType, setRenewalType] = useState(initialRenewalType);
  const [nextTierFormPageId, setNextTierFormPageId] = useState(initialNextTierFormPageId);
  const [nextTierId, setNextTierId] = useState(initialNextTierId);
  const [isSaving, setIsSaving] = useState(false);
  const [saveResult, setSaveResult] = useState("");

  useEffect(() => {
    if (dates) {
      setStartsAt(isoToPickerDate(dates.starts_at));
      setEndsAt(isoToPickerDate(dates.ends_at));
      setExpiresAt(isoToPickerDate(dates.expires_at));
    }
  }, [dates]);

  useEffect(() => {
    setRenewalType(initialRenewalType);
  }, [initialRenewalType]);

  useEffect(() => {
    setNextTierFormPageId(initialNextTierFormPageId);
  }, [initialNextTierFormPageId]);

  useEffect(() => {
    setNextTierId(initialNextTierId);
  }, [initialNextTierId]);

  const handleRenewalTypeChange = ({ renewalType: rt, nextTierFormPageId: fp, nextTierId: ti }) => {
    if (rt !== undefined) setRenewalType(rt);
    if (fp !== undefined) setNextTierFormPageId(fp);
    if (ti !== undefined) setNextTierId(ti);
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    if (isSaving || !onSave) return;

    setIsSaving(true);
    setSaveResult("");

    const rawStartsAt = pickerDateToIso(startsAt, "membership_starts_at");
    const rawEndsAt = pickerDateToIso(endsAt, "membership_ends_at");
    const rawExpiresAt = pickerDateToIso(expiresAt, "membership_expires_at");

    const payload = {};
    if (rawStartsAt) payload.membership_starts_at = rawStartsAt;
    if (rawEndsAt) payload.membership_ends_at = rawEndsAt;
    if (rawExpiresAt) payload.membership_expires_at = rawExpiresAt;

    onSave({ ...payload, renewalType, nextTierFormPageId, nextTierId })
      .then((response) => {
        const message = response?.success || response?.error || "";
        setSaveResult(message);
        if (response?.success && onSaved) {
          onSaved({
            starts_at: rawStartsAt,
            ends_at: rawEndsAt,
            expires_at: rawExpiresAt,
            renewalType,
            nextTierFormPageId,
            nextTierId,
          });
        }
      })
      .catch((error) => {
        setSaveResult(error?.message || __("An error occurred.", "wicket-memberships"));
      })
      .finally(() => {
        setIsSaving(false);
      });
  };

  return (
    <BorderedBox>
      {saveResult.length > 0 && (
        <ErrorsRow>
          <Notice
            isDismissible={true}
            onDismiss={() => setSaveResult("")}
            status="info"
          >
            {saveResult}
          </Notice>
        </ErrorsRow>
      )}

      <form onSubmit={handleSubmit}>
        <MembershipDatesSection
          startsAt={startsAt}
          endsAt={endsAt}
          expiresAt={expiresAt}
          disabled={disabled}
          onStartsAtChange={setStartsAt}
          onEndsAtChange={setEndsAt}
          onExpiresAtChange={setExpiresAt}
        />

        <MembershipRenewalTypeSection
          renewalType={renewalType}
          tierRenewalType={tierRenewalType}
          nextTierFormPageId={nextTierFormPageId}
          nextTierId={nextTierId}
          disabled={disabled}
          onChange={handleRenewalTypeChange}
        />

        <MarginedFlex>
          <FlexItem>
            <Button
              variant="primary"
              type="submit"
              disabled={isSaving || disabled}
              isBusy={isSaving}
            >
              {__("Update Membership", "wicket-memberships")}
            </Button>
          </FlexItem>
        </MarginedFlex>
      </form>
    </BorderedBox>
  );
};

export default MembershipDetailsForm;
