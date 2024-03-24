import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { Card, CardBody, TextControl } from '@wordpress/components';

const CreateMembershipConfig = () => {

	const [ form, setForm ] = useState( {
		name: '',
		renewalWindowData: {
			daysCount: null
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

	useEffect( () => {
		const queryParams = { include: [781, 756, 3] };

		apiFetch( { path: addQueryArgs( '/wp/v2/posts', queryParams ) } ).then( ( posts ) => {
				console.log( posts );
		} );

	}, [] );

	return (
		<div className="wrap" >
			<h1 className="wp-heading-inline">{ __( 'Add New Membership Config', 'wicket-memberships' ) }</h1>
			<hr className="wp-header-end"></hr>

			<TextControl
				label={ __( 'Name', 'wicket-memberships' ) }
				onChange={value => {
					setForm({
						...form,
						name: value
					});
				}}
				value={form.name}
			/>
		</div>
	);
};

const rootElement = document.getElementById( 'create_membership_config' );
if ( rootElement ) {
	createRoot( rootElement ).render( <CreateMembershipConfig /> );
}