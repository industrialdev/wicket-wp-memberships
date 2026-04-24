import { createRoot } from "react-dom/client";
import MembershipGroupPage from "../components/MembershipGroupPage";

const app = document.getElementById("group_member_edit");
if (app) {
  createRoot(app).render(
    <MembershipGroupPage
      postId={app.dataset.postId}
      listUrl={app.dataset.listUrl}
      individualMembersUrl={app.dataset.individualMembersUrl}
    />
  );
}
