import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, Button, Flex, FlexItem, Modal, TextareaControl, FlexBlock, Notice, SelectControl, CheckboxControl, Disabled, __experimentalHeading as Heading, Icon, __experimentalText as Text } from '@wordpress/components';
import styled from 'styled-components';
import { API_URL, MDP_API_URL } from './constants';
import he from 'he';
import Select from 'react-select'
import { Wrap, ErrorsRow, BorderedBox, LabelWpStyled, SelectWpStyled, ActionRow, FormFlex } from './styled_elements';

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

const CreateMembershipTier = ({ tierCptSlug, configCptSlug, tierListUrl, postId }) => {

	const [isSubmitting, setSubmitting] = useState(false);

	const [mdpTiers, setMdpTiers] = useState([]);

	const [membershipConfigOptions, setMembershipConfigOptions] = useState([]); // { id, name }

	const [wcProductOptions, setWcProductOptions] = useState([]); // { id, name }

	const [errors, setErrors] = useState([]);

	const [tempProduct, setTempProduct] = useState(
		{
			product_id: null,
			max_seats: 0
		}
	);

	const [form, setForm] = useState({
		approval_required: false,
		mdp_tier_name: '',
		mdp_tier_uuid: '',
		mdp_next_tier_uuid: '',
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

	const getSelectedPerSeatProductId = () => {
		if (form.product_data.length === 0) { return null; }

		return form.product_data[0].product_id;
	};

	const handleSubmit = (e) => {
		e.preventDefault();

		// TODO: Validate form data

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

		setTempProduct({
			product_id: null,
			max_seats: 0
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

	const handlePerRangeOfSeatsAddProduct = () => {
		if (tempProduct.product_id === null) { return; }

		if ( tempProduct.max_seats < 0 ) {
			setErrors([__('Range maximum value cannot be less than 0', 'wicket-memberships')]);
			return;
		}

		setForm({
			...form,
			product_data: [
				...form.product_data,
				{
					product_id: tempProduct.product_id,
					max_seats: tempProduct.max_seats
				}
			]
		});
		setTempProduct({
			product_id: null,
			max_seats: 0
		});
	};

	useEffect(() => {
		let queryParams = {};

		// Fetch WooCommerce products
		queryParams = { status: 'publish' };
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
						category: '', // TODO: Update here
					}
				})
			);
		});

		// Fetch the membership tier
		if (postId) {
			apiFetch({ path: addQueryArgs(`${API_URL}/${tierCptSlug}/${postId}`, queryParams) }).then((post) => {
				console.log('Post:');
				console.log(post.tier_data);

				setForm(post.tier_data);
			});
		}
	}, []);

	console.log('Tiers:');
	console.log(mdpTiers);
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
							{form.mdp_tier_uuid && (
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
						{form.mdp_tier_uuid && (
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
										<FlexItem>
											<CheckboxControl
												label={__('Approval Required', 'wicket-memberships')}
												checked={form.approval_required}
												onChange={(value) => setForm({ ...form, approval_required: value })}
											/>
										</FlexItem>
									</Flex>
								</ActionRow>
								<MarginedFlex>
									<FlexBlock>
										<LabelWpStyled htmlFor="next_mdp_tier">
											{__('Sequential Logic', 'wicket-memberships')}
										</LabelWpStyled>
										<SelectWpStyled
											id="next_mdp_tier"
											classNamePrefix="select"
											value={getMdpTierOptions().find(option => option.value === form.mdp_next_tier_uuid)}
											isClearable={false}
											isSearchable={true}
											isLoading={getMdpTierOptions().length === 0}
											options={getMdpTierOptions()}
											onChange={(selected) => setForm({ ...form, mdp_next_tier_uuid: selected.value })}
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
															<LabelWpStyled
																htmlFor="temp_product"
															>
																{__('Product', 'wicket-memberships')}
															</LabelWpStyled>
															<SelectWpStyled
																id="temp_product"
																classNamePrefix="select"
																value={wcProductOptions.find(option => tempProduct.product_id === option.value)}
																isClearable={false}
																isSearchable={true}
																isLoading={wcProductOptions.length === 0}
																options={wcProductOptions}
																__nextHasNoMarginBottom={true}
																onChange={(selected) => setTempProduct({
																	...tempProduct,
																	product_id: selected.value
																})}
															/>
														</FlexBlock>
														<FlexBlock>
															<TextControl
																label={__('Range Maximum (USE 0 FOR UNLIMITED)', 'wicket-memberships')}
																type='number'
																min={0}
																__nextHasNoMarginBottom={true}
																value={tempProduct.max_seats}
																onChange={(value) => setTempProduct({
																	...tempProduct,
																	max_seats: value
																})}
															/>
														</FlexBlock>
														<FlexBlock>
															<Button
																disabled={tempProduct.product_id === null}
																variant="primary"
																onClick={handlePerRangeOfSeatsAddProduct}
															>
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
																						// initSeasonModal(index)
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
				</Wrap>
			</div>
		</>
	);
};

const app = document.getElementById('create_membership_tier');
if (app) {
	createRoot(app).render(<CreateMembershipTier {...app.dataset} />);
}