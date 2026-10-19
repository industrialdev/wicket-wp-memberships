import { __ } from "@wordpress/i18n";
import MultiSelectField from "../../shared/components/MultiSelectField";
import { BorderedBox, SectionTitle } from "../../shared/styled_elements";

const EligibleTiersSection = ({
  eligibleTierIds,
  tierOptions,
  isDisabled,
  isLoading,
  isLoadingOptions,
  onChange,
}) => (
  <BorderedBox>
    <SectionTitle>{__("Eligibility", "wicket-memberships")}</SectionTitle>
    <MultiSelectField
      helpText={__(
        "If no tiers are selected, all membership tiers are eligible for this configuration.",
        "wicket-memberships",
      )}
      id="eligible_tier_ids"
      isDisabled={isDisabled}
      isLoading={isLoading}
      isLoadingOptions={isLoadingOptions}
      label={__("Eligible Membership Tiers", "wicket-memberships")}
      onChange={onChange}
      options={tierOptions}
      placeholder={__("All membership tiers eligible…", "wicket-memberships")}
      selectedValues={eligibleTierIds}
    />
  </BorderedBox>
);

export default EligibleTiersSection;
