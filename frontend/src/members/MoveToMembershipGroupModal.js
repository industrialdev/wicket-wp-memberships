import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import styled from "styled-components";
import WicketModal from "../shared/components/WicketModal";
import ModalPostSelector from "../shared/components/ModalPostSelector";
import Alert from "../shared/components/Alert";
import { fetchMembershipGroups, moveIndividualMembership } from "../shared/services/api";

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;

const VALID_STATUSES = ["pending", "active", "delayed"];

/**
 * MoveToMembershipGroupModal
 *
 * Opens from the "Move to Another Group" button in MembershipGroupDetails.
 * Lets an admin select a target group and move the individual membership to it.
 * The source membership is cancelled and a new one is created in the target group.
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.membershipPostId   - WP post ID of the membership being moved.
 * @param {number}   props.sourceGroupPostId  - WP post ID of the current group (excluded from selector).
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess          - Called after a successful move; parent should refresh.
 */
const MoveToMembershipGroupModal = ({
  isOpen,
  membershipPostId,
  sourceGroupPostId,
  onRequestClose,
  onSuccess,
}) => {
  const [selectedGroup, setSelectedGroup] = useState(null);
  const [error, setError]                 = useState(null);
  const [submitting, setSubmitting]       = useState(false);

  const resetState = () => {
    setSelectedGroup(null);
    setError(null);
    setSubmitting(false);
  };

  const handleClose = () => {
    resetState();
    onRequestClose();
  };

  const loadGroupOptions = () =>
    fetchMembershipGroups({ posts_per_page: 500 }).then((response) => {
      const groups = response?.results ?? response ?? [];
      return groups
        .filter(
          (g) =>
            VALID_STATUSES.includes(g.status?.slug) &&
            g.id !== sourceGroupPostId
        )
        .map((g) => ({ value: g.id, title: g.group_name }));
    });

  const handleSubmit = async () => {
    if (!selectedGroup) return;
    setSubmitting(true);
    setError(null);
    try {
      await moveIndividualMembership(sourceGroupPostId, {
        membership_post_id:   membershipPostId,
        target_group_post_id: selectedGroup.value,
      });
      resetState();
      onSuccess(__("Member moved to new group.", "wicket-memberships"));
    } catch (err) {
      setError(err?.error ?? err?.message ?? __("An error occurred.", "wicket-memberships"));
      setSubmitting(false);
    }
  };

  return (
    <WicketModal
      isOpen={isOpen}
      title={__("Move to Another Group", "wicket-memberships")}
      onRequestClose={handleClose}
      shouldCloseOnClickOutside={false}
    >
      {error && (
        <Alert
          saveResult={{ type: "error", message: error }}
          onDismiss={() => setError(null)}
        />
      )}

      <p>
        {__(
          "Please select the membership group this member is moving to.",
          "wicket-memberships"
        )}
      </p>

      <ModalPostSelector
        id="move_to_group_selector"
        label={__("Target Membership Group", "wicket-memberships")}
        modalTitle={__("Select Target Group", "wicket-memberships")}
        value={selectedGroup}
        onChange={setSelectedGroup}
        loadOptions={loadGroupOptions}
        columnLabels={{ name: __("Group Name", "wicket-memberships") }}
      />

      <ModalFooter>
        <Button variant="secondary" onClick={handleClose} disabled={submitting}>
          {__("Cancel", "wicket-memberships")}
        </Button>
        <Button
          variant="primary"
          onClick={handleSubmit}
          disabled={!selectedGroup || submitting}
          isBusy={submitting}
        >
          {__("Move to Group", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default MoveToMembershipGroupModal;
