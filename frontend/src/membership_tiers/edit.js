import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, TextareaControl, Button, Flex, FlexItem, Modal, FlexBlock, Notice, SelectControl, CheckboxControl, __experimentalText as Text } from '@wordpress/components';
import styled from 'styled-components';
import { API_URL, PLUGIN_API_URL, PLUGIN_SETTINGS } from '../constants';
import he from 'he';
import { Wrap, ErrorsRow, BorderedBox, LabelWpStyled, SelectWpStyled, ActionRow, CustomDisabled } from '../styled_elements';
import { fetchMembershipTiers, fetchProductVariations } from '../services/api';
import { fetchSwitchTargetProducts } from '../services/switch_products';
import ManageTierProducts from './manage_products';

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

const CreateMembershipTier = ({ tierCptSlug, configCptSlug, tierListUrl, postId, productsInUse, productVariationsInUse, individualListUrl, orgListUrl, languageCodes }) => {

	const languageCodesArray = languageCodes.split(',');
	const productVariationsInUseArray = productVariationsInUse.split(',');

	const [currentApprovalCalloutLocale, setCurrentApprovalCalloutLocale] = useState(languageCodesArray[0]);

	const renewalTypeOptions = [
		{ label: __('Current Tier', 'wicket-memberships'), value: 'current_tier' },
		{ label: __('Sequential Logic', 'wicket-memberships'), value: 'sequential_logic' },
		{ label: __('Renewal Form Flow', 'wicket-memberships'), value: 'form_flow' },
		{ label: __('Subscription', 'wicket-memberships'), value: 'subscription' }
	];

	const switchTypeOptions = [
		{ label: __('Specific Tier', 'wicket-memberships'), value: 'specific_tier' },
		{ label: __('Form Flow', 'wicket-memberships'), value: 'form_flow' }
	];

	const [isApprovalCalloutModalOpen, setApprovalCalloutModalOpen] = useState(false);
	const openApprovalCalloutModal = () => setApprovalCalloutModalOpen(true);
	const closeApprovalCalloutModal = () => setApprovalCalloutModalOpen(false);

	const [currentSwitchCalloutLocale, setCurrentSwitchCalloutLocale] = useState(languageCodesArray[0]);

	const [isSwitchCalloutModalOpen, setSwitchCalloutModalOpen] = useState(false);
	const openSwitchCalloutModal = () => setSwitchCalloutModalOpen(true);
	const closeSwitchCalloutModal = () => setSwitchCalloutModalOpen(false);

	const [switchProductOptions, setSwitchProductOptions] = useState([]); // { label, value, type }
	const [isLoadingSwitchProducts, setLoadingSwitchProducts] = useState(false);
	// Variation ids linked to THIS tier — hidden from the target variation picker so the switch
	// destination cannot be the variation members are already on.
	const [switchExcludedVariationIds, setSwitchExcludedVariationIds] = useState(new Set());
	const [switchProductVariations, setSwitchProductVariations] = useState({}); // { product_id: [] }

	const [tierInfo, setTierInfo] = useState(null);

	const [approvalCalloutErrors, setApprovalCalloutErrors] = useState([]);

	const [isSubmitting, setSubmitting] = useState(false);

	const [mdpTiers, setMdpTiers] = useState([]);

	const [wpTierOptions, setWpTierOptions] = useState([]); // { id, name }

	const [wpPagesOptions, setWpPagesOptions] = useState([]); // { id, name }

	const [membershipConfigOptions, setMembershipConfigOptions] = useState([]); // { id, name }

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
		grant_owner_assignment: false,
		copy_active_assignments_on_renewal: true,
		approval_required: false,
		renew_approval_required: false,
		approval_email_recipient: '',
		mdp_tier_name: '',
		mdp_tier_uuid: '',
		next_tier_id: '',
		next_tier_form_page_id: '',
		config_id: '',
		renewal_type: 'current_tier', // current_tier, sequential_logic, form_flow, subscription
		type: '', // orgranization, individual
		seat_type: 'per_seat', // per_seat, per_range_of_seats
		product_data: [], // { product_id:, max_seats:, variation_id: }
		approval_callout_data: {
			locales: default_locales
		},
		self_serve_switch_enabled: false,
		switch_type: 'specific_tier', // specific_tier, form_flow
		switch_target_product_id: '',
		switch_target_variation_id: '',
		switch_form_flow_page_id: '',
		switch_callout_data: {
			locales: default_locales
		}
	});

	const [tempForm, setTempForm] = useState(form);

	const getSelectedTierData = () => {
		if (!form.mdp_tier_uuid) { return null; }
		const selectedTier = mdpTiers.find(tier => tier.uuid === form.mdp_tier_uuid);

		return selectedTier;
	}

	const allRemoteDataLoaded = () => {
		return mdpTiers.length > 0 && membershipConfigOptions.length > 0;
	}

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
    // all next tier ids should be cleared if subscription selected
		const newForm = {
			...form,
			product_data: productData,
			next_tier_id: form.renewal_type === 'current_tier' || form.renewal_type === 'subscription' ? '' : form.next_tier_id,
			next_tier_form_page_id: form.renewal_type === 'sequential_logic' || form.renewal_type === 'subscription' ? '' : form.next_tier_form_page_id,
		};

		// copy_active_assignments_on_renewal only applies to organization tiers;
		// strip it from individual/person tiers to avoid persisting dead data.
		if (form.type !== 'organization') {
			delete newForm.copy_active_assignments_on_renewal;
		}

		// Self-serve switch: only the destination the selected switch type uses may be persisted —
		// the REST validate_callback rejects a payload carrying both. With the toggle off, drop the
		// destination and the callout copy so a disabled tier stores no dead switch config.
		if (!form.self_serve_switch_enabled) {
			newForm.switch_target_product_id = '';
			newForm.switch_target_variation_id = '';
			newForm.switch_form_flow_page_id = '';
			// Dropped rather than blanked: a tier with switching off should carry no callout object
			// at all, so saving one never materializes empty copy on a tier that never had it.
			delete newForm.switch_callout_data;
		} else if (form.switch_type === 'specific_tier') {
			newForm.switch_form_flow_page_id = '';
		} else if (form.switch_type === 'form_flow') {
			newForm.switch_target_product_id = '';
			newForm.switch_target_variation_id = '';
		}

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

	const validateApprovalCallout = () => {
		let isValid = true;
		const newErrors = [];

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

	/**
	 * Reinitialize the switch callout form with the current form data
	 */
	const reInitSwitchCallout = () => {
		setTempForm(form)
		openSwitchCalloutModal();
	}

	/**
	 * Read one locale's switch callout copy, tolerating a tier that has none.
	 *
	 * Tiers predating self-serve switching have no switch_callout_data at all, and a language added
	 * to the site after a tier was last saved has no entry inside it. Both cases resolve to an empty
	 * record so the copy inputs render blank rather than throwing on undefined.
	 */
	const getSwitchCalloutLocale = (source, locale) => {
		if (!source || !source.switch_callout_data || !source.switch_callout_data.locales) { return {}; }

		return source.switch_callout_data.locales[locale] || {};
	}

	/**
	 * Write one field of the current locale's switch callout copy onto tempForm, creating the
	 * switch_callout_data / locales objects on first keystroke when the tier has none.
	 */
	const updateSwitchCalloutField = (field, value) => {
		setTempForm((previous) => ({
			...previous,
			switch_callout_data: {
				...previous.switch_callout_data,
				locales: {
					...((previous.switch_callout_data && previous.switch_callout_data.locales) || {}),
					[currentSwitchCalloutLocale]: {
						...getSwitchCalloutLocale(previous, currentSwitchCalloutLocale),
						[field]: value
					}
				}
			}
		}));
	}

	const handleSwitchCalloutSubmit = (e) => {
		e.preventDefault();

		setForm({
			...form,
			switch_callout_data: tempForm.switch_callout_data
		});

		closeSwitchCalloutModal();
	}

	const handleSwitchTypeChange = (selected) => {
		// Reset both destinations: only the one belonging to the newly selected type may be saved.
		setForm({
			...form,
			switch_type: selected.value,
			switch_target_product_id: '',
			switch_target_variation_id: '',
			switch_form_flow_page_id: ''
		});
	}

	/**
	 * Fetch variations for the selected switch target product id
	 */
	const getSwitchProductVariations = (productId) => {
		if (!productId || switchProductVariations[productId]) { return; }

		fetchProductVariations(
			productId,
			{
				per_page: 100,
				status: 'publish'
			}).then((variations) => {
			setSwitchProductVariations((previous) => ({
				...previous,
				[productId]: variations
			}));
		});
	}

	const handleSwitchTargetProductChange = (selected) => {
		const productId = selected ? selected.value : '';
		const product = switchProductOptions.find(option => option.value === productId);

		setForm({
			...form,
			switch_target_product_id: productId,
			switch_target_variation_id: ''
		});

		// Only a variable subscription needs a second pick; a simple subscription is tier-linked by
		// its parent product alone.
		if (product && product.type === 'variable-subscription') {
			getSwitchProductVariations(productId);
		}
	}

	const updateProductData = (productData) => {
		setForm({
			...form,
			product_data: productData
		});
	}

	useEffect(() => {

		// Fetch Local WP Pages (via plugin endpoint so pages with visibility
		// restrictions from plugins like WP Private Content Plus are still listed)
		apiFetch({ path: `${PLUGIN_API_URL}/wp_pages_all` }).then((pages) => {
			let options = pages.map((page) => {
				const decodedTitle = he.decode(page.title.rendered);
				return {
					label: `${decodedTitle} | ID: ${page.id}`,
					value: page.id
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
        let multitier = '';
				const decodedTitle = he.decode(config.title.rendered);
        if(config.multi_tier_renewal && PLUGIN_SETTINGS.WICKET_MSHIP_MULTI_TIER_RENEWALS) {
          multitier = '| Multiple Tier Enabled';
        }
				return {
					label: `${decodedTitle} | ID: ${config.id} ${multitier}`,
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

				// Fetch the tier info to get the count of members
				fetchTierInfo(post.tier_data.mdp_tier_uuid);

				// Renewal type logic
				const nextTierFormPageId = post.tier_data.next_tier_form_page_id; // int value
				const nextTierId = post.tier_data.next_tier_id;

				let initialRenewalType = 'sequential_logic';

				if ( post.tier_data.renewal_type === 'subscription') {
					initialRenewalType = 'subscription';
				} else if ( nextTierFormPageId !== 0 ) {
					initialRenewalType = 'form_flow';
				} else if ( nextTierId === parseInt(postId) ) {
					initialRenewalType = 'current_tier';
				}

				console.log('Initial Renewal Type:');
				console.log(initialRenewalType);

				// Tiers saved before self-serve switching existed carry none of the switch_* keys. They
				// are loaded as-is: the switch controls read through guards instead, so nothing is
				// written back onto a tier the admin never configured for switching.
				setForm({
					...post.tier_data,
					copy_active_assignments_on_renewal: post.tier_data.copy_active_assignments_on_renewal ?? true,
					product_data: productData,
					renewal_type: initialRenewalType
				});
			});
		}
	}, []);

	// Load the self-serve switch target products lazily — only once the admin has enabled the switch,
	// chosen the specific-tier type, and selected an MDP tier (whose type drives the same-family filter).
	useEffect(() => {
		const selectedTierData = getSelectedTierData();

		if (!form.self_serve_switch_enabled || form.switch_type !== 'specific_tier' || !selectedTierData) {
			return;
		}

		setLoadingSwitchProducts(true);

		fetchSwitchTargetProducts({
			membershipType: selectedTierData.type,
			currentTierPostId: postId
		}).then(({ options, excludedVariationIds }) => {
			setSwitchProductOptions(options);
			setSwitchExcludedVariationIds(excludedVariationIds);

			// A saved variable-subscription target needs its variations fetched up front, otherwise the
			// variation picker renders blank instead of the stored selection.
			const savedTarget = options.find(option => option.value === form.switch_target_product_id);
			if (savedTarget && savedTarget.type === 'variable-subscription') {
				getSwitchProductVariations(savedTarget.value);
			}
		}).catch(() => {
			setSwitchProductOptions([]);
		}).finally(() => {
			setLoadingSwitchProducts(false);
		});
	}, [form.self_serve_switch_enabled, form.switch_type, form.mdp_tier_uuid, mdpTiers]);

	console.log('MDP Tiers:');
	console.log(mdpTiers);
	console.log('--------------');

	console.log('WP Tiers:');
	console.log(wpTierOptions);
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
													<Flex direction="column" gap={2}>
														<FlexItem>
															<CheckboxControl
																label={__('Approval Required', 'wicket-memberships')}
																checked={form.approval_required}
																onChange={(value) => setForm({ ...form, approval_required: value })}
																__nextHasNoMarginBottom={true}
															/>
														</FlexItem>
														<FlexItem>
															<CheckboxControl
																label={__('Renew Approval Required', 'wicket-memberships')}
																checked={form.renew_approval_required}
																onChange={(value) => setForm({ ...form, renew_approval_required: value })}
																__nextHasNoMarginBottom={true}
															/>
														</FlexItem>
													</Flex>
												</FlexItem>
													<FlexBlock>
														<CustomDisabled isDisabled={!form.approval_required && !form.renew_approval_required}>
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
															disabled={!form.approval_required && !form.renew_approval_required}
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

									{/* Self-Serve Membership Switch */}
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
															label={__('Enable Self-Serve Membership Switch', 'wicket-memberships')}
															/* Coerced: a tier saved before this feature has no such key, and an
															   undefined `checked` would make this an uncontrolled input. */
															checked={!!form.self_serve_switch_enabled}
															onChange={(value) => setForm({ ...form, self_serve_switch_enabled: value })}
															__nextHasNoMarginBottom={true}
														/>
													</FlexItem>
													<FlexBlock>
														<CustomDisabled isDisabled={!form.self_serve_switch_enabled}>
															<LabelWpStyled htmlFor="switch_type">
																{__('Switch Type', 'wicket-memberships')}
															</LabelWpStyled>
															<SelectWpStyled
																id="switch_type"
																classNamePrefix="select"
																/* A tier with no stored switch_type shows the placeholder until the
																   admin picks one; the destination fields stay hidden until then. */
																value={switchTypeOptions.find(option => option.value === form.switch_type) || null}
																isClearable={false}
																isSearchable={true}
																options={switchTypeOptions}
																onChange={handleSwitchTypeChange}
															/>
														</CustomDisabled>
													</FlexBlock>
													<FlexItem>
														<Button
															variant="secondary"
															disabled={!form.self_serve_switch_enabled}
															onClick={reInitSwitchCallout}
														>
															<span className="dashicons dashicons-screenoptions me-2"></span>&nbsp;
															{__('Callout Configuration', 'wicket-memberships')}
														</Button>
													</FlexItem>
												</Flex>

												{/* Specific Tier — the switch order buys this product */}
												{form.self_serve_switch_enabled && form.switch_type === 'specific_tier' && (
													<MarginedFlex>
														<FlexBlock>
															<LabelWpStyled htmlFor="switch_target_product_id">
																{__('Switch Target Product', 'wicket-memberships')}
															</LabelWpStyled>
															<SelectWpStyled
																id="switch_target_product_id"
																classNamePrefix="select"
																value={switchProductOptions.find(option => option.value === form.switch_target_product_id) || null}
																isClearable={false}
																isSearchable={true}
																isLoading={isLoadingSwitchProducts}
																options={switchProductOptions}
																onChange={handleSwitchTargetProductChange}
															/>
														</FlexBlock>
													</MarginedFlex>
												)}

												{/* A variable subscription target needs the exact variation the switch buys */}
												{form.self_serve_switch_enabled && form.switch_type === 'specific_tier' && (() => {
													const targetProduct = switchProductOptions.find(option => option.value === form.switch_target_product_id);

													if (!targetProduct || targetProduct.type !== 'variable-subscription') { return null; }

													const variations = switchProductVariations[form.switch_target_product_id];
													const variationOptions = (variations || [])
														// Hide this tier's own variation — the switch must land somewhere else.
														.filter((variation) => !switchExcludedVariationIds.has(variation.id))
														.map((variation) => ({
															label: `${variation.name} (#${variation.id})`,
															value: variation.id
														}));

													return (
														<MarginedFlex>
															<FlexBlock>
																<LabelWpStyled htmlFor="switch_target_variation_id">
																	{__('Switch Target Variation', 'wicket-memberships')}
																</LabelWpStyled>
																<SelectWpStyled
																	id="switch_target_variation_id"
																	classNamePrefix="select"
																	value={variationOptions.find(option => option.value === form.switch_target_variation_id) || null}
																	isClearable={false}
																	isSearchable={true}
																	isLoading={variations === undefined}
																	options={variationOptions}
																	onChange={(selected) => {
																		setForm({ ...form, switch_target_variation_id: selected ? selected.value : '' });
																	}}
																/>
															</FlexBlock>
														</MarginedFlex>
													);
												})()}

												{/* Form Flow — the callout links members to this page instead of the cart */}
												{form.self_serve_switch_enabled && form.switch_type === 'form_flow' && (
													<MarginedFlex>
														<FlexBlock>
															<LabelWpStyled htmlFor="switch_form_flow_page">
																{__('Switch Form Page', 'wicket-memberships')}
															</LabelWpStyled>
															<SelectWpStyled
																id="switch_form_flow_page"
																classNamePrefix="select"
																value={wpPagesOptions.find(option => option.value === form.switch_form_flow_page_id) || null}
																isSearchable={true}
																options={wpPagesOptions}
																onChange={(selected) => {
																	setForm({ ...form, switch_form_flow_page_id: selected.value });
																}}
															/>
														</FlexBlock>
													</MarginedFlex>
												)}
											</BorderedBox>
										</FlexBlock>
									</MarginedFlex>

									{getSelectedTierData().type === 'individual' && (
										<>
											<ManageTierProducts
												saveProductChanges={updateProductData}
												products={form.product_data}
												limit={99}
												productsInUse={productsInUse}
												productVariationsInUse={productVariationsInUseArray}
												productListLabel={__('Granted Via', 'wicket-memberships')}
											/>
										</>
									)}
									{getSelectedTierData().type === 'organization' && (
										<>
											<BorderedBox>
                      <Flex gap={10}>
                        <FlexItem style={{ flex: 1 }}>
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
                                product_data: [] // reset product data
                              });
                            }}
                          />
                        </FlexItem>
                        <FlexItem style={{ flex: 1, marginTop: '16px' }} >
                          <CheckboxControl
                            label={__('Automatically Grant Owner Seat', 'wicket-memberships')}
                            checked={form.grant_owner_assignment}
                            onChange={(value) => setForm({ ...form, grant_owner_assignment: value })}
                            __nextHasNoMarginBottom={true}
                          />
                          <div style={{ marginTop: '8px' }}>
                            <CheckboxControl
                              label={__('Copy active assignments on renewal', 'wicket-memberships')}
                              help={__('When off, renewing this tier will not copy people from the previous membership.', 'wicket-memberships')}
                              checked={form.copy_active_assignments_on_renewal}
                              onChange={(value) => setForm({ ...form, copy_active_assignments_on_renewal: value })}
                              __nextHasNoMarginBottom={true}
                            />
                          </div>
                        </FlexItem>
                      </Flex>

												{form.seat_type === 'per_seat' && (
													<>
														<ManageTierProducts
															saveProductChanges={updateProductData}
															limit={99}
															products={form.product_data}
															productsInUse={productsInUse}
															productVariationsInUse={productVariationsInUseArray}
														/>
													</>
												)}

												{form.seat_type === 'per_range_of_seats' && (
													<>
														<ManageTierProducts
															saveProductChanges={updateProductData}
															maxRangeEnabled={true}
															products={form.product_data}
															productsInUse={productsInUse}
															productVariationsInUse={productVariationsInUseArray}
															productListLabel={'Seats Data'}
														/>
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

			{/* Self-Serve Switch - Callout Modal */}
			{isSwitchCalloutModalOpen && (
				<Modal
					title={__('Self-Serve Switch - Callout Configuration', 'wicket-memberships')}
					onRequestClose={closeSwitchCalloutModal}
					style={
						{
							maxWidth: '840px',
							width: '100%'
						}
					}
				>
					<form onSubmit={handleSwitchCalloutSubmit}>
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
							value={currentSwitchCalloutLocale}
							onChange={value => setCurrentSwitchCalloutLocale(value)}
						/>

						<TextControl
							label={__('Callout Header', 'wicket-memberships')}
							onChange={value => updateSwitchCalloutField('callout_header', value)}
							value={getSwitchCalloutLocale(tempForm, currentSwitchCalloutLocale).callout_header || ''}
						/>

						<TextareaControl
							label={__('Callout Content', 'wicket-memberships')}
							onChange={value => updateSwitchCalloutField('callout_content', value)}
							value={getSwitchCalloutLocale(tempForm, currentSwitchCalloutLocale).callout_content || ''}
						/>

						<TextControl
							label={__('Button Label', 'wicket-memberships')}
							onChange={value => updateSwitchCalloutField('callout_button_label', value)}
							value={getSwitchCalloutLocale(tempForm, currentSwitchCalloutLocale).callout_button_label || ''}
						/>

						<Button variant="primary" type='submit'>
							{__('Save', 'wicket-memberships')}
						</Button>
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