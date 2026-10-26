import { __ } from "@wordpress/i18n";
import { Button, Icon } from "@wordpress/components";

/**
 * SwitchToButton — renders a "Switch to" impersonation button for a WP user.
 *
 * The switch URL is generated server-side (via Helper::get_user_switch_to_url)
 * and passed in as a prop. The button is disabled when no URL is available,
 * which happens when the User Switching plugin is not active or the current
 * admin cannot switch to the target user.
 *
 * @param {object}       props
 * @param {string|null}  props.switchToUrl  - Impersonation URL from the REST API.
 *                                            Empty string or null renders a disabled button.
 */
const SwitchToButton = ({ switchToUrl = null }) => {
  const url = switchToUrl ? switchToUrl.replaceAll("&amp;", "&") : "";

  return (
    <>
      <Button
        variant="link"
        href={url || undefined}
        disabled={!url}
        aria-disabled={!url}
        style={{ textTransform: 'initial' }}
      >
        {__("Switch to", "wicket-memberships")}
      </Button>
      &nbsp;<Icon icon="update" style={{ color: 'var(--wp-admin-theme-color)' }} />
    </>
  );
};

export default SwitchToButton;
