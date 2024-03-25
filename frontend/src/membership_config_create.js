import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { TextControl, Button, Flex, FlexItem, Modal, TextareaControl } from '@wordpress/components';
import styled from 'styled-components';

const Wrap = styled.div`
	max-width: 600px;
`;

const CreateMembershipConfig = () => {

	const [isRenewalWindowCalloutModalOpen, setRenewalWindowCalloutModalOpen] = useState(false);
	const openRenewalWindowCalloutModal = () => setRenewalWindowCalloutModalOpen(true);
	const closeRenewalWindowCalloutModal = () => setRenewalWindowCalloutModalOpen(false);

	const [form, setForm] = useState({
		name: '',
		renewalWindowData: {
			daysCount: null,
			calloutHeader: '',
			calloutContent: '',
			calloutButtonLabel: ''
		},
		late_fee_window_data: {
			daysCount: null,
			productId: null,
			cycleType: 'calendar', // calendar or anniversary
			anniversaryData: {
				periodCount: null,
				periodType: null, // year/month/week
				alignEndDates: false,
				alignEndDatesValue: 'first_month_day' // First day of month / 15th of Month / Last Day of Month
			},
			calendarData: {
				seasonName: '',
				active: true, // true or false
				startDate: null,
				endDate: null
			}
		}
	});

	const [tempForm, setTempForm] = useState(form);

	const reInitRenewalWindowCallout = () => {
		setTempForm(form)
		openRenewalWindowCalloutModal()
	}

	const saveRenewalWindowCallout = () => {
		console.log('Saving renewal window callout');

		setForm({
			...form,
			renewalWindowData: {
				...form.renewalWindowData,
				calloutHeader: tempForm.renewalWindowData.calloutHeader,
				calloutContent: tempForm.renewalWindowData.calloutContent,
				calloutButtonLabel: tempForm.renewalWindowData.calloutButtonLabel
			}
		});
	}

	// useEffect(() => {
	// 	const queryParams = { include: [781, 756, 3] };

	// 	apiFetch({ path: addQueryArgs('/wp/v2/posts', queryParams) }).then((posts) => {
	// 		console.log(posts);
	// 	});

	// }, []);

	console.log(form);

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">{__('Add New Membership Config', 'wicket-memberships')}</h1>
				<hr className="wp-header-end"></hr>

				<Wrap>
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
							<TextControl
								label={__('Membership Configuration Name', 'wicket-memberships')}
								onChange={value => {
									setForm({
										...form,
										name: value
									});
								}}
								value={form.name}
								__nextHasNoMarginBottom={true}
							/>
						</FlexItem>
						<FlexItem>
							<Button
								variant="primary"
								onClick={
									() => {
										reInitRenewalWindowCallout()
									}
								}
							>
								{__('Callout Configuration', 'wicket-memberships')}
							</Button>
						</FlexItem>
					</Flex>
				</Wrap>

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
									renewalWindowData: {
										...tempForm.renewalWindowData,
										calloutHeader: value
									}
								});
							}}
							value={tempForm.renewalWindowData.calloutHeader}
						/>

						<TextareaControl
							label={__('Callout Content', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									renewalWindowData: {
										...tempForm.renewalWindowData,
										calloutContent: value
									}
								});
							}}
							value={tempForm.renewalWindowData.calloutContent}
						/>

						<TextControl
							label={__('Button Label', 'wicket-memberships')}
							onChange={value => {
								setTempForm({
									...tempForm,
									renewalWindowData: {
										...tempForm.renewalWindowData,
										calloutButtonLabel: value
									}
								});
							}}
							value={tempForm.renewalWindowData.calloutButtonLabel}
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
			</div>
		</>
	);
};

const rootElement = document.getElementById('create_membership_config');
if (rootElement) {
	createRoot(rootElement).render(<CreateMembershipConfig />);
}