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

const CreateMembershipTier = ({ tierCptSlug, configCptSlug, tierListUrl, postId }) => {

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
		const selectedTier = mdpTiers.find(tier => tier.uuid === form.mdp_tier_uuid);

		return selectedTier;
	};

	const handleSubmit = (e) => {
		//
	}

	const handleMdpTierChange = (selected) => {
		const mdpTier = mdpTiers.find(tier => tier.uuid === selected.value);

		setForm({
			...form,
			mdp_tier_name: mdpTier.name,
			mdp_tier_uuid: mdpTier.uuid,
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
								align='end'
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
								<ActionRow>
									<Flex>
										<FlexItem>
											<Text size={14}>
												{__('Status', 'wicket-memberships')}:&nbsp;
												<strong>{getSelectedTierData().active ? __('Active', 'wicket-memberships') : __('Inactive', 'wicket-memberships')}</strong>
											</Text>
										</FlexItem>
									</Flex>
								</ActionRow>
							)}
						</BorderedBox>
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