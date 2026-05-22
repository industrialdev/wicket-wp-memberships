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
 * @param {string|number} params.postId - WP post ID of the membership bundle.
 * @returns {{ pageData: object|null, setPageData: Function, requestState: object, retryLoad: function }}
 */
export const useMembershipBundleBootstrap = ({ postId }) => {
  const [pageData, setPageData] = useState(null);
  const [requestState, setRequestState] = useState(REQUEST_LOADING);

  const loadPageData = useCallback(async () => {
    setRequestState(REQUEST_LOADING);

    try {
      const data = await fetchBundleEditPageInfo(postId);
      setPageData(data);
      setRequestState(REQUEST_SUCCESS);
    } catch (error) {
      setRequestState({ status: "error", error });
    }
  }, [postId]);

  useEffect(() => {
    if (postId) {
      loadPageData();
    }
  }, [postId, loadPageData]);

  return {
    pageData,
    setPageData,
    requestState,
    retryLoad: loadPageData,
  };
};
