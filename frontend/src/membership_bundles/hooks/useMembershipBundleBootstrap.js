import { useCallback, useEffect, useRef, useState } from "react";
import { fetchBundleEditPageInfo } from "../../shared/services/api";

const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

const RENEWAL_POLL_INTERVAL_MS = 10000;

/**
 * Returns the parsed membership_renewal_processing object if the renewal is
 * still in progress (meta present and no completed_at), otherwise null.
 *
 * @param {object|null} data - pageData from fetchBundleEditPageInfo
 * @returns {object|null}
 */
const getRenewalProcessingMeta = (data) => {
  if (!data?.meta?.membership_renewal_processing) return null;
  try {
    const parsed =
      typeof data.meta.membership_renewal_processing === "string"
        ? JSON.parse(data.meta.membership_renewal_processing)
        : data.meta.membership_renewal_processing;
    // completed_at presence means the batch finished — overlay should dismiss.
    if (parsed?.completed_at) return null;
    return parsed ?? null;
  } catch {
    return null;
  }
};

/**
 * useMembershipBundleBootstrap
 *
 * Loads all data required to populate the membership bundle detail page.
 * While a renewal batch is in progress (membership_renewal_processing meta present
 * and no completed_at), polls the REST endpoint every 10 seconds to pick up
 * progress updates and detect completion.
 *
 * @param {object} params
 * @param {string} params.bundleGroupUuid - membership_bundle_group_uuid for the series.
 * @returns {{ pageData: object|null, setPageData: Function, requestState: object, retryLoad: function, renewalProcessingMeta: object|null }}
 */
export const useMembershipBundleBootstrap = ({ bundleGroupUuid }) => {
  const [pageData, setPageData] = useState(null);
  const [requestState, setRequestState] = useState(REQUEST_LOADING);
  const pollTimerRef = useRef(null);

  const stopPolling = useCallback(() => {
    if (pollTimerRef.current) {
      clearTimeout(pollTimerRef.current);
      pollTimerRef.current = null;
    }
  }, []);

  const loadPageData = useCallback(async () => {
    setRequestState(REQUEST_LOADING);
    stopPolling();

    try {
      const data = await fetchBundleEditPageInfo(bundleGroupUuid);
      setPageData(data);
      setRequestState(REQUEST_SUCCESS);
      return data;
    } catch (error) {
      setRequestState({ status: "error", error });
      return null;
    }
  }, [bundleGroupUuid, stopPolling]);

  // Silent background refresh — does not reset requestState to loading so the
  // overlay can update progress without re-rendering the full page skeleton.
  const silentRefresh = useCallback(async () => {
    if (!bundleGroupUuid) return;
    try {
      const data = await fetchBundleEditPageInfo(bundleGroupUuid);
      setPageData(data);
      return data;
    } catch {
      return null;
    }
  }, [bundleGroupUuid]);

  // Schedule next poll if renewal is still in progress.
  const scheduleNextPoll = useCallback(
    (data) => {
      stopPolling();
      if (getRenewalProcessingMeta(data)) {
        pollTimerRef.current = setTimeout(async () => {
          const refreshed = await silentRefresh();
          if (refreshed) {
            scheduleNextPoll(refreshed);
          }
        }, RENEWAL_POLL_INTERVAL_MS);
      }
    },
    [silentRefresh, stopPolling],
  );

  useEffect(() => {
    if (bundleGroupUuid) {
      loadPageData().then((data) => {
        if (data) scheduleNextPoll(data);
      });
    }
    return stopPolling;
  }, [bundleGroupUuid, loadPageData, scheduleNextPoll, stopPolling]);

  return {
    pageData,
    setPageData,
    requestState,
    retryLoad: loadPageData,
    renewalProcessingMeta: getRenewalProcessingMeta(pageData),
  };
};
