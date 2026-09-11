import { useCallback, useEffect, useRef, useState } from "react";
import { fetchBundleEditPageInfo } from "../../shared/services/api";
import { getRenewalProcessingMeta, getRenewalOrderCreationMeta } from "../utils/renewalMeta";

const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

const RENEWAL_POLL_INTERVAL_MS = 10000;

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

  // Schedule next poll while either phase (order creation, then member
  // processing) is still in progress.
  const scheduleNextPoll = useCallback(
    (data) => {
      stopPolling();
      if (getRenewalOrderCreationMeta(data) || getRenewalProcessingMeta(data)) {
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
    renewalOrderCreationMeta: getRenewalOrderCreationMeta(pageData),
  };
};
