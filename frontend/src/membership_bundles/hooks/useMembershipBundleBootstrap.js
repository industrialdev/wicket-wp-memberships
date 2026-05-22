import { useCallback, useEffect, useState } from "react";
import { fetchBundleEditPageInfo } from "../../shared/services/api";

const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

/**
 * useMembershipBundleBootstrap
 *
 * Loads all data required to populate the membership bundle detail page.
 * Calls fetchBundleEditPageInfo from api.js — never calls apiFetch directly.
 *
 * @param {object} params
 * @param {string} params.bundleGroupUuid - membership_bundle_group_uuid for the series.
 * @returns {{ pageData: object|null, setPageData: Function, requestState: object, retryLoad: function }}
 */
export const useMembershipBundleBootstrap = ({ bundleGroupUuid }) => {
  const [pageData, setPageData] = useState(null);
  const [requestState, setRequestState] = useState(REQUEST_LOADING);

  const loadPageData = useCallback(async () => {
    setRequestState(REQUEST_LOADING);

    try {
      const data = await fetchBundleEditPageInfo(bundleGroupUuid);
      setPageData(data);
      setRequestState(REQUEST_SUCCESS);
    } catch (error) {
      setRequestState({ status: "error", error });
    }
  }, [bundleGroupUuid]);

  useEffect(() => {
    if (bundleGroupUuid) {
      loadPageData();
    }
  }, [bundleGroupUuid, loadPageData]);

  return {
    pageData,
    setPageData,
    requestState,
    retryLoad: loadPageData,
  };
};
