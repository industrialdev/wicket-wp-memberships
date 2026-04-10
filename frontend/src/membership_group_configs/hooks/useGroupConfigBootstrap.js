import { useCallback, useEffect, useState } from "react";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import he from "he";
import { API_URL } from "../../shared/constants";
import { fetchWcProducts } from "../../shared/services/api";
import { normalizeGroupConfigPostToForm } from "../utils/formUtils";

const REQUEST_IDLE = { status: "idle", error: null };
const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

const mapPagesToOptions = (pages = []) =>
  pages.map((page) => ({
    label: `${he.decode(page.title.rendered)} | ID: ${page.id}`,
    value: page.id,
  }));

const mapProductsToOptions = (products = []) =>
  products.map((product) => ({
    label: `${product.name} | ID: ${product.id}`,
    value: product.id,
  }));

const getInitialRecordRequest = (hasPostId) =>
  hasPostId ? REQUEST_LOADING : REQUEST_SUCCESS;

export const useGroupConfigBootstrap = ({
  postId,
  groupConfigCptSlug,
  languageCodes,
  defaultForm,
}) => {
  const hasPostId = Boolean(postId);
  const [form, setForm] = useState(defaultForm);
  const [recordRequest, setRecordRequest] = useState(
    getInitialRecordRequest(hasPostId),
  );
  const [pagesRequest, setPagesRequest] = useState(REQUEST_IDLE);
  const [productsRequest, setProductsRequest] = useState(REQUEST_IDLE);
  const [wpPagesOptions, setWpPagesOptions] = useState([]);
  const [wcProductOptions, setWcProductOptions] = useState([]);

  const loadRecord = useCallback(async () => {
    if (!hasPostId) {
      setRecordRequest(REQUEST_SUCCESS);
      return;
    }

    setRecordRequest(REQUEST_LOADING);

    try {
      const post = await apiFetch({
        path: addQueryArgs(`${API_URL}/${groupConfigCptSlug}/${postId}`, {}),
      });

      setForm(normalizeGroupConfigPostToForm(post, languageCodes));
      setRecordRequest(REQUEST_SUCCESS);
    } catch (error) {
      setRecordRequest({ status: "error", error });
    }
  }, [groupConfigCptSlug, hasPostId, languageCodes, postId]);

  const loadPages = useCallback(async () => {
    setPagesRequest(REQUEST_LOADING);

    try {
      const pages = await apiFetch({
        path: addQueryArgs(`${API_URL}/pages`, {
          _fields: "id,title",
          status: "publish",
          per_page: -1,
        }),
      });

      setWpPagesOptions(mapPagesToOptions(pages));
      setPagesRequest(REQUEST_SUCCESS);
    } catch (error) {
      setPagesRequest({ status: "error", error });
    }
  }, []);

  const loadProducts = useCallback(async () => {
    setProductsRequest(REQUEST_LOADING);

    try {
      const products = await fetchWcProducts({
        status: "publish",
        per_page: 100,
      });

      setWcProductOptions(mapProductsToOptions(products));
      setProductsRequest(REQUEST_SUCCESS);
    } catch (error) {
      setProductsRequest({ status: "error", error });
    }
  }, []);

  useEffect(() => {
    setForm(defaultForm);
    setRecordRequest(getInitialRecordRequest(hasPostId));
    setPagesRequest(REQUEST_IDLE);
    setProductsRequest(REQUEST_IDLE);
    setWpPagesOptions([]);
    setWcProductOptions([]);

    loadPages();
    loadProducts();

    if (hasPostId) {
      loadRecord();
    }
  }, [defaultForm, hasPostId, loadPages, loadProducts, loadRecord]);

  return {
    form,
    setForm,
    recordRequest,
    pagesRequest,
    productsRequest,
    wpPagesOptions,
    wcProductOptions,
    retryRecord: loadRecord,
    retryPages: loadPages,
    retryProducts: loadProducts,
    isRecordReady: !hasPostId || recordRequest.status === "success",
  };
};
