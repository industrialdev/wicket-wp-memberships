import { createRoot } from "react-dom/client";
import { useState, useEffect } from "react";
import { Spinner } from "@wordpress/components";
import { fetchMembershipTiers } from "../services/api";
import { __ } from "@wordpress/i18n";

const MembershipTierCellInfo = ({ tierUuid, tierField }) => {
  const [status, setStatus] = useState(null);

  useEffect(() => {
    fetchMembershipTiers({
      filters: { id: [tierUuid] },
    })
      .then((tiers) => {
        const value =
          tiers[0][tierField] === null
            ? __("N/A", "wicket-memberships")
            : tiers[0][tierField];
        setStatus(value);
      })
      .catch((error) => {
        console.log("Tier Info Error:");
        console.log(error);
      });
  }, []);

  return (
    <>
      {status === null && <Spinner />}
      {status !== null && status}
    </>
  );
};

// init multiple instances
const app = document.querySelectorAll(".wicket_memberships_tier_cell_info");
if (app) {
  app.forEach((el) => {
    createRoot(el).render(<MembershipTierCellInfo {...el.dataset} />);
  });
}
