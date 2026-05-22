import { createRoot } from "react-dom/client";
import MembershipBundlePage from "../components/MembershipBundlePage";

const app = document.getElementById("bundle_member_edit");
if (app) {
  createRoot(app).render(
    <MembershipBundlePage
      bundleGroupUuid={app.dataset.bundleGroupUuid}
      listUrl={app.dataset.listUrl}
      individualMembersUrl={app.dataset.individualMembersUrl}
    />
  );
}
