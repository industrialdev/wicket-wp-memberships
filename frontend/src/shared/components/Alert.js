import { Notice } from "@wordpress/components";
import styled from "styled-components";

const NoticeWrap = styled.div`
  .components-notice {
    margin-top: 8px;
    margin-bottom: 8px;
  }
`;

/**
 * Alert — displays a dismissible notice bar. Green on success, red on error.
 *
 * @param {object}        props.saveResult            - Result object: { type: 'success'|'error', message }
 * @param {Function}      props.onDismiss             - Called when the user dismisses the notice.
 */
const Alert = ({ saveResult, onDismiss }) => {
  if ( ! saveResult ) { return null; }

  return (
    <NoticeWrap>
      <Notice
        isDismissible={true}
        onDismiss={onDismiss}
        status={saveResult.type === 'error' ? 'error' : 'success'}
      >
        {saveResult.message}
      </Notice>
    </NoticeWrap>
  );
};

export default Alert;
