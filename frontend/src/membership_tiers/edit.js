import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, TextareaControl, Button, Flex, FlexItem, Modal, FlexBlock, Notice, SelectControl, CheckboxControl, __experimentalHeading as Heading, Icon, __experimentalText as Text } from '@wordpress/components';
import styled from 'styled-components';
import { API_URL, PLUGIN_API_URL, WC_PRODUCT_TYPES } from '../constants';
import he from 'he';
import { Wrap, ErrorsRow, BorderedBox, LabelWpStyled, SelectWpStyled, ActionRow, FormFlex, CustomDisabled } from '../styled_elements';
import { fetchMembershipTiers, fetchProductVariations, fetchWcProducts } from '../services/api';

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

const CreateMembershipTier = ({ tierCptSlug, configCptSlug, tierListUrl, postId, productsInUse, individualListUrl, orgListUrl, languageCodes }) => {

	const languageCodesArray = languageCodes.split(',');

	const [currentApprovalCalloutLocale, setCurrentApprovalCalloutLocale] = useState(languageCodesArray[0]);

	const renewalTypeOptions = [
		{ label: __('Current Tier', 'wicket-memberships'), value: 'current_tier' },
		{ label: __('Sequential Logic', 'wicket-memberships'), value: 'sequential_logic' },
		{ label: __('Renewal Form Flow', 'wicket-memberships'), value: 'form_flow' }
	];

	const [isRangeOfSeatsProductsModalOpen, setRangeOfSeatsProductsModalOpen] = useState(false);
	const openRangeOfSeatsProductsModalOpen = () => setRangeOfSeatsProductsModalOpen(true);
	const closeRangeOfSeatsProductsModalOpen = () => setRangeOfSeatsProductsModalOpen(false);

	const [isApprovalCalloutModalOpen, setApprovalCalloutModalOpen] = useState(false);
	const openApprovalCalloutModal = () => setApprovalCalloutModalOpen(true);
	const closeApprovalCalloutModal = () => setApprovalCalloutModalOpen(false);

	const [currentRangeOfSeatsProductIndex, setCurrentRangeOfSeatsProductIndex] = useState(null);

	const [tierInfo, setTierInfo] = useState(null);

	const [approvalCalloutErrors, setApprovalCalloutErrors] = useState([]);

	const [rangeOfSeatsProductErrors, setRangeOfSeatsProductErrors] = useState([]);

	const [tempRangeOfSeatsProduct, setTempRangeOfSeatsProduct] = useState({
		product_id: null,
		max_seats: 0,
		variation_id: null
	});

	const [isSubmitting, setSubmitting] = useState(false);

	const [mdpTiers, setMdpTiers] = useState([]);

	const [wpTierOptions, setWpTierOptions] = useState([]); // { id, name }

	const [wpPagesOptions, setWpPagesOptions] = useState([]); // { id, name }

	const [membershipConfigOptions, setMembershipConfigOptions] = useState([]); // { id, name }

	const [wcProductOptions, setWcProductOptions] = useState([]); // { label, value, type }

	// we are going to store variations for the loaded products in this state
	const [productVariations, setProductVariations] = useState([]); // { product_id: [] }

	const [errors, setErrors] = useState([]); // Array of strings

	let default_locales = {};
	languageCodesArray.forEach((code) => {
		default_locales[code] = {
			callout_header: '',
			callout_content: '',
			callout_button_label: ''
		}
	});

	const [form, setForm] = useState({
		approval_required: false,
		approval_email_recipient: '',
		mdp_tier_name: '',
		mdp_tier_uuid: '',
		next_tier_id: '',
		next_tier_form_page_id: '',
		config_id: '',
		renewal_type: 'current_tier', // current_tier, sequential_logic, form_flow
		type: '', // orgranization, individual
		seat_type: 'per_seat', // per_seat, per_range_of_seats
		product_data: [], // { product_id:, max_seats:, variation_id: }
		approval_callout_data: {
			locales: default_locales
		}
	});

	const [tempForm, setTempForm] = useState(form);

	const stateProductDataExists = () => {
		return form.product_data.length > 0;
	}

	const wcProductOptionsExist = () => {
		return wcProductOptions.length > 0;
	}

	const getSelectedTierData = () => {
		if (!form.mdp_tier_uuid) { return null; }
		const selectedTier = mdpTiers.find(tier => tier.uuid === form.mdp_tier_uuid);

		return selectedTier;
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

	const allRemoteDataLoaded = () => {
		return mdpTiers.length > 0 && membershipConfigOptions.length > 0 && wcProductOptions.length > 0;
	}

	/**
	 * Get the product id of the selected "per seat" product
	 *
	 * @returns {number|null} Returns the product id of the selected per seat product
	 */
	const getSelectedPerSeatProductId = () => {
		// return null if there are no products
		if ( ! stateProductDataExists() ) { return null; }

		// per seat product has only one product so we can return the first product id
		return form.product_data[0].product_id;
	};

	/**
	 * Get the product id of the selected "per range of seats" product (in the modal)
	 */
	const getSelectedPerRangeSeatsProductId = () => {
		// return null if there is no product
		if (tempRangeOfSeatsProduct.product_id === null) { return null; }

		return tempRangeOfSeatsProduct.product_id;
	};

	/**
	 * Get the variation id of the selected "per seat" product
	 */
	const getSelectedPerSeatProductVariationId = () => {
		// return null if there are no products
		if ( ! stateProductDataExists() ) { return null; }

		// per seat product has only one product so we can return the first product id
		return form.product_data[0].variation_id;
	};

	/**
	 * Get the variation id of the selected "per range of seats" product (in the modal)
	 */
	const getSelectedPerRangeSeatsProductVariationId = () => {
		// return null if there are no products
		if (tempRangeOfSeatsProduct.variation_id === null) { return null; }

		return tempRangeOfSeatsProduct.variation_id;
	};

	/**
	 * Get the type of the selected "per seat" product
	 *
	 * @returns {string|null} Returns the type of the selected "per seat" product
	 */
	const getSelectedPerSeatProductType = () => {
		if ( ! stateProductDataExists() || ! wcProductOptionsExist() ) { return null; }

		const product = wcProductOptions.find(option => option.value === form.product_data[0].product_id);

		if (product === undefined) { return null; }

		return product.type;
	};

	/**
	 * Get the product id of the selected "per range of seats" product
	 */
	const getSelectedPerRangeOfSeatsProductType = () => {
		if (tempRangeOfSeatsProduct.product_id === null) { return null; }

		const product = wcProductOptions.find(option => option.value === tempRangeOfSeatsProduct.product_id);

		return product.type;
	};

	const handleSubmit = (e) => {
		e.preventDefault();

		// TODO: Frontend data validation here if needed?

		setSubmitting(true);
		console.log('Saving membership tier');

		const endpoint = postId ? `${API_URL}/${tierCptSlug}/${postId}` : `${API_URL}/${tierCptSlug}`;

		// change max_seats to -1 if it is 0
		const productData = form.product_data.map((product) => {
			return {
				product_id: product.product_id,
				max_seats: parseInt(product.max_seats) === 0 ? -1 : product.max_seats,
				variation_id: product.variation_id
			}
		});

		// next_tier_id should be empty if current_tier is selected
		const newForm = {
			...form,
			product_data: productData,
			next_tier_id: form.renewal_type === 'current_tier' ? '' : form.next_tier_id,
		};

		apiFetch({
			path: endpoint,
			method: 'POST',
			data: {
				title: newForm.mdp_tier_name,
				status: 'publish',
				tier_data: newForm
			}
		}).then((response) => {
			console.log(response);
			if (response.id) {
				// Redirect to the cpt list page
				window.location.href = tierListUrl;
			}
		}).catch((error) => {
			let newErrors = [];

			Object.keys(error.data.params).forEach((key) => {
				let errors = error.data.params[key].split(/(?<=[.?!])\s+|\.$/);
				newErrors = newErrors.concat(errors).filter(sentence => sentence.trim() !== '');
			})

			setErrors(newErrors);
			setSubmitting(false);
		});
	}

  const fetchTierInfo = (tierUuid) => {
    if ( tierUuid.length === 0 ) { return }

    apiFetch({ path: addQueryArgs(`${PLUGIN_API_URL}/membership_tier_info`, {
      filter: {
        tier_uuid: [tierUuid]
      },
      'properties[]': 'count'
    }) }).then((tiersInfo) => {
      setTierInfo(tiersInfo.tier_data[tierUuid]);
		}).catch((error) => {
      console.log('Tier Info Error:');
      console.log(error);
		});
  }

	const handleMdpTierChange = (selected) => {
		const mdpTier = mdpTiers.find(tier => tier.uuid === selected.value);

		setForm({
			...form,
			mdp_tier_name: mdpTier.name,
			mdp_tier_uuid: mdpTier.uuid,
			type: mdpTier.type,
			product_data: []
		});

		fetchTierInfo(mdpTier.uuid);
	}

	const getMdpTierOptions = () => {
		return mdpTiers.map((tier) => {
			return {
				label: tier.name,
				value: tier.uuid
			}
		});
	}

	const handleIndividualGrantedViaChange = (selected) => {
		const productData = selected.map((product) => {
			return {
				product_id: product.value,
				max_seats: -1,
				variation_id: null
			}
		});

		setForm({
			...form,
			product_data: productData
		});
	}

	const handleOrganizationPerSeatGrantedViaChange = (selected) => {
		// Load variations if the selected product is a variable subscription
		const product = wcProductOptions.find(option => option.value === selected.value);

		if (product.type === 'variable-subscription') {
			getProductVariations(selected.value);
		}

		setForm({
			...form,
			product_data: [
				{
					product_id: selected.value,
					max_seats: -1,
					variation_id: null
				}
			]
		});
	}

	const handleOrganizationPerSeatVariationChange = (selected) => {

		setForm({
			...form,
			product_data: [
				{
					...form.product_data[0],
					variation_id: selected.value
				}
			]
		});

	}

	const initRangeOfSeatsProductModal = (rangeOfSeatsProductIndex) => {
		setCurrentRangeOfSeatsProductIndex(rangeOfSeatsProductIndex);

		// Clear errors
		setRangeOfSeatsProductErrors([]);

		if (rangeOfSeatsProductIndex === null) {
			// Adding new
			console.log('Add new product');
			setTempRangeOfSeatsProduct({
				product_id: null,
				max_seats: 0,
				variation_id: null
			});
		} else {
			// Editing existing product
			console.log('Editing existing product');
			const product = form.product_data[rangeOfSeatsProductIndex];
			setTempRangeOfSeatsProduct(product);
		}
		openRangeOfSeatsProductsModalOpen();
	}

	const validateRangeOfSeatsProduct = () => {
		let isValid = true;
		const newErrors = [];

		if (tempRangeOfSeatsProduct.product_id === null) {
			newErrors.push(__('Product is required', 'wicket-memberships'));
			isValid = false;
		}

		// check if the selected product is a variable subscription
		const product = wcProductOptions.find(option => option.value === tempRangeOfSeatsProduct.product_id);

		if (product.type === 'variable-subscription' && tempRangeOfSeatsProduct.variation_id === null) {
			newErrors.push(__('Variation is required', 'wicket-memberships'));
			isValid = false;
		}

		if (tempRangeOfSeatsProduct.max_seats < 0) {
			newErrors.push(__('Range maximum value cannot be less than 0', 'wicket-memberships'));
			isValid = false;
		}


		if (parseInt(tempRangeOfSeatsProduct.max_seats) === NaN) {
			newErrors.push(__('Range maximum value must be a number', 'wicket-memberships'));
			isValid = false;
		}

		setRangeOfSeatsProductErrors(newErrors);

		return isValid;
	}

	const validateApprovalCallout = () => {
		let isValid = true;
		const newErrors = [];

		// if (tempForm.approval_callout_data.callout_header.length === 0) {
		// 	newErrors.push(__('Callout Header is required', 'wicket-memberships'));
		// 	isValid = false;
		// }

		// if (tempForm.approval_callout_data.callout_content.length === 0) {
		// 	newErrors.push(__('Callout Content is required', 'wicket-memberships'));
		// 	isValid = false;
		// }

		// if (tempForm.approval_callout_data.callout_button_label.length === 0) {
		// 	newErrors.push(__('Callout Button Label is required', 'wicket-memberships'));
		// 	isValid = false;
		// }

		setApprovalCalloutErrors(newErrors);

		return isValid;
	}

	const handleApprovalCalloutSubmit = (e) => {
		e.preventDefault();

		setForm({
			...form,
			approval_callout_data: tempForm.approval_callout_data
		});

		if (!validateApprovalCallout()) { return }

		closeApprovalCalloutModal();
	}

	const handleRangeOfSeatsModalSubmit = (e) => {
		e.preventDefault();

		console.log('Saving product')

		if (!validateRangeOfSeatsProduct()) { return }

		if (currentRangeOfSeatsProductIndex === null) {
			setForm({
				...form,
				product_data: [
					...form.product_data,
					{
						product_id: tempRangeOfSeatsProduct.product_id,
						max_seats: tempRangeOfSeatsProduct.max_seats,
						variation_id: tempRangeOfSeatsProduct.variation_id
					}
				]
			});
		} else {
			const product_data = form.product_data.map((product, index) => {
				if (index === currentRangeOfSeatsProductIndex) {
					return {
						product_id: tempRangeOfSeatsProduct.product_id,
						max_seats: tempRangeOfSeatsProduct.max_seats,
						variation_id: tempRangeOfSeatsProduct.variation_id
					}
				}
				return product;
			});

			setForm({
				...form,
				product_data: product_data
			});
		}

		closeRangeOfSeatsProductsModalOpen()
	}

	const getMemberListUrl = () => {
		if (form.type === 'individual') {
			return individualListUrl;
		}

		return orgListUrl;
	}

	const handleRenewalTypeChange = (selected) => {
		const selectedValue = selected.value;

		setForm({
			...form,
			next_tier_id: '',
			next_tier_form_page_id: '',
			renewal_type: selectedValue
		});
	}

	/**
	 * Reinitialize the approval callout form with the current form data
	 */
	const reInitApprovalCallout = () => {
		setTempForm(form)
		openApprovalCalloutModal();
	}

	const getSelectedPerSeatVariationOption = () => {
		if ( productVariations[getSelectedPerSeatProductId()] === undefined ) { return null; }

		if ( getSelectedPerSeatProductVariationId() === null ) { return null; }

		const variation = productVariations[getSelectedPerSeatProductId()].find(variation => variation.id === getSelectedPerSeatProductVariationId());

		return {
			label: `#${variation.id}`,
			value: variation.id
		}
	}

	const getSelectedPerRangeSeatsVariationOption = () => {
		if ( productVariations[getSelectedPerRangeSeatsProductId()] === undefined ) { return null; }

		if ( getSelectedPerRangeSeatsProductVariationId() === null ) { return null; }

		const variation = productVariations[getSelectedPerRangeSeatsProductId()].find(variation => variation.id === getSelectedPerRangeSeatsProductVariationId());

		return {
			label: `#${variation.id}`,
			value: variation.id
		}
	}

	useEffect(() => {
		// Fetch WooCommerce products
		WC_PRODUCT_TYPES.forEach((type) => {
			fetchWcProducts({
				status: 'publish',
				per_page: 100,
				exclude: productsInUse,
				type: type
			}).then((products) => {
				const options = products.map((product) => {
					return {
						label: `${product.name} | ID: ${product.id}`,
						value: product.id,
						type: product.type
					}
				});
				setWcProductOptions((prevOptions) => [...prevOptions, ...options]);
			});
		});

		// Fetch Local WP Pages
		apiFetch({ path: addQueryArgs(`${API_URL}/pages`, {
			status: 'publish',
			per_page: -1
		}) }).then((tiers) => {
			let options = tiers.map((tier) => {
				const decodedTitle = he.decode(tier.title.rendered);
				return {
					label: `${decodedTitle} | ID: ${tier.id}`,
					value: tier.id
				}
			});

			setWpPagesOptions(options);
		});

		// Fetch Local Membership Tiers Posts
		apiFetch({ path: addQueryArgs(`${API_URL}/${tierCptSlug}`, { status: 'publish' }) }).then((tiers) => {
			let options = tiers.map((tier) => {
				const decodedTitle = he.decode(tier.title.rendered);
				return {
					label: `${decodedTitle} | ID: ${tier.id}`,
					value: tier.id
				}
			});

			setWpTierOptions(options);
		});

		// Fetch Membership Configs
		apiFetch({ path: addQueryArgs(`${API_URL}/${configCptSlug}`, { status: 'publish' }) }).then((configs) => {
			let options = configs.map((config) => {
				const decodedTitle = he.decode(config.title.rendered);
				return {
					label: `${decodedTitle} | ID: ${config.id}`,
					value: config.id
				}
			});

			setMembershipConfigOptions(options);
		});

		// Fetch MDP Tiers
		fetchMembershipTiers().then((tiers) => {
			setMdpTiers(
				tiers.map((tier) => {
					return {
						uuid: tier.uuid,
						name: tier.name,
						active: tier.status === 'Active' ? true : false,
						type: tier.type, // orgranization, individual
						grace_period_days: 0, // TODO: Update when grace period is added to MDP
						category: tier.category === null ? '' : tier.category,
					}
				})
			);
		});

		// Fetch the membership tier
		if (postId) {
			apiFetch({ path: addQueryArgs(`${API_URL}/${tierCptSlug}/${postId}`, { status: 'publish' }) }).then((post) => {
				console.log('Post:');
				console.log(post.tier_data);

				// change max_seats to 0 if it is -1
				const productData = post.tier_data.product_data.map((product) => {
					return {
						product_id: product.product_id,
						max_seats: parseInt(product.max_seats) === -1 ? 0 : product.max_seats,
						variation_id: product.variation_id
					}
				});

				// Load variations for the selected product id
				productData.forEach((product) => {
					if (product.variation_id) {
						getProductVariations(product.product_id);
					}
				});

				// Fetch the tier info to get the count of members
				fetchTierInfo(post.tier_data.mdp_tier_uuid);

				// Renewal type logic
				const nextTierFormPageId = post.tier_data.next_tier_form_page_id; // int value
				const nextTierId = post.tier_data.next_tier_id;

				let initialRenewalType = 'sequential_logic';

				if ( nextTierFormPageId !== 0 ) {
					initialRenewalType = 'form_flow';
				} else if ( nextTierId === parseInt(postId) ) {
					initialRenewalType = 'current_tier';
				}

				console.log('Initial Renewal Type:');
				console.log(initialRenewalType);

				setForm({
					...post.tier_data,
					product_data: productData,
					renewal_type: initialRenewalType
				});
			});
		}
	}, []);

	console.log('MDP Tiers:');
	console.log(mdpTiers);
	console.log('--------------');

	console.log('WP Tiers:');
	console.log(wpTierOptions);
	console.log('--------------');

	console.log('Products:');
	console.log(wcProductOptions);
	console.log('--------------');

	console.log('Configs:');
	console.log(membershipConfigOptions);
	console.log('--------------');

	console.log('Form:');
	console.log(form);
	console.log('--------------');

	console.log('Errors:');
	console.log(errors);
	console.log('--------------');

	console.log('Product Variations:');
	console.log(productVariations);
	console.log('--------------');

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">
					{postId ? __('Edit Membership Tier', 'wicket-memberships') : __('Add New Membership Tier', 'wicket-memberships')}
				</h1>
				<hr className="wp-header-end"></hr>

				<Wrap>
					{errors.length > 0 && (
						<ErrorsRow>
							{errors.map((error) => (
								<Notice isDismissible={false} key={error} status="warning">{error}</Notice>
							))}
						</ErrorsRow>
					)}
					<CustomDisabled
						isDisabled={!allRemoteDataLoaded() || isSubmitting}
					>
						<form onSubmit={handleSubmit}>
							<BorderedBox>
								<Flex
									justify='start'
									gap={5}
									direction={[
										'column',
										'row'
									]}
								>
									<FlexBlock>
										<LabelWpStyled htmlFor="mdp_tier">
											{__('Membership Tier', 'wicket-memberships')}
										</LabelWpStyled>
										<SelectWpStyled
											id="mdp_tier"
											classNamePrefix="select"
											value={getMdpTierOptions().find(option => option.value === form.mdp_tier_uuid)}
											isClearable={false}
											isSearchable={true}
											isLoading={getMdpTierOptions().length === 0}
											options={getMdpTierOptions()}
											onChange={handleMdpTierChange}
										/>
									</FlexBlock>
								</Flex>
								{getSelectedTierData() && (
									<>
										<ActionRow>
											<Flex
												align='start'
												justify='start'
												gap={5}
												direction={[
													'column',
													'row'
												]}
											>
												<FlexItem>
													<Text size={14} color="#3c434a" >
														{__('Status', 'wicket-memberships')}:&nbsp;
														<strong>{getSelectedTierData().active ? __('Active', 'wicket-memberships') : __('Inactive', 'wicket-memberships')}</strong>
													</Text>
												</FlexItem>
												<FlexItem>
													<Text size={14} color="#3c434a" >
														{__('Type', 'wicket-memberships')}:&nbsp;
														<strong>{getSelectedTierData().type === 'individual' ? __('Individual', 'wicket-memberships') : __('Organization', 'wicket-memberships')}</strong>
													</Text>
												</FlexItem>
												<FlexItem>
													<Text size={14} color="#3c434a" >
														{__('Category', 'wicket-memberships')}:&nbsp;
														<strong>{getSelectedTierData().category.length === 0 ? __('N/A', 'wicket-memberships') : getSelectedTierData().category}</strong>
													</Text>
												</FlexItem>
											</Flex>
											<MarginedFlex
												align='start'
												justify='start'
												gap={5}
												direction={[
													'column',
													'row'
												]}
											>
												<FlexItem>
													<Text size={14} color="#3c434a" >
														{__('Grace Period (Days)', 'wicket-memberships')}:&nbsp;
														<strong>{getSelectedTierData().grace_period_days}</strong>
													</Text>
												</FlexItem>
												<FlexItem>
													<Flex
														gap={4}
													>
														<FlexItem>
															<Text size={14} color="#3c434a" >
																{__('# of Members', 'wicket-memberships')}:&nbsp;
																{tierInfo === null && <>-</>}
																{tierInfo !== null && <strong>{tierInfo.count}</strong>}
															</Text>
														</FlexItem>
														<FlexItem>
															<Button
																variant="link"
																href={getMemberListUrl()}
																target='_blank'
															>
																{__('View All Members', 'wicket-memberships')}
															</Button>
														</FlexItem>
													</Flex>
												</FlexItem>
											</MarginedFlex>
										</ActionRow>
									</>
								)}
							</BorderedBox>
							{/* Other Controls */}
							{getSelectedTierData() && (
								<>
									<ActionRow>
										<Flex
											align='end'
											justify='start'
											gap={5}
											direction={[
												'column',
												'row'
											]}
										>
											<FlexBlock>
												<LabelWpStyled htmlFor="config_id">
													{__('Membership Config', 'wicket-memberships')}
												</LabelWpStyled>
												<SelectWpStyled
													id="config_id"
													classNamePrefix="select"
													value={membershipConfigOptions.find(option => option.value === form.config_id)}
													isClearable={false}
													isSearchable={true}
													options={membershipConfigOptions}
													onChange={(selected) => setForm({ ...form, config_id: selected.value })}
												/>
											</FlexBlock>
										</Flex>
									</ActionRow>
									<MarginedFlex>
										<FlexBlock>
											<BorderedBox>
												<Flex
													align='end'
													justify='start'
													gap={5}
													direction={[
														'column',
														'row'
													]}
												>
													<FlexItem>
														<CheckboxControl
															label={__('Approval Required', 'wicket-memberships')}
															checked={form.approval_required}
															onChange={(value) => setForm({ ...form, approval_required: value })}
															__nextHasNoMarginBottom={true}
														/>
													</FlexItem>
													<FlexBlock>
														<CustomDisabled isDisabled={!form.approval_required}>
															<TextControl
																label={__('Approval Email Recipient', 'wicket-memberships')}
																value={form.approval_email_recipient}
																type='email'
																onChange={(value) => setForm({ ...form, approval_email_recipient: value })}
																__nextHasNoMarginBottom={true}
															/>
														</CustomDisabled>
													</FlexBlock>
													<FlexItem>
														<Button
															variant="secondary"
															disabled={!form.approval_required}
															onClick={reInitApprovalCallout}
														>
															<span className="dashicons dashicons-screenoptions me-2"></span>&nbsp;
															{__('Callout Configuration', 'wicket-memberships')}
														</Button>
													</FlexItem>
												</Flex>
											</BorderedBox>
										</FlexBlock>
									</MarginedFlex>
									<MarginedFlex>
										<FlexBlock>
											<LabelWpStyled htmlFor="renewal_type">
												{__('Renewal Type', 'wicket-memberships')}
											</LabelWpStyled>
											<SelectWpStyled
												id="renewal_type"
												classNamePrefix="select"
												value={renewalTypeOptions.find(option => option.value === form.renewal_type)}
												isSearchable={true}
												options={renewalTypeOptions}
												onChange={handleRenewalTypeChange}
											/>
										</FlexBlock>
									</MarginedFlex>

									{/* Sequential Logic */}
									{form.renewal_type === 'sequential_logic' &&
										<MarginedFlex>
											<FlexBlock>
												<LabelWpStyled htmlFor="next_tier">
													{__('Sequential Tier', 'wicket-memberships')}
												</LabelWpStyled>
												<SelectWpStyled
													id="next_tier"
													classNamePrefix="select"
													value={wpTierOptions.find(option => option.value === form.next_tier_id)}
													isClearable={true}
													isSearchable={true}
													options={wpTierOptions}
													onChange={(selected) => {
														if (selected === null) {
															setForm({ ...form, next_tier_id: '' });
															return;
														}
														setForm({ ...form, next_tier_id: selected.value });
													}}
												/>
											</FlexBlock>
										</MarginedFlex>
									}

									{/* Sequential Logic */}
									{form.renewal_type === 'form_flow' &&
										<MarginedFlex>
											<FlexBlock>
												<LabelWpStyled htmlFor="next_tier_form">
													{__('Form Page', 'wicket-memberships')}
												</LabelWpStyled>
												<SelectWpStyled
													id="next_tier_form"
													classNamePrefix="select"
													value={wpPagesOptions.find(option => option.value === form.next_tier_form_page_id)}
													isSearchable={true}
													options={wpPagesOptions}
													onChange={(selected) => {
														setForm({ ...form, next_tier_form_page_id: selected.value });
													}}
												/>
											</FlexBlock>
										</MarginedFlex>
									}

									{getSelectedTierData().type === 'individual' && (
										<>
											<MarginedFlex>
												<FlexBlock>
													<LabelWpStyled htmlFor="seat_data">
														{__('Granted Via', 'wicket-memberships')}
													</LabelWpStyled>
													<SelectWpStyled
														id="seat_data"
														classNamePrefix="select"
														value={wcProductOptions.filter(option => form.product_data.map(product => product.product_id).includes(option.value))}
														isClearable={false}
														isMulti={true}
														isSearchable={true}
														isLoading={wcProductOptions.length === 0}
														options={wcProductOptions}
														onChange={handleIndividualGrantedViaChange}
													/>
												</FlexBlock>
											</MarginedFlex>
										</>
									)}
									{getSelectedTierData().type === 'organization' && (
										<>
											<BorderedBox>
												<Flex>
													<FlexBlock>
														<SelectControl
															label={__('Seat Settings', 'wicket-memberships')}
															value={form.seat_type}
															options={[
																{ label: __('Per Seat', 'wicket-memberships'), value: 'per_seat' },
																{ label: __('Per Range of Seats', 'wicket-memberships'), value: 'per_range_of_seats' }
															]}
															onChange={(selected) => {
																	setForm({
																		...form,
																		seat_type: selected,
																		product_data: [] //reset product data
																	});
																}
															}
														/>
													</FlexBlock>
												</Flex>

												{form.seat_type === 'per_seat' && (
													<>
														<MarginedFlex>
															<FlexBlock>
																<LabelWpStyled htmlFor="seat_data_per_seat">
																	{__('Product', 'wicket-memberships')}
																</LabelWpStyled>
																<SelectWpStyled
																	id="seat_data_per_seat"
																	classNamePrefix="select"
																	value={wcProductOptions.find(option => getSelectedPerSeatProductId() === option.value)}
																	isClearable={false}
																	isSearchable={true}
																	isLoading={wcProductOptions.length === 0}
																	options={wcProductOptions}
																	onChange={handleOrganizationPerSeatGrantedViaChange}
																/>
															</FlexBlock>
														</MarginedFlex>

														{getSelectedPerSeatProductType() === 'variable-subscription' && (
														<MarginedFlex>
															<FlexBlock>
																<LabelWpStyled htmlFor="variation_id_per_seat">
																	{__('Variation', 'wicket-memberships')}
																</LabelWpStyled>
																<SelectWpStyled
																	id="variation_id_per_seat"
																	classNamePrefix="select"
																	value={getSelectedPerSeatVariationOption()}
																	isClearable={false}
																	isSearchable={true}
																	isLoading={productVariations[getSelectedPerSeatProductId()] === undefined}
																	options={productVariations[getSelectedPerSeatProductId()] ? productVariations[getSelectedPerSeatProductId()].map((variation) => {
																		return {
																			label: `#${variation.id}`,
																			value: variation.id
																		}
																	}) : []}

																	onChange={handleOrganizationPerSeatVariationChange}
																/>
															</FlexBlock>
														</MarginedFlex>
														)}
													</>
												)}

												{form.seat_type === 'per_range_of_seats' && (
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
																	onClick={() => initRangeOfSeatsProductModal(null)}
																>
																	<Icon icon="plus" />&nbsp;
																	{__('Add Product', 'wicket-memberships')}
																</Button>
															</FlexBlock>
														</MarginedFlex>

														{/* Seats Data Table */}
														<FormFlex>
															<Heading level='4' weight='300' >
																{__('Seats Data', 'wicket-memberships')}
															</Heading>
														</FormFlex>
														<FormFlex>
															<table className="widefat" cellSpacing="0">
																<thead>
																	<tr>
																		<th className="manage-column column-columnname" scope="col">
																			{__('Product Name', 'wicket-memberships')}
																		</th>
																		<th className="manage-column column-columnname" scope="col">
																			{__('Range Max', 'wicket-memberships')}
																		</th>
																		<th className="manage-column column-columnname" scope="col">
																			{__('Variation', 'wicket-memberships')}
																		</th>
																		<th className='check-column'></th>
																	</tr>
																</thead>
																<tbody>
																	{form.product_data.map((product, index) => (
																			<tr key={index} className={index % 2 === 0 ? 'alternate' : ''}>
																				<td className="column-columnname">
																					{wcProductOptions.find(option => option.value === product.product_id).label}
																				</td>
																				<td className="column-columnname">
																					{product.max_seats}
																				</td>
																				<td className="column-columnname">
																					{product.variation_id ? `#${product.variation_id}` : '-'}
																				</td>
																				<td>
																					<Button
																						onClick={() => {
																							initRangeOfSeatsProductModal(index)
																						}}
																					>
																						<span className="dashicons dashicons-edit"></span>
																					</Button>
																				</td>
																			</tr>
																		)
																	)}
																</tbody>
															</table>
														</FormFlex>
													</>
												)}
											</BorderedBox>
										</>
									)}
								</>
							)}
							{/* Submit row */}
							<ActionRow>
								<Flex
									align='end'
									justify='end'
									gap={5}
									direction={[
										'column',
										'row'
									]}
								>
									<FlexItem>
										<Button
											isBusy={isSubmitting}
											disabled={isSubmitting}
											variant="primary"
											type='submit'
										>
											{isSubmitting && __('Saving now...', 'wicket-memberships')}
											{!isSubmitting && __('Save Membership Tier', 'wicket-memberships')}
										</Button>
									</FlexItem>
								</Flex>
							</ActionRow>
						</form>
					</CustomDisabled>
				</Wrap>
			</div>

			{/* Approval - Callout Modal */}
			{isApprovalCalloutModalOpen && (
				<Modal
					title={__('Approval - Callout Configuration', 'wicket-memberships')}
					onRequestClose={closeApprovalCalloutModal}
					style={
						{
							maxWidth: '840px',
							width: '100%'
						}
					}
	>

					{approvalCalloutErrors.length > 0 && (
						<ErrorsRow>
							{approvalCalloutErrors.map((error) => (
								<Notice isDismissible={false} key={error} status="warning">{error}</Notice>
							))}
						</ErrorsRow>
					)}

					<form onSubmit={handleApprovalCalloutSubmit}>
						<SelectControl
							label={__('Language', 'wicket-memberships')}
							options={
								languageCodesArray.map((code) => {
									return {
										label: code,
										value: code
									}
								})
							}
							value={currentApprovalCalloutLocale}
							onChange={value => setCurrentApprovalCalloutLocale(value)}
						/>

						<TextControl
							label={__('Callout Header', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									approval_callout_data: {
										...tempForm.approval_callout_data,
										locales: {
											...tempForm.approval_callout_data.locales,
											[currentApprovalCalloutLocale]: {
												...tempForm.approval_callout_data.locales[currentApprovalCalloutLocale],
												callout_header: value
											}
										}
									}
								});
							}}
							value={tempForm.approval_callout_data.locales[currentApprovalCalloutLocale].callout_header}
						/>

						<TextareaControl
							label={__('Callout Content', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									approval_callout_data: {
										...tempForm.approval_callout_data,
										locales: {
											...tempForm.approval_callout_data.locales,
											[currentApprovalCalloutLocale]: {
												...tempForm.approval_callout_data.locales[currentApprovalCalloutLocale],
												callout_content: value
											}
										}
									}
								});
							}}
							value={tempForm.approval_callout_data.locales[currentApprovalCalloutLocale].callout_content}
						/>

						<TextControl
							label={__('Button Label', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									approval_callout_data: {
										...tempForm.approval_callout_data,
										locales: {
											...tempForm.approval_callout_data.locales,
											[currentApprovalCalloutLocale]: {
												...tempForm.approval_callout_data.locales[currentApprovalCalloutLocale],
												callout_button_label: value
											}
										}
									}
								});
							}}
							value={tempForm.approval_callout_data.locales[currentApprovalCalloutLocale].callout_button_label}
						/>

						<Button variant="primary" type='submit'>
							{__('Save', 'wicket-memberships')}
						</Button>
					</form>
				</Modal>
			)}

			{/* Add "Range of Seats" Product Modal */}
			{isRangeOfSeatsProductsModalOpen && (
				<Modal
					title={currentRangeOfSeatsProductIndex === null ? __('Add Product', 'wicket-memberships') : __('Edit Product', 'wicket-memberships')}
					onRequestClose={closeRangeOfSeatsProductsModalOpen}
					style={
						{
							maxWidth: '840px',
							width: '100%'
						}
					}
				>
					<form onSubmit={handleRangeOfSeatsModalSubmit}>

						{rangeOfSeatsProductErrors.length > 0 && (
							<ErrorsRow>
								{rangeOfSeatsProductErrors.map((errorMessage, index) => (
									<Notice isDismissible={false} key={index} status="warning">{errorMessage}</Notice>
								))}
							</ErrorsRow>
						)}

						<LabelWpStyled htmlFor="range_of_seats_product_id">{__('Product', 'wicket-memberships')}</LabelWpStyled>
						<SelectWpStyled
							id="range_of_seats_product_id"
							classNamePrefix="select"
							value={wcProductOptions.find(option => option.value === tempRangeOfSeatsProduct.product_id)}
							isClearable={false}
							isSearchable={true}
							isLoading={wcProductOptions.length === 0}
							options={wcProductOptions}
							onChange={selected => {
								setTempRangeOfSeatsProduct({
									...tempRangeOfSeatsProduct,
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

						{getSelectedPerRangeOfSeatsProductType() === 'variable-subscription' && (
							<MarginedFlex>
								<FlexBlock>
								<LabelWpStyled htmlFor="range_of_seats_variation_id">{__('Variable', 'wicket-memberships')}</LabelWpStyled>
								<SelectWpStyled
									id="range_of_seats_variation_id"
									classNamePrefix="select"
									value={getSelectedPerRangeSeatsVariationOption()}
									isClearable={false}
									isSearchable={true}
									isLoading={productVariations[getSelectedPerRangeSeatsProductId()] === undefined}
									options={productVariations[getSelectedPerRangeSeatsProductId()] ? productVariations[getSelectedPerRangeSeatsProductId()].map((variation) => {
										return {
											label: `#${variation.id}`,
											value: variation.id
										}
									}) : []}
									onChange={selected => {
										setTempRangeOfSeatsProduct({
											...tempRangeOfSeatsProduct,
											variation_id: selected.value
										});
									}}
								/>
								</FlexBlock>
							</MarginedFlex>
						)}

						<MarginedFlex>
							<FlexBlock>
								<TextControl
									label={__('Range Maximum (USE 0 FOR UNLIMITED)', 'wicket-memberships')}
									type="number"
									min={0}
									onChange={value => {
										setTempRangeOfSeatsProduct({
											...tempRangeOfSeatsProduct,
											max_seats: value
										});
									}}
									value={tempRangeOfSeatsProduct.max_seats}
								/>
							</FlexBlock>
						</MarginedFlex>

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
									{currentRangeOfSeatsProductIndex !== null && (
										<Button
											isDestructive={true}
											onClick={() => {
												const productData = form.product_data.filter((_, index) => index !== currentRangeOfSeatsProductIndex);

												setForm({
													...form,
													product_data: productData
												});

												closeRangeOfSeatsProductsModalOpen();
											}}
										>
											<Icon icon="archive" />&nbsp;
											{__('Delete', 'wicket-memberships')}
										</Button>
									)}
								</FlexItem>
								<FlexItem>
									<Button variant="primary" type='submit'>
										{currentRangeOfSeatsProductIndex === null ? __('Add Product', 'wicket-memberships') : __('Update Product', 'wicket-memberships')}
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

const app = document.getElementById('create_membership_tier');
if (app) {
	createRoot(app).render(<CreateMembershipTier {...app.dataset} />);
}