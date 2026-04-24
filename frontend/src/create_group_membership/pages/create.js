import { createRoot } from "react-dom/client";
import CreateGroupMembershipPage from "../components/CreateGroupMembershipPage";

const app = document.getElementById("create_group_membership");
if (app) {
  createRoot(app).render(
    <CreateGroupMembershipPage
      groupConfigCptSlug={app.dataset.groupConfigCptSlug}
      listUrl={app.dataset.listUrl}
      editGroupBaseUrl={app.dataset.editGroupBaseUrl}
    />
  );
}
