import { __ } from '@wordpress/i18n';
import { useState, useEffect, useId } from 'react';
import { Button, Flex, FlexItem, FlexBlock, Notice, TextControl, __experimentalHeading as Heading, Icon, Spinner} from '@wordpress/components';
import styled from 'styled-components';
import { ErrorsRow, LabelWpStyled, SelectWpStyled, ActionRow, FormFlex, ModalStyled } from '../styled_elements';
import { fetchProductVariations, fetchWcProducts } from '../services/api';
import { WC_PRODUCT_TYPES } from '../constants';

const MarginedFlex = styled(Flex)`
	margin: 15px 0;
`;

const ManageTierProducts = ({
    saveProductChanges,
    maxRangeEnabled = false,
    products = [],
    limit = -1,
    productsInUse = [],
    productListLabel = ''
  }) => {

  const componentId = useId();

	const [tempProduct, setTempProduct] = useState( null );

  const [tempProductErrors, setTempProductErrors] = useState( [] );

  const [wcProductOptions, setWcProductOptions] = useState([]); // { label, value, type }

  const [isModalOpen, setIsModalOpen] = useState( false );

  const [currentProductIndex, setCurrentProductIndex] = useState(null);

  const [productVariations, setProductVariations] = useState([]); // { product_id: [] }

  const getAllWcProducts = async () => {
    const promises = WC_PRODUCT_TYPES.map((type) =>
      fetchWcProducts({
          status: 'publish',
          per_page: 100,
          exclude: productsInUse,
          type: type
      })
    );

    try {
      const results = await Promise.all(promises);
      const options = results.flat().map((product) => ({
          label: `${product.name} | ID: ${product.id}`,
          value: product.id,
          type: product.type
      }));

      setWcProductOptions(options);
    } catch (error) {
      console.error("Error fetching products:", error);
    }
  };

	useEffect(() => {
		// Fetch WooCommerce products
		getAllWcProducts();

    // Load variations for the selected product id
    products.forEach((product) => {
      if (product.variation_id) {
        getProductVariations(product.product_id);
      }
    });

  }, []);

	const initProductModal = (productIndex) => {
		setCurrentProductIndex(productIndex);

		// Clear errors
		setTempProductErrors([]);

		if (productIndex === null) {
			// Adding new product
			setTempProduct({
				product_id: null,
				max_seats: 0,
				variation_id: null
			});
		} else {
			// Editing existing product
			console.log('Editing existing product');
			const product = products[productIndex];
			setTempProduct(product);
		}
		setIsModalOpen(true);
	}

	const wcProductOptionsExist = () => {
		return wcProductOptions.length > 0;
	}

	// Load variations for the selected product id
	const getProductVariations = (productId) => {

		// check if we already have variations for this product
		if (productId === null || productVariations[productId]) { return; }

		fetchProductVariations(productId).then((variations) => {
			setProductVariations({
				...productVariations,
				[productId]: variations
			});
		});
	}

  /**
   * Validate the product
   */
	const validateProduct = () => {
		let isValid = true;
		const newErrors = [];

		if (tempProduct.product_id === null) {
			newErrors.push(__('Product is required', 'wicket-memberships'));
			isValid = false;
		} else {

      // check if the selected product is a variable subscription
      const product = wcProductOptions.find(option => option.value === tempProduct.product_id);

      if (product.type === 'variable-subscription' && tempProduct.variation_id === null) {
        newErrors.push(__('Variation is required', 'wicket-memberships'));
        isValid = false;
      }
    }

		if (tempProduct.max_seats < 0) {
			newErrors.push(__('Range maximum value cannot be less than 0', 'wicket-memberships'));
			isValid = false;
		}


		if (parseInt(tempProduct.max_seats) === NaN) {
			newErrors.push(__('Range maximum value must be a number', 'wicket-memberships'));
			isValid = false;
		}

		setTempProductErrors(newErrors);

		return isValid;
	}

	/**
	 * Get the product id of the selected "per range of seats" product (in the modal)
	 */
	const getSelectedProductId = () => {
		// return null if there is no product
		if (tempProduct.product_id === null) { return null; }

		return tempProduct.product_id;
	};

	const getSelectedVariationOption = () => {
		if ( productVariations[getSelectedProductId()] === undefined ) { return null; }

		if ( getSelectedProductVariationId() === null ) { return null; }

		const variation = productVariations[getSelectedProductId()].find(variation => variation.id === getSelectedProductVariationId());

		return {
			label: `#${variation.id}`,
			value: variation.id
		}
	}

	/**
	 * Get the variation id of the selected product (in the modal)
	 */
	const getSelectedProductVariationId = () => {
		// return null if there are no products
		if (tempProduct.variation_id === null) { return null; }

		return tempProduct.variation_id;
	};


	/**
	 * Get the product id of the selected product
	 */
	const getSelectedProductType = () => {
		if (tempProduct.product_id === null) { return null; }

		const product = wcProductOptions.find(option => option.value === tempProduct.product_id);

		return product.type;
	};

  const handleSave = (e) => {

		if ( ! validateProduct() ) { return }

    console.log( 'currentProductIndex' );
    console.log( currentProductIndex );

    let newProducts;

		if (currentProductIndex === null) {
			newProducts = [
        ...products,
        tempProduct
      ];
		} else {
			newProducts = products.map((product, index) => {
				if (index === currentProductIndex) {
					return {
						product_id: tempProduct.product_id,
						max_seats: tempProduct.max_seats,
						variation_id: tempProduct.variation_id
					}
				}
				return product;
			});

		}

    setIsModalOpen(false);
    saveProductChanges(newProducts);
  }

  const getProductListLabel = () => {
    if (productListLabel) {
      return productListLabel;
    }

    return limit === 1 ? __('Product', 'wicket-memberships') : __('Products', 'wicket-memberships');
  }

  const allRemoteDataLoaded = () => {
		return wcProductOptions.length > 0;
	}

	console.log('Subcomponent Products:');
	console.log(products);
	console.log('--------------');

  console.log('WC Products:');
	console.log(wcProductOptions);
	console.log('--------------');

	console.log('Product Variations:');
	console.log(productVariations);
	console.log('--------------');

	return (
		<>
      { ! allRemoteDataLoaded() && (
        <Spinner />
      )}

      { allRemoteDataLoaded() && (
        <>
          <MarginedFlex
            align='end'
            justify='start'
            gap={5}
            direction={[
              'column',
              'row'
            ]}
          >
            <FlexBlock>
              <Button
                variant="secondary"
                onClick={() => {
                  initProductModal(null);
                }}
                disabled={limit !== -1 && products.length >= limit}
              >
                <Icon icon="plus" />&nbsp;
                {__('Add Product', 'wicket-memberships')}
              </Button>
            </FlexBlock>
          </MarginedFlex>

          <FormFlex>
            <Heading level='4' weight='300' >
              {getProductListLabel()}
            </Heading>
          </FormFlex>
          <FormFlex>
            <table className="widefat" cellSpacing="0">
              <thead>
                <tr>
                  <th className="manage-column column-columnname" scope="col">
                    {__('Product Name', 'wicket-memberships')}
                  </th>
                  {maxRangeEnabled && (
                    <th className="manage-column column-columnname" scope="col">
                      {__('Range Max', 'wicket-memberships')}
                    </th>
                  )}
                  <th className="manage-column column-columnname" scope="col">
                    {__('Variation', 'wicket-memberships')}
                  </th>
                  <th className='check-column'></th>
                </tr>
              </thead>
              <tbody>
                {products.map((product, index) => (
                  <tr key={index} className={index % 2 === 0 ? 'alternate' : ''}>
                    <td className="column-columnname">
                      {wcProductOptions.find(option => option.value === product.product_id).label}
                    </td>
                    {maxRangeEnabled && (
                      <td className="column-columnname">
                        {product.max_seats}
                      </td>
                    )}
                    <td className="column-columnname">
                      {product.variation_id ? `#${product.variation_id}` : '-'}
                    </td>
                    <td>
                      <Button
                        onClick={() => {
                          initProductModal(index);
                        }}
                      >
                        <span className="dashicons dashicons-edit"></span>
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </FormFlex>

          {isModalOpen && (
            <ModalStyled
              title={currentProductIndex === null ? __('Add Product', 'wicket-memberships') : __('Edit Product', 'wicket-memberships')}
              onRequestClose={
                () => {
                  setIsModalOpen(false);
                }
              }
              style={
                {
                  maxWidth: '840px',
                  width: '100%'
                }
              }
            >
              <div>

                {tempProductErrors.length > 0 && (
                  <ErrorsRow>
                    {tempProductErrors.map((errorMessage, index) => (
                      <Notice isDismissible={false} key={index} status="warning">{errorMessage}</Notice>
                    ))}
                  </ErrorsRow>
                )}

                <LabelWpStyled htmlFor={`${componentId}_product_id`}>{__('Product', 'wicket-memberships')}</LabelWpStyled>
                <SelectWpStyled
                  id={`${componentId}_product_id`}
                  classNamePrefix="select"
                  value={wcProductOptions.find(option => option.value === tempProduct.product_id)}
                  isClearable={false}
                  isSearchable={true}
                  isLoading={ !wcProductOptionsExist() }
                  options={wcProductOptions}
                  styles={{ menuPortal: base => ({ ...base, zIndex: 9999 }) }}
                  onChange={selected => {
                    setTempProduct({
                      ...tempProduct,
                      product_id: selected.value,
                      variation_id: null
                    });

                    // Load variations if the selected product is a variable subscription
                    const product = wcProductOptions.find(option => option.value === selected.value);

                    if (product.type === 'variable-subscription') {
                      getProductVariations(selected.value);
                    }
                  }}
                />

                {getSelectedProductType() === 'variable-subscription' && (
                  <MarginedFlex>
                    <FlexBlock>
                    <LabelWpStyled htmlFor={`${componentId}_variation_id`}>{__('Variable', 'wicket-memberships')}</LabelWpStyled>
                    <SelectWpStyled
                      id={`${componentId}_variation_id`}
                      classNamePrefix="select"
                      value={getSelectedVariationOption()}
                      isClearable={false}
                      isSearchable={true}
                      isLoading={productVariations[getSelectedProductId()] === undefined}
                      options={productVariations[getSelectedProductId()] ? productVariations[getSelectedProductId()].map((variation) => {
                        return {
                          label: `#${variation.id}`,
                          value: variation.id
                        }
                      }) : []}
                      onChange={selected => {
                        setTempProduct({
                          ...tempProduct,
                          variation_id: selected.value
                        });
                      }}
                    />
                    </FlexBlock>
                  </MarginedFlex>
                )}

                {maxRangeEnabled && (
                <MarginedFlex>
                  <FlexBlock>
                    <TextControl
                      label={__('Range Maximum (USE 0 FOR UNLIMITED)', 'wicket-memberships')}
                      type="number"
                      min={0}
                      onChange={value => {
                        setTempProduct({
                          ...tempProduct,
                          max_seats: value
                        });
                      }}
                      value={tempProduct.max_seats}
                    />
                  </FlexBlock>
                </MarginedFlex>
                )}

                <ActionRow>
                  <Flex
                    align='end'
                    gap={5}
                    direction={[
                      'column',
                      'row'
                    ]}
                  >
                    <FlexItem>
                      {currentProductIndex !== null && (
                        <Button
                          isDestructive={true}
                          onClick={() => {
                            const productData = products.filter((_, index) => index !== currentProductIndex);

                            saveProductChanges(productData);
                            setIsModalOpen(false);
                          }}
                        >
                          <Icon icon="archive" />&nbsp;
                          {__('Delete', 'wicket-memberships')}
                        </Button>
                      )}
                    </FlexItem>
                    <FlexItem>
                      <Button variant="primary" onClick={handleSave}>
                        {currentProductIndex === null ? __('Add Product', 'wicket-memberships') : __('Update Product', 'wicket-memberships')}
                      </Button>
                    </FlexItem>
                  </Flex>

                </ActionRow>
              </div>
            </ModalStyled>
          )}
        </>
      )}
    </>
	);
};

export default ManageTierProducts;
