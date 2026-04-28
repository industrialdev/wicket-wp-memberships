import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button, SelectControl } from "@wordpress/components";
import styled from "styled-components";
import WicketModal from "../shared/components/WicketModal";
import Alert from "../shared/components/Alert";
import { removeMemberFromGroup } from "../shared/services/api";
import { formatDateWithTooltip } from "../shared/constants";

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;

const InfoNote = styled.p`
  margin-top: 8px;
  font-style: italic;
  color: #50575e;
  font-size: 13px;
`;

const MODE_CANCEL = "cancel";
const MODE_KEEP   = "keep_as_individual";

/**
 * RemoveFromMembershipGroupModal
 *
 * Opens from the "Remove from Group" button in MembershipGroupDetails.
 * Lets an admin either cancel the membership immediately or convert it to
 * a standalone individual membership that ends when the group ends.
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.membershipPostId  - WP post ID of the membership being removed.
 * @param {number}   props.groupPostId       - WP post ID of the membership group.
 * @param {string}   props.groupEndDate      - ISO 8601 group end date (for the info note).
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess         - Called after a successful removal; parent should refresh.
 */
const RemoveFromMembershipGroupModal = ({
  isOpen,
  membershipPostId,
  groupPostId,
  groupEndDate,
  onRequestClose,
  onSuccess,
}) => {
  const [mode, setMode]           = useState(MODE_CANCEL);
  const [error, setError]         = useState(null);
  const [submitting, setSubmitting] = useState(false);

  const resetState = () => {
    setMode(MODE_CANCEL);
    setError(null);
    setSubmitting(false);
  };

  const handleClose = () => {
    resetState();
    onRequestClose();
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    setError(null);
    try {
      await removeMemberFromGroup(groupPostId, {
        membership_post_id: membershipPostId,
        mode,
      });
      const message = mode === MODE_KEEP
        ? __("Membership removed from group and converted to individual membership.", "wicket-memberships")
        : __("Membership removed from group and cancelled.", "wicket-memberships");
      resetState();
      onSuccess(message);
    } catch (err) {
      setError(err?.error ?? err?.message ?? __("An error occurred.", "wicket-memberships"));
      setSubmitting(false);
    }
  };

  return (
    <WicketModal
      isOpen={isOpen}
      title={__("Remove from Group", "wicket-memberships")}
      onRequestClose={handleClose}
      shouldCloseOnClickOutside={false}
    >
      {error && (
        <Alert
          saveResult={{ type: "error", message: error }}
          onDismiss={() => setError(null)}
        />
      )}

      <p>{__("Do you want to cancel this individual membership or keep the member as an individual?", "wicket-memberships")}</p>

      <SelectControl
        label={__("Action", "wicket-memberships")}
        value={mode}
        options={[
          { label: __("Cancel membership immediately", "wicket-memberships"), value: MODE_CANCEL },
          { label: __("Keep as individual member", "wicket-memberships"), value: MODE_KEEP },
        ]}
        onChange={(val) => setMode(val)}
      />

      {mode === MODE_KEEP && groupEndDate && (
        <InfoNote>
          {__("A new individual membership will be created starting today and ending ", "wicket-memberships")}
          {formatDateWithTooltip(groupEndDate)}
          {"."}
        </InfoNote>
      )}

      <ModalFooter>
        <Button variant="secondary" onClick={handleClose} disabled={submitting}>
          {__("Cancel", "wicket-memberships")}
        </Button>
        <Button
          variant="primary"
          onClick={handleSubmit}
          disabled={submitting}
          isBusy={submitting}
        >
          {__("Confirm", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default RemoveFromMembershipGroupModal;
