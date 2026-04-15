import { createRoot } from "react-dom/client";
import GroupMembershipPage from "../components/GroupMembershipPage";

const app = document.getElementById("group_member_edit");
if (app) {
  createRoot(app).render(
    <GroupMembershipPage
      postId={app.dataset.postId}
      listUrl={app.dataset.listUrl}
    />
  );
}
