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
import { Wrap, ErrorsRow, BorderedBox, LabelWpStyled, SelectWpStyled, ActionRow } from './styled_elements';

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

const CreateMembershipTier = ({ tierCptSlug, configCptSlug, tierListUrl, postId }) => {

	const [isSubmitting, setSubmitting] = useState(false);

	const [mdpTiers, setMdpTiers] = useState([]);

	const [membershipConfigOptions, setMembershipConfigOptions] = useState([]); // { id, name }

	const [wcProductOptions, setWcProductOptions] = useState([]); // { id, name }

	const [errors, setErrors] = useState([]);

	const [form, setForm] = useState({
		approval_required: false,
		mdp_tier_name: '',
		mdp_tier_uuid: '',
		mdp_next_tier_uuid: '',
		config_id: '',
		type: '', // orgranization, individual
		seat_type: '',
		product_data: [] // { product_id:, max_seats: }
	});

	const getSelectedTierData = () => {
		if (!form.mdp_tier_uuid) { return null; }
		const selectedTier = mdpTiers.find(tier => tier.uuid === form.mdp_tier_uuid);

		return selectedTier;
	};

	const handleSubmit = (e) => {
		e.preventDefault();

		// TODO: Validate form data

		setSubmitting(true);
		console.log('Saving membership tier');

		const endpoint = postId ? `${API_URL}/${tierCptSlug}/${postId}` : `${API_URL}/${tierCptSlug}`;

		apiFetch({
			path: endpoint,
			method: 'POST',
			data: {
				title: form.mdp_tier_name,
				status: 'publish',
				tier_data: form
			}
		}).then((response) => {
			console.log(response);
			if (response.id) {
				// Redirect to the cpt list page
				// window.location.href = configListUrl;
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
			type: mdpTier.type
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

	useEffect(() => {
		// Fetch WooCommerce products
		let queryParams = { status: 'publish' };
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

			let options = tiers.map((tier) => {
				return {
					label: tier.name,
					value: tier.uuid
				}
			});

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