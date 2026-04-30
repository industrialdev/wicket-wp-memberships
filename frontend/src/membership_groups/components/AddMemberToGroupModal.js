import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import styled from "styled-components";
import WicketModal from "../../shared/components/WicketModal";
import ModalPostSelector from "../../shared/components/ModalPostSelector";
import Alert from "../../shared/components/Alert";
import { AsyncSelectWpStyled, LabelWpStyled } from "../../shared/styled_elements";
import { API_URL, TIER_CPT_SLUG } from "../../shared/constants";
import { fetchMdpPersons, fetchMembershipProducts, addMemberToGroup } from "../../shared/services/api";

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;

/**
 * AddMemberToGroupModal — Flow B
 *
 * Opens from the Membership Actions dropdown on the membership group page.
 * Lets an admin add a new member (MDP person) to a membership group by
 * selecting user, tier, and (when needed) product.
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.groupPostId
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess       - Called after successful add; parent should refresh.
 */
const AddMemberToGroupModal = ({
  isOpen,
  groupPostId,
  onRequestClose,
  onSuccess,
}) => {
  const [selectedUser, setSelectedUser]       = useState(null);
  const [selectedTier, setSelectedTier]       = useState(null);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [error, setError]                     = useState(null);
  const [submitting, setSubmitting]           = useState(false);

  const resetState = () => {
    setSelectedUser(null);
    setSelectedTier(null);
    setSelectedProduct(null);
    setError(null);
    setSubmitting(false);
  };

  const handleClose = () => {
    resetState();
    onRequestClose();
  };

  // Debounced MDP person search — min 3 chars, matching loadOwnerOptions pattern.
  const loadUserOptions = (inputValue, callback) => {
    if (inputValue.length < 3) return;
    fetchMdpPersons({ term: inputValue })
      .then((response) => {
        callback(
          response.map((person) => ({ label: `${person.full_name} (${person.id}) — ${person.primary_email_address}`, value: person.id }))
        );
      })
      .catch((err) => {
        console.error("[AddMemberToGroupModal] loadUserOptions error", err);
      });
  };

  const handleUserChange = (option) => {
    setSelectedUser(option);
    setSelectedTier(null);
    setSelectedProduct(null);
  };

  // Load WP tier CPT posts, then resolve all product/variation names in one
  // follow-up call to /membership_products so product selectors show real names.
  const loadTierOptions = () =>
    apiFetch({
      path: addQueryArgs(`${API_URL}/${TIER_CPT_SLUG}`, {
        posts_per_page: -1,
        status: "publish",
      }),
    }).then(async (posts) => {
      const tiers = posts
        .filter((post) => post.tier_data?.type === "individual")
        .map((post) => ({
          value: post.id,
          title: post.title.rendered,
          productData: post.tier_data?.product_data ?? [],
        }));

      // Collect unique IDs to resolve names — prefer variation_id when present,
      // fall back to product_id for non-variable products.
      const allIds = [
        ...new Set(
          tiers.flatMap((tier) =>
            tier.productData.map((p) => p.variation_id || p.product_id).filter(Boolean)
          )
        ),
      ];

      if (allIds.length === 0) return tiers;

      // One request resolves names for all products and variations.
      const nameMap = {};
      try {
        const resolved = await fetchMembershipProducts(allIds);
        resolved.forEach((p) => { nameMap[p.id] = p.name; });
      } catch (err) {
        console.error("[AddMemberToGroupModal] fetchMembershipProducts error", err);
      }

      // Merge resolved name into each productData entry.
      return tiers.map((tier) => ({
        ...tier,
        productData: tier.productData.map((p) => {
          const lookupId = p.variation_id || p.product_id;
          return { ...p, name: nameMap[lookupId] ?? String(lookupId) };
        }),
      }));
    });

  // Each productData entry is one selectable option. value is variation_id when
  // present (uniquely identifies the variation), else product_id. Both IDs are
  // carried so the submit handler can send them separately to the backend.
  const resolveProductValue = (product) => ({
    value: product.variation_id || product.product_id,
    title: product.name,
    productId: product.product_id,
    variationId: product.variation_id || null,
  });

  const handleTierChange = (option) => {
    setSelectedTier(option);
    if (option?.productData?.length === 1) {
      setSelectedProduct(resolveProductValue(option.productData[0]));
    } else {
      setSelectedProduct(null);
    }
  };

  // Derive product options from already-enriched tier data — no extra fetch.
  const loadProductOptions = () => {
    if (!selectedTier?.productData) return Promise.resolve([]);
    return Promise.resolve(selectedTier.productData.map(resolveProductValue));
  };

  const showProductSelector =
    selectedTier?.productData && selectedTier.productData.length > 1;

  const canSubmit =
    selectedUser && selectedTier && selectedProduct && !submitting;

  const handleSubmit = async () => {
    if (!canSubmit) return;
    setSubmitting(true);
    setError(null);
    try {
      await addMemberToGroup(groupPostId, {
        mode: "new",
        person_uuid: selectedUser.value,
        tier_post_id: selectedTier.value,
        product_id: selectedProduct.productId,
        ...(selectedProduct.variationId ? { variation_id: selectedProduct.variationId } : {}),
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
      title={__("Add Member to Group", "wicket-memberships")}
      onRequestClose={handleClose}
      shouldCloseOnClickOutside={false}
    >
      {error && (
        <Alert
          saveResult={{ type: "error", message: error }}
          onDismiss={() => setError(null)}
        />
      )}

      <div style={{ marginBottom: "16px" }}>
        <LabelWpStyled>
          {__("User", "wicket-memberships")}
        </LabelWpStyled>
        <AsyncSelectWpStyled
          inputId="add_member_user"
          classNamePrefix="select"
          cacheOptions
          loadOptions={loadUserOptions}
          value={selectedUser}
          onChange={handleUserChange}
          placeholder={__("Search by name (min. 3 characters)…", "wicket-memberships")}
          noOptionsMessage={({ inputValue }) =>
            inputValue.length < 3
              ? __("Type at least 3 characters to search.", "wicket-memberships")
              : __("No users found.", "wicket-memberships")
          }
        />
      </div>

      <div style={{ marginBottom: "16px" }}>
        <ModalPostSelector
          id="add_member_tier_selector"
          label={__("Membership Tier", "wicket-memberships")}
          modalTitle={__("Select Membership Tier", "wicket-memberships")}
          value={selectedTier}
          onChange={handleTierChange}
          disabled={!selectedUser}
          loadOptions={loadTierOptions}
          columnLabels={{ name: __("Tier Name", "wicket-memberships") }}
        />
      </div>

      {showProductSelector && (
        <div style={{ marginBottom: "16px" }}>
          <ModalPostSelector
            id="add_member_product_selector"
            label={__("Product", "wicket-memberships")}
            modalTitle={__("Select Product", "wicket-memberships")}
            value={selectedProduct}
            onChange={setSelectedProduct}
            loadOptions={loadProductOptions}
            columnLabels={{ name: __("Product Name", "wicket-memberships") }}
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
          disabled={!canSubmit}
          isBusy={submitting}
        >
          {__("Add Member", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default AddMemberToGroupModal;
