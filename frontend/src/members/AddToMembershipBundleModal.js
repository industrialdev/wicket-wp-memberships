import { useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import WicketModal from "../shared/components/WicketModal";
import ModalPostSelector from "../shared/components/ModalPostSelector";
import Alert from "../shared/components/Alert";
import styled from "styled-components";
import { fetchMembershipBundles } from "../shared/services/api";
import { addMemberToBundle } from "../shared/services/api";

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;

/**
 * AddToMembershipBundleModal — Flow A
 *
 * Opens from the Membership Actions dropdown on the individual membership page.
 * Lets an admin add an existing membership to a membership bundle.
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.membershipPostId   - WP post ID of the existing membership.
 * @param {number}   props.tierPostId         - WP post ID of the membership tier (from membership meta).
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess          - Called after a successful add; parent should refresh.
 */
const AddToMembershipBundleModal = ({
  isOpen,
  membershipPostId,
  tierPostId,
  onRequestClose,
  onSuccess,
}) => {
  const [selectedBundle, setSelectedGroup] = useState(null);
  const [error, setError] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  const resetState = () => {
    setSelectedGroup(null);
    setError(null);
    setSubmitting(false);
  };

  const handleClose = () => {
    resetState();
    onRequestClose();
  };

  const VALID_STATUSES = ["pending", "active", "delayed"];

  const loadGroupOptions = () =>
    fetchMembershipBundles({ posts_per_page: 500 }).then((response) => {
      const groups = response?.results ?? response ?? [];
      return groups
        .filter((bundle) => VALID_STATUSES.includes(bundle.status?.slug))
        .map((bundle) => ({
          value: bundle.post_id,
          title: bundle.bundle_name,
          org_name: bundle.org_name ?? "",
        }));
    });

  const handleSubmit = async () => {
    if (!selectedBundle) return;
    setSubmitting(true);
    setError(null);
    try {
      await addMemberToBundle(selectedBundle.value, {
        mode: "existing",
        existing_membership_post_id: membershipPostId,
        tier_post_id: tierPostId,
      });
      resetState();
      onSuccess();
    } catch (err) {
      setError(err?.error ?? err?.message ?? __("An error occurred.", "wicket-memberships"));
      setSubmitting(false);
    }
  };

  return (
    <WicketModal
      isOpen={isOpen}
      title={__("Add to Membership Bundle", "wicket-memberships")}
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
          "Select a membership bundle to add this membership to.",
          "wicket-memberships"
        )}
      </p>

      <ModalPostSelector
        id="add_to_group_selector"
        label={__("Membership Bundle", "wicket-memberships")}
        modalTitle={__("Select Membership Bundle", "wicket-memberships")}
        value={selectedBundle}
        onChange={setSelectedGroup}
        loadOptions={loadGroupOptions}
        columns={[
          { key: "title",    label: __("Bundle Name",    "wicket-memberships"), width: 250, searchable: true },
          { key: "org_name", label: __("Organization",  "wicket-memberships"), width: 250, searchable: true },
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
          {__("Add to Bundle", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default AddToMembershipBundleModal;
