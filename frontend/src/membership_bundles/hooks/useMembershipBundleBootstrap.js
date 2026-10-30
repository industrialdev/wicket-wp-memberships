import { useCallback, useEffect, useRef, useState } from "react";
import { fetchBundleEditPageInfo } from "../../shared/services/api";
import { getRenewalProcessingMeta, getRenewalOrderCreationMeta, parseMetaJson } from "../utils/renewalMeta";

const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

const RENEWAL_POLL_INTERVAL_MS = 10000;
// A small bundle's renewal order job finishes within seconds, so check back sooner first.
const RENEWAL_FIRST_POLL_MS = 2000;

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
 * @param {Function} [params.onRenewalOrderResolved] - Called with (claim, data) once a watched renewal
 *                                                     order claim completes or fails, or with (null, data)
 *                                                     when its outcome can't be read.
 * @returns {{ pageData: object|null, setPageData: Function, requestState: object, retryLoad: function, watchRenewalOrderCreation: function, renewalProcessingMeta: object|null, renewalOrderCreationMeta: object|null }}
 */
export const useMembershipBundleBootstrap = ({ bundleGroupUuid, onRenewalOrderResolved }) => {
  const [pageData, setPageData] = useState(null);
  const [requestState, setRequestState] = useState(REQUEST_LOADING);
  const pollTimerRef = useRef(null);
  // Bumped on every stop so a fetch still in flight from a superseded loop, or
  // after unmount, drops its result instead of re-arming a second loop.
  const pollGenerationRef = useRef(0);
  const awaitingOrderRef = useRef(false);
  const orderInProgressRef = useRef(false);
  const onResolvedRef = useRef(onRenewalOrderResolved);
  onResolvedRef.current = onRenewalOrderResolved;

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
  // after a reload) once it leaves the in-progress state or can't be fetched.
  const checkRenewalOrderClaim = useCallback((data) => {
    const inProgress = Boolean(getRenewalOrderCreationMeta(data));
    const watching = awaitingOrderRef.current || orderInProgressRef.current;
    orderInProgressRef.current = inProgress;
    if (!watching || inProgress) return;

    awaitingOrderRef.current = false;
    const claim = parseMetaJson(data?.meta?.membership_renewal_order_creation);
    if (onResolvedRef.current) onResolvedRef.current(claim, data);
  }, []);

  // Schedule next poll while a renewal order is awaited or either phase (order
  // creation, then member processing) is still in progress.
  const scheduleNextPoll = useCallback(
    (data, delay = RENEWAL_POLL_INTERVAL_MS) => {
      stopPolling();
      if (!awaitingOrderRef.current && !getRenewalOrderCreationMeta(data) && !getRenewalProcessingMeta(data)) {
        return;
      }
      const generation = pollGenerationRef.current;
      pollTimerRef.current = setTimeout(async () => {
        pollTimerRef.current = null;
        const refreshed = await silentRefresh();
        if (generation !== pollGenerationRef.current) return;
        if (refreshed) setPageData(refreshed);
        checkRenewalOrderClaim(refreshed);
        if (refreshed) scheduleNextPoll(refreshed);
      }, delay);
    },
    [silentRefresh, stopPolling, checkRenewalOrderClaim],
  );

  // Starts watching a renewal order that was just queued (202). The claim may
  // already be resolved by the first poll, so it can't rely on seeing it in progress.
  const watchRenewalOrderCreation = useCallback(() => {
    awaitingOrderRef.current = true;
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
      orderInProgressRef.current = Boolean(getRenewalOrderCreationMeta(data));
      // Resume polling if a renewal is already in progress, e.g. the page was reloaded mid-job.
      scheduleNextPoll(data);
      return data;
    } catch (error) {
      if (generation !== pollGenerationRef.current) return null;
      setRequestState({ status: "error", error });
      return null;
    }
  }, [bundleGroupUuid, stopPolling, scheduleNextPoll]);

  useEffect(() => {
    if (bundleGroupUuid) {
      loadPageData();
    }
    return () => {
      stopPolling();
      awaitingOrderRef.current = false;
      orderInProgressRef.current = false;
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
