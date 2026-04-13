import { __ } from "@wordpress/i18n";
import { useState, useRef, useEffect } from "react";
import { Button, Icon } from "@wordpress/components";
import styled from "styled-components";
import { BorderedBox, LabelWpStyled } from "../styled_elements";

const DropdownWrap = styled(BorderedBox)`
  position: relative;
  display: inline-block;
`;

const DropdownMenu = styled.div`
  position: absolute;
  top: 100%;
  left: 0;
  z-index: 1000;
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 2px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
  min-width: 200px;
  margin-top: 4px;
`;

const DropdownItem = styled.button`
  display: block;
  width: 100%;
  padding: 8px 12px;
  text-align: left;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 13px;
  line-height: 1.4;
  color: #1e1e1e;

  &:hover:not(:disabled) {
    background: #f0f0f1;
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
`;

/**
 * MembershipActionsDropdown
 *
 * A dropdown button that reveals a list of actions.
 *
 * Props:
 *   label   {string}   - Button label. Defaults to "Membership Actions".
 *   actions {Array}    - Array of action objects:
 *                          { label: string, onClick: function, disabled?: boolean }
 */
const MembershipActionsDropdown = ({ label, actions = [] }) => {
  const [isOpen, setIsOpen] = useState(false);
  const wrapRef = useRef(null);

  const buttonLabel = label || __("Membership Actions", "wicket-memberships");

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (wrapRef.current && !wrapRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleActionClick = (action) => {
    setIsOpen(false);
    action.onClick();
  };

  return (
    <DropdownWrap ref={wrapRef}>
      <div>
        <LabelWpStyled>
          {__("Actions", "wicket-memberships")}
        </LabelWpStyled>
      </div>
      <div>
        <Button
          variant="secondary"
          onClick={() => setIsOpen((prev) => !prev)}
        >
          {buttonLabel}
          &nbsp;
          <Icon icon={isOpen ? "arrow-up-alt2" : "arrow-down-alt2"} />
        </Button>
      </div>

      {isOpen && actions.length > 0 && (
        <DropdownMenu>
          {actions.map((action, index) => (
            <DropdownItem
              key={index}
              disabled={!!action.disabled}
              onClick={() => handleActionClick(action)}
            >
              {action.label}
            </DropdownItem>
          ))}
        </DropdownMenu>
      )}
    </DropdownWrap>
  );
};

export default MembershipActionsDropdown;
