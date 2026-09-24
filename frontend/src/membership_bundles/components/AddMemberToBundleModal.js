import { useRef, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Button, Spinner, Tooltip } from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import styled from "styled-components";
import WicketModal from "../../shared/components/WicketModal";
import ModalPostSelector from "../../shared/components/ModalPostSelector";
import Alert from "../../shared/components/Alert";
import AddMemberErrorMessage from "../../shared/components/AddMemberErrorMessage";
import { AsyncSelectWpStyled, LabelWpStyled } from "../../shared/styled_elements";
import { API_URL, TIER_CPT_SLUG } from "../../shared/constants";
import {
  fetchMdpPersons,
  fetchMembershipProducts,
  fetchBundleEligibleMemberships,
  addMemberToBundle,
} from "../../shared/services/api";

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;

const LoadingRow = styled.div`
  display: flex;
  align-items: center;
  gap: 8px;
  color: #757575;
  font-size: 13px;
`;

// Two-option segmented switch for the new-vs-existing membership choice.
const SegmentedSwitch = styled.div`
  display: inline-flex;
  border: 1px solid #949494;
  border-radius: 4px;
  overflow: hidden;
  width: 100%;
`;

const SegmentedSwitchOption = styled.button`
  flex: 1;
  border: none;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 500;
  cursor: ${(props) => (props.disabled ? "not-allowed" : "pointer")};
  background: ${(props) => (props.$active ? "#2271b1" : "#fff")};
  color: ${(props) => (props.$active ? "#fff" : "#1e1e1e")};
  opacity: ${(props) => (props.disabled ? 0.5 : 1)};

  &:not(:last-child) {
    border-right: 1px solid #949494;
  }
`;

/**
 * AddMemberToBundleModal — Flow B
 *
 * Opens from the Membership Actions dropdown on the membership bundle page.
 * Adds an MDP person to a bundle, either as a new membership (tier + product)
 * or by reusing an existing eligible membership (mode = "existing", same
 * backend path as AddToMembershipBundleModal).
 *
 * @param {bool}     props.isOpen
 * @param {number}   props.bundlePostId
 * @param {number[]} props.eligibleTierIds - Bundle config's eligible_tier_ids.
 *   Empty means all active individual tiers are eligible (fallback rule) —
 *   the tier dropdown is unfiltered in that case, not empty.
 * @param {Function} props.onRequestClose
 * @param {Function} props.onSuccess       - Called after successful add; parent should refresh.
 */
const AddMemberToBundleModal = ({
  isOpen,
  bundlePostId,
  eligibleTierIds = [],
  onRequestClose,
  onSuccess,
}) => {
  const [selectedUser, setSelectedUser]       = useState(null);
  const [selectedTier, setSelectedTier]       = useState(null);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [error, setError]                     = useState(null);
  const [submitting, setSubmitting]           = useState(false);

  // Existing-membership discovery, keyed off the selected person.
  const [eligibleMemberships, setEligibleMemberships]           = useState([]);
  const [loadingEligibleMemberships, setLoadingEligibleMemberships] = useState(false);
  // null | 'new' | 'existing' — null means the admin hasn't chosen yet, so
  // neither the tier/product fields nor the existing-membership picker show.
  const [addMode, setAddMode]                             = useState(null);
  const [selectedExistingMembership, setSelectedExistingMembership] = useState(null);
  // Guards against an out-of-order response overwriting state after a newer
  // person selection has already fired its own lookup.
  const eligibleMembershipsRequestId = useRef(0);

  const resetState = () => {
    setSelectedUser(null);
    setSelectedTier(null);
    setSelectedProduct(null);
    setError(null);
    setSubmitting(false);
    setEligibleMemberships([]);
    setLoadingEligibleMemberships(false);
    setAddMode(null);
    setSelectedExistingMembership(null);
    eligibleMembershipsRequestId.current++;
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
        console.error("[AddMemberToBundleModal] loadUserOptions error", err);
      });
  };

  const handleUserChange = (option) => {
    setSelectedUser(option);
    setSelectedTier(null);
    setSelectedProduct(null);
    setEligibleMemberships([]);
    setAddMode(null);
    setSelectedExistingMembership(null);
    setError(null);

    if (!option) {
      // Invalidate any in-flight lookup for the person just cleared.
      eligibleMembershipsRequestId.current++;
      setLoadingEligibleMemberships(false);
      return;
    }

    const requestId = ++eligibleMembershipsRequestId.current;
    setLoadingEligibleMemberships(true);
    fetchBundleEligibleMemberships(bundlePostId, option.value)
      .then((response) => {
        if (requestId !== eligibleMembershipsRequestId.current) return;
        const memberships = response?.memberships ?? [];
        setEligibleMemberships(memberships);
        // Default to "existing" when eligible memberships are found, else "new".
        setAddMode(memberships.length > 0 ? "existing" : "new");
      })
      .catch((err) => {
        if (requestId !== eligibleMembershipsRequestId.current) return;
        console.error("[AddMemberToBundleModal] fetchBundleEligibleMemberships error", err);
        setError(err);
        setAddMode("new");
      })
      .finally(() => {
        if (requestId !== eligibleMembershipsRequestId.current) return;
        setLoadingEligibleMemberships(false);
      });
  };

  const handleExistingMembershipChange = (option) => {
    setSelectedExistingMembership(option);
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
        // Empty eligibleTierIds means all tiers are eligible — no-op filter.
        .filter(
          (post) =>
            eligibleTierIds.length === 0 || eligibleTierIds.includes(post.id),
        )
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
        console.error("[AddMemberToBundleModal] fetchMembershipProducts error", err);
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

  // value is variation_id when present, else product_id; both are carried
  // separately for the submit handler.
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

  const hasEligibleMemberships = eligibleMemberships.length > 0;

  const canSubmit =
    !submitting &&
    selectedUser &&
    ((addMode === "existing" && !!selectedExistingMembership) ||
      (addMode === "new" && selectedTier && selectedProduct));

  const handleSubmit = async () => {
    if (!canSubmit) return;
    setSubmitting(true);
    setError(null);
    try {
      if (addMode === "existing") {
        await addMemberToBundle(bundlePostId, {
          mode: "existing",
          existing_membership_post_id: selectedExistingMembership.value,
          tier_post_id: selectedExistingMembership.tierPostId,
        });
      } else {
        await addMemberToBundle(bundlePostId, {
          mode: "new",
          person_uuid: selectedUser.value,
          tier_post_id: selectedTier.value,
          product_id: selectedProduct.productId,
          ...(selectedProduct.variationId ? { variation_id: selectedProduct.variationId } : {}),
        });
      }
      resetState();
      onSuccess();
    } catch (err) {
      console.error("[AddMemberToBundleModal] add member failed", err);
      setError(err);
      setSubmitting(false);
    }
  };

  return (
    <WicketModal
      isOpen={isOpen}
      title={__("Add Member to Bundle", "wicket-memberships")}
      onRequestClose={handleClose}
      shouldCloseOnClickOutside={false}
    >
      {error && (
        <Alert
          saveResult={{ type: "error", message: <AddMemberErrorMessage error={error} /> }}
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

      {loadingEligibleMemberships && (
        <LoadingRow style={{ marginBottom: "16px" }}>
          <Spinner />
          {__("Checking for existing eligible memberships…", "wicket-memberships")}
        </LoadingRow>
      )}

      {!loadingEligibleMemberships && selectedUser && (
        <div style={{ marginBottom: "16px" }}>
          <LabelWpStyled>
            {__("Membership", "wicket-memberships")}
          </LabelWpStyled>
          <Tooltip
            text={
              hasEligibleMemberships
                ? undefined
                : __("No eligible memberships exist for this user.", "wicket-memberships")
            }
          >
            {/* A plain div (not the disabled buttons themselves) is what
                receives pointer events, so the tooltip fires while disabled. */}
            <div>
              <SegmentedSwitch>
                <SegmentedSwitchOption
                  type="button"
                  $active={addMode === "existing"}
                  disabled={!hasEligibleMemberships}
                  onClick={() => {
                    if (!hasEligibleMemberships) return;
                    setAddMode("existing");
                    setSelectedExistingMembership(null);
                    setSelectedTier(null);
                    setSelectedProduct(null);
                  }}
                >
                  {__("Use an existing membership", "wicket-memberships")}
                </SegmentedSwitchOption>
                <SegmentedSwitchOption
                  type="button"
                  $active={addMode === "new"}
                  onClick={() => {
                    setAddMode("new");
                    setSelectedExistingMembership(null);
                    setSelectedTier(null);
                    setSelectedProduct(null);
                  }}
                >
                  {__("Create a new membership", "wicket-memberships")}
                </SegmentedSwitchOption>
              </SegmentedSwitch>
            </div>
          </Tooltip>
        </div>
      )}

      {addMode === "existing" && hasEligibleMemberships && (
        <div style={{ marginBottom: "16px" }}>
          <ModalPostSelector
            id="add_member_existing_membership_selector"
            label={__("Existing Membership", "wicket-memberships")}
            modalTitle={__("Select Existing Membership", "wicket-memberships")}
            value={selectedExistingMembership}
            onChange={handleExistingMembershipChange}
            loadOptions={() =>
              Promise.resolve(
                eligibleMemberships.map((membership) => ({
                  value: membership.membership_post_id,
                  tierPostId: membership.tier_post_id,
                  title: membership.tier_name,
                  starts_at: membership.starts_at,
                  ends_at: membership.ends_at,
                  status: membership.status,
                }))
              )
            }
            columns={[
              { key: "title",      label: __("Tier Name",    "wicket-memberships"), flex: 1,   searchable: true },
              { key: "starts_at",  label: __("Start Date",   "wicket-memberships"), width: 160, format: "date" },
              { key: "ends_at",    label: __("End Date",     "wicket-memberships"), width: 160, format: "date" },
              { key: "status",     label: __("Status",       "wicket-memberships"), width: 120 },
            ]}
          />
        </div>
      )}

      {addMode === "new" && (
        <div style={{ marginBottom: "16px" }}>
          <ModalPostSelector
            id="add_member_tier_selector"
            label={__("Membership Tier", "wicket-memberships")}
            modalTitle={__("Select Membership Tier", "wicket-memberships")}
            value={selectedTier}
            onChange={handleTierChange}
            disabled={!selectedUser}
            loadOptions={loadTierOptions}
            emptyMessage={__("No eligible options available.", "wicket-memberships")}
            columns={[
              { key: "title", label: __("Tier Name", "wicket-memberships"), flex: 1, searchable: true },
            ]}
          />
        </div>
      )}

      {addMode === "new" && showProductSelector && (
        <div style={{ marginBottom: "16px" }}>
          <ModalPostSelector
            id="add_member_product_selector"
            label={__("Product", "wicket-memberships")}
            modalTitle={__("Select Product", "wicket-memberships")}
            value={selectedProduct}
            onChange={setSelectedProduct}
            loadOptions={loadProductOptions}
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
          disabled={!canSubmit}
          isBusy={submitting}
        >
          {__("Add Member", "wicket-memberships")}
        </Button>
      </ModalFooter>
    </WicketModal>
  );
};

export default AddMemberToBundleModal;
