import { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Flex, FlexItem, FlexBlock, Notice, SelectControl, TextControl } from '@wordpress/components';
import styled from 'styled-components';
import { ErrorsRow, ActionRow } from '../styled_elements';
import WicketModal from '../components/WicketModal';
import { fetchMembershipStatuses, updateMembershipStatus } from '../services/api';

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

/**
 * ManageStatusModal
 *
 * Allows an admin to transition a membership record to a new status.
 *
 * Props:
 *   isOpen             {boolean}   Whether the modal is visible.
 *   onClose            {Function}  Called when the modal should close.
 *   membershipPostId   {number}    WP post ID of the membership record.
 *   currentStatus      {string}    The membership's current status string.
 *   onStatusUpdated    {Function}  Called with (postId, newStatus, responseData) on success.
 */
const ManageStatusModal = ({ isOpen, onClose, membershipPostId, currentStatus, onStatusUpdated }) => {
	const [newStatus, setNewStatus] = useState('');
	const [availableStatuses, setAvailableStatuses] = useState([]);
	const [errors, setErrors] = useState([]);
	const [statusChangeConfirmed, setStatusChangeConfirmed] = useState(false);

	useEffect(() => {
		if ( isOpen && membershipPostId ) {
			setNewStatus('');
			setErrors([]);
			setStatusChangeConfirmed(false);
			fetchMembershipStatuses(membershipPostId)
				.then((response) => {
					setAvailableStatuses(response);
				})
				.catch(console.error);
		}
	}, [isOpen, membershipPostId]);

	const statusRequiresConfirmation = (status) => {
		return ['cancelled', 'expired'].includes(status);
	};

	const getStatusOptions = () => {
		const options = Object.keys(availableStatuses).map((status) => ({
			label: availableStatuses[status].name,
			value: availableStatuses[status].slug,
		}));
		options.unshift({ label: __('Select Status', 'wicket-memberships'), value: '' });
		return options;
	};

	const handleSubmit = (event) => {
		event.preventDefault();
		updateMembershipStatus(membershipPostId, newStatus)
			.then((response) => {
				if (response.success) {
					onStatusUpdated(membershipPostId, newStatus, response.response);
					onClose();
				} else {
					setErrors([response.error]);
				}
			})
			.catch(console.error);
	};

	const handleStatusChange = (value) => {
		setNewStatus(value);
		setStatusChangeConfirmed(false);
	};

	return (
		<WicketModal
			isOpen={ isOpen }
			title={ __('Change Status', 'wicket-memberships') }
			onRequestClose={ onClose }
		>
			<form onSubmit={ handleSubmit }>

				{ errors.length > 0 && (
					<ErrorsRow>
						{ errors.map((errorMessage, index) => (
							<Notice isDismissible={ false } key={ index } status="warning">{ errorMessage }</Notice>
						)) }
					</ErrorsRow>
				) }

				<MarginedFlex
					align='end'
					justify='start'
					gap={ 5 }
					direction={ ['column', 'row'] }
				>
					<FlexBlock>
						<TextControl
							label={ __('Current Status', 'wicket-memberships') }
							disabled={ true }
							style={{ backgroundColor: '#F6F7F7' }}
							value={ currentStatus }
							__nextHasNoMarginBottom={ true }
						/>
					</FlexBlock>
					<FlexItem>
						<div style={{ fontWeight: 500, marginBottom: '5px' }}>
							{ __('To', 'wicket-memberships') }
						</div>
					</FlexItem>
					<FlexBlock>
						<SelectControl
							label={ __('New Status', 'wicket-memberships') }
							value={ newStatus }
							onChange={ handleStatusChange }
							options={ getStatusOptions() }
							__nextHasNoMarginBottom={ true }
						/>
					</FlexBlock>
				</MarginedFlex>

				{ statusRequiresConfirmation(newStatus) && !statusChangeConfirmed && (
					<MarginedFlex direction='column' gap={ 3 }>
						<Notice isDismissible={ false } status="warning">
							{ sprintf(
								/* translators: %s: status name (cancelled or expired) */
								__("Once a membership status is changed to '%s' it cannot be undone. Are you certain you would like to proceed?", 'wicket-memberships'),
								newStatus
							) }
						</Notice>
						<FlexItem>
							<Button
								variant="secondary"
								isDestructive
								onClick={ () => setStatusChangeConfirmed(true) }
							>
								{ __('Confirm Action', 'wicket-memberships') }
							</Button>
						</FlexItem>
					</MarginedFlex>
				) }

				<ActionRow>
					<Flex align='end' gap={ 5 } direction={ ['column', 'row'] }>
						<FlexItem>
							<Button
								variant="primary"
								type='submit'
								disabled={ newStatus === '' || (statusRequiresConfirmation(newStatus) && !statusChangeConfirmed) }
							>
								{ __('Update Status', 'wicket-memberships') }
							</Button>
						</FlexItem>
					</Flex>
				</ActionRow>

			</form>
		</WicketModal>
	);
};

export default ManageStatusModal;
