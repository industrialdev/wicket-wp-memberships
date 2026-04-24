import { useState, useEffect } from "react";
import { __, sprintf } from "@wordpress/i18n";
import {
  Button,
  Flex,
  FlexBlock,
  FlexItem,
  Notice,
  SelectControl,
  TextControl,
} from "@wordpress/components";
import styled from "styled-components";
import { BorderedBox, ErrorsRow, ActionRow } from "../styled_elements";
import WicketModal from "./WicketModal";

const MarginedFlex = styled(Flex)`
  margin-top: 15px;
`;

/**
 * MembershipStatusSection — status display, management button, and transition
 * modal for a membership record.
 *
 * Renders the current status as a read-only field with a "Manage Status" button.
 * On click, opens an inline modal that loads available status transitions and
 * submits the change.
 *
 * Data-agnostic: receives only flat props. API calls are injected via
 * `fetchStatuses` and `updateStatus` so this component works for both
 * individual and membership group pages without modification.
 *
 * @param {number|string|null}  props.postId          - WP post ID of the record.
 * @param {string}              props.currentStatus   - Current status label/slug.
 * @param {Function}            props.fetchStatuses   - `(postId) => Promise<statusMap>`.
 *                                                       statusMap: { [slug]: { name, slug } }.
 * @param {Function}            props.updateStatus    - `(postId, newStatus) => Promise<response>`.
 *                                                       response: { success?, error? }.
 * @param {Function}            [props.onStatusUpdated] - Called with (postId, newStatus, responseData)
 *                                                         after a successful change.
 */
const MembershipStatusSection = ({
  postId,
  currentStatus = "",
  fetchStatuses,
  updateStatus,
  onStatusUpdated,
}) => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [newStatus, setNewStatus] = useState("");
  const [availableStatuses, setAvailableStatuses] = useState([]);
  const [errors, setErrors] = useState([]);
  const [statusChangeConfirmed, setStatusChangeConfirmed] = useState(false);

  useEffect(() => {
    if (isModalOpen && postId && fetchStatuses) {
      setNewStatus("");
      setErrors([]);
      setStatusChangeConfirmed(false);
      fetchStatuses(postId)
        .then((response) => setAvailableStatuses(response))
        .catch(console.error);
    }
  }, [isModalOpen, postId, fetchStatuses]);

  const statusRequiresConfirmation = (status) =>
    ["cancelled", "expired"].includes(status);

  const getStatusOptions = () => {
    const options = Object.keys(availableStatuses).map((status) => ({
      label: availableStatuses[status].name,
      value: availableStatuses[status].slug,
    }));
    options.unshift({ label: __("Select Status", "wicket-memberships"), value: "" });
    return options;
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    if (!updateStatus) return;
    updateStatus(postId, newStatus)
      .then((response) => {
        if (response.success) {
          if (onStatusUpdated) {
            onStatusUpdated(postId, newStatus, response.response);
          }
          setIsModalOpen(false);
        } else {
          setErrors([response.error]);
        }
      })
      .catch(console.error);
  };

  const handleStatusChange = (value) => {
    setNewStatus(value);
    setStatusChangeConfirmed(false);
  };

  return (
    <>
      <BorderedBox style={{ flex: "1" }}>
        <Flex align="end" justify="start" gap={6} direction={["column", "row"]}>
          <FlexBlock style={{ flex: "1" }}>
            <TextControl
              label={__("Membership Status", "wicket-memberships")}
              disabled={true}
              __nextHasNoMarginBottom={true}
              value={currentStatus}
            />
          </FlexBlock>
          <FlexItem>
            <Button
              variant="secondary"
              disabled={!postId}
              onClick={() => setIsModalOpen(true)}
            >
              {__("Manage Status", "wicket-memberships")}
            </Button>
          </FlexItem>
        </Flex>
      </BorderedBox>

      <WicketModal
        isOpen={isModalOpen}
        title={__("Change Status", "wicket-memberships")}
        onRequestClose={() => setIsModalOpen(false)}
      >
        <form onSubmit={handleSubmit}>
          {errors.length > 0 && (
            <ErrorsRow>
              {errors.map((errorMessage, index) => (
                <Notice isDismissible={false} key={index} status="warning">
                  {errorMessage}
                </Notice>
              ))}
            </ErrorsRow>
          )}

          <MarginedFlex align="end" justify="start" gap={5} direction={["column", "row"]}>
            <FlexBlock>
              <TextControl
                label={__("Current Status", "wicket-memberships")}
                disabled={true}
                style={{ backgroundColor: "#F6F7F7" }}
                value={currentStatus}
                __nextHasNoMarginBottom={true}
              />
            </FlexBlock>
            <FlexItem>
              <div style={{ fontWeight: 500, marginBottom: "5px" }}>
                {__("To", "wicket-memberships")}
              </div>
            </FlexItem>
            <FlexBlock>
              <SelectControl
                label={__("New Status", "wicket-memberships")}
                value={newStatus}
                onChange={handleStatusChange}
                options={getStatusOptions()}
                __nextHasNoMarginBottom={true}
              />
            </FlexBlock>
          </MarginedFlex>

          {statusRequiresConfirmation(newStatus) && !statusChangeConfirmed && (
            <MarginedFlex direction="column" gap={3}>
              <Notice isDismissible={false} status="warning">
                {sprintf(
                  /* translators: %s: status name (cancelled or expired) */
                  __(
                    "Once a membership status is changed to '%s' it cannot be undone. Are you certain you would like to proceed?",
                    "wicket-memberships",
                  ),
                  newStatus,
                )}
              </Notice>
              <FlexItem>
                <Button
                  variant="secondary"
                  isDestructive
                  onClick={() => setStatusChangeConfirmed(true)}
                >
                  {__("Confirm Action", "wicket-memberships")}
                </Button>
              </FlexItem>
            </MarginedFlex>
          )}

          <ActionRow>
            <Flex align="end" gap={5} direction={["column", "row"]}>
              <FlexItem>
                <Button
                  variant="primary"
                  type="submit"
                  disabled={
                    newStatus === "" ||
                    (statusRequiresConfirmation(newStatus) && !statusChangeConfirmed)
                  }
                >
                  {__("Update Status", "wicket-memberships")}
                </Button>
              </FlexItem>
            </Flex>
          </ActionRow>
        </form>
      </WicketModal>
    </>
  );
};

export default MembershipStatusSection;
