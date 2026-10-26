import { __ } from "@wordpress/i18n";
import { Flex, FlexBlock, TextControl } from "@wordpress/components";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";

const GeneralSettingsSection = ({
  name,
  disabled,
  isLoading,
  loadingLabel,
  onNameChange,
}) => {
  if (isLoading) {
    return (
      <AdminLoadingSkeleton
        boxed={false}
        label={loadingLabel || __("Name", "wicket-memberships")}
        variant="singleField"
      />
    );
  }

  return (
    <Flex align="end" direction={["column", "row"]} gap={5} justify="start">
      <FlexBlock>
        <TextControl
          disabled={disabled}
          label={loadingLabel || __("Name", "wicket-memberships")}
          onChange={onNameChange}
          value={name}
        />
      </FlexBlock>
    </Flex>
  );
};

export default GeneralSettingsSection;
