import { __ } from "@wordpress/i18n";

/**
 * Renders an add_member() error for display in an Alert.
 *
 * When the API rejects a tier as ineligible, the response includes
 * `eligible_tier_names` (the bundle config's actual allowed tiers) alongside
 * the plain-text `error` string. Rendering that list as bullets — instead of
 * relying on the comma-joined sentence baked into `error` — gives an admin a
 * scannable answer to "what tier should I use instead" rather than a wall of
 * text they have to parse themselves.
 *
 * Falls back to the plain error string for every other error shape.
 */
const AddMemberErrorMessage = ({ error }) => {
  if (!error) {
    return null;
  }

  const message =
    error?.error ?? error?.message ?? __("An error occurred.", "wicket-memberships");

  if (error?.code !== "tier_not_eligible" || !error?.eligible_tier_names?.length) {
    return message;
  }

  return (
    <>
      <p style={{ margin: "0 0 4px" }}>
        {__("This membership tier is not eligible for this bundle configuration.", "wicket-memberships")}
      </p>
      <p style={{ margin: "0 0 4px" }}>
        {__("Eligible tiers:", "wicket-memberships")}
      </p>
      <ul style={{ margin: "0 0 0 20px", listStyleType: "disc" }}>
        {error.eligible_tier_names.map((tierName) => (
          <li key={tierName}>{tierName}</li>
        ))}
      </ul>
    </>
  );
};

export default AddMemberErrorMessage;
