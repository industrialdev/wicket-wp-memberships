import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, Button, Flex, FlexItem, Modal, TextareaControl, FlexBlock, Notice, SelectControl, CheckboxControl, Disabled, __experimentalHeading as Heading, Icon } from '@wordpress/components';
import { API_URL } from './constants';
import he from 'he';
import { Wrap, ActionRow, FormFlex, ErrorsRow, BorderedBox, SelectWpStyled, CustomDisabled, LabelWpStyled } from './styled_elements';

const CreateMembershipConfig = ({ configCptSlug, configListUrl, postId }) => {

	const [currentSeasonIndex, setCurrentSeasonIndex] = useState(null);

	const [tempSeason, setTempSeason] = useState({
		season_name: '',
		active: true, // true or false
		start_date: '',
		end_date: ''
	});

	const [isCreateSeasonModalOpen, setCreateSeasonModalOpen] = useState(false);
	const openCreateSeasonModalOpen = () => setCreateSeasonModalOpen(true);
	const closeCreateSeasonModalOpen = () => setCreateSeasonModalOpen(false);

	const [isRenewalWindowCalloutModalOpen, setRenewalWindowCalloutModalOpen] = useState(false);
	const openRenewalWindowCalloutModal = () => setRenewalWindowCalloutModalOpen(true);
	const closeRenewalWindowCalloutModal = () => setRenewalWindowCalloutModalOpen(false);

	const [isLateFeeWindowCalloutModalOpen, setLateFeeWindowCalloutModalOpen] = useState(false);
	const openLateFeeWindowCalloutModal = () => setLateFeeWindowCalloutModalOpen(true);
	const closeLateFeeWindowCalloutModal = () => setLateFeeWindowCalloutModalOpen(false);

	const [isSubmitting, setSubmitting] = useState(false);
	const [errors, setErrors] = useState([]);
	const [seasonErrors, setSeasonErrors] = useState({});
	const [wcProductOptions, setWcProductOptions] = useState([]);

	const [form, setForm] = useState({
		name: '',
		renewal_window_data: {
			days_count: '1',
			callout_header: '',
			callout_content: '',
			callout_button_label: ''
		},
		late_fee_window_data: {
			days_count: '1',
			product_id: '-1',
			callout_header: '',
			callout_content: '',
			callout_button_label: ''
		},
		cycle_data: {
			cycle_type: 'calendar', // calendar/anniversary
			anniversary_data: {
				period_count: '1',
				period_type: 'year', // year/month/week
				align_end_dates_enabled: false,
				align_end_dates_type: 'first-day-of-month' // first-day-of-month | 15th-of-month | last-day-of-month
			},
			calendar_items: [
				// {
				// 	season_name: '',
				// 	active: true, // true or false
				// 	start_date: '',
				// 	end_date: ''
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
    let newErrors = [];

    if (form.name.length === 0) {
			newErrors.push(__('Membership Configuration Name is required', 'wicket-memberships'))
      isValid = false
    }

		// if (form.renewal_window_data.callout_header.length === 0) {
		// 	newErrors.renewalWindowcallout_header = __('Renewal Window Callout Header is required', 'wicket-memberships')
		// 	isValid = false
		// }

		// if (form.renewal_window_data.callout_content.length === 0) {
		// 	newErrors.renewalWindowCalloutContent = __('Renewal Window Callout Content is required', 'wicket-memberships')
		// 	isValid = false
		// }

		// if (form.renewal_window_data.callout_button_label.length === 0) {
		// 	newErrors.renewalWindowButtonLabel = __('Renewal Window Callout Button Label is required', 'wicket-memberships')
		// 	isValid = false
		// }

		// if (form.late_fee_window_data.product_id === '-1') {
		// 	newErrors.lateFeeProduct = __('Late Fee Window Product is required', 'wicket-memberships')
		// 	isValid = false
		// }

		// if (form.cycle_data.cycle_type === 'calendar') {
		// 	if (form.cycle_data.calendar_items.length === 0) {
		// 		newErrors.calendarItems = __('At least one season is required', 'wicket-memberships')
		// 		isValid = false
		// 	}
		// }

    setErrors(newErrors)
    return isValid
  }

	const initSeasonModal = (season_index) => {
		setCurrentSeasonIndex(season_index);

		// Clear errors
		setSeasonErrors({});

		if (season_index === null) {
			// Creating
			console.log('Creating season');
			setTempSeason({
				season_name: '',
				active: true,
				start_date: '',
				end_date: ''
			});
		} else {
			// Editing
			console.log('Editing season');
			const season = form.cycle_data.calendar_items[season_index];
			setTempSeason(season);
		}
		openCreateSeasonModalOpen();
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

	const validateSeason = () => {
		let isValid = true;
		const newErrors = {};

		if (tempSeason.season_name.length === 0) {
			newErrors.seasonName = __('Season Name is required', 'wicket-memberships')
			isValid = false
		}

		if (tempSeason.start_date.length === 0) {
			newErrors.seasonStartDate = __('Season Start Date is required', 'wicket-memberships')
			isValid = false
		}

		if (tempSeason.end_date.length === 0) {
			newErrors.seasonEndDate = __('Season End Date is required', 'wicket-memberships')
			isValid = false
		}

		if (tempSeason.start_date.length > 0 && tempSeason.end_date.length > 0) {
			const startDate = new Date(tempSeason.start_date);
			const endDate = new Date(tempSeason.end_date);

			if (startDate > endDate) {
				newErrors.seasonEndDate = __('Season End Date must be greater than Start Date', 'wicket-memberships')
				isValid = false
			}
		}

		setSeasonErrors(newErrors)

		return isValid
	}

	const handleCreateSeasonSubmit = (e) => {
		e.preventDefault();

		console.log('Saving season')

		if (!validateSeason()) { return }

		if (currentSeasonIndex === null) {
			setForm({
				...form,
				cycle_data: {
					...form.cycle_data,
					calendar_items: [
						...form.cycle_data.calendar_items,
						{
							season_name: tempSeason.season_name,
							active: tempSeason.active,
							start_date: tempSeason.start_date,
							end_date: tempSeason.end_date
						}
					]
				}
			});
		} else {
			const seasons = form.cycle_data.calendar_items.map((season, index) => {
				if (index === currentSeasonIndex) {
					return {
						season_name: tempSeason.season_name,
						active: tempSeason.active,
						start_date: tempSeason.start_date,
						end_date: tempSeason.end_date
					}
				}
				return season;
			});

			setForm({
				...form,
				cycle_data: {
					...form.cycle_data,
					calendar_items: seasons
				}
			});
		}

		closeCreateSeasonModalOpen()
	}

	const handleSubmit = (e) => {
		e.preventDefault();
		if (!validateForm()) { return }

		setSubmitting(true);
		console.log('Saving membership config');

		const endpoint = postId ? `${API_URL}/${configCptSlug}/${postId}` : `${API_URL}/${configCptSlug}`;

		// I need to create new Wordpress CPT with the form data
		apiFetch({
			path: endpoint,
			method: 'POST',
			data: {
				title: form.name,
				status: 'publish',
				renewal_window_data: form.renewal_window_data,
				late_fee_window_data: form.late_fee_window_data,
				cycle_data: form.cycle_data
			}
		}).then((response) => {
			console.log(response);
			if (response.id) {
				// Redirect to the cpt list page
				window.location.href = configListUrl;
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

	useEffect(() => {
		let queryParams = {};

		// Fetch the membership configuration
		if (postId) {
			apiFetch({ path: addQueryArgs(`${API_URL}/${configCptSlug}/${postId}`, queryParams) }).then((post) => {
				console.log(post);

				const decodedTitle = he.decode(post.title.rendered);
				setForm({
					name: decodedTitle,
					renewal_window_data: post.renewal_window_data,
					late_fee_window_data: post.late_fee_window_data,
					cycle_data: post.cycle_data
				});
			});
		}

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

			console.log(options);

			setWcProductOptions(options);
		});

	}, []);

	console.log(errors);
	console.log(form);

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">
					{postId ? __('Edit Membership Configuration', 'wicket-memberships') : __('Add New Membership Configuration', 'wicket-memberships')}
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
									<TextControl
										label={__('Renewal Window (Days)', 'wicket-memberships')}
										type="number"
										min="1"
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
							</Flex>
						</BorderedBox>

						{/* Late Fee Window */}
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
									<TextControl
										label={__('Late Fee Window (Days)', 'wicket-memberships')}
										type="number"
										min="1"
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
									<LabelWpStyled htmlFor="late_fee_product_id">{__('Product', 'wicket-memberships')}</LabelWpStyled>
									<SelectWpStyled
										id="late_fee_product_id"
										classNamePrefix="select"
										value={wcProductOptions.find(option => option.value === form.late_fee_window_data.product_id)}
										isClearable={false}
										isSearchable={true}
										isLoading={wcProductOptions.length === 0}
										options={wcProductOptions}
										onChange={selected => {
											setForm({
												...form,
												late_fee_window_data: {
													...form.late_fee_window_data,
													product_id: selected.value
												}
											});
										}}
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
							</Flex>
						</BorderedBox>

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
											onClick={() => initSeasonModal(null)}
										>
											<span className="dashicons dashicons-plus-alt"></span>&nbsp;
											{__('Add Season', 'wicket-memberships')}
										</Button>
									</FlexItem>
								)}
							</Flex>

							{/* Anniversary Data */}
							{form.cycle_data.cycle_type === 'anniversary' && (
								<>
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
												min="1"
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
								</>
							)}

							{/* Calendar Items */}
							{form.cycle_data.cycle_type === 'calendar' && (
								<>
									<FormFlex>
										<Heading level='4' weight='300' >
											{__('Seasons', 'wicket-memberships')}
										</Heading>
									</FormFlex>
									<FormFlex>
										<table className="widefat" cellSpacing="0">
											<thead>
												<tr>
													<th className="manage-column column-columnname" scope="col">
														{__('Season Name', 'wicket-memberships')}
													</th>
													<th className="manage-column column-columnname" scope="col">
														{__('Status', 'wicket-memberships')}
													</th>
													<th className="manage-column column-columnname" scope="col">
														{__('Start Date', 'wicket-memberships')}
													</th>
													<th className="manage-column column-columnname" scope="col">
														{__('End Date', 'wicket-memberships')}
													</th>
													<th className='check-column'></th>
												</tr>
											</thead>
											<tbody>
												{form.cycle_data.calendar_items.map((season, index) => (
														<tr key={index} className={index % 2 === 0 ? 'alternate' : ''}>
															<td className="column-columnname">
																{season.season_name}
															</td>
															<td className="column-columnname">
																{season.active ? __('Active', 'wicket-memberships') : __('Inactive', 'wicket-memberships')}
															</td>
															<td className="column-columnname">
																{season.start_date}
															</td>
															<td className="column-columnname">
																{season.end_date}
															</td>
															<td>
																<Button
																	onClick={() => initSeasonModal(index)}
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
						style={
							{
								maxWidth: '840px',
								width: '100%'
							}
						}
					>
						<form onSubmit={
							() => {
								saveRenewalWindowCallout();
								closeRenewalWindowCalloutModal();
							}
						}>
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

							<Button variant="primary" type='submit'>
								{__('Save', 'wicket-memberships')}
							</Button>
						</form>
					</Modal>
				)}

				{/* Late Fee Window Callout Modal */}
				{isLateFeeWindowCalloutModalOpen && (
					<Modal
						title={__('Late Fee Window - Callout Configuration', 'wicket-memberships')}
						onRequestClose={closeLateFeeWindowCalloutModal}
						style={
							{
								maxWidth: '840px',
								width: '100%'
							}
						}
					>
						<form onSubmit={
							() => {
								saveLateFeeWindowCallout();
								closeLateFeeWindowCalloutModal();
							}
						}>
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

							<Button variant="primary" type='submit'>
								{__('Save', 'wicket-memberships')}
							</Button>
						</form>
					</Modal>
				)}

				{/* Season Modal */}
				{isCreateSeasonModalOpen && (
					<Modal
						title={currentSeasonIndex === null ? __('Add Season', 'wicket-memberships') : __('Edit Season', 'wicket-memberships')}
						onRequestClose={closeCreateSeasonModalOpen}
						style={
							{
								maxWidth: '840px',
								width: '100%'
							}
						}
					>
						<form onSubmit={handleCreateSeasonSubmit}>

							{Object.keys(seasonErrors).length > 0 && (
								<ErrorsRow>
									{Object.keys(seasonErrors).map((key) => (
										<Notice isDismissible={false} key={key} status="warning">{seasonErrors[key]}</Notice>
									))}
								</ErrorsRow>
							)}

							<TextControl
								label={__('Season Name', 'wicket-memberships')}
								onChange={value => {
									setTempSeason({
										...tempSeason,
										season_name: value
									});
								}}
								value={tempSeason.season_name}
							/>

							<SelectControl
								label={__('Status', 'wicket-memberships')}
								value={tempSeason.active ? 'true' : 'false'}
								onChange={value => {
									setTempSeason({
										...tempSeason,
										active: value === 'true'
									});
								}}
								options={[
									{
										label: __('Active', 'wicket-memberships'),
										value: 'true'
									},
									{
										label: __('Inactive', 'wicket-memberships'),
										value: 'false'
									}
								]}
							/>

							<FormFlex>
								<FlexBlock>
									<TextControl
										label={__('Start Date', 'wicket-memberships')}
										type="date"
										value={tempSeason.start_date}
										onChange={value => {
											setTempSeason({
												...tempSeason,
												start_date: value
											});
										}
									} />
								</FlexBlock>
								<FlexBlock>
									<TextControl
										label={__('End Date', 'wicket-memberships')}
										type="date"
										value={tempSeason.end_date}
										onChange={value => {
											setTempSeason({
												...tempSeason,
												end_date: value
											});
										}
									} />
								</FlexBlock>
							</FormFlex>

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
										{currentSeasonIndex !== null && (
											<Button
												isDestructive={true}
												onClick={() => {
													const seasons = form.cycle_data.calendar_items.filter((_, index) => index !== currentSeasonIndex);
													setForm({
														...form,
														cycle_data: {
															...form.cycle_data,
															calendar_items: seasons
														}
													});
													closeCreateSeasonModalOpen();
												}}
											>
												<Icon icon="archive" />&nbsp;
												{__('Archive', 'wicket-memberships')}
											</Button>
										)}
									</FlexItem>
									<FlexItem>
										<Button variant="primary" type='submit'>
											{currentSeasonIndex === null ? __('Add Season', 'wicket-memberships') : __('Update Season', 'wicket-memberships')}
										</Button>
									</FlexItem>
								</Flex>

							</ActionRow>
						</form>
					</Modal>
				)}
			</div>
		</>
	);
};

const app = document.getElementById('create_membership_config');
if (app) {
	createRoot(app).render(<CreateMembershipConfig {...app.dataset} />);
}