import { createRoot } from "react-dom/client";
import CreateMembershipGroupPage from "../components/CreateMembershipGroupPage";

const app = document.getElementById("create_membership_group");
if (app) {
  createRoot(app).render(
    <CreateMembershipGroupPage
      groupConfigCptSlug={app.dataset.groupConfigCptSlug}
      listUrl={app.dataset.listUrl}
      editGroupBaseUrl={app.dataset.editGroupBaseUrl}
    />
  );
}
