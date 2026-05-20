import { __ } from "@wordpress/i18n";
import { FlexBlock } from "@wordpress/components";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import ModalPostSelector from "./ModalPostSelector";
import { LabelWpStyled, SelectWpStyled } from "../styled_elements";

const RENEWAL_TYPE_OPTIONS = [
  { label: __("Subscription", "wicket-memberships"), value: "subscription" },
  { label: __("Renewal Form Flow", "wicket-memberships"), value: "form_page" },
];

const RenewalTypeSection = ({
  renewalType,
  selectedPostOption,
  disabled,
  isLoading,
  isLoadingValue,
  onRenewalTypeChange,
  onRenewalFormPostIdChange,
  loadPostOptions,
  postTypeLabel = "Post",
}) => {
  if (isLoading) {
    return (
      <div style={{ marginTop: "15px" }}>
        <AdminLoadingSkeleton
          label={__("Renewal Type", "wicket-memberships")}
          variant="singleField"
        />
      </div>
    );
  }

  return (
    <>
      <div style={{ marginTop: "15px" }}>
        <FlexBlock>
          <LabelWpStyled htmlFor="renewal_type">
            {__("Renewal Type", "wicket-memberships")}
          </LabelWpStyled>
          <SelectWpStyled
            classNamePrefix="select"
            id="renewal_type"
            isDisabled={disabled}
            isSearchable={false}
            onChange={(selected) => onRenewalTypeChange(selected.value)}
            options={RENEWAL_TYPE_OPTIONS}
            value={RENEWAL_TYPE_OPTIONS.find((o) => o.value === renewalType) ?? null}
          />
        </FlexBlock>
      </div>

      {renewalType === "form_page" ? (
        <div style={{ marginTop: "15px" }}>
          <FlexBlock>
            <ModalPostSelector
              id="renewal_form_post"
              label={`${__("Renewal Form", "wicket-memberships")} ${postTypeLabel}`}
              placeholder={`${__("Select a", "wicket-memberships")} ${postTypeLabel}…`}
              modalTitle={`${__("Select a", "wicket-memberships")} ${postTypeLabel}`}
              value={selectedPostOption}
              onChange={onRenewalFormPostIdChange}
              disabled={disabled}
              isLoadingValue={isLoadingValue}
              loadOptions={loadPostOptions}
              columns={[
                { key: "title", label: __("Title", "wicket-memberships"), flex: 1, searchable: true },
              ]}
            />
          </FlexBlock>
        </div>
      ) : null}
    </>
  );
};

export default RenewalTypeSection;
