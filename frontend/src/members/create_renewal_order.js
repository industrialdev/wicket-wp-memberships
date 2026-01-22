import { __ } from "@wordpress/i18n";
import { useState } from "react";
import { WC_PRODUCT_TYPES } from "../constants";
import {
  ErrorsRow,
  BorderedBox,
  ActionRow,
  LabelWpStyled,
  SelectWpStyled,
} from "../styled_elements";
import {
  Button,
  Flex,
  FlexItem,
  FlexBlock,
  Notice,
  Icon,
  Modal,
} from "@wordpress/components";
import styled from "styled-components";
import {
  fetchWcProducts,
  fetchProductVariations,
  createRenewalOrder,
} from "../services/api";

const MarginedFlex = styled(Flex)`
  margin-top: 15px;
`;

const CreateRenewalOrder = ({ membership }) => {
  const [isCreateRenewalOrderModalOpen, setIsCreateRenewalOrderModalOpen] =
    useState(false);

  const [createRenewalOrderErrors, setCreateRenewalOrderErrors] = useState([]);

  const [createRenewalOrderFormData, setCreateRenewalOrderFormData] = useState({
    product_id: null,
    variation_id: null,
    order_link: null,
  });

  const [isLoading, setIsLoading] = useState(false);

  const [wcProductOptions, setWcProductOptions] = useState([]); // { label, value, type }

  const [productVariations, setProductVariations] = useState([]); // { product_id: [] }

  /**
   * Open the Create Renewal Order Modal
   */
  const openCreateRenewalOrderModal = () => {
    // If there are no WooCommerce products, fetch them
    if (wcProductOptions.length === 0) {
      getAllWcProducts();
    }
    setIsCreateRenewalOrderModalOpen(true);
  };

  /**
   * Close the Create Renewal Order Modal
   */
  const closeCreateRenewalOrderModalOpen = () => {
    setCreateRenewalOrderFormData({
      product_id: null,
      variation_id: null,
      order_link: null,
    });
    setIsCreateRenewalOrderModalOpen(false);
  };

  /**
   * Fetch all WooCommerce products
   */
  const getAllWcProducts = async () => {
    const promises = WC_PRODUCT_TYPES.map((type) =>
      fetchWcProducts({
        status: "publish",
        per_page: 100,
        type: type,
      }),
    );

    try {
      const results = await Promise.all(promises);
      const options = results.flat().map((product) => ({
        label: `${product.name} | ID: ${product.id}`,
        value: product.id,
        type: product.type,
      }));

      setWcProductOptions(options);
    } catch (error) {
      console.error("Error fetching products:", error);
    }
  };

  /**
   * Get the product id of the selected product (in the modal)
   */
  const getSelectedProductId = () => {
    // return null if there is no product
    if (createRenewalOrderFormData.product_id === null) {
      return null;
    }

    return createRenewalOrderFormData.product_id;
  };

  /**
   * Get the variation id of the selected product (in the modal)
   */
  const getSelectedProductVariationId = () => {
    // return null if there are no products
    if (createRenewalOrderFormData.variation_id === null) {
      return null;
    }

    return createRenewalOrderFormData.variation_id;
  };

  /**
   * Generate the product variation option for the selected variation
   */
  const getSelectedVariationOption = () => {
    if (productVariations[getSelectedProductId()] === undefined) {
      return null;
    }

    if (getSelectedProductVariationId() === null) {
      return null;
    }

    const variation = productVariations[getSelectedProductId()].find(
      (variation) => variation.id === getSelectedProductVariationId(),
    );

    return {
      label: `#${variation.id}`,
      value: variation.id,
    };
  };

  /**
   * Fetch variations for the selected product id
   */
  const getProductVariations = (productId) => {
    // check if we already have variations for this product
    if (productId === null || productVariations[productId]) {
      return;
    }

    fetchProductVariations(productId, {
      per_page: 100,
      status: "publish",
    }).then((variations) => {
      setProductVariations({
        ...productVariations,
        [productId]: variations,
      });
    });
  };

  /**
   * Handle the Create Renewal Order Modal Submit
   */
  const handleCreateRenewalOrderModalSubmit = (event) => {
    event.preventDefault();

    setIsLoading(true);

    createRenewalOrder(
      membership.ID,
      createRenewalOrderFormData.product_id,
      createRenewalOrderFormData.variation_id,
    )
      .then((response) => {
        console.log(response);

        setIsLoading(false);

        if (response.success) {
          setCreateRenewalOrderFormData({
            ...createRenewalOrderFormData,
            order_link: response.order_url,
          });
        }
      })
      .catch((response) => {
        setCreateRenewalOrderErrors([response.error]);
        setIsLoading(false);
        console.error(response);
      });
  };

  /**
   * Get the product id of the selected product
   */
  const getSelectedCreateRenewalOrderProductType = () => {
    if (createRenewalOrderFormData.product_id === null) {
      return null;
    }

    const product = wcProductOptions.find(
      (option) => option.value === createRenewalOrderFormData.product_id,
    );

    return product.type;
  };

  console.log(createRenewalOrderFormData);

  return (
    <>
      <BorderedBox>
        <div style={{ textAlign: "left" }}>
          <div>
            <LabelWpStyled>{__("Renewal", "wicket-memberships")}</LabelWpStyled>
          </div>
          <div>
            <Button
              variant="secondary"
              // Disable the button if the membership is not "Active, Grace Period, or Delayed"
              disabled={
                membership.data.membership_status_slug !== "active" &&
                membership.data.membership_status_slug !== "grace_period" &&
                membership.data.membership_status_slug !== "delayed"
              }
              onClick={() => {
                openCreateRenewalOrderModal();
              }}
            >
              <Icon icon="update" />
              &nbsp;
              {__("Create Renewal Order", "wicket-memberships")}
            </Button>
          </div>
        </div>
      </BorderedBox>

      {/* "Create Renewal Order" Modal */}
      {isCreateRenewalOrderModalOpen && (
        <Modal
          title={__("Create Renewal Order", "wicket-memberships")}
          onRequestClose={closeCreateRenewalOrderModalOpen}
          style={{
            maxWidth: "650px",
            width: "100%",
          }}
        >
          {createRenewalOrderErrors.length > 0 && (
            <ErrorsRow>
              {createRenewalOrderErrors.map((errorMessage, index) => (
                <Notice isDismissible={false} key={index} status="warning">
                  {errorMessage}
                </Notice>
              ))}
            </ErrorsRow>
          )}

          {createRenewalOrderFormData.order_link !== null && (
            <Notice isDismissible={false} status="success">
              {__("Renewal Order created successfully.", "wicket-memberships")}{" "}
              <Button
                variant="link"
                href={createRenewalOrderFormData.order_link}
                target="_blank"
              >
                {__("View Order", "wicket-memberships")}
              </Button>
              &nbsp;
              <Icon
                icon="external"
                style={{ color: "var(--wp-admin-theme-color)" }}
              />
            </Notice>
          )}

          <form onSubmit={handleCreateRenewalOrderModalSubmit}>
            <MarginedFlex
              align="end"
              justify="start"
              gap={5}
              direction={["column", "row"]}
            >
              <FlexBlock>
                <LabelWpStyled htmlFor={"create_renewal_order_product_id"}>
                  {__("Product", "wicket-memberships")}
                </LabelWpStyled>
                <SelectWpStyled
                  id={"create_renewal_order_product_id"}
                  classNamePrefix="select"
                  value={wcProductOptions.find(
                    (option) =>
                      option.value === createRenewalOrderFormData.product_id,
                  )}
                  isClearable={false}
                  isSearchable={true}
                  isLoading={wcProductOptions.length === 0}
                  options={wcProductOptions}
                  menuPortalTarget={document.body}
                  styles={{
                    menuPortal: (provided) => ({ ...provided, zIndex: 100001 }),
                  }}
                  onChange={(selected) => {
                    setCreateRenewalOrderFormData({
                      ...createRenewalOrderFormData,
                      product_id: selected.value,
                      variation_id: null,
                    });

                    // Fetch variations if the selected product is a variable subscription
                    const product = wcProductOptions.find(
                      (option) => option.value === selected.value,
                    );

                    if (product.type === "variable-subscription") {
                      getProductVariations(selected.value);
                    }
                  }}
                />
              </FlexBlock>
            </MarginedFlex>

            {getSelectedCreateRenewalOrderProductType() ===
              "variable-subscription" && (
              <MarginedFlex>
                <FlexBlock>
                  <LabelWpStyled htmlFor={"create_order_renewal_variation_id"}>
                    {__("Variable", "wicket-memberships")}
                  </LabelWpStyled>
                  <SelectWpStyled
                    id={"create_order_renewal_variation_id"}
                    classNamePrefix="select"
                    value={getSelectedVariationOption()}
                    isClearable={false}
                    isSearchable={true}
                    isLoading={
                      productVariations[getSelectedProductId()] === undefined
                    }
                    menuPortalTarget={document.body}
                    styles={{
                      menuPortal: (provided) => ({
                        ...provided,
                        zIndex: 100001,
                      }),
                    }}
                    options={
                      productVariations[getSelectedProductId()]
                        ? productVariations[getSelectedProductId()].map(
                            (variation) => {
                              return {
                                label: `${variation.name} (#${variation.id})`,
                                value: variation.id,
                              };
                            },
                          )
                        : []
                    }
                    onChange={(selected) => {
                      setCreateRenewalOrderFormData({
                        ...createRenewalOrderFormData,
                        variation_id: selected.value,
                      });
                    }}
                  />
                </FlexBlock>
              </MarginedFlex>
            )}

            <ActionRow>
              <Flex align="end" gap={5} direction={["column", "row"]}>
                <FlexItem>
                  <Button
                    variant="primary"
                    type="submit"
                    isBusy={isLoading}
                    disabled={
                      getSelectedProductId() === null ||
                      (getSelectedCreateRenewalOrderProductType() ===
                        "variable-subscription" &&
                        getSelectedProductVariationId() === null) ||
                      isLoading ||
                      createRenewalOrderFormData.order_link !== null
                    }
                  >
                    {__("Create Order", "wicket-memberships")}
                  </Button>
                </FlexItem>
              </Flex>
            </ActionRow>
          </form>
        </Modal>
      )}
    </>
  );
};

export default CreateRenewalOrder;
