import { __ } from "@wordpress/i18n";
import IntroBlock from "../../shared/components/IntroBlock";

/**
 * IntroBlockSection — group membership page adapter for IntroBlock.
 *
 * Reads group page data and maps it to the flat props that the shared
 * IntroBlock UI component expects. Contains no JSX of its own beyond
 * the IntroBlock invocation.
 *
 * @param {object}       props
 * @param {object|null}  props.pageData   - Data returned by fetchGroupEditPageInfo.
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

  // TODO: Replace org MDP link with the correct group membership MDP link once
  // group membership MDP sync is implemented — see TODO.md.
  const actions = [
    {
      label: __("View in MDP", "wicket-memberships"),
      href: pageData?.org?.mdp_link || "",
      target: "_blank",
      icon: "external",
    },
  ];

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
