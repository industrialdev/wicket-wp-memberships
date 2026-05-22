import { __ } from "@wordpress/i18n";
import he from "he";
import { getDefaultCycleData, normalizeCycleData } from "../../shared/cycleUtils";

export { getDefaultCycleData, normalizeCycleData };

export const createDefaultLocales = (languageCodes = []) =>
  languageCodes.reduce((locales, code) => {
    locales[code] = {
      callout_header: "",
      callout_content: "",
      callout_button_label: "",
    };

    return locales;
  }, {});

export const mergeLocales = (defaultLocales, incomingLocales = {}) =>
  Object.keys(defaultLocales).reduce((locales, code) => {
    locales[code] = {
      ...defaultLocales[code],
      ...(incomingLocales?.[code] || {}),
    };

    return locales;
  }, {});

export const mergeCalloutData = (defaultLocales, calloutData = {}) => ({
  ...calloutData,
  locales: mergeLocales(defaultLocales, calloutData?.locales),
});

export const createEmptySeason = () => ({
  season_name: "",
  active: true,
  start_date: "",
  end_date: "",
});

export const createDefaultForm = (languageCodes = []) => {
  return {
    name: "",
    renewal_window_data: {
      days_count: "1",
      locales: createDefaultLocales(languageCodes),
    },
    late_fee_window_data: {
      days_count: "0",
      product_id: "-1",
      locales: createDefaultLocales(languageCodes),
    },
    cycle_data: getDefaultCycleData(),
    bundle_config_data: {
      renewal_type: "subscription",
      renewal_form_page_id: "",
      approval_required: false,
      grant_owner_assignment: false,
      approval_email_recipient: "",
      approval_callout_data: {
        locales: createDefaultLocales(languageCodes),
      },
    },
  };
};

export const normalizeBundleConfigPostToForm = (post, languageCodes = []) => {
  const defaultForm = createDefaultForm(languageCodes);
  const defaultLocales = createDefaultLocales(languageCodes);
  const groupConfigData = post?.bundle_config_data || {};

  return {
    ...defaultForm,
    name: he.decode(post?.title?.rendered || ""),
    renewal_window_data: {
      ...defaultForm.renewal_window_data,
      ...(post?.renewal_window_data || {}),
      locales: mergeLocales(
        defaultLocales,
        post?.renewal_window_data?.locales || {},
      ),
    },
    late_fee_window_data: {
      ...defaultForm.late_fee_window_data,
      ...(post?.late_fee_window_data || {}),
      locales: mergeLocales(
        defaultLocales,
        post?.late_fee_window_data?.locales || {},
      ),
    },
    cycle_data: normalizeCycleData(post?.cycle_data),
    bundle_config_data: {
      ...defaultForm.bundle_config_data,
      ...groupConfigData,
      renewal_type: groupConfigData.renewal_type || "subscription",
      renewal_form_page_id: groupConfigData.renewal_form_page_id || "",
      approval_required:
        groupConfigData.approval_required === true ||
        groupConfigData.approval_required === 1 ||
        groupConfigData.approval_required === "1",
      grant_owner_assignment:
        groupConfigData.grant_owner_assignment === true ||
        groupConfigData.grant_owner_assignment === 1 ||
        groupConfigData.grant_owner_assignment === "1",
      approval_email_recipient: groupConfigData.approval_email_recipient || "",
      approval_callout_data: mergeCalloutData(
        defaultLocales,
        groupConfigData.approval_callout_data || {},
      ),
    },
  };
};

export const buildBundleConfigPayload = (form) => ({
  title: form.name,
  status: "publish",
  renewal_window_data: form.renewal_window_data,
  late_fee_window_data: form.late_fee_window_data,
  cycle_data: form.cycle_data,
  bundle_config_data: {
    ...form.bundle_config_data,
    renewal_form_page_id:
      form.bundle_config_data.renewal_type === "form_page"
        ? parseInt(form.bundle_config_data.renewal_form_page_id, 10) || 0
        : 0,
    approval_required: !!form.bundle_config_data.approval_required,
    grant_owner_assignment: !!form.bundle_config_data.grant_owner_assignment,
  },
});

export const normalizeApiErrors = (
  error,
  fallbackMessage = __(
    "Something went wrong. Please try again.",
    "wicket-memberships",
  ),
) => {
  if (error?.data?.params) {
    return Object.keys(error.data.params)
      .flatMap((key) =>
        String(error.data.params[key])
          .split(/(?<=[.?!])\s+|\.$/)
          .map((message) => message.trim())
          .filter(Boolean),
      )
      .filter(Boolean);
  }

  if (error?.message) {
    return [error.message];
  }

  return [fallbackMessage];
};

export const getPrimaryErrorMessage = (error, fallbackMessage) =>
  normalizeApiErrors(error, fallbackMessage)[0];

export const findOptionByValue = (options = [], value) =>
  options.find((option) => String(option.value) === String(value));
