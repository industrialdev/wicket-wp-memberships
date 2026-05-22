import { createRoot } from "react-dom/client";
import BundleConfigPage from "../components/BundleConfigPage";

const app = document.getElementById("create_membership_bundle_config");

if (app) {
  createRoot(app).render(<BundleConfigPage {...app.dataset} />);
}
