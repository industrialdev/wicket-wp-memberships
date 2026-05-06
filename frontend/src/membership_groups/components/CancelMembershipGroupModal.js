import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button, SelectControl } from "@wordpress/components";
import styled from "styled-components";
import WicketModal from "../../shared/components/WicketModal";
import Alert from "../../shared/components/Alert";
import { cancelMembershipGroup } from "../../shared/services/api";

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;


const MEMBER_HANDLING_CANCEL_ALL     = "cancel_all";
const MEMBER_HANDLING_KEEP           = "keep_as_individual";
const TIMING_IMMEDIATELY             = "immediately";
const TIMING_AT_END_DATE             = "at_end_date";

/**
 * CancelMembershipGroupModal
 *
 * Opened when an admin sets a membership group status to "cancelled".
 * Lets the admin choose how individual memberships should be handled and,
 * if cancelling all, when the cancellation should take effect.
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.groupPostId        - WP post ID of the membership group.
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess          - Called after a successful cancellation; parent should refresh.
 */
const CancelMembershipGroupModal = ({
  isOpen,
  groupPostId,
  onRequestClose,
  onSuccess,
}) => {
  const [memberHandling, setMemberHandling] = useState("");
  const [timing, setTiming]                 = useState("");
  const [error, setError]                   = useState(null);
  const [submitting, setSubmitting]         = useState(false);

  const resetState = () => {
    setMemberHandling("");
    setTiming("");
    setError(null);
    setSubmitting(false);
  };

  const handleClose = () => {
    resetState();
    onRequestClose();
  };

  const isSubmitDisabled = () => {
    if (!memberHandling) return true;
    if (memberHandling === MEMBER_HANDLING_CANCEL_ALL && !timing) return true;
    return false;
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    setError(null);
    try {
      const data = { member_handling: memberHandling };
      if (memberHandling === MEMBER_HANDLING_CANCEL_ALL) {
        data.timing = timing;
      }
      const result = await cancelMembershipGroup(groupPostId, data);
      resetState();
      onSuccess(result?.success ?? __("Membership group cancelled successfully.", "wicket-memberships"));
    } catch (err) {
      setError(err?.error ?? err?.message ?? __("An error occurred.", "wicket-memberships"));
      setSubmitting(false);
    }
  };

  return (
    <WicketModal
      isOpen={isOpen}
      title={__("Cancel Membership Group", "wicket-memberships")}
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
        {__("Cancelling this membership group is permanent and cannot be undone. Choose how individual memberships should be handled before proceeding.", "wicket-memberships")}
      </p>

      <div style={{ marginBottom: "16px" }}>
        <SelectControl
          label={__("What should happen to individual memberships?", "wicket-memberships")}
          value={memberHandling}
          options={[
            { label: __("Select an option", "wicket-memberships"), value: "" },
            { label: __("Cancel all individual memberships", "wicket-memberships"), value: MEMBER_HANDLING_CANCEL_ALL },
            { label: __("Continue memberships as individual memberships", "wicket-memberships"), value: MEMBER_HANDLING_KEEP },
          ]}
          onChange={(val) => {
            setMemberHandling(val);
            setTiming("");
          }}
        />
      </div>

      {memberHandling === MEMBER_HANDLING_CANCEL_ALL && (
        <div style={{ marginBottom: "16px" }}>
          <SelectControl
            label={__("When should cancellation take effect?", "wicket-memberships")}
            value={timing}
            options={[
              { label: __("Select timing", "wicket-memberships"), value: "" },
              { label: __("Cancel immediately", "wicket-memberships"), value: TIMING_IMMEDIATELY },
              { label: __("Cancel at membership group end date", "wicket-memberships"), value: TIMING_AT_END_DATE },
            ]}
            onChange={(val) => setTiming(val)}
          />
        </div>
      )}

      <ModalFooter>
        <Button variant="secondary" onClick={handleClose} disabled={submitting}>
          {__("Cancel", "wicket-memberships")}
        </Button>
        <Button
          variant="primary"
          isDestructive
          onClick={handleSubmit}
          disabled={isSubmitDisabled() || submitting}
          isBusy={submitting}
        >
          {__("Confirm Cancellation", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default CancelMembershipGroupModal;
