import { useCallback, useEffect, useRef, useState } from "react";
import { fetchBundleEditPageInfo } from "../../shared/services/api";
import { getRenewalProcessingMeta, getRenewalOrderCreationMeta, parseMetaJson } from "../utils/renewalMeta";

const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

const RENEWAL_POLL_INTERVAL_MS = 10000;
// A small bundle's renewal order job finishes within seconds, so check back sooner first.
const RENEWAL_FIRST_POLL_MS = 2000;
// Consecutive failed fetches (with doubling backoff) before a watched order is reported unknown.
const RENEWAL_MAX_FAILED_POLLS = 3;
// An order claim still in progress after this long is reported once and polled less often.
const RENEWAL_ORDER_DELAYED_MS = 5 * 60 * 1000;
const RENEWAL_DELAYED_POLL_INTERVAL_MS = 60000;

/**
 * useMembershipBundleBootstrap
 *
 * Loads all data required to populate the membership bundle detail page.
 * While a renewal is in progress (order creation, then member processing), or a
 * renewal order was just queued from this page, polls the REST endpoint every
 * 10 seconds to pick up progress and detect completion.
 *
 * @param {object}   params
 * @param {string}   params.bundleGroupUuid          - membership_bundle_group_uuid for the series.
 * @param {Function} [params.onRenewalOrderStatus]   - Called with (status, claim, data) for a watched renewal
 *                                                     order: "completed" | "failed" | "unknown" (outcome can't
 *                                                     be read) | "delayed" (still in progress, reported once).
 * @returns {{ pageData: object|null, setPageData: Function, requestState: object, retryLoad: function, watchRenewalOrderCreation: function, renewalProcessingMeta: object|null, renewalOrderCreationMeta: object|null }}
 */
export const useMembershipBundleBootstrap = ({ bundleGroupUuid, onRenewalOrderStatus }) => {
  const [pageData, setPageData] = useState(null);
  const [requestState, setRequestState] = useState(REQUEST_LOADING);
  const pollTimerRef = useRef(null);
  // Bumped on every stop so a fetch still in flight from a superseded loop, or
  // after unmount, drops its result instead of re-arming a second loop.
  const pollGenerationRef = useRef(0);
  const awaitingOrderRef = useRef(false);
  const orderInProgressRef = useRef(false);
  const orderDelayedRef = useRef(false);
  const failedPollsRef = useRef(0);
  const onStatusRef = useRef(onRenewalOrderStatus);
  onStatusRef.current = onRenewalOrderStatus;

  const reportOrderStatus = (status, claim = null, data = null) => {
    if (onStatusRef.current) onStatusRef.current(status, claim, data);
  };

  const stopPolling = useCallback(() => {
    pollGenerationRef.current += 1;
    if (pollTimerRef.current) {
      clearTimeout(pollTimerRef.current);
      pollTimerRef.current = null;
    }
  }, []);

  // Background fetch — does not reset requestState to loading so the overlay can
  // update progress without re-rendering the full page skeleton.
  const silentRefresh = useCallback(async () => {
    if (!bundleGroupUuid) return null;
    try {
      return await fetchBundleEditPageInfo(bundleGroupUuid);
    } catch {
      return null;
    }
  }, [bundleGroupUuid]);

  // Reports a watched claim (queued from this page, or seen in progress, e.g.
  // after a reload) once it resolves, or once if it stays in progress too long.
  const checkRenewalOrderClaim = useCallback((data) => {
    const inProgressClaim = getRenewalOrderCreationMeta(data);
    const watching = awaitingOrderRef.current || orderInProgressRef.current;
    orderInProgressRef.current = Boolean(inProgressClaim);

    if (inProgressClaim) {
      const queuedAt = Date.parse(inProgressClaim.queued_at);
      if (!orderDelayedRef.current && queuedAt && Date.now() - queuedAt > RENEWAL_ORDER_DELAYED_MS) {
        orderDelayedRef.current = true;
        reportOrderStatus("delayed", inProgressClaim, data);
      }
      return;
    }
    if (!watching) return;

    awaitingOrderRef.current = false;
    orderDelayedRef.current = false;
    const claim = parseMetaJson(data?.meta?.membership_renewal_order_creation);
    if (!claim) {
      reportOrderStatus("unknown", null, data);
    } else {
      reportOrderStatus(claim.failed_at ? "failed" : "completed", claim, data);
    }
  }, []);

  // Schedule next poll while a renewal order is awaited or either phase (order
  // creation, then member processing) is still in progress.
  const scheduleNextPoll = useCallback(
    (data, delay = orderDelayedRef.current ? RENEWAL_DELAYED_POLL_INTERVAL_MS : RENEWAL_POLL_INTERVAL_MS) => {
      stopPolling();
      if (!awaitingOrderRef.current && !getRenewalOrderCreationMeta(data) && !getRenewalProcessingMeta(data)) {
        return;
      }
      const generation = pollGenerationRef.current;
      pollTimerRef.current = setTimeout(async () => {
        pollTimerRef.current = null;
        const refreshed = await silentRefresh();
        if (generation !== pollGenerationRef.current) return;

        if (!refreshed) {
          // Keep polling from the last known data; one transient error shouldn't end the watch.
          failedPollsRef.current += 1;
          if (failedPollsRef.current < RENEWAL_MAX_FAILED_POLLS) {
            scheduleNextPoll(data, RENEWAL_POLL_INTERVAL_MS * 2 ** failedPollsRef.current);
            return;
          }
          failedPollsRef.current = 0;
          // Watch flags stay set so a retryLoad() picks the claim back up.
          if (awaitingOrderRef.current || orderInProgressRef.current) reportOrderStatus("unknown");
          return;
        }

        failedPollsRef.current = 0;
        setPageData(refreshed);
        checkRenewalOrderClaim(refreshed);
        scheduleNextPoll(refreshed);
      }, delay);
    },
    [silentRefresh, stopPolling, checkRenewalOrderClaim],
  );

  // Starts watching a renewal order that was just queued (202). The claim may
  // already be resolved by the first poll, so it can't rely on seeing it in progress.
  const watchRenewalOrderCreation = useCallback(() => {
    awaitingOrderRef.current = true;
    orderDelayedRef.current = false;
    failedPollsRef.current = 0;
    scheduleNextPoll(null, RENEWAL_FIRST_POLL_MS);
  }, [scheduleNextPoll]);

  const loadPageData = useCallback(async () => {
    setRequestState(REQUEST_LOADING);
    stopPolling();
    const generation = pollGenerationRef.current;

    try {
      const data = await fetchBundleEditPageInfo(bundleGroupUuid);
      if (generation !== pollGenerationRef.current) return null;
      setPageData(data);
      setRequestState(REQUEST_SUCCESS);
      failedPollsRef.current = 0;
      checkRenewalOrderClaim(data);
      // Resume polling if a renewal is already in progress, e.g. the page was reloaded mid-job.
      scheduleNextPoll(data);
      return data;
    } catch (error) {
      if (generation !== pollGenerationRef.current) return null;
      setRequestState({ status: "error", error });
      return null;
    }
  }, [bundleGroupUuid, stopPolling, scheduleNextPoll, checkRenewalOrderClaim]);

  useEffect(() => {
    if (bundleGroupUuid) {
      loadPageData();
    }
    return () => {
      stopPolling();
      awaitingOrderRef.current = false;
      orderInProgressRef.current = false;
      orderDelayedRef.current = false;
    };
  }, [bundleGroupUuid, loadPageData, stopPolling]);

  return {
    pageData,
    setPageData,
    requestState,
    retryLoad: loadPageData,
    watchRenewalOrderCreation,
    renewalProcessingMeta: getRenewalProcessingMeta(pageData),
    renewalOrderCreationMeta: getRenewalOrderCreationMeta(pageData),
  };
};
