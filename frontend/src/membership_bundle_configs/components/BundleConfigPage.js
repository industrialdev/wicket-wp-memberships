import { useMemo, useState } from "react";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import AdminNoticeStack from "../../shared/components/AdminNoticeStack";
import AdminPageErrorBoundary from "../../shared/components/AdminPageErrorBoundary";
import { Wrap } from "../../shared/styled_elements";
import { API_URL } from "../../shared/constants";
import {
  buildBundleConfigPayload,
  createDefaultForm,
  getPrimaryErrorMessage,
  normalizeApiErrors,
} from "../utils/formUtils";
import { useBundleConfigBootstrap } from "../hooks/useBundleConfigBootstrap";
import BundleConfigForm from "./BundleConfigForm";

const BundleConfigPageContent = ({
  bundleConfigCptSlug,
  bundleConfigListUrl,
  postId,
  languageCodes,
}) => {
  const languageCodesArray = useMemo(() => {
    const codes = String(languageCodes || "")
      .split(",")
      .map((code) => code.trim())
      .filter(Boolean);

    return codes.length > 0 ? codes : ["en"];
  }, [languageCodes]);

  const defaultForm = useMemo(
    () => createDefaultForm(languageCodesArray),
    [languageCodesArray],
  );
  const [isSubmitting, setSubmitting] = useState(false);
  const [submitErrors, setSubmitErrors] = useState([]);
  const isEditing = Boolean(postId);

  const {
    form,
    setForm,
    recordRequest,
    wpPostsOptions,
    wcProductOptions,
    retryRecord,
    loadPostOptions,
    loadProductOptions,
    isRecordReady,
  } = useBundleConfigBootstrap({
    postId,
    bundleConfigCptSlug,
    languageCodes: languageCodesArray,
    defaultForm,
  });

  const validateForm = () => {
    const errors = [];

    if (!form.name) {
      errors.push(
        __("Bundle Configuration Name is required", "wicket-memberships"),
      );
    }

    setSubmitErrors(errors);
    return errors.length === 0;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!validateForm()) {
      return;
    }

    setSubmitting(true);
    setSubmitErrors([]);

    try {
      const endpoint = postId
        ? `${API_URL}/${bundleConfigCptSlug}/${postId}`
        : `${API_URL}/${bundleConfigCptSlug}`;

      const response = await apiFetch({
        path: endpoint,
        method: "POST",
        data: buildBundleConfigPayload(form),
      });

      if (response.id) {
        window.location.href = bundleConfigListUrl;
        return;
      }

      setSubmitting(false);
    } catch (error) {
      setSubmitErrors(normalizeApiErrors(error));
      setSubmitting(false);
    }
  };

  const notices = [
    ...(isEditing && recordRequest.status === "error"
      ? [
          {
            id: "record-error",
            status: "warning",
            message: getPrimaryErrorMessage(
              recordRequest.error,
              __(
                "The saved bundle configuration could not be loaded. Retry to continue editing.",
                "wicket-memberships",
              ),
            ),
            action: {
              label: __("Retry loading", "wicket-memberships"),
              onClick: retryRecord,
            },
          },
        ]
      : []),
    ...submitErrors.map((message, index) => ({
      id: `submit-error-${index}`,
      status: "warning",
      message,
    })),
  ];

  return (
    <Wrap>
      <AdminNoticeStack notices={notices} />
      <BundleConfigForm
        form={form}
        bundleConfigListUrl={bundleConfigListUrl}
        isEditing={isEditing}
        isRecordReady={isRecordReady}
        isSubmitting={isSubmitting}
        languageCodes={languageCodesArray}
        loadPostOptions={loadPostOptions}
        loadProductOptions={loadProductOptions}
        onSubmit={handleSubmit}
        postId={postId}
        setForm={setForm}
        wcProductOptions={wcProductOptions}
        wpPostsOptions={wpPostsOptions}
      />
    </Wrap>
  );
};

const BundleConfigPage = ({
  bundleConfigCptSlug,
  bundleConfigListUrl,
  postId,
  languageCodes,
}) => {
  const [errorBoundaryResetKey, setErrorBoundaryResetKey] = useState(0);

  return (
    <div className="wrap">
      <h1 className="wp-heading-inline">
        {postId
          ? __("Edit Membership Bundle Configuration", "wicket-memberships")
          : __("Add New Membership Bundle Configuration", "wicket-memberships")}
      </h1>
      <hr className="wp-header-end" />

      <AdminPageErrorBoundary
        onReset={() => setErrorBoundaryResetKey((value) => value + 1)}
        resetKey={errorBoundaryResetKey}
      >
        <BundleConfigPageContent
          bundleConfigCptSlug={bundleConfigCptSlug}
          bundleConfigListUrl={bundleConfigListUrl}
          key={errorBoundaryResetKey}
          languageCodes={languageCodes}
          postId={postId}
        />
      </AdminPageErrorBoundary>
    </div>
  );
};

export default BundleConfigPage;
