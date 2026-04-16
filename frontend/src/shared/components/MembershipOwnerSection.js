import { useState, useCallback, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { Button, Flex, FlexBlock, FlexItem, Icon, Tooltip } from "@wordpress/components";
import { AsyncSelectWpStyled, BorderedBox, LabelWpStyled } from "../styled_elements";
import Alert from "./Alert";
import SwitchToButton from "./SwitchToButton";

/**
 * MembershipOwnerSection — displays and allows changing the owner of a membership.
 *
 * Data-agnostic: receives only flat props. Callers are responsible for mapping
 * their page-specific data shapes to these props and for injecting the
 * `onSave` function that calls the appropriate API endpoint.
 *
 * @param {string}        props.title        - Label shown above the select.
 * @param {string}        props.tooltipText  - Text shown in the info tooltip.
 * @param {object|null}   props.ownerOption  - Current owner as a select option: { label, value }.
 * @param {string}        [props.mdpLink]    - URL to view the current owner in MDP.
 * @param {string}        [props.switchToUrl] - Impersonation URL for the current owner (from server).
 * @param {Function}      props.onLoadOptions - `(inputValue, callback) => void` for AsyncSelect.
 * @param {Function}      props.onSave       - `(selectedOption) => Promise<{ success?, error? }>`.
 *                                             Resolved value drives the inline feedback message.
 */
const MembershipOwnerSection = ({
  title,
  tooltipText,
  ownerOption = null,
  mdpLink = null,
  switchToUrl = null,
  onLoadOptions,
  onSave,
}) => {
  const [selectedOption, setSelectedOption] = useState(ownerOption);
  const [isSaving, setIsSaving]             = useState(false);
  const [saveResult, setSaveResult]         = useState(null); // { type: 'success'|'error', message }

  const debounceTimer = useRef(null);
  const debouncedLoadOptions = useCallback((inputValue, callback) => {
    if ( debounceTimer.current ) { clearTimeout(debounceTimer.current); }
    debounceTimer.current = setTimeout(() => {
      onLoadOptions(inputValue, callback);
    }, 300);
  }, [onLoadOptions]);

  const handleChange = (selected) => {
    setSelectedOption(selected);
    setSaveResult(null);
  };

  const handleSave = () => {
    if ( ! selectedOption || ! onSave ) { return; }

    setIsSaving(true);
    setSaveResult(null);

    onSave(selectedOption)
      .then((response) => {
        if ( response?.success ) {
          setSaveResult({ type: 'success', message: response.success });
        } else {
          setSaveResult({ type: 'error', message: response?.error ?? __('An error occurred.', 'wicket-memberships') });
        }
      })
      .catch((error) => {
        console.error('[MembershipOwnerSection] handleSave error', error);
        const message = error?.error ?? error?.message ?? __('An error occurred.', 'wicket-memberships');
        setSaveResult({ type: 'error', message });
      })
      .finally(() => {
        setIsSaving(false);
      });
  };

  return (
    <BorderedBox>
      <Flex align="start" justify="start" gap={6} direction={["column", "row"]}>
        <FlexBlock>
          <LabelWpStyled style={{ height: '20px' }}>
            {title}&nbsp;
            <Tooltip text={tooltipText}>
              <div><Icon icon="info" /></div>
            </Tooltip>
          </LabelWpStyled>
          
          <Alert
            saveResult={saveResult}
            onDismiss={() => setSaveResult(null)}
          />

          <AsyncSelectWpStyled
            id="membership_owner_id"
            classNamePrefix="select"
            name="membership_owner_uuid"
            value={selectedOption}
            defaultOptions={ownerOption ? [ownerOption] : []}
            loadOptions={debouncedLoadOptions}
            isClearable={false}
            isSearchable={true}
            onChange={handleChange}
          />

          <Flex align="center" justify="start" gap={4} style={{ marginTop: '10px' }}>
            <FlexItem>
              <Button
                variant="secondary"
                onClick={handleSave}
                disabled={isSaving || ! selectedOption}
                isBusy={isSaving}
              >
                {__('Save Owner', 'wicket-memberships')}
              </Button>
            </FlexItem>

            <FlexItem>
              <SwitchToButton switchToUrl={switchToUrl} />
            </FlexItem>

            {mdpLink && (
              <FlexItem>
                <Button
                  href={mdpLink}
                  target="_blank"
                  variant="link"
                >
                  {__('View in MDP', 'wicket-memberships')}
                </Button>
                &nbsp;<Icon icon="external" style={{ color: 'var(--wp-admin-theme-color)' }} />
              </FlexItem>
            )}
          </Flex>

        </FlexBlock>
      </Flex>
    </BorderedBox>
  );
};

export default MembershipOwnerSection;
