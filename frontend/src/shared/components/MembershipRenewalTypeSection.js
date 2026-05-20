import { useCallback } from "react";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import { Flex, FlexBlock, FlexItem } from "@wordpress/components";
import he from "he";
import styled from "styled-components";
import ModalPostSelector from "./ModalPostSelector";
import { LabelWpStyled, SelectWpStyled } from "../styled_elements";
import { API_URL } from "../constants";
import { fetchTiers } from "../services/api";

const MarginedFlex = styled(Flex)`
  margin-top: 15px;
`;

const RENEWAL_TYPE_OPTIONS = [
  { label: __("Inherited from Tier", "wicket-memberships"), value: "inherited" },
  { label: __("Sequential Logic", "wicket-memberships"), value: "sequential_logic" },
  { label: __("Renewal Form Flow", "wicket-memberships"), value: "form_flow" },
  { label: __("Subscription Renewal", "wicket-memberships"), value: "subscription" },
  { label: __("Current Tier", "wicket-memberships"), value: "current_tier" },
];

// Renewal type options available for membership group records.
// Groups support only subscription and form_flow (form_page in config terminology).
export const GROUP_RENEWAL_TYPE_OPTIONS = ["subscription", "form_flow"];

/**
 * MembershipRenewalTypeSection — renewal type selector with conditional
 * sub-fields, matching the renewal type UI on individual/org membership pages.
 *
 * Manages its own pages/tiers option lists. Calls `onChange` with a flat
 * patch object whenever any field changes so the caller can merge it into
 * its save payload.
 *
 * @param {string|null}  props.renewalType            - Current renewal type value.
 * @param {string|null}  [props.tierRenewalType]      - Renewal type inherited from the tier/config,
 *                                                      shown as a hint when renewalType === 'inherited'.
 * @param {number|null}  [props.nextTierFormPageId]   - Current form page ID (used when form_flow).
 * @param {number|null}  [props.nextTierId]           - Current next tier ID (used when sequential_logic).
 * @param {boolean}      [props.disabled]             - Disables all inputs.
 * @param {string[]|null} [props.allowedRenewalTypes] - When provided, only these option values are shown.
 *                                                      Use GROUP_RENEWAL_TYPE_OPTIONS for group memberships.
 * @param {Function}     props.onChange               - Called with a patch object on any field change:
 *                                                      { renewalType?, nextTierFormPageId?, nextTierId? }
 */
const MembershipRenewalTypeSection = ({
  renewalType = null,
  tierRenewalType = null,
  nextTierFormPageId = null,
  nextTierId = null,
  disabled = false,
  allowedRenewalTypes = null,
  onChange,
}) => {
  const visibleOptions = allowedRenewalTypes
    ? RENEWAL_TYPE_OPTIONS.filter((o) => allowedRenewalTypes.includes(o.value))
    : RENEWAL_TYPE_OPTIONS;
  const loadPageOptions = useCallback(() => {
    return apiFetch({
      path: addQueryArgs(`${API_URL}/pages`, {
        _fields: "id,title",
        status: "publish",
        per_page: -1,
      }),
    }).then((posts) =>
      posts.map((post) => ({
        value: post.id,
        title: he.decode(post.title.rendered),
      }))
    );
  }, []);

  const loadTierOptions = useCallback(() => {
    return fetchTiers().then((tiers) =>
      tiers.map((tier) => ({
        value: tier.id,
        title: he.decode(tier.title.rendered),
      }))
    );
  }, []);

  const CONFIG_RENEWAL_TYPE_LABELS = {
    subscription: __("Subscription", "wicket-memberships"),
    form_page: __("Renewal Form Flow", "wicket-memberships"),
  };

  const tierRenewalTypeLabel =
    RENEWAL_TYPE_OPTIONS.find((o) => o.value === tierRenewalType)?.label ??
    CONFIG_RENEWAL_TYPE_LABELS[tierRenewalType] ??
    tierRenewalType;

  const selectedPageOption = nextTierFormPageId
    ? { value: nextTierFormPageId, title: String(nextTierFormPageId) }
    : null;

  const selectedTierOption = nextTierId
    ? { value: nextTierId, title: String(nextTierId) }
    : null;

  return (
    <>
      <MarginedFlex>
        <FlexBlock>
          <LabelWpStyled htmlFor="renewal_type">
            {__("Renewal Type", "wicket-memberships")}
          </LabelWpStyled>
          <SelectWpStyled
            classNamePrefix="select"
            id="renewal_type"
            isDisabled={disabled}
            isSearchable={false}
            onChange={(selected) => onChange({ renewalType: selected.value })}
            options={visibleOptions}
            value={visibleOptions.find((o) => o.value === renewalType) ?? null}
          />
          {renewalType === "inherited" && tierRenewalType && (
            <FlexItem style={{ marginTop: "5px" }}>
              <small>
                {__("Inherited Renewal Type: ", "wicket-memberships")}
                <strong>{tierRenewalTypeLabel}</strong>
              </small>
            </FlexItem>
          )}
        </FlexBlock>
      </MarginedFlex>

      {renewalType === "form_flow" && (
        <MarginedFlex>
          <FlexBlock>
            <ModalPostSelector
              id="next_tier_form_page_id"
              label={__("Form Page", "wicket-memberships")}
              placeholder={__("Select a page…", "wicket-memberships")}
              modalTitle={__("Select Form Page", "wicket-memberships")}
              value={selectedPageOption}
              onChange={(selected) => onChange({ nextTierFormPageId: selected ? selected.value : null })}
              disabled={disabled}
              loadOptions={loadPageOptions}
              columns={[
                { key: "title", label: __("Title", "wicket-memberships"), flex: 1, searchable: true },
              ]}
            />
          </FlexBlock>
        </MarginedFlex>
      )}

      {renewalType === "sequential_logic" && (
        <MarginedFlex>
          <FlexBlock>
            <ModalPostSelector
              id="next_tier_id"
              label={__("Sequential Tier", "wicket-memberships")}
              placeholder={__("Select a tier…", "wicket-memberships")}
              modalTitle={__("Select Sequential Tier", "wicket-memberships")}
              value={selectedTierOption}
              onChange={(selected) => onChange({ nextTierId: selected ? selected.value : null })}
              disabled={disabled}
              loadOptions={loadTierOptions}
              columns={[
                { key: "title", label: __("Tier Name", "wicket-memberships"), flex: 1, searchable: true },
              ]}
            />
          </FlexBlock>
        </MarginedFlex>
      )}
    </>
  );
};

export default MembershipRenewalTypeSection;
