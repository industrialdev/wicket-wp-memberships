import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl, Button, SelectControl, Notice } from '@wordpress/components';
import { ErrorsRow } from '../shared/styled_elements';
import WicketModal from '../shared/components/WicketModal';

/**
 * Read one locale's approval callout copy, tolerating a tier that has none.
 *
 * A tier saved before a WPML language was added carries no entry for it, and indexing straight
 * into locales[code] throws while rendering the modal.
 *
 * @param {object} calloutData approval_callout_data shape: { locales: { [code]: {...} } }.
 * @param {string} locale      Locale code.
 * @return {object} The locale's copy, or an empty record.
 */
const getCalloutLocale = (calloutData, locale) => {
	if (!calloutData || !calloutData.locales) { return {}; }

	return calloutData.locales[locale] || {};
};

/**
 * Mirror of the REST rule in Membership_Post_Types::register_membership_tier_cpt_fields():
 * when approval is required, every active language needs a header, content and a button label.
 *
 * Also used by the tier editor before it saves the tier.
 *
 * @param {object}   calloutData   approval_callout_data shape: { locales: { [code]: {...} } }.
 * @param {string[]} languageCodes Active locale codes.
 * @return {string[]} Empty when the callout is complete.
 */
export const collectApprovalCalloutIssues = (calloutData, languageCodes) => {
	const fields = [
		{ key: 'callout_header', label: __('Callout Header', 'wicket-memberships') },
		{ key: 'callout_content', label: __('Callout Content', 'wicket-memberships') },
		{ key: 'callout_button_label', label: __('Button Label', 'wicket-memberships') }
	];

	const missing = [];

	languageCodes.forEach((locale) => {
		const localeData = getCalloutLocale(calloutData, locale);

		fields.forEach((field) => {
			if (String(localeData[field.key] || '').trim() === '') {
				missing.push(`${field.label} [${locale.toUpperCase()}]`);
			}
		});
	});

	if (missing.length === 0) { return []; }

	return [
		__('The Approval callout configuration is incomplete, missing:', 'wicket-memberships')
		+ ' ' + missing.join(', ')
	];
};

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
				...(tempCalloutData && tempCalloutData.locales),
				[currentLocale]: {
					...getCalloutLocale(tempCalloutData, currentLocale),
					[field]: value,
				},
			},
		});
	};

	const handleSubmit = (e) => {
		e.preventDefault();
		// Saved either way so a partially filled modal never loses the admin's typing; the modal
		// stays open, with the missing fields named, until the copy is complete.
		onSave(tempCalloutData);

		const issues = collectApprovalCalloutIssues(tempCalloutData, languageCodes);
		setErrors(issues);
		if (issues.length > 0) { return; }

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
					value={ getCalloutLocale(tempCalloutData, currentLocale).callout_header || '' }
					onChange={ (value) => updateLocaleField('callout_header', value) }
				/>

				<TextareaControl
					label={ __('Callout Content', 'wicket-memberships') }
					value={ getCalloutLocale(tempCalloutData, currentLocale).callout_content || '' }
					onChange={ (value) => updateLocaleField('callout_content', value) }
				/>

				<TextControl
					label={ __('Button Label', 'wicket-memberships') }
					value={ getCalloutLocale(tempCalloutData, currentLocale).callout_button_label || '' }
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
