import { __ } from "@wordpress/i18n";
import moment from "moment-timezone";
import styled, { keyframes } from "styled-components";
import { PLUGIN_SETTINGS } from "../../shared/constants";

const spin = keyframes`
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
`;

const Overlay = styled.div`
  position: absolute;
  inset: 0;
  z-index: 100;
  background: rgba(255, 255, 255, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: all;
`;

const ContentBlock = styled.div`
  text-align: center;
  max-width: 340px;
  padding: 32px 24px;
`;

const SpinnerWrap = styled.span`
  display: block;
  font-size: 40px;
  color: #2271b1;
  margin-bottom: 16px;
  line-height: 1;
  .dashicons {
    display: inline-block;
    animation: ${spin} 1.4s linear infinite;
    font-size: inherit;
    width: auto;
    height: auto;
  }
`;

const Heading = styled.h2`
  font-size: 18px;
  font-weight: 600;
  color: #1e1e1e;
  margin: 0 0 8px;
`;

const Subtext = styled.p`
  font-size: 14px;
  color: #3c434a;
  margin: 0 0 16px;
`;

const Progress = styled.p`
  font-size: 14px;
  color: #1e1e1e;
  font-weight: 500;
  margin: 0 0 8px;
`;

const StartedAt = styled.p`
  font-size: 12px;
  color: #787c82;
  margin: 0;
`;

/**
 * RenewalProcessingOverlay
 *
 * Full-page blocking overlay shown while a bundle renewal batch is in progress.
 * Covers the bundle detail content area and blocks all interaction.
 * Unmounts when processingMeta is null (renewal complete or not running).
 *
 * @param {object|null} props.processingMeta - Parsed membership_renewal_processing object, or null.
 */
const RenewalProcessingOverlay = ({ processingMeta }) => {
  if (!processingMeta) return null;

  const { offset = 0, total_members = 0, started_at } = processingMeta;

  return (
    <Overlay>
      <ContentBlock>
        <SpinnerWrap aria-hidden="true">
          <span className="dashicons dashicons-update-alt" />
        </SpinnerWrap>
        <Heading>{__("Renewal In Progress", "wicket-memberships")}</Heading>
        <Subtext>
          {__("Please wait for the renewal process to finish.", "wicket-memberships")}
        </Subtext>
        <Progress>
          {offset}/{total_members} {__("records processed.", "wicket-memberships")}
        </Progress>
        {started_at && (
          <StartedAt>
            {__("Started:", "wicket-memberships")}{" "}
            {moment.tz(started_at, PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || "UTC").format("YYYY-MM-DD HH:mm:ss z")}
          </StartedAt>
        )}
      </ContentBlock>
    </Overlay>
  );
};

export default RenewalProcessingOverlay;
