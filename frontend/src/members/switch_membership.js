import React, { useState, useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { SelectWpStyled, LabelWpStyled } from '../styled_elements';
import { Button, Icon } from '@wordpress/components';
import { WC_PRODUCT_TYPES } from '../constants';
import { fetchWcProducts, fetchProductVariations } from '../services/api';
import { switchMembership as switchMembershipApi } from '../services/api';

const SwitchMembership = ({ membership }) => {
  const [switchOption, setSwitchOption] = useState(null);
  const [selectedTier, setSelectedTier] = useState(null);
  const [tierOptions, setTierOptions] = useState([]);
  const [loadingTiers, setLoadingTiers] = useState(false);
  const [wcProductOptions, setWcProductOptions] = useState([]); // { label, value, type }
  const [isLoadingProducts, setIsLoadingProducts] = useState(false);
  const [productVariations, setProductVariations] = useState({}); // { product_id: [] }
  const [selectedProductId, setSelectedProductId] = useState(null);
  const [selectedVariationId, setSelectedVariationId] = useState(null);
  const didMountRef = useRef(false);

  // Set didMountRef after first render
  useEffect(() => {
    didMountRef.current = true;
  }, []);

  // Fetch all WooCommerce products (with Membership category)
  const getAllWcProducts = async () => {
    setIsLoadingProducts(true);
    const promises = WC_PRODUCT_TYPES.map((type) =>
      fetchWcProducts({
        status: 'publish',
        per_page: 100,
        type: type,
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
      setWcProductOptions([]);
    }
    setIsLoadingProducts(false);
  };

  // Load products immediately on mount
  useEffect(() => {
    getAllWcProducts();
  }, []);

  /**
   * Fetch variations for the selected product id
   */
  const getProductVariations = (productId) => {

    // check if we already have variations for this product
    if (productId === null || productVariations[productId]) { return; }

    fetchProductVariations(
      productId, 
      {
        per_page: 100,
        status: 'publish'
      }).then((variations) => {
      setProductVariations({
        ...productVariations,
        [productId]: variations
      });
    });
  }

  // Load membership tiers (typeahead)
  const loadTiers = (inputValue = '') => {
    setLoadingTiers(true);
    apiFetch({
      path: addQueryArgs('/wp/v2/wicket_mship_tier', { search: inputValue, per_page: -1 }),
    }).then((response) => {
      const options = (Array.isArray(response) ? response : []).map((tier) => ({
        label: tier.mdp_tier_name || (tier.title && tier.title.rendered) || __('(No Name)', 'wicket-memberships'),
        value: tier.id
      }));
      setTierOptions(options);
      setLoadingTiers(false);
    });
  };

  // (loadProducts) removed; use getAllWcProducts instead

  // Helper to call switch_membership API and redirect
  // switchType: 'tier' or 'order', postId: product/tier id
  const handleSwitchMembership = async (switchType, postId) => {
    if (!membership || !membership.ID || !postId || !switchType) return;
    try {
      const response = await switchMembershipApi(membership.ID, postId, switchType);
      if (response && response.url) {
        window.location.href = response.url;
      }
    } catch (e) {
      // Optionally handle error
      // eslint-disable-next-line no-console
      console.error('Switch membership failed', e);
    }
  };

  return (
    <div style={{ marginBottom: '16px' }}>
      <LabelWpStyled style={{ height: '20px' }} >
        {__('Switch Tier Action', 'wicket-memberships')}&nbsp;
        <span title={__('Choose to create an order or a new membership for tier immediately.', 'wicket-memberships')}>
          <Icon icon='info' />
        </span>
      </LabelWpStyled>
      <div style={{ marginTop: '20px', maxWidth: 250 }}>
        <SelectWpStyled
          options={[
            { label: __('Create Membership', 'wicket-memberships'), value: 'create_membership' },
            { label: __('Create Order', 'wicket-memberships'), value: 'create_order' }
          ]}
          value={switchOption}
          onChange={selected => {
            setSwitchOption(selected);
            if (selected && selected.value === 'create_membership') {
              loadTiers('');
            }
          }}
          isSearchable={false}
          isClearable={false}
          placeholder={__('Select Option', 'wicket-memberships')}
        />

        {switchOption && switchOption.value === 'create_membership' && (
          <div style={{ marginTop: '20px' }}>
            <LabelWpStyled>{__('Select Membership Tier', 'wicket-memberships')}</LabelWpStyled>
            <SelectWpStyled
              options={tierOptions}
              value={selectedTier}
              isLoading={loadingTiers}
              onInputChange={val => loadTiers(val)}
              onChange={option => setSelectedTier(option)}
              isSearchable={true}
              isClearable={false}
              placeholder={__('Type to search tiers...', 'wicket-memberships')}
              menuPortalTarget={typeof window !== 'undefined' ? document.body : null}
              styles={{
                menuPortal: base => ({ ...base, zIndex: 99999999 }),
                menuList: base => ({ ...base, maxHeight: 250, overflowY: 'auto' })
              }}
            />
          </div>
        )}

        {switchOption && switchOption.value === 'create_order' && (
          <div style={{ marginTop: '20px' }}>
            <LabelWpStyled>{__('Select Membership Product', 'wicket-memberships')}</LabelWpStyled>
            <SelectWpStyled
              id={'switch_membership_product_id'}
              classNamePrefix="select"
              value={wcProductOptions.find(option => option.value === selectedProductId) || null}
              isClearable={false}
              isSearchable={true}
              isLoading={isLoadingProducts}
              options={wcProductOptions}
              menuPortalTarget={typeof window !== 'undefined' ? document.body : null}
              styles={{ menuPortal: provided => ({ ...provided, zIndex: 100001 }) }}

              onChange={selected => {
                setSelectedProductId(prevId => {
                  const newId = selected ? selected.value : null;
                  setSelectedVariationId(null);
                  // Only call getProductVariations if this is a real user change (not initial mount)
                  if (didMountRef.current) {
                    const product = wcProductOptions.find(option => option.value === newId);
                    if (product && product.type === 'variable-subscription') {
                      getProductVariations(product.value);
                    }
                  }
                  return newId;
                });
                // Do not call switch_membership here; only on button click
              }
            }
            />
            {/* Show variations if variable-subscription */}
            {(() => {
              const product = wcProductOptions.find(option => option.value === selectedProductId);
              if (product && product.type === 'variable-subscription') {
                return (
                  <div style={{ marginTop: '20px' }}>
                    <LabelWpStyled htmlFor={'switch_membership_variation_id'}>{__('Variable', 'wicket-memberships')}</LabelWpStyled>
                    <SelectWpStyled
                      id={'switch_membership_variation_id'}
                      classNamePrefix="select"
                      value={
                        productVariations[selectedProductId] && selectedVariationId
                          ? productVariations[selectedProductId].find(v => v.id === selectedVariationId) && { label: `${productVariations[selectedProductId].find(v => v.id === selectedVariationId).name} (#${selectedVariationId})`, value: selectedVariationId }
                          : null
                      }
                      isClearable={false}
                      isSearchable={true}
                      isLoading={productVariations[selectedProductId] === undefined}
                      menuPortalTarget={typeof window !== 'undefined' ? document.body : null}
                      styles={{ menuPortal: provided => ({ ...provided, zIndex: 100001 }) }}
                      options={productVariations[selectedProductId] ? productVariations[selectedProductId].map((variation) => ({
                        label: `${variation.name} (#${variation.id})`,
                        value: variation.id
                      })) : []}
                      onChange={selected => {
                        setSelectedVariationId(selected ? selected.value : null);
                        if (selected && selected.value) {
                          // Just set the selected variation, do not call switch_membership here
                        }
                      }}
                    />
                  </div>
                );
              }
              return null;
            })()}
          </div>
        )}

        <Button
          style={{ marginTop: '20px'}}
          variant="primary"
          disabled={
            !selectedProductId ||
            (wcProductOptions.find(option => option.value === selectedProductId)?.type === 'variable-subscription' && !selectedVariationId)
          }
          onClick={() => {
            // Use variationId if set, otherwise use productId
            const postIdToSend = selectedVariationId || selectedProductId;
            handleSwitchMembership('order', postIdToSend);
          }}
        >
          {__('Switch Membership', 'wicket-memberships')}
        </Button>
      </div>

    </div>
  );
}

export default SwitchMembership;
