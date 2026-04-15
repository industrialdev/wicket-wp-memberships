import { __ } from "@wordpress/i18n";
import { Button, Flex, FlexBlock, FlexItem, Icon, __experimentalHeading as Heading } from "@wordpress/components";
import styled from "styled-components";
import AdminLoadingSkeleton from "./AdminLoadingSkeleton";
import { BorderedBox, RecordTopInfo } from "../styled_elements";

const IntroBoxWrap = styled(BorderedBox)`
  background: #fff;
`;

/**
 * IntroBlock — shared header block for membership entity detail pages.
 *
 * Renders the entity name, action buttons, and a configurable row of
 * metadata fields. Data-agnostic — receives only flat props. Adapter
 * components in each page's components/ folder handle the mapping from
 * page-specific data shapes to these props.
 *
 * @param {object}   props
 * @param {string}   props.title       - Primary heading (e.g. group name, org name).
 * @param {Array}    props.infoFields  - Metadata items: [{ label, value }].
 *                                      Rendered in the RecordTopInfo bar.
 *                                      Different pages pass different fields.
 * @param {Array}    props.actions     - Action buttons: [{ label, href, target, icon }].
 * @param {boolean}  props.isLoading   - Show skeleton while data is pending.
 */
const IntroBlock = ({
  title = "",
  infoFields = [],
  actions = [],
  isLoading = false,
}) => {
  if (isLoading) {
    return (
      <AdminLoadingSkeleton
        label={__("Loading…", "wicket-memberships")}
        variant="introBlock"
      />
    );
  }

  return (
    <IntroBoxWrap>
      <Flex
        align="end"
        justify="start"
        gap={5}
        direction={["column", "row"]}
      >
        <FlexBlock>
          <Heading level={3}>{title}</Heading>
        </FlexBlock>

        {actions.length > 0 && (
          <FlexItem>
            <Flex gap={3}>
              {actions.map((action, index) => (
                <Button
                  key={index}
                  variant="secondary"
                  href={action.href || undefined}
                  target={action.target || undefined}
                  disabled={!action.href}
                >
                  {action.icon && (
                    <>
                      <Icon icon={action.icon} />
                      &nbsp;
                    </>
                  )}
                  {action.label}
                </Button>
              ))}
            </Flex>
          </FlexItem>
        )}
      </Flex>

      {infoFields.length > 0 && (
        <RecordTopInfo>
          <Flex
            align="end"
            justify="start"
            gap={10}
            direction={["column", "row"]}
          >
            {infoFields.map((field, index) => (
              <FlexItem key={index}>
                <strong>{field.label}:</strong> {field.value || "-"}
              </FlexItem>
            ))}
          </Flex>
        </RecordTopInfo>
      )}
    </IntroBoxWrap>
  );
};

export default IntroBlock;
