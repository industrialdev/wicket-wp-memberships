import { useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";
import WicketModal from "../shared/components/WicketModal";
import ModalPostSelector from "../shared/components/ModalPostSelector";
import Alert from "../shared/components/Alert";
import AddMemberErrorMessage from "../shared/components/AddMemberErrorMessage";
import styled from "styled-components";
import { API_URL, TIER_CPT_SLUG } from "../shared/constants";
import {
  fetchMembershipBundles,
  fetchMembershipProducts,
  addMemberToBundle,
} from "../shared/services/api";

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
  const [productOptions, setProductOptions] = useState(null);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [resolvingProducts, setResolvingProducts] = useState(false);

  const resetState = () => {
    setSelectedGroup(null);
    setError(null);
    setSubmitting(false);
    setProductOptions(null);
    setSelectedProduct(null);
    setResolvingProducts(false);
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
        // A config with no eligible_tier_ids is all-tiers-eligible (the
        // config field's own fallback rule) — those bundles are always
        // shown. Otherwise, only show bundles whose config lists this
        // membership's own tier as eligible.
        .filter(
          (bundle) =>
            !bundle.eligible_tier_ids?.length ||
            bundle.eligible_tier_ids.includes(tierPostId),
        )
        .map((bundle) => ({
          value: bundle.post_id,
          title: bundle.bundle_name,
          org_name: bundle.org_name ?? "",
        }));
    });

  // Resolve the tier's product/variation options when the backend can't infer
  // one, e.g. when the existing membership has no linked order/subscription.
  const resolveProductValue = (product) => ({
    value: product.variation_id || product.product_id,
    title: product.name,
    productId: product.product_id,
    variationId: product.variation_id || null,
  });

  const loadProductOptionsForAmbiguousTier = async () => {
    setResolvingProducts(true);
    try {
      const tier = await apiFetch({ path: `${API_URL}/${TIER_CPT_SLUG}/${tierPostId}` });
      const productData = tier?.tier_data?.product_data ?? [];

      const ids = [
        ...new Set(productData.map((p) => p.variation_id || p.product_id).filter(Boolean)),
      ];
      const nameMap = {};
      if (ids.length > 0) {
        try {
          const resolved = await fetchMembershipProducts(ids);
          resolved.forEach((p) => { nameMap[p.id] = p.name; });
        } catch (err) {
          console.error("[AddToMembershipBundleModal] fetchMembershipProducts error", err);
        }
      }

      setProductOptions(
        productData.map((p) => resolveProductValue({
          ...p,
          name: nameMap[p.variation_id || p.product_id] ?? String(p.variation_id || p.product_id),
        }))
      );
    } catch (err) {
      console.error("[AddToMembershipBundleModal] loadProductOptionsForAmbiguousTier error", err);
      setError(__("Could not load the tier's products. Please try again.", "wicket-memberships"));
    } finally {
      setResolvingProducts(false);
    }
  };

  const handleSubmit = async () => {
    if (!selectedBundle) return;
    setSubmitting(true);
    setError(null);
    try {
      await addMemberToBundle(selectedBundle.value, {
        mode: "existing",
        existing_membership_post_id: membershipPostId,
        tier_post_id: tierPostId,
        ...(selectedProduct?.productId ? { product_id: selectedProduct.productId } : {}),
        ...(selectedProduct?.variationId ? { variation_id: selectedProduct.variationId } : {}),
      });
      resetState();
      onSuccess();
    } catch (err) {
      // The backend can't infer a product when this membership has no linked
      // order/subscription (e.g. imported or comp memberships) and its tier has
      // more than one product — ask the admin to pick one instead of dead-ending.
      if (err?.code === "ambiguous_product" && !productOptions) {
        setSubmitting(false);
        loadProductOptionsForAmbiguousTier();
        return;
      }
      setError(err);
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
          saveResult={{ type: "error", message: <AddMemberErrorMessage error={error} /> }}
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
        emptyMessage={__("No eligible options available.", "wicket-memberships")}
        columns={[
          { key: "title",    label: __("Bundle Name",    "wicket-memberships"), width: 250, searchable: true },
          { key: "org_name", label: __("Organization",  "wicket-memberships"), width: 250, searchable: true },
        ]}
      />

      {(resolvingProducts || productOptions) && (
        <div style={{ marginTop: "16px" }}>
          <p>
            {__(
              "Select the product to use for this membership.",
              "wicket-memberships"
            )}
          </p>
          <ModalPostSelector
            id="add_to_group_product_selector"
            label={__("Product", "wicket-memberships")}
            modalTitle={__("Select Product", "wicket-memberships")}
            value={selectedProduct}
            onChange={setSelectedProduct}
            disabled={resolvingProducts}
            loadOptions={() => Promise.resolve(productOptions ?? [])}
            columns={[
              { key: "title", label: __("Product Name", "wicket-memberships"), flex: 1,   searchable: true },
              { key: "sku",   label: __("SKU",          "wicket-memberships"), width: 180, searchable: true },
              { key: "price", label: __("Price",        "wicket-memberships"), width: 120, format: "currency" },
            ]}
          />
        </div>
      )}

      <ModalFooter>
        <Button variant="secondary" onClick={handleClose} disabled={submitting}>
          {__("Cancel", "wicket-memberships")}
        </Button>
        <Button
          variant="primary"
          onClick={handleSubmit}
          disabled={
            !selectedBundle ||
            submitting ||
            resolvingProducts ||
            (productOptions && !selectedProduct)
          }
          isBusy={submitting || resolvingProducts}
        >
          {__("Add to Bundle", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default AddToMembershipBundleModal;
