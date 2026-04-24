import { useCallback, useEffect, useState } from "react";
import { fetchGroupEditPageInfo } from "../../shared/services/api";

const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

/**
 * useMembershipGroupBootstrap
 *
 * Loads all data required to populate the membership group detail page.
 * Calls fetchGroupEditPageInfo from api.js — never calls apiFetch directly.
 *
 * @param {object} params
 * @param {string|number} params.postId - WP post ID of the membership group.
 * @returns {{ pageData: object|null, setPageData: Function, requestState: object, retryLoad: function }}
 */
export const useMembershipGroupBootstrap = ({ postId }) => {
  const [pageData, setPageData] = useState(null);
  const [requestState, setRequestState] = useState(REQUEST_LOADING);

  const loadPageData = useCallback(async () => {
    setRequestState(REQUEST_LOADING);

    try {
      const data = await fetchGroupEditPageInfo(postId);
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
