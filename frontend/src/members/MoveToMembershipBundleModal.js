import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import styled from "styled-components";
import WicketModal from "../shared/components/WicketModal";
import ModalPostSelector from "../shared/components/ModalPostSelector";
import Alert from "../shared/components/Alert";
import { fetchMembershipBundles, moveIndividualMembership } from "../shared/services/api";

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
 * MoveToMembershipBundleModal
 *
 * Opens from the "Move to Another Bundle" button in MembershipBundleDetails.
 * Lets an admin select a target bundle and move the individual membership to it.
 * The source membership is cancelled and a new one is created in the target bundle.
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.membershipPostId   - WP post ID of the membership being moved.
 * @param {number}   props.sourceBundlePostId  - WP post ID of the current bundle (excluded from selector).
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess          - Called after a successful move; parent should refresh.
 */
const MoveToMembershipBundleModal = ({
  isOpen,
  membershipPostId,
  sourceBundlePostId,
  onRequestClose,
  onSuccess,
}) => {
  const [selectedBundle, setSelectedGroup] = useState(null);
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
    fetchMembershipBundles({ posts_per_page: 500 }).then((response) => {
      const groups = response?.results ?? response ?? [];
      return groups
        .filter(
          (g) =>
            VALID_STATUSES.includes(g.status?.slug) &&
            g.id !== sourceBundlePostId
        )
        .map((g) => ({ value: g.id, title: g.bundle_name, org_name: g.org_name ?? "" }));
    });

  const handleSubmit = async () => {
    if (!selectedBundle) return;
    setSubmitting(true);
    setError(null);
    try {
      await moveIndividualMembership(sourceBundlePostId, {
        membership_post_id:   membershipPostId,
        target_group_post_id: selectedBundle.value,
      });
      resetState();
      onSuccess(__("Member moved to new bundle.", "wicket-memberships"));
    } catch (err) {
      setError(err?.error ?? err?.message ?? __("An error occurred.", "wicket-memberships"));
      setSubmitting(false);
    }
  };

  return (
    <WicketModal
      isOpen={isOpen}
      title={__("Move to Another Bundle", "wicket-memberships")}
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
          "Please select the membership bundle this member is moving to.",
          "wicket-memberships"
        )}
      </p>

      <ModalPostSelector
        id="move_to_group_selector"
        label={__("Target Membership Bundle", "wicket-memberships")}
        modalTitle={__("Select Target Bundle", "wicket-memberships")}
        value={selectedBundle}
        onChange={setSelectedGroup}
        loadOptions={loadGroupOptions}
        columns={[
          { key: "title",    label: __("Bundle Name",   "wicket-memberships"), width: 250, searchable: true },
          { key: "org_name", label: __("Organization", "wicket-memberships"), width: 250, searchable: true },
        ]}
      />

      <ModalFooter>
        <Button variant="secondary" onClick={handleClose} disabled={submitting}>
          {__("Cancel", "wicket-memberships")}
        </Button>
        <Button
          variant="primary"
          onClick={handleSubmit}
          disabled={!selectedBundle || submitting}
          isBusy={submitting}
        >
          {__("Move to Bundle", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default MoveToMembershipBundleModal;
