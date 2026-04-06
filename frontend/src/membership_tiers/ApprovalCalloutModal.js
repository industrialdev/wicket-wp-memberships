import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl, Button, SelectControl, Notice } from '@wordpress/components';
import { ErrorsRow } from '../styled_elements';
import WicketModal from '../components/WicketModal';

/**
 * ApprovalCalloutModal
 *
 * Configures per-locale callout content shown when membership approval is required.
 *
 * Props:
 *   isOpen        {boolean}   Whether the modal is visible.
 *   onClose       {Function}  Called when the modal should close.
 *   languageCodes {string[]}  Array of locale codes available for editing.
 *   calloutData   {object}    Current callout data shape: { locales: { [code]: { callout_header, callout_content, callout_button_label } } }
 *   onSave        {Function}  Called with the updated calloutData object when the form is submitted.
 */
const ApprovalCalloutModal = ({ isOpen, onClose, languageCodes, calloutData, onSave }) => {
	const [currentLocale, setCurrentLocale] = useState(languageCodes[0]);
	const [tempCalloutData, setTempCalloutData] = useState(calloutData);
	const [errors, setErrors] = useState([]);

	// Re-sync temp state each time the modal opens so edits start fresh from the saved data.
	useEffect(() => {
		if ( isOpen ) {
			setTempCalloutData(calloutData);
			setCurrentLocale(languageCodes[0]);
			setErrors([]);
		}
	}, [isOpen]);

	const updateLocaleField = (field, value) => {
		setTempCalloutData({
			...tempCalloutData,
			locales: {
				...tempCalloutData.locales,
				[currentLocale]: {
					...tempCalloutData.locales[currentLocale],
					[field]: value,
				},
			},
		});
	};

	const handleSubmit = (e) => {
		e.preventDefault();
		onSave(tempCalloutData);
		onClose();
	};

	return (
		<WicketModal
			isOpen={ isOpen }
			title={ __('Approval - Callout Configuration', 'wicket-memberships') }
			onRequestClose={ onClose }
		>
			{ errors.length > 0 && (
				<ErrorsRow>
					{ errors.map((error) => (
						<Notice isDismissible={ false } key={ error } status="warning">{ error }</Notice>
					)) }
				</ErrorsRow>
			) }

			<form onSubmit={ handleSubmit }>
				<SelectControl
					label={ __('Language', 'wicket-memberships') }
					options={ languageCodes.map((code) => ({ label: code, value: code })) }
					value={ currentLocale }
					onChange={ setCurrentLocale }
				/>

				<TextControl
					label={ __('Callout Header', 'wicket-memberships') }
					value={ tempCalloutData.locales[currentLocale].callout_header }
					onChange={ (value) => updateLocaleField('callout_header', value) }
				/>

				<TextareaControl
					label={ __('Callout Content', 'wicket-memberships') }
					value={ tempCalloutData.locales[currentLocale].callout_content }
					onChange={ (value) => updateLocaleField('callout_content', value) }
				/>

				<TextControl
					label={ __('Button Label', 'wicket-memberships') }
					value={ tempCalloutData.locales[currentLocale].callout_button_label }
					onChange={ (value) => updateLocaleField('callout_button_label', value) }
				/>

				<Button variant="primary" type='submit'>
					{ __('Save', 'wicket-memberships') }
				</Button>
			</form>
		</WicketModal>
	);
};

export default ApprovalCalloutModal;
