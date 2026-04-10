import { createRoot } from "react-dom/client";
import GroupConfigPage from "../components/GroupConfigPage";

const app = document.getElementById("create_membership_group_config");

if (app) {
  createRoot(app).render(<GroupConfigPage {...app.dataset} />);
}
