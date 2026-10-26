import { ModalStyled } from '../styled_elements';

/**
 * WicketModal
 *
 * Standard modal wrapper for the Wicket Memberships admin UI.
 * Handles conditional rendering and enforces consistent sizing.
 *
 * Props:
 *   isOpen        {boolean}  Whether the modal is visible.
 *   title         {string}   Modal title (required by @wordpress/components Modal).
 *   onRequestClose {Function} Called when the modal requests to close.
 *   children      {ReactNode} Modal body content.
 *   ...rest                  Any additional props forwarded to the underlying Modal.
 */
const WicketModal = ({ isOpen, title, onRequestClose, children, ...rest }) => {
	if ( ! isOpen ) {
		return null;
	}

	return (
		<ModalStyled
			title={ title }
			onRequestClose={ onRequestClose }
			style={ { maxWidth: '840px', width: '100%' } }
			{ ...rest }
		>
			{ children }
		</ModalStyled>
	);
};

export default WicketModal;
