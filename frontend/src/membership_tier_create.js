import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, Button, Flex, FlexItem, Modal, FlexBlock, Notice, SelectControl, CheckboxControl, __experimentalHeading as Heading, Icon, __experimentalText as Text } from '@wordpress/components';
import styled from 'styled-components';
import { API_URL, MDP_API_URL } from './constants';
import he from 'he';
import { Wrap, ErrorsRow, BorderedBox, LabelWpStyled, SelectWpStyled, ActionRow, FormFlex, CustomDisabled } from './styled_elements';

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

const CreateMembershipTier = ({ tierCptSlug, configCptSlug, tierListUrl, postId, productsInUse }) => {

	const [isRangeOfSeatsProductsModalOpen, setRangeOfSeatsProductsModalOpen] = useState(false);

	const openRangeOfSeatsProductsModalOpen = () => setRangeOfSeatsProductsModalOpen(true);

	const closeRangeOfSeatsProductsModalOpen = () => setRangeOfSeatsProductsModalOpen(false);

	const [currentRangeOfSeatsProductIndex, setCurrentRangeOfSeatsProductIndex] = useState(null);

	const [rangeOfSeatsProductErrors, setRangeOfSeatsProductErrors] = useState([]);

	const [tempRangeOfSeatsProduct, setTempRangeOfSeatsProduct] = useState({
		product_id: null,
		max_seats: 0
	});

	const [isSubmitting, setSubmitting] = useState(false);

	const [mdpTiers, setMdpTiers] = useState([]);

	const [wpTierOptions, setWpTierOptions] = useState([]); // { id, name }

	const [membershipConfigOptions, setMembershipConfigOptions] = useState([]); // { id, name }

	const [wcProductOptions, setWcProductOptions] = useState([]); // { id, name }

	const [errors, setErrors] = useState([]); // Array of strings

	const [form, setForm] = useState({
		approval_required: false,
		approval_email_recipient: '',
		mdp_tier_name: '',
		mdp_tier_uuid: '',
		next_tier_id: '',
		config_id: '',
		type: '', // orgranization, individual
		seat_type: 'per_seat', // per_seat, per_range_of_seats
		product_data: [] // { product_id:, max_seats: }
	});

	const getSelectedTierData = () => {
		if (!form.mdp_tier_uuid) { return null; }
		const selectedTier = mdpTiers.find(tier => tier.uuid === form.mdp_tier_uuid);

		return selectedTier;
	};

	const allRemoteDataLoaded = () => {
		return mdpTiers.length > 0 && membershipConfigOptions.length > 0 && wcProductOptions.length > 0;
	}

	const getSelectedPerSeatProductId = () => {
		if (form.product_data.length === 0) { return null; }

		return form.product_data[0].product_id;
	};

	const handleSubmit = (e) => {
		e.preventDefault();

		// TODO: Frontend data validation here if needed

		setSubmitting(true);
		console.log('Saving membership tier');

		const endpoint = postId ? `${API_URL}/${tierCptSlug}/${postId}` : `${API_URL}/${tierCptSlug}`;

		// change max_seats to -1 if it is 0
		const productData = form.product_data.map((product) => {
			return {
				product_id: product.product_id,
				max_seats: parseInt(product.max_seats) === 0 ? -1 : product.max_seats
			}
		});

		const newForm = {
			...form,
			product_data: productData
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

	const handleMdpTierChange = (selected) => {
		const mdpTier = mdpTiers.find(tier => tier.uuid === selected.value);

		setForm({
			...form,
			mdp_tier_name: mdpTier.name,
			mdp_tier_uuid: mdpTier.uuid,
			type: mdpTier.type,
			product_data: []
		});
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
				max_seats: -1
			}
		});

		setForm({
			...form,
			product_data: productData
		});
	}

	const handleOrganizationPerSeatGrantedViaChange = (selected) => {
		const productData = [
			{
				product_id: selected.value,
				max_seats: -1
			}
		];

		setForm({
			...form,
			product_data: productData
		});
	}

	const initRangeOfSeatsProductModal = (range_of_seats_product_index) => {
		setCurrentRangeOfSeatsProductIndex(range_of_seats_product_index);

		// Clear errors
		setRangeOfSeatsProductErrors([]);

		if (range_of_seats_product_index === null) {
			// Adding new
			console.log('Add new product');
			setTempRangeOfSeatsProduct({
				product_id: null,
				max_seats: 0
			});
		} else {
			// Editing existing product
			console.log('Editing existing product');
			const product = form.product_data[range_of_seats_product_index];
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
						max_seats: tempRangeOfSeatsProduct.max_seats
					}
				]
			});
		} else {
			const product_data = form.product_data.map((product, index) => {
				if (index === currentRangeOfSeatsProductIndex) {
					return {
						product_id: tempRangeOfSeatsProduct.product_id,
						max_seats: tempRangeOfSeatsProduct.max_seats
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

	useEffect(() => {
		let queryParams = {};

		// Fetch WooCommerce products
		queryParams = { status: 'publish', per_page: 100, exclude: productsInUse };
		apiFetch({ path: addQueryArgs(`${API_URL}/product`, queryParams) }).then((products) => {
			console.log(products);

			let options = products.map((product) => {
				const decodedTitle = he.decode(product.title.rendered);
				return {
					label: `${decodedTitle} | ID: ${product.id}`,
					value: product.id
				}
			});

			setWcProductOptions(options);
		});

		// Fetch Local Membership Tiers Posts
		queryParams = { status: 'publish' };
		apiFetch({ path: addQueryArgs(`${API_URL}/${tierCptSlug}`, queryParams) }).then((tiers) => {
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
		queryParams = { status: 'publish' };
		apiFetch({ path: addQueryArgs(`${API_URL}/${configCptSlug}`, queryParams) }).then((configs) => {
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
		queryParams = {};
		apiFetch({ path: addQueryArgs(`${MDP_API_URL}/membership_tiers`, queryParams) }).then((tiers) => {

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
			apiFetch({ path: addQueryArgs(`${API_URL}/${tierCptSlug}/${postId}`, queryParams) }).then((post) => {
				console.log('Post:');
				console.log(post.tier_data);

				// change max_seats to 0 if it is -1
				const productData = post.tier_data.product_data.map((product) => {
					return {
						product_id: product.product_id,
						max_seats: parseInt(product.max_seats) === -1 ? 0 : product.max_seats
					}
				});

				setForm({
					...post.tier_data,
					product_data: productData
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
																<strong>%COUNT%</strong>
															</Text>
														</FlexItem>
														<FlexItem>
															<Button variant="link">
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
																onChange={(value) => setForm({ ...form, approval_email_recipient: value })}
																__nextHasNoMarginBottom={true}
															/>
														</CustomDisabled>
													</FlexBlock>
												</Flex>
											</BorderedBox>
										</FlexBlock>
									</MarginedFlex>
									<MarginedFlex>
										<FlexBlock>
											<LabelWpStyled htmlFor="next_tier">
												{__('Sequential Logic', 'wicket-memberships')}
											</LabelWpStyled>
											<SelectWpStyled
												id="next_tier"
												classNamePrefix="select"
												placeholder={__('Current Tier', 'wicket-memberships')}
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
									product_id: selected.value
								});
							}}
						/>

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