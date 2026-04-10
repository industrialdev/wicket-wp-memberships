import { __ } from "@wordpress/i18n";
import { FlexBlock, Notice } from "@wordpress/components";
import WicketButton from "./WicketButton";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import { LabelWpStyled, SelectWpStyled } from "../styled_elements";

const RENEWAL_TYPE_OPTIONS = [
  { label: __("Subscription", "wicket-memberships"), value: "subscription" },
  { label: __("Renewal Form Flow", "wicket-memberships"), value: "form_page" },
];

const RenewalTypeSection = ({
  renewalType,
  selectedPageOption,
  wpPagesOptions,
  pagesRequest,
  disabled,
  isLoading,
  onRenewalTypeChange,
  onRenewalFormPageIdChange,
  retryPages,
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
            isSearchable={true}
            onChange={(selected) => onRenewalTypeChange(selected.value)}
            options={RENEWAL_TYPE_OPTIONS}
            value={RENEWAL_TYPE_OPTIONS.find((o) => o.value === renewalType)}
          />
        </FlexBlock>
      </div>

      {renewalType === "form_page" ? (
        <div style={{ marginTop: "15px" }}>
          {pagesRequest.status === "error" ? (
            <Notice isDismissible={false} status="warning">
              <div>
                {pagesRequest.errorMessage ||
                  __(
                    "Pages could not be loaded. You can retry this section without leaving the page.",
                    "wicket-memberships",
                  )}
              </div>
              <div>
                <WicketButton onClick={retryPages} variant="link">
                  {__("Retry pages", "wicket-memberships")}
                </WicketButton>
              </div>
            </Notice>
          ) : null}

          <FlexBlock>
            <LabelWpStyled htmlFor="renewal_form_page">
              {__("Renewal Form Page", "wicket-memberships")}
            </LabelWpStyled>
            <SelectWpStyled
              classNamePrefix="select"
              id="renewal_form_page"
              isDisabled={disabled || pagesRequest.status === "error"}
              isLoading={pagesRequest.status === "loading"}
              isSearchable={true}
              onChange={onRenewalFormPageIdChange}
              options={wpPagesOptions}
              placeholder={__("Select a page…", "wicket-memberships")}
              value={selectedPageOption}
            />
          </FlexBlock>
        </div>
      ) : null}
    </>
  );
};

export default RenewalTypeSection;
