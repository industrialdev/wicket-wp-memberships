import { __ } from "@wordpress/i18n";
import IntroBlock from "../../shared/components/IntroBlock";

/**
 * IntroBlockSection — membership bundle page adapter for IntroBlock.
 *
 * Reads bundle page data and maps it to the flat props that the shared
 * IntroBlock UI component expects. Contains no JSX of its own beyond
 * the IntroBlock invocation.
 *
 * @param {object}       props
 * @param {object|null}  props.pageData   - Data returned by fetchBundleEditPageInfo.
 * @param {boolean}      props.isLoading  - Pass-through to IntroBlock.
 */
const IntroBlockSection = ({ pageData, isLoading }) => {
  const title = pageData?.title || "";

  const infoFields = [
    {
      label: __("Organization", "wicket-memberships"),
      value: pageData?.org?.name || "",
    },
  ];

  const actions = pageData?.bundle_mdp_link
    ? [
        {
          label: __("View in MDP", "wicket-memberships"),
          href: pageData.bundle_mdp_link,
          target: "_blank",
          icon: "external",
        },
      ]
    : [];

  return (
    <IntroBlock
      title={title}
      infoFields={infoFields}
      actions={actions}
      isLoading={isLoading}
    />
  );
};

export default IntroBlockSection;
