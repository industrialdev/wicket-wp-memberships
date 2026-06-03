import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import styled from "styled-components";
import WicketModal from "../../shared/components/WicketModal";
import Alert from "../../shared/components/Alert";
import { createBundleRenewalOrder } from "../../shared/services/api";

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;

/**
 * CreateBundleRenewalOrderModal
 *
 * Creates a WooCommerce renewal order off the bundle's existing subscription.
 * Does not create a new subscription — line items are inherited from the
 * existing bundle subscription so billing history stays intact.
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.bundlePostId  - WP post ID of the membership bundle.
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess     - Called with the order URL after success.
 */
const CreateBundleRenewalOrderModal = ({
  isOpen,
  bundlePostId,
  onRequestClose,
  onSuccess,
}) => {
  const [error, setError]         = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [orderUrl, setOrderUrl]   = useState(null);

  const resetState = () => {
    setError(null);
    setSubmitting(false);
    setOrderUrl(null);
  };

  const handleClose = () => {
    resetState();
    onRequestClose();
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    setError(null);
    try {
      const result = await createBundleRenewalOrder(bundlePostId);
      setOrderUrl(result?.order_url ?? null);
      if (onSuccess) onSuccess(result?.order_url ?? null);
    } catch (err) {
      setError(err?.error ?? err?.message ?? __("An error occurred.", "wicket-memberships"));
      setSubmitting(false);
    }
  };

  return (
    <WicketModal
      isOpen={isOpen}
      title={__("Create Renewal Order", "wicket-memberships")}
      onRequestClose={handleClose}
      shouldCloseOnClickOutside={false}
    >
      {error && (
        <Alert
          saveResult={{ type: "error", message: error }}
          onDismiss={() => setError(null)}
        />
      )}

      {orderUrl ? (
        <>
          <Alert
            saveResult={{
              type: "success",
              message: __("Renewal order created successfully.", "wicket-memberships"),
            }}
          />
          <p>
            <a href={orderUrl} target="_blank" rel="noreferrer">
              {__("View renewal order in WooCommerce", "wicket-memberships")}
            </a>
          </p>
          <ModalFooter>
            <Button variant="secondary" onClick={handleClose}>
              {__("Close", "wicket-memberships")}
            </Button>
          </ModalFooter>
        </>
      ) : (
        <>
          <p>
            {__(
              "This will create a renewal order off the existing bundle subscription. The subscription itself will not be changed.",
              "wicket-memberships"
            )}
          </p>
          <ModalFooter>
            <Button variant="secondary" onClick={handleClose} disabled={submitting}>
              {__("Cancel", "wicket-memberships")}
            </Button>
            <Button variant="primary" onClick={handleSubmit} isBusy={submitting} disabled={submitting}>
              {__("Create Renewal Order", "wicket-memberships")}
            </Button>
          </ModalFooter>
        </>
      )}
    </WicketModal>
  );
};

export default CreateBundleRenewalOrderModal;
