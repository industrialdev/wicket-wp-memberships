import { useCallback, useEffect, useState } from "react";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import he from "he";
import { API_URL, TIER_CPT_SLUG } from "../../shared/constants";
import { fetchWcProducts } from "../../shared/services/api";
import { normalizeBundleConfigPostToForm } from "../utils/formUtils";

const REQUEST_IDLE = { status: "idle", error: null };
const REQUEST_LOADING = { status: "loading", error: null };
const REQUEST_SUCCESS = { status: "success", error: null };

const mapPostsToOptions = (posts = []) =>
  posts.map((post) => ({
    title: he.decode(post.title.rendered),
    value: post.id,
    modified: post.modified,
    published: post.date,
  }));

const mapProductsToOptions = (products = []) =>
  products.map((product) => ({
    title: product.name,
    value: product.id,
    sku: product.sku,
    price: product.price,
  }));

// Individual-only filter mirrors AddMemberToBundleModal.js's loadTierOptions()
// so the config field and the add-member dropdown never diverge on what
// counts as an eligible tier type.
//
// Uses `label` (not `title`, unlike mapPostsToOptions/mapProductsToOptions
// above) because this option list is passed straight into SelectWpStyled
// (react-select), which reads option.label natively — no getOptionLabel
// override, unlike ModalPostSelector's custom rendering elsewhere in this
// form, which reads .title itself.
const mapTiersToOptions = (posts = []) =>
  posts
    .filter((post) => post.tier_data?.type === "individual")
    .map((post) => ({
      label: he.decode(post.title.rendered),
      value: post.id,
    }));

const getInitialRecordRequest = (hasPostId) =>
  hasPostId ? REQUEST_LOADING : REQUEST_SUCCESS;

export const useBundleConfigBootstrap = ({
  postId,
  bundleConfigCptSlug,
  languageCodes,
  defaultForm,
}) => {
  const hasPostId = Boolean(postId);
  const [form, setForm] = useState(defaultForm);
  const [recordRequest, setRecordRequest] = useState(
    getInitialRecordRequest(hasPostId),
  );
  const [postsRequest, setPostsRequest] = useState(REQUEST_IDLE);
  const [productsRequest, setProductsRequest] = useState(REQUEST_IDLE);
  const [tiersRequest, setTiersRequest] = useState(REQUEST_IDLE);
  const [wpPostsOptions, setWpPostsOptions] = useState([]);
  const [wcProductOptions, setWcProductOptions] = useState([]);
  const [tierOptions, setTierOptions] = useState([]);

  const loadRecord = useCallback(async () => {
    if (!hasPostId) {
      setRecordRequest(REQUEST_SUCCESS);
      return;
    }

    setRecordRequest(REQUEST_LOADING);

    try {
      const post = await apiFetch({
        path: addQueryArgs(`${API_URL}/${bundleConfigCptSlug}/${postId}`, {}),
      });

      setForm(normalizeBundleConfigPostToForm(post, languageCodes));
      setRecordRequest(REQUEST_SUCCESS);
    } catch (error) {
      setRecordRequest({ status: "error", error });
    }
  }, [bundleConfigCptSlug, hasPostId, languageCodes, postId]);

  const loadPosts = useCallback(async (restSlug = "pages") => {
    setPostsRequest(REQUEST_LOADING);

    try {
      const posts = await apiFetch({
        path: addQueryArgs(`${API_URL}/${restSlug}`, {
          _fields: "id,title,date,modified",
          status: "publish",
          per_page: -1,
        }),
      });

      const options = mapPostsToOptions(posts);
      setWpPostsOptions(options);
      setPostsRequest(REQUEST_SUCCESS);
      return options;
    } catch (error) {
      setPostsRequest({ status: "error", error });
      throw error;
    }
  }, []);

  const loadProducts = useCallback(async () => {
    setProductsRequest(REQUEST_LOADING);

    try {
      const products = await fetchWcProducts({
        status: "publish",
        per_page: -1,
      });

      const options = mapProductsToOptions(products);
      setWcProductOptions(options);
      setProductsRequest(REQUEST_SUCCESS);
      return options;
    } catch (error) {
      setProductsRequest({ status: "error", error });
      throw error;
    }
  }, []);

  const loadTiers = useCallback(async () => {
    setTiersRequest(REQUEST_LOADING);

    try {
      const posts = await apiFetch({
        path: addQueryArgs(`${API_URL}/${TIER_CPT_SLUG}`, {
          posts_per_page: -1,
          status: "publish",
        }),
      });

      const options = mapTiersToOptions(posts);
      setTierOptions(options);
      setTiersRequest(REQUEST_SUCCESS);
      return options;
    } catch (error) {
      setTiersRequest({ status: "error", error });
      throw error;
    }
  }, []);

  useEffect(() => {
    setForm(defaultForm);
    setRecordRequest(getInitialRecordRequest(hasPostId));
    setPostsRequest(REQUEST_IDLE);
    setProductsRequest(REQUEST_IDLE);
    setTiersRequest(REQUEST_IDLE);
    setWpPostsOptions([]);
    setWcProductOptions([]);
    setTierOptions([]);

    if (hasPostId) {
      loadRecord();
    }
  }, [defaultForm, hasPostId, loadRecord]);

  useEffect(() => {
    loadTiers();
  }, [loadTiers]);

  return {
    form,
    setForm,
    recordRequest,
    postsRequest,
    productsRequest,
    tiersRequest,
    wpPostsOptions,
    wcProductOptions,
    tierOptions,
    retryRecord: loadRecord,
    retryPosts: loadPosts,
    retryProducts: loadProducts,
    retryTiers: loadTiers,
    loadPostOptions: loadPosts,
    loadProductOptions: loadProducts,
    isRecordReady: !hasPostId || recordRequest.status === "success",
  };
};
