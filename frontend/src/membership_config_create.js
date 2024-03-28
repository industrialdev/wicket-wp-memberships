import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, Button, Flex, FlexItem, Modal, TextareaControl, FlexBlock, Notice, SelectControl, CheckboxControl, Disabled } from '@wordpress/components';
import styled from 'styled-components';

const CustomDisabled = styled(Disabled)`
	opacity: .5;
`;

const Wrap = styled.div`
	max-width: 600px;
`;

const ActionRow = styled.div`
	margin-top: 30px;
`;

const FormFlex = styled(Flex)`
	margin-top: 15px;
`;

const ErrorsRow = styled.div`
	padding: 10px 0;
	margin-left: -15px;
`;

const BorderedBox = styled.div`
	border: 1px solid #c3c4c7;
	padding: 15px;
	margin-top: 15px;
`;

const CreateMembershipConfig = () => {

	const [isRenewalWindowCalloutModalOpen, setRenewalWindowCalloutModalOpen] = useState(false);
	const openRenewalWindowCalloutModal = () => setRenewalWindowCalloutModalOpen(true);
	const closeRenewalWindowCalloutModal = () => setRenewalWindowCalloutModalOpen(false);

	const [isLateFeeWindowCalloutModalOpen, setLateFeeWindowCalloutModalOpen] = useState(false);
	const openLateFeeWindowCalloutModal = () => setLateFeeWindowCalloutModalOpen(true);
	const closeLateFeeWindowCalloutModal = () => setLateFeeWindowCalloutModalOpen(false);

	const [isSubmitting, setSubmitting] = useState(false);
	const [errors, setErrors] = useState({});
	const [wcProductOptions, setWcProductOptions] = useState([
		{
			label: __('Loading products...', 'wicket-memberships'),
			value: -1
		}
	]);

	const [form, setForm] = useState({
		name: '',
		renewal_window_data: {
			days_count: 0,
			callout_header: '',
			callout_content: '',
			callout_button_label: ''
		},
		late_fee_window_data: {
			days_count: 0,
			product_id: '-1',
			callout_header: '',
			callout_content: '',
			callout_button_label: ''
		},
		cycle_data: {
			cycle_type: 'anniversary', // calendar or anniversary
			anniversary_data: {
				period_count: 365,
				period_type: 'year', // year/month/week
				align_end_dates_enabled: false,
				align_end_dates_type: 'first-day-of-month' // first-day-of-month | 15th-of-month | last-day-of-month
			},
			calendar_items: [
				// {
				// 	season_name: '',
				// 	active: true, // true or false
				// 	start_date: null,
				// 	end_date: null
				// }
			]
		}
	});

	const [tempForm, setTempForm] = useState(form);

	/**
	 * Reinitialize the renewal window callout form with the current form data
	 */
	const reInitRenewalWindowCallout = () => {
		setTempForm(form)
		openRenewalWindowCalloutModal()
	}

	/**
	 * Reinitialize the late fee window callout form with the current form data
	 */
	const reInitLateFeeWindowCallout = () => {
		setTempForm(form)
		openLateFeeWindowCalloutModal()
	}

	/**
	 * Validate the form
	 * @returns {boolean}
	 */
	const validateForm = () => {
    let isValid = true;
    const newErrors = {};

    if (form.name.length === 0) {
      newErrors.name = __('Name is required', 'wicket-memberships')
      isValid = false
    }

		if (form.renewal_window_data.callout_header.length === 0) {
			newErrors.renewalWindowcallout_header = __('Renewal Window Callout Header is required', 'wicket-memberships')
			isValid = false
		}

		if (form.renewal_window_data.callout_content.length === 0) {
			newErrors.renewalWindowCalloutContent = __('Renewal Window Callout Content is required', 'wicket-memberships')
			isValid = false
		}

		if (form.renewal_window_data.callout_button_label.length === 0) {
			newErrors.renewalWindowButtonLabel = __('Renewal Window Callout Button Label is required', 'wicket-memberships')
			isValid = false
		}

		if (form.late_fee_window_data.product_id === '-1') {
			newErrors.lateFeeProduct = __('Late Fee Window Product is required', 'wicket-memberships')
			isValid = false
		}

    setErrors(newErrors)
    return isValid
  }

	const saveRenewalWindowCallout = () => {
		console.log('Saving renewal window callout');

		setForm({
			...form,
			renewal_window_data: {
				...form.renewal_window_data,
				callout_header: tempForm.renewal_window_data.callout_header,
				callout_content: tempForm.renewal_window_data.callout_content,
				callout_button_label: tempForm.renewal_window_data.callout_button_label
			}
		});
	}

	const saveLateFeeWindowCallout = () => {
		console.log('Saving late fee window callout');

		setForm({
			...form,
			late_fee_window_data: {
				...form.late_fee_window_data,
				callout_header: tempForm.late_fee_window_data.callout_header,
				callout_content: tempForm.late_fee_window_data.callout_content,
				callout_button_label: tempForm.late_fee_window_data.callout_button_label
			}
		});
	}

	const handleSubmit = (e) => {
		e.preventDefault();
		if (!validateForm()) { return }

		setSubmitting(true);
		console.log('Saving membership config');

		// I need to create new Wordpress CPT with the form data
		apiFetch({
			path: '/wp/v2/wicket_mship_config',
			method: 'POST',
			data: {
				title: form.name,
				status: 'publish',
				meta: {
					renewal_window_data: form.renewal_window_data,
					late_fee_window_data: form.late_fee_window_data,
					cycle_data: form.cycle_data
				}
			}
		}).then((response) => {
			console.log(response);
			setSubmitting(false);
		}).catch((error) => {
			console.log(error);
			setSubmitting(false);
		});

	}

	// TODO: Fetch by ID if editing
	useEffect(() => {
		// const queryParams = { include: [781, 756, 3] };
		let queryParams = {};
		apiFetch({ path: addQueryArgs('/wp/v2/wicket_mship_config', queryParams) }).then((posts) => {
			console.log(posts);
		});

		// Fetch WooCommerce products
		queryParams = { status: 'publish' };
		apiFetch({ path: addQueryArgs('/wp/v2/product', queryParams) }).then((products) => {
			console.log(products);

			let options = products.map((product) => {
				return {
					label: `${product.title.rendered} | (ID: ${product.id})`,
					value: product.id
				}
			});

			options.unshift({
				label: __('Select a product', 'wicket-memberships'),
				value: -1
			});

			console.log(options);

			setWcProductOptions(options);
		});

	}, []);

	console.log(errors);
	console.log(form);

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">{__('Add New Membership Config', 'wicket-memberships')}</h1>
				<hr className="wp-header-end"></hr>

				<Wrap>
					{Object.keys(errors).length > 0 && (
						<ErrorsRow>
							{Object.keys(errors).map((key) => (
								<Notice isDismissible={false} key={key} status="warning">{errors[key]}</Notice>
							))}
						</ErrorsRow>
					)}
					<form onSubmit={handleSubmit}>
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
								<TextControl
									label={__('Membership Configuration Name', 'wicket-memberships')}
									onChange={value => {
										setForm({
											...form,
											name: value
										});
									}}
									value={form.name}
								/>
							</FlexBlock>
						</Flex>

						{/* Renewal Window */}
						<FormFlex
							align='end'
							justify='start'
							gap={5}
							direction={[
								'column',
								'row'
							]}
						>
							<FlexBlock>
								<TextControl
									label={__('Renewal Window (Days)', 'wicket-memberships')}
									type="number"
									onChange={value => {
										setForm({
											...form,
											renewal_window_data: {
												...form.renewal_window_data,
												days_count: value
											}
										});
									}}
									value={form.renewal_window_data.days_count}
									__nextHasNoMarginBottom={true}
								/>
							</FlexBlock>
							<FlexItem>
								<Button
									variant="secondary"
									onClick={reInitRenewalWindowCallout}
								>
									<span className="dashicons dashicons-screenoptions me-2"></span>&nbsp;
									{__('Callout Configuration', 'wicket-memberships')}
								</Button>
							</FlexItem>
						</FormFlex>

						{/* Late Fee Window */}
						<FormFlex
							align='end'
							gap={5}
							direction={[
								'column',
								'row'
							]}
						>
							<FlexBlock>
								<TextControl
									label={__('Late Fee Window (Days)', 'wicket-memberships')}
									type="number"
									onChange={value => {
										setForm({
											...form,
											late_fee_window_data: {
												...form.late_fee_window_data,
												days_count: value
											}
										});
									}}
									value={form.late_fee_window_data.days_count}
									__nextHasNoMarginBottom={true}
								/>
							</FlexBlock>
							<FlexBlock>
								<SelectControl
									label={__('Product', 'wicket-memberships')}
									value={form.late_fee_window_data.product_id}
									__nextHasNoMarginBottom={true}
									onChange={value => {
										setForm({
											...form,
											late_fee_window_data: {
												...form.late_fee_window_data,
												product_id: value
											}
										});
									}}
									options={wcProductOptions}
								/>
							</FlexBlock>
							<FlexItem>
								<Button
									variant="secondary"
									onClick={reInitLateFeeWindowCallout}
								>
									<span className="dashicons dashicons-screenoptions me-2"></span>&nbsp;
									{__('Callout Configuration', 'wicket-memberships')}
								</Button>
							</FlexItem>
						</FormFlex>

						{/* Cycle Data */}
						<BorderedBox>
							<Flex
								align='end'
								gap={5}
								direction={[
									'column',
									'row'
								]}
							>
								<FlexBlock>
									<SelectControl
										label={__('Cycle', 'wicket-memberships')}
										value={form.cycle_data.cycle_type}
										__nextHasNoMarginBottom={true}
										onChange={value => {
											setForm({
												...form,
												cycle_data: {
													...form.cycle_data,
													cycle_type: value
												}
											});
										}}
										options={[
											{
												label: __('Calendar', 'wicket-memberships'),
												value: 'calendar'
											},
											{
												label: __('Anniversary', 'wicket-memberships'),
												value: 'anniversary'
											}
										]}
									/>
								</FlexBlock>
								{ form.cycle_data.cycle_type === 'calendar' && (
									<FlexItem>
										<Button
											variant="secondary"
										>
											<span className="dashicons dashicons-plus-alt"></span>&nbsp;
											{__('Add Season', 'wicket-memberships')}
										</Button>
									</FlexItem>
								)}
							</Flex>

							<FormFlex
								align='end'
								gap={5}
								direction={[
									'column',
									'row'
								]}
							>
								<FlexBlock>
									<TextControl
										label={__('Membership Period', 'wicket-memberships')}
										type="number"
										__nextHasNoMarginBottom={true}
										onChange={value => {
											setForm({
												...form,
												cycle_data: {
													...form.cycle_data,
													anniversary_data: {
														...form.cycle_data.anniversary_data,
														period_count: value
													}
												}
											});
										}}
										value={form.cycle_data.anniversary_data.period_count}
									/>
								</FlexBlock>
								<FlexBlock>
									<SelectControl
										label=''
										value={form.cycle_data.anniversary_data.period_type}
										__nextHasNoMarginBottom={true}
										onChange={value => {
											setForm({
												...form,
												cycle_data: {
													...form.cycle_data,
													anniversary_data: {
														...form.cycle_data.anniversary_data,
														period_type: value
													}
												}
											});
										}}
										options={[
											{
												label: __('Year', 'wicket-memberships'),
												value: 'year'
											},
											{
												label: __('Month', 'wicket-memberships'),
												value: 'month'
											},
											{
												label: __('Week', 'wicket-memberships'),
												value: 'week'
											}
										]}
									/>
								</FlexBlock>
							</FormFlex>
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
											checked={form.cycle_data.anniversary_data.align_end_dates_enabled}
											label={__('Align End Dates', 'wicket-memberships')}
											__nextHasNoMarginBottom={true}
											onChange={value => {
												setForm({
													...form,
													cycle_data: {
														...form.cycle_data,
														anniversary_data: {
															...form.cycle_data.anniversary_data,
															align_end_dates_enabled: value
														}
													}
												});
											}}
										/>
									</FlexItem>
									<FlexBlock>
										<CustomDisabled
											isDisabled={!form.cycle_data.anniversary_data.align_end_dates_enabled}
										>
											<SelectControl
												label={__('Align by', 'wicket-memberships')}
												value={form.cycle_data.anniversary_data.align_end_dates_type}
												__nextHasNoMarginBottom={true}
												onChange={value => {
													setForm({
														...form,
														cycle_data: {
															...form.cycle_data,
															anniversary_data: {
																...form.cycle_data.anniversary_data,
																align_end_dates_type: value
															}
														}
													});
												}}
												options={[
													{
														label: __('First Day of Month', 'wicket-memberships'),
														value: 'first-day-of-month'
													},
													{
														label: __('15th of Month', 'wicket-memberships'),
														value: '15th-of-month'
													},
													{
														label: __('Last Day of Month', 'wicket-memberships'),
														value: 'last-day-of-month'
													}
												]}
											/>
										</CustomDisabled>
									</FlexBlock>
								</Flex>
							</BorderedBox>
						</BorderedBox>

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
										{!isSubmitting && __('Save Membership Configuration', 'wicket-memberships')}
									</Button>
								</FlexItem>
							</Flex>
						</ActionRow>
					</form>
				</Wrap>

				{/* Renewal Window Callout Modal */}
				{isRenewalWindowCalloutModalOpen && (
					<Modal
						title={__('Renewal Window - Callout Configuration', 'wicket-memberships')}
						onRequestClose={closeRenewalWindowCalloutModal}
						size="large"
					>

						<TextControl
							label={__('Callout Header', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									renewal_window_data: {
										...tempForm.renewal_window_data,
										callout_header: value
									}
								});
							}}
							value={tempForm.renewal_window_data.callout_header}
						/>

						<TextareaControl
							label={__('Callout Content', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									renewal_window_data: {
										...tempForm.renewal_window_data,
										callout_content: value
									}
								});
							}}
							value={tempForm.renewal_window_data.callout_content}
						/>

						<TextControl
							label={__('Button Label', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									renewal_window_data: {
										...tempForm.renewal_window_data,
										callout_button_label: value
									}
								});
							}}
							value={tempForm.renewal_window_data.callout_button_label}
						/>

						<Button variant="primary" onClick={
							() => {
								saveRenewalWindowCallout();
								closeRenewalWindowCalloutModal();
							}
						}>
							{__('Save', 'wicket-memberships')}
						</Button>
					</Modal>
				)}

				{/* Late Fee Window Callout Modal */}
				{isLateFeeWindowCalloutModalOpen && (
					<Modal
						title={__('Late Fee Window - Callout Configuration', 'wicket-memberships')}
						onRequestClose={closeLateFeeWindowCalloutModal}
						size="large"
					>

						<TextControl
							label={__('Callout Header', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									late_fee_window_data: {
										...tempForm.late_fee_window_data,
										callout_header: value
									}
								});
							}}
							value={tempForm.late_fee_window_data.callout_header}
						/>

						<TextareaControl
							label={__('Callout Content', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									late_fee_window_data: {
										...tempForm.late_fee_window_data,
										callout_content: value
									}
								});
							}}
							value={tempForm.late_fee_window_data.callout_content}
						/>

						<TextControl
							label={__('Button Label', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									late_fee_window_data: {
										...tempForm.late_fee_window_data,
										callout_button_label: value
									}
								});
							}}
							value={tempForm.late_fee_window_data.callout_button_label}
						/>

						<Button variant="primary" onClick={
							() => {
								saveLateFeeWindowCallout();
								closeLateFeeWindowCalloutModal();
							}
						}>
							{__('Save', 'wicket-memberships')}
						</Button>
					</Modal>
				)}
			</div>
		</>
	);
};

const rootElement = document.getElementById('create_membership_config');
if (rootElement) {
	createRoot(rootElement).render(<CreateMembershipConfig />);
}