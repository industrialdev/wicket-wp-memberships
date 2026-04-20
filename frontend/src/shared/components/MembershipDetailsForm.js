import { useState, useEffect, useCallback, useRef } from "react";
import { __ } from "@wordpress/i18n";
import {
  Button,
  FlexBlock,
  FlexItem,
  Flex,
  Icon,
  Tooltip,
} from "@wordpress/components";
import styled from "styled-components";
import { BorderedBox, AsyncSelectWpStyled, LabelWpStyled } from "../styled_elements";
import Alert from "./Alert";
import SwitchToButton from "./SwitchToButton";
import MembershipDatesSection, {
  isoToPickerDate,
  pickerDateToIso,
} from "./MembershipDatesSection";
import MembershipRenewalTypeSection from "./MembershipRenewalTypeSection";

const MarginedFlex = styled(Flex)`
  margin-top: 15px;
`;

/**
 * MembershipDetailsForm — combined form for membership dates, renewal type, and
 * optional group membership owner.
 *
 * Owns all local state for the date pickers, renewal type fields, and owner
 * selector. Submits everything in a single "Update Membership" button click.
 *
 * @param {object|null}  props.dates                - Initial date values: { starts_at, ends_at, expires_at }.
 * @param {string|null}  props.renewalType          - Current renewal type value.
 * @param {string|null}  [props.tierRenewalType]    - Renewal type inherited from tier/config (hint label).
 * @param {number|null}  [props.nextTierFormPageId] - Current form page ID (for form_flow).
 * @param {number|null}  [props.nextTierId]         - Current next tier ID (for sequential_logic).
 * @param {boolean}      [props.disabled]           - Disables all inputs and the save button.
 * @param {Function}     props.onSave               - Called with merged payload:
 *                                                    { membership_starts_at, membership_ends_at,
 *                                                      membership_expires_at, renewalType,
 *                                                      nextTierFormPageId, nextTierId }
 *                                                    Must return a Promise<{ success?, error? }>.
 * @param {Function}     [props.onSaved]            - Called after a successful save with updated values.
 * @param {object|null}  [props.ownerOption]        - Current owner as a select option: { label, value }.
 *                                                    When provided, renders the Group Membership Owner field.
 * @param {string|null}  [props.ownerMdpLink]        - URL to view the current owner in MDP.
 * @param {string|null}  [props.ownerSwitchToUrl]   - Impersonation URL for the current owner.
 * @param {Function}     [props.onLoadOwnerOptions] - `(inputValue, callback) => void` for the async owner select.
 * @param {Function}     [props.onOwnerSave]        - Called with selectedOption when saving owner.
 *                                                    Must return a Promise<{ success?, error? }>.
 * @param {Function}     [props.onOwnerUpdated]     - Called with new owner data after a successful owner save.
 * @param {Function}     [props.renderExtra]        - Optional. Called with no args, returns ReactNode rendered
 *                                                    between the owner field and the Update button.
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
  ownerOption: initialOwnerOption = null,
  ownerMdpLink = null,
  ownerSwitchToUrl = null,
  onLoadOwnerOptions = null,
  onOwnerSave = null,
  onOwnerUpdated = null,
  renderExtra = null,
}) => {
  const [startsAt, setStartsAt] = useState(null);
  const [endsAt, setEndsAt] = useState(null);
  const [expiresAt, setExpiresAt] = useState(null);
  const [renewalType, setRenewalType] = useState(initialRenewalType);
  const [nextTierFormPageId, setNextTierFormPageId] = useState(initialNextTierFormPageId);
  const [nextTierId, setNextTierId] = useState(initialNextTierId);
  const [isSaving, setIsSaving] = useState(false);
  const [saveResult, setSaveResult] = useState(null);
  const [selectedOwner, setSelectedOwner] = useState(initialOwnerOption);

  const ownerDebounceTimer = useRef(null);
  const debouncedLoadOwnerOptions = useCallback((inputValue, callback) => {
    if ( ownerDebounceTimer.current ) { clearTimeout(ownerDebounceTimer.current); }
    ownerDebounceTimer.current = setTimeout(() => {
      if ( onLoadOwnerOptions ) { onLoadOwnerOptions(inputValue, callback); }
    }, 300);
  }, [onLoadOwnerOptions]);

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

  useEffect(() => {
    setSelectedOwner(initialOwnerOption);
  }, [initialOwnerOption]);

  const handleRenewalTypeChange = ({ renewalType: rt, nextTierFormPageId: fp, nextTierId: ti }) => {
    if (rt !== undefined) setRenewalType(rt);
    if (fp !== undefined) setNextTierFormPageId(fp);
    if (ti !== undefined) setNextTierId(ti);
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    if (isSaving || !onSave) return;

    setIsSaving(true);
    setSaveResult(null);

    const rawStartsAt = pickerDateToIso(startsAt, "membership_starts_at");
    const rawEndsAt = pickerDateToIso(endsAt, "membership_ends_at");
    const rawExpiresAt = pickerDateToIso(expiresAt, "membership_expires_at");

    const payload = {};
    if (rawStartsAt) payload.membership_starts_at = rawStartsAt;
    if (rawEndsAt) payload.membership_ends_at = rawEndsAt;
    if (rawExpiresAt) payload.membership_expires_at = rawExpiresAt;

    const savePromises = [
      onSave({ ...payload, renewalType, nextTierFormPageId, nextTierId }),
    ];

    if ( onOwnerSave && selectedOwner ) {
      savePromises.push( onOwnerSave(selectedOwner) );
    }

    Promise.all(savePromises)
      .then(([membershipResponse, ownerResponse]) => {
        const membershipError = membershipResponse?.error;
        const ownerError = ownerResponse?.error;

        if ( membershipError || ownerError ) {
          const message = [ membershipError, ownerError ].filter(Boolean).join(' ');
          setSaveResult({ type: 'error', message });
          return;
        }

        setSaveResult({ type: 'success', message: membershipResponse?.success || __("Membership updated successfully.", "wicket-memberships") });

        if ( onSaved ) {
          onSaved({
            starts_at: rawStartsAt,
            ends_at: rawEndsAt,
            expires_at: rawExpiresAt,
            renewalType,
            nextTierFormPageId,
            nextTierId,
          });
        }

        if ( ownerResponse?.success && onOwnerUpdated && selectedOwner ) {
          onOwnerUpdated({ name: selectedOwner.label, uuid: selectedOwner.value });
        }
      })
      .catch((error) => {
        setSaveResult({ type: 'error', message: error?.error || __("An error occurred.", "wicket-memberships") });
      })
      .finally(() => {
        setIsSaving(false);
      });
  };

  return (
    <BorderedBox>
        <Alert
          saveResult={saveResult}
          onDismiss={() => setSaveResult(null)}
        />

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

        {(renderExtra || onLoadOwnerOptions) && (
          <MarginedFlex align="start" justify="start" gap={6} direction={["column", "row"]}>
            {renderExtra && (
              <FlexBlock>
                {renderExtra()}
              </FlexBlock>
            )}

            {onLoadOwnerOptions && (
              <FlexItem style={{ minWidth: "260px" }}>
                <Flex direction="column" gap={2}>
                  <Flex align="center" justify="space-between">
                    <FlexItem>
                      <LabelWpStyled style={{ height: '20px' }}>
                        {__('Group Membership Owner', 'wicket-memberships')}&nbsp;
                        <Tooltip text={__('Represents the person responsible for managing and renewing this Group Membership.', 'wicket-memberships')}>
                          <div><Icon icon="info" /></div>
                        </Tooltip>
                      </LabelWpStyled>
                    </FlexItem>
                    <FlexItem>
                      <SwitchToButton switchToUrl={ownerSwitchToUrl} />
                    </FlexItem>
                  </Flex>

                  <AsyncSelectWpStyled
                    id="membership_owner_id"
                    classNamePrefix="select"
                    name="membership_owner_uuid"
                    value={selectedOwner}
                    defaultOptions={initialOwnerOption ? [initialOwnerOption] : []}
                    loadOptions={debouncedLoadOwnerOptions}
                    isClearable={false}
                    isSearchable={true}
                    onChange={(selected) => setSelectedOwner(selected)}
                    isDisabled={disabled}
                  />

                  {ownerMdpLink && (
                    <Flex align="center" justify="start" gap={4}>
                      <FlexItem>
                        <Button
                          href={ownerMdpLink}
                          target="_blank"
                          variant="link"
                        >
                          {__('View in MDP', 'wicket-memberships')}
                        </Button>
                        &nbsp;<Icon icon="external" style={{ color: 'var(--wp-admin-theme-color)' }} />
                      </FlexItem>
                    </Flex>
                  )}
                </Flex>
              </FlexItem>
            )}
          </MarginedFlex>
        )}

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
