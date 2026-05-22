import { createRoot } from "react-dom/client";
import CreateMembershipBundlePage from "../components/CreateMembershipBundlePage";

const app = document.getElementById("create_membership_bundle");
if (app) {
  createRoot(app).render(
    <CreateMembershipBundlePage
      bundleConfigCptSlug={app.dataset.bundleConfigCptSlug}
      listUrl={app.dataset.listUrl}
      editBundleBaseUrl={app.dataset.editBundleBaseUrl}
    />
  );
}
