import { Button } from "@wordpress/components";

/**
 * WicketButton wraps @wordpress/components Button with project conventions:
 *
 * - `type="button"` by default (prevents accidental form submission)
 * - `dashicon` prop renders a dashicon before the label — pass the icon slug
 *   (e.g. dashicon="screenoptions" renders dashicons-screenoptions)
 *
 * All other @wordpress/components Button props (variant, disabled, isBusy,
 * isDestructive, href, onClick, type="submit", etc.) pass through unchanged.
 */
const WicketButton = ({ children, dashicon, type = "button", ...props }) => (
  <Button type={type} {...props}>
    {dashicon && <span className={`dashicons dashicons-${dashicon}`} />}
    {dashicon && children && <>&nbsp;</>}
    {children}
  </Button>
);

export default WicketButton;
