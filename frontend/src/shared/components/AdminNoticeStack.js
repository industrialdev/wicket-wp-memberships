import { Notice } from "@wordpress/components";
import WicketButton from "./WicketButton";
import { ErrorsRow } from "../styled_elements";

const AdminNoticeStack = ({ notices = [] }) => {
  if (notices.length === 0) {
    return null;
  }

  return (
    <ErrorsRow>
      {notices.map((notice) => (
        <Notice
          isDismissible={!!notice.onDismiss}
          onDismiss={notice.onDismiss}
          key={notice.id}
          status={notice.status || "warning"}
        >
          <div>{notice.message}</div>
          {notice.action ? (
            <div>
              <WicketButton onClick={notice.action.onClick} variant="link">
                {notice.action.label}
              </WicketButton>
            </div>
          ) : null}
        </Notice>
      ))}
    </ErrorsRow>
  );
};

export default AdminNoticeStack;
