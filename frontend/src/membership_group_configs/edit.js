import { __ } from "@wordpress/i18n";
import { createRoot } from "react-dom/client";
import apiFetch from "@wordpress/api-fetch";
import { useState, useEffect } from "react";
import { addQueryArgs } from "@wordpress/url";
import {
  TextControl,
  TextareaControl,
  Button,
  Flex,
  FlexItem,
  FlexBlock,
  Modal,
  Notice,
  SelectControl,
  CheckboxControl,
  __experimentalHeading as Heading,
  Icon,
} from "@wordpress/components";
import { API_URL, DEFAULT_DATE_FORMAT } from "../constants";
import he from "he";
import {
  Wrap,
  ActionRow,
  FormFlex,
  ErrorsRow,
  BorderedBox,
  SelectWpStyled,
  CustomDisabled,
  LabelWpStyled,
  ReactDatePickerStyledWrap,
  AppWrap,
} from "../styled_elements";
import DatePicker from "react-datepicker";
import styled from "styled-components";
import { fetchWcProducts } from "../services/api";

const MarginedFlex = styled(Flex)`
  margin-top: 15px;
`;

const getDefaultCycleData = () => ({
  cycle_type: "calendar",
  anniversary_data: {
    period_count: "1",
    period_type: "year",
    align_end_dates_enabled: false,
    align_end_dates_type: "first-day-of-month",
  },
  calendar_items: [],
});

const normalizeCycleData = (cycleData) => {
  const defaultCycleData = getDefaultCycleData();

  return {
    ...defaultCycleData,
    ...(cycleData || {}),
    anniversary_data: {
      ...defaultCycleData.anniversary_data,
      ...(cycleData?.anniversary_data || {}),
    },
    calendar_items: Array.isArray(cycleData?.calendar_items)
      ? cycleData.calendar_items
      : [],
  };
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

const CreateMembershipGroupConfig = ({
  groupConfigCptSlug,
  groupConfigListUrl,
  postId,
  languageCodes,
}) => {
  const languageCodesArray = languageCodes.split(",");

  // Active locale state for each callout modal
  const [currentRenewalWindowDataLocale, setCurrentRenewalWindowDataLocale] =
    useState(languageCodesArray[0]);
  const [currentLateFeeWindowDataLocale, setCurrentLateFeeWindowDataLocale] =
    useState(languageCodesArray[0]);
  const [currentApprovalCalloutLocale, setCurrentApprovalCalloutLocale] =
    useState(languageCodesArray[0]);

  // Season modal state
  const [currentSeasonIndex, setCurrentSeasonIndex] = useState(null);
  const [tempSeason, setTempSeason] = useState({
    season_name: "",
    active: true,
    start_date: "",
    end_date: "",
  });
  const [isCreateSeasonModalOpen, setCreateSeasonModalOpen] = useState(false);
  const openCreateSeasonModalOpen = () => setCreateSeasonModalOpen(true);
  const closeCreateSeasonModalOpen = () => setCreateSeasonModalOpen(false);

  // Callout modal state
  const [isRenewalWindowCalloutModalOpen, setRenewalWindowCalloutModalOpen] =
    useState(false);
  const openRenewalWindowCalloutModal = () =>
    setRenewalWindowCalloutModalOpen(true);
  const closeRenewalWindowCalloutModal = () =>
    setRenewalWindowCalloutModalOpen(false);

  const [isLateFeeWindowCalloutModalOpen, setLateFeeWindowCalloutModalOpen] =
    useState(false);
  const openLateFeeWindowCalloutModal = () =>
    setLateFeeWindowCalloutModalOpen(true);
  const closeLateFeeWindowCalloutModal = () =>
    setLateFeeWindowCalloutModalOpen(false);

  const [isApprovalCalloutModalOpen, setApprovalCalloutModalOpen] =
    useState(false);
  const openApprovalCalloutModal = () => setApprovalCalloutModalOpen(true);
  const closeApprovalCalloutModal = () => setApprovalCalloutModalOpen(false);

  const [isSubmitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState([]);
  const [seasonErrors, setSeasonErrors] = useState({});

  // WP pages for form_page renewal type
  const [wpPagesOptions, setWpPagesOptions] = useState([]);
  const [wcProductOptions, setWcProductOptions] = useState([]);

  // Build default locale structure
  let default_locales = {};
  languageCodesArray.forEach((code) => {
    default_locales[code] = {
      callout_header: "",
      callout_content: "",
      callout_button_label: "",
    };
  });

  // Main form state
  const [form, setForm] = useState({
    name: "",
    renewal_window_data: {
      days_count: "1",
      locales: default_locales,
    },
    late_fee_window_data: {
      days_count: "0",
      product_id: "-1",
      locales: default_locales,
    },
    cycle_data: {
      ...getDefaultCycleData(),
    },
    group_config_data: {
      renewal_type: "subscription",
      renewal_form_page_id: "",
      approval_required: false,
      grant_owner_assignment: false,
      approval_email_recipient: "",
      approval_callout_data: {
        locales: default_locales,
      },
    },
  });

  // Temp copy used inside modals (so changes don't affect main form until saved)
  const [tempForm, setTempForm] = useState(form);

  // ---------------------------------------------------------------------------
  // Callout helpers
  // ---------------------------------------------------------------------------

  const reInitRenewalWindowCallout = () => {
    setTempForm(form);
    openRenewalWindowCalloutModal();
  };

  const reInitLateFeeWindowCallout = () => {
    setTempForm(form);
    openLateFeeWindowCalloutModal();
  };

  const reInitApprovalCallout = () => {
    setTempForm(form);
    openApprovalCalloutModal();
  };

  const saveRenewalWindowCallout = () => {
    setForm({
      ...form,
      renewal_window_data: {
        ...form.renewal_window_data,
        locales: tempForm.renewal_window_data.locales,
      },
    });
  };

  const saveLateFeeWindowCallout = () => {
    setForm({
      ...form,
      late_fee_window_data: {
        ...form.late_fee_window_data,
        locales: tempForm.late_fee_window_data.locales,
      },
    });
  };

  const saveApprovalCallout = () => {
    setForm({
      ...form,
      group_config_data: {
        ...form.group_config_data,
        approval_callout_data: {
          locales: tempForm.group_config_data.approval_callout_data.locales,
        },
      },
    });
  };

  // ---------------------------------------------------------------------------
  // Validation
  // ---------------------------------------------------------------------------

  const validateForm = () => {
    let isValid = true;
    let newErrors = [];

    if (form.name.length === 0) {
      newErrors.push(
        __("Group Configuration Name is required", "wicket-memberships"),
      );
      isValid = false;
    }

    setErrors(newErrors);
    return isValid;
  };

  // ---------------------------------------------------------------------------
  // Season helpers
  // ---------------------------------------------------------------------------

  const initSeasonModal = (season_index) => {
    setCurrentSeasonIndex(season_index);
    setSeasonErrors({});

    if (season_index === null) {
      setTempSeason({ season_name: "", active: true, start_date: "", end_date: "" });
    } else {
      setTempSeason(form.cycle_data.calendar_items[season_index]);
    }
    openCreateSeasonModalOpen();
  };

  const validateSeason = () => {
    let isValid = true;
    const newErrors = {};

    if (tempSeason.season_name.length === 0) {
      newErrors.seasonName = __("Season Name is required", "wicket-memberships");
      isValid = false;
    }
    if (tempSeason.start_date.length === 0) {
      newErrors.seasonStartDate = __("Season Start Date is required", "wicket-memberships");
      isValid = false;
    }
    if (tempSeason.end_date.length === 0) {
      newErrors.seasonEndDate = __("Season End Date is required", "wicket-memberships");
      isValid = false;
    }
    if (tempSeason.start_date.length > 0 && tempSeason.end_date.length > 0) {
      if (new Date(tempSeason.start_date) > new Date(tempSeason.end_date)) {
        newErrors.seasonEndDate = __(
          "Season End Date must be greater than Start Date",
          "wicket-memberships",
        );
        isValid = false;
      }
    }

    setSeasonErrors(newErrors);
    return isValid;
  };

  const handleCreateSeasonSubmit = (e) => {
    e.preventDefault();
    if (!validateSeason()) return;

    if (currentSeasonIndex === null) {
      setForm({
        ...form,
        cycle_data: {
          ...form.cycle_data,
          calendar_items: [
            ...form.cycle_data.calendar_items,
            {
              season_name: tempSeason.season_name,
              active: tempSeason.active,
              start_date: tempSeason.start_date,
              end_date: tempSeason.end_date,
            },
          ],
        },
      });
    } else {
      const seasons = form.cycle_data.calendar_items.map((season, index) => {
        if (index === currentSeasonIndex) {
          return {
            season_name: tempSeason.season_name,
            active: tempSeason.active,
            start_date: tempSeason.start_date,
            end_date: tempSeason.end_date,
          };
        }
        return season;
      });
      setForm({
        ...form,
        cycle_data: { ...form.cycle_data, calendar_items: seasons },
      });
    }
    closeCreateSeasonModalOpen();
  };

  // ---------------------------------------------------------------------------
  // Submit
  // ---------------------------------------------------------------------------

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!validateForm()) return;

    setSubmitting(true);

    const endpoint = postId
      ? `${API_URL}/${groupConfigCptSlug}/${postId}`
      : `${API_URL}/${groupConfigCptSlug}`;

    apiFetch({
      path: endpoint,
      method: "POST",
      data: {
        title: form.name,
        status: "publish",
        renewal_window_data: form.renewal_window_data,
        late_fee_window_data: form.late_fee_window_data,
        cycle_data: form.cycle_data,
        group_config_data: {
          ...form.group_config_data,
          renewal_form_page_id:
            form.group_config_data.renewal_type === "form_page"
              ? parseInt(form.group_config_data.renewal_form_page_id) || 0
              : 0,
          approval_required: !!form.group_config_data.approval_required,
          grant_owner_assignment: !!form.group_config_data.grant_owner_assignment,
        },
      },
    })
      .then((response) => {
        if (response.id) {
          window.location.href = groupConfigListUrl;
        }
      })
      .catch((error) => {
        let newErrors = [];
        if (error.data && error.data.params) {
          Object.keys(error.data.params).forEach((key) => {
            let msgs = error.data.params[key].split(/(?<=[.?!])\s+|\.$/)
              .filter((s) => s.trim() !== "");
            newErrors = newErrors.concat(msgs);
          });
        } else if (error.message) {
          newErrors.push(error.message);
        }
        setErrors(newErrors);
        setSubmitting(false);
      });
  };

  // ---------------------------------------------------------------------------
  // Data loading
  // ---------------------------------------------------------------------------

  useEffect(() => {
    // Fetch WP pages for form_page renewal type
    apiFetch({
      path: addQueryArgs(`${API_URL}/pages`, {
        _fields: "id,title",
        status: "publish",
        per_page: -1,
      }),
    }).then((pages) => {
      setWpPagesOptions(
        pages.map((page) => ({
          label: `${he.decode(page.title.rendered)} | ID: ${page.id}`,
          value: page.id,
        })),
      );
    });

    fetchWcProducts({
      status: "publish",
      per_page: 100,
    }).then((products) => {
      setWcProductOptions(
        products.map((product) => ({
          label: `${product.name} | ID: ${product.id}`,
          value: product.id,
        })),
      );
    });

    // Load existing record when editing
    if (postId) {
      apiFetch({
        path: addQueryArgs(`${API_URL}/${groupConfigCptSlug}/${postId}`, {}),
      }).then((post) => {
        const groupConfigData = post.group_config_data || {};
        setForm({
          name: he.decode(post.title.rendered),
          renewal_window_data: post.renewal_window_data || form.renewal_window_data,
          late_fee_window_data: {
            ...form.late_fee_window_data,
            ...(post.late_fee_window_data || {}),
          },
          cycle_data: normalizeCycleData(post.cycle_data),
          group_config_data: {
            renewal_type: groupConfigData.renewal_type || "subscription",
            renewal_form_page_id: groupConfigData.renewal_form_page_id || "",
            approval_required: groupConfigData.approval_required == 1,
            grant_owner_assignment: groupConfigData.grant_owner_assignment == 1,
            approval_email_recipient: groupConfigData.approval_email_recipient || "",
            approval_callout_data: groupConfigData.approval_callout_data || {
              locales: default_locales,
            },
          },
        });
      });
    }
  }, []);

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <>
      <div className="wrap">
        <h1 className="wp-heading-inline">
          {postId
            ? __("Edit Membership Group Configuration", "wicket-memberships")
            : __("Add New Membership Group Configuration", "wicket-memberships")}
        </h1>
        <hr className="wp-header-end" />

        <Wrap>
          {errors.length > 0 && (
            <ErrorsRow>
              {errors.map((error) => (
                <Notice isDismissible={false} key={error} status="warning">
                  {error}
                </Notice>
              ))}
            </ErrorsRow>
          )}

          <form onSubmit={handleSubmit}>
            {/* ----------------------------------------------------------------
                Name
            ---------------------------------------------------------------- */}
            <Flex align="end" justify="start" gap={5} direction={["column", "row"]}>
              <FlexBlock>
                <TextControl
                  label={__("Group Configuration Name", "wicket-memberships")}
                  onChange={(value) => setForm({ ...form, name: value })}
                  value={form.name}
                />
              </FlexBlock>
            </Flex>

            {/* Renewal Window */}
            <BorderedBox>
              <Flex align="end" justify="start" gap={5} direction={["column", "row"]}>
                <FlexBlock>
                  <TextControl
                    label={__("Renewal Window (Days)", "wicket-memberships")}
                    type="number"
                    min="1"
                    value={form.renewal_window_data.days_count}
                    __nextHasNoMarginBottom={true}
                    onChange={(value) =>
                      setForm({
                        ...form,
                        renewal_window_data: {
                          ...form.renewal_window_data,
                          days_count: value,
                        },
                      })
                    }
                  />
                </FlexBlock>
                <FlexItem>
                  <Button variant="secondary" onClick={reInitRenewalWindowCallout}>
                    <span className="dashicons dashicons-screenoptions me-2"></span>
                    &nbsp;
                    {__("Callout Configuration", "wicket-memberships")}
                  </Button>
                </FlexItem>
              </Flex>
            </BorderedBox>

            {/* Grace Period Window */}
            <BorderedBox>
              <Flex align="end" gap={5} direction={["column", "row"]}>
                <FlexBlock>
                  <TextControl
                    label={__("Grace Period Window (Days)", "wicket-memberships")}
                    type="number"
                    min="0"
                    value={form.late_fee_window_data.days_count}
                    __nextHasNoMarginBottom={true}
                    onChange={(value) =>
                      setForm({
                        ...form,
                        late_fee_window_data: {
                          ...form.late_fee_window_data,
                          days_count: value,
                        },
                      })
                    }
                  />
                </FlexBlock>
                <FlexBlock>
                  <LabelWpStyled htmlFor="late_fee_product_id">
                    {__("Product", "wicket-memberships")}
                  </LabelWpStyled>
                  <SelectWpStyled
                    id="late_fee_product_id"
                    classNamePrefix="select"
                    value={wcProductOptions.find(
                      (option) =>
                        option.value === form.late_fee_window_data.product_id,
                    )}
                    isClearable={true}
                    isSearchable={true}
                    isLoading={wcProductOptions.length === 0}
                    options={wcProductOptions}
                    onChange={(selected) => {
                      if (selected === null) {
                        setForm({
                          ...form,
                          late_fee_window_data: {
                            ...form.late_fee_window_data,
                            product_id: "-1",
                          },
                        });
                      } else {
                        setForm({
                          ...form,
                          late_fee_window_data: {
                            ...form.late_fee_window_data,
                            product_id: selected.value,
                          },
                        });
                      }
                    }}
                  />
                </FlexBlock>
                <FlexItem>
                  <Button variant="secondary" onClick={reInitLateFeeWindowCallout}>
                    <span className="dashicons dashicons-screenoptions me-2"></span>
                    &nbsp;
                    {__("Callout Configuration", "wicket-memberships")}
                  </Button>
                </FlexItem>
              </Flex>
            </BorderedBox>

            {/* Cycle Data */}
            <BorderedBox>
              <Flex align="end" justify="start" gap={5} direction={["column", "row"]}>
                <FlexBlock>
                  <SelectControl
                    label={__("Cycle", "wicket-memberships")}
                    value={form.cycle_data.cycle_type}
                    __nextHasNoMarginBottom={true}
                    options={[
                      { label: __("Calendar", "wicket-memberships"), value: "calendar" },
                      { label: __("Anniversary", "wicket-memberships"), value: "anniversary" },
                    ]}
                    onChange={(value) =>
                      setForm({
                        ...form,
                        cycle_data: {
                          ...normalizeCycleData(form.cycle_data),
                          cycle_type: value,
                        },
                      })
                    }
                  />
                </FlexBlock>
                {form.cycle_data.cycle_type === "calendar" && (
                  <FlexItem>
                    <Button variant="secondary" onClick={() => initSeasonModal(null)}>
                      <span className="dashicons dashicons-plus-alt"></span>
                      &nbsp;
                      {__("Add Season", "wicket-memberships")}
                    </Button>
                  </FlexItem>
                )}
              </Flex>

              {/* Anniversary Data */}
              {form.cycle_data.cycle_type === "anniversary" && (
                <>
                  <FormFlex align="end" gap={5} direction={["column", "row"]}>
                    <FlexBlock>
                      <TextControl
                        label={__("Membership Period", "wicket-memberships")}
                        type="number"
                        min="1"
                        __nextHasNoMarginBottom={true}
                        value={form.cycle_data.anniversary_data.period_count}
                        onChange={(value) =>
                          setForm({
                            ...form,
                            cycle_data: {
                              ...form.cycle_data,
                              anniversary_data: {
                                ...form.cycle_data.anniversary_data,
                                period_count: value,
                              },
                            },
                          })
                        }
                      />
                    </FlexBlock>
                    <FlexBlock>
                      <SelectControl
                        label=""
                        value={form.cycle_data.anniversary_data.period_type}
                        __nextHasNoMarginBottom={true}
                        options={[
                          { label: __("Year", "wicket-memberships"), value: "year" },
                          { label: __("Month", "wicket-memberships"), value: "month" },
                          { label: __("Week", "wicket-memberships"), value: "week" },
                        ]}
                        onChange={(value) =>
                          setForm({
                            ...form,
                            cycle_data: {
                              ...form.cycle_data,
                              anniversary_data: {
                                ...form.cycle_data.anniversary_data,
                                period_type: value,
                              },
                            },
                          })
                        }
                      />
                    </FlexBlock>
                  </FormFlex>

                  <BorderedBox>
                    <Flex align="end" justify="start" gap={5} direction={["column", "row"]}>
                      <FlexItem>
                        <CheckboxControl
                          checked={form.cycle_data.anniversary_data.align_end_dates_enabled}
                          label={__("Align End Dates", "wicket-memberships")}
                          __nextHasNoMarginBottom={true}
                          onChange={(value) =>
                            setForm({
                              ...form,
                              cycle_data: {
                                ...form.cycle_data,
                                anniversary_data: {
                                  ...form.cycle_data.anniversary_data,
                                  align_end_dates_enabled: value,
                                },
                              },
                            })
                          }
                        />
                      </FlexItem>
                      <FlexBlock>
                        <CustomDisabled isDisabled={!form.cycle_data.anniversary_data.align_end_dates_enabled}>
                          <SelectControl
                            label={__("Align by", "wicket-memberships")}
                            value={form.cycle_data.anniversary_data.align_end_dates_type}
                            __nextHasNoMarginBottom={true}
                            options={[
                              { label: __("First Day of Month", "wicket-memberships"), value: "first-day-of-month" },
                              { label: __("15th of Month", "wicket-memberships"), value: "15th-of-month" },
                              { label: __("Last Day of Month", "wicket-memberships"), value: "last-day-of-month" },
                            ]}
                            onChange={(value) =>
                              setForm({
                                ...form,
                                cycle_data: {
                                  ...form.cycle_data,
                                  anniversary_data: {
                                    ...form.cycle_data.anniversary_data,
                                    align_end_dates_type: value,
                                  },
                                },
                              })
                            }
                          />
                        </CustomDisabled>
                      </FlexBlock>
                    </Flex>
                  </BorderedBox>
                </>
              )}

              {/* Calendar Items */}
              {form.cycle_data.cycle_type === "calendar" && (
                <>
                  <FormFlex>
                    <Heading level="4" weight="300">
                      {__("Seasons", "wicket-memberships")}
                    </Heading>
                  </FormFlex>
                  <FormFlex>
                    <table className="widefat" cellSpacing="0">
                      <thead>
                        <tr>
                          <th className="manage-column column-columnname" scope="col">
                            {__("Season Name", "wicket-memberships")}
                          </th>
                          <th className="manage-column column-columnname" scope="col">
                            {__("Status", "wicket-memberships")}
                          </th>
                          <th className="manage-column column-columnname" scope="col">
                            {__("Start Date", "wicket-memberships")}
                          </th>
                          <th className="manage-column column-columnname" scope="col">
                            {__("End Date", "wicket-memberships")}
                          </th>
                          <th className="check-column"></th>
                        </tr>
                      </thead>
                      <tbody>
                        {normalizeCycleData(form.cycle_data).calendar_items.map((season, index) => (
                          <tr key={index} className={index % 2 === 0 ? "alternate" : ""}>
                            <td className="column-columnname">{season.season_name}</td>
                            <td className="column-columnname">
                              {season.active ? __("Active", "wicket-memberships") : __("Inactive", "wicket-memberships")}
                            </td>
                            <td className="column-columnname">{season.start_date}</td>
                            <td className="column-columnname">{season.end_date}</td>
                            <td>
                              <Button onClick={() => initSeasonModal(index)}>
                                <span className="dashicons dashicons-edit"></span>
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </FormFlex>
                </>
              )}
            </BorderedBox>

            {/* Approval */}
            <MarginedFlex>
              <FlexBlock>
                <BorderedBox>
                  <Flex align="end" justify="start" gap={5} direction={["column", "row"]}>
                    <FlexItem>
                      <CheckboxControl
                        label={__("Approval Required", "wicket-memberships")}
                        checked={form.group_config_data.approval_required}
                        onChange={(value) =>
                          setForm({
                            ...form,
                            group_config_data: {
                              ...form.group_config_data,
                              approval_required: value,
                            },
                          })
                        }
                        __nextHasNoMarginBottom={true}
                      />
                    </FlexItem>
                    <FlexBlock>
                      <CustomDisabled isDisabled={!form.group_config_data.approval_required}>
                        <TextControl
                          label={__("Approval Email Recipient", "wicket-memberships")}
                          type="email"
                          value={form.group_config_data.approval_email_recipient}
                          onChange={(value) =>
                            setForm({
                              ...form,
                              group_config_data: {
                                ...form.group_config_data,
                                approval_email_recipient: value,
                              },
                            })
                          }
                          __nextHasNoMarginBottom={true}
                        />
                      </CustomDisabled>
                    </FlexBlock>
                    <FlexItem>
                      <Button
                        variant="secondary"
                        disabled={!form.group_config_data.approval_required}
                        onClick={reInitApprovalCallout}
                      >
                        <span className="dashicons dashicons-screenoptions me-2"></span>&nbsp;
                        {__("Callout Configuration", "wicket-memberships")}
                      </Button>
                    </FlexItem>
                  </Flex>
                </BorderedBox>
              </FlexBlock>
            </MarginedFlex>

            {/* Renewal Type */}
            <MarginedFlex>
              <FlexBlock>
                <LabelWpStyled htmlFor="renewal_type">
                  {__("Renewal Type", "wicket-memberships")}
                </LabelWpStyled>
                <SelectWpStyled
                  id="renewal_type"
                  classNamePrefix="select"
                  value={[
                    { label: __("Subscription", "wicket-memberships"), value: "subscription" },
                    { label: __("Renewal Form Flow", "wicket-memberships"), value: "form_page" },
                  ].find((o) => o.value === form.group_config_data.renewal_type)}
                  isSearchable={true}
                  options={[
                    { label: __("Subscription", "wicket-memberships"), value: "subscription" },
                    { label: __("Renewal Form Flow", "wicket-memberships"), value: "form_page" },
                  ]}
                  onChange={(selected) =>
                    setForm({
                      ...form,
                      group_config_data: {
                        ...form.group_config_data,
                        renewal_type: selected.value,
                        renewal_form_page_id: "",
                      },
                    })
                  }
                />
              </FlexBlock>
            </MarginedFlex>

            {form.group_config_data.renewal_type === "form_page" && (
              <MarginedFlex>
                <FlexBlock>
                  <LabelWpStyled htmlFor="renewal_form_page">
                    {__("Renewal Form Page", "wicket-memberships")}
                  </LabelWpStyled>
                  <SelectWpStyled
                    id="renewal_form_page"
                    classNamePrefix="select"
                    placeholder={__("Select a page…", "wicket-memberships")}
                    value={wpPagesOptions.find(
                      (o) => o.value == form.group_config_data.renewal_form_page_id,
                    ) || null}
                    isSearchable={true}
                    options={wpPagesOptions}
                    onChange={(selected) =>
                      setForm({
                        ...form,
                        group_config_data: {
                          ...form.group_config_data,
                          renewal_form_page_id: selected ? selected.value : "",
                        },
                      })
                    }
                  />
                </FlexBlock>
              </MarginedFlex>
            )}

            {/* Submit row */}
            <ActionRow>
              <Flex align="end" justify="end" gap={5} direction={["column", "row"]}>
                <FlexItem>
                  <Button
                    isBusy={isSubmitting}
                    disabled={isSubmitting}
                    variant="primary"
                    type="submit"
                  >
                    {isSubmitting && __("Saving now...", "wicket-memberships")}
                    {!isSubmitting &&
                      (postId
                        ? __("Update Group Configuration", "wicket-memberships")
                        : __("Save Group Configuration", "wicket-memberships"))}
                  </Button>
                </FlexItem>
                <FlexItem>
                  <Button variant="tertiary" href={groupConfigListUrl}>
                    {__("Cancel", "wicket-memberships")}
                  </Button>
                </FlexItem>
              </Flex>
            </ActionRow>
          </form>
        </Wrap>

        {/* --------------------------------------------------------------------
            Renewal Window Callout Modal
        -------------------------------------------------------------------- */}
        {isRenewalWindowCalloutModalOpen && (
          <Modal
            title={__("Renewal Window - Callout Configuration", "wicket-memberships")}
            onRequestClose={closeRenewalWindowCalloutModal}
            style={{ maxWidth: "840px", width: "100%" }}
          >
            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveRenewalWindowCallout();
                closeRenewalWindowCalloutModal();
              }}
            >
                <SelectControl
                  label={__("Language", "wicket-memberships")}
                  options={languageCodesArray.map((code) => ({ label: code, value: code }))}
                  value={currentRenewalWindowDataLocale}
                  onChange={setCurrentRenewalWindowDataLocale}
                />

                <TextControl
                  label={__("Callout Header", "wicket-memberships")}
                  value={tempForm.renewal_window_data.locales[currentRenewalWindowDataLocale]?.callout_header || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      renewal_window_data: {
                        ...tempForm.renewal_window_data,
                        locales: {
                          ...tempForm.renewal_window_data.locales,
                          [currentRenewalWindowDataLocale]: {
                            ...tempForm.renewal_window_data.locales[currentRenewalWindowDataLocale],
                            callout_header: value,
                          },
                        },
                      },
                    })
                  }
                />

                <TextareaControl
                  label={__("Callout Content", "wicket-memberships")}
                  value={tempForm.renewal_window_data.locales[currentRenewalWindowDataLocale]?.callout_content || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      renewal_window_data: {
                        ...tempForm.renewal_window_data,
                        locales: {
                          ...tempForm.renewal_window_data.locales,
                          [currentRenewalWindowDataLocale]: {
                            ...tempForm.renewal_window_data.locales[currentRenewalWindowDataLocale],
                            callout_content: value,
                          },
                        },
                      },
                    })
                  }
                />

                <TextControl
                  label={__("Button Label", "wicket-memberships")}
                  value={tempForm.renewal_window_data.locales[currentRenewalWindowDataLocale]?.callout_button_label || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      renewal_window_data: {
                        ...tempForm.renewal_window_data,
                        locales: {
                          ...tempForm.renewal_window_data.locales,
                          [currentRenewalWindowDataLocale]: {
                            ...tempForm.renewal_window_data.locales[currentRenewalWindowDataLocale],
                            callout_button_label: value,
                          },
                        },
                      },
                    })
                  }
                />

                <ActionRow>
                  <Flex justify="end">
                    <FlexItem>
                      <Button variant="primary" type="submit">
                        {__("Save", "wicket-memberships")}
                      </Button>
                    </FlexItem>
                  </Flex>
                </ActionRow>
              </form>
          </Modal>
        )}

        {/* --------------------------------------------------------------------
            Grace Period Callout Modal
        -------------------------------------------------------------------- */}
        {isLateFeeWindowCalloutModalOpen && (
          <Modal
            title={__("Grace Period Window - Callout Configuration", "wicket-memberships")}
            onRequestClose={closeLateFeeWindowCalloutModal}
            style={{ maxWidth: "840px", width: "100%" }}
          >
            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveLateFeeWindowCallout();
                closeLateFeeWindowCalloutModal();
                }}
              >
                <SelectControl
                  label={__("Language", "wicket-memberships")}
                  options={languageCodesArray.map((code) => ({ label: code, value: code }))}
                  value={currentLateFeeWindowDataLocale}
                  onChange={setCurrentLateFeeWindowDataLocale}
                />

                <TextControl
                  label={__("Callout Header", "wicket-memberships")}
                  value={tempForm.late_fee_window_data.locales[currentLateFeeWindowDataLocale]?.callout_header || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      late_fee_window_data: {
                        ...tempForm.late_fee_window_data,
                        locales: {
                          ...tempForm.late_fee_window_data.locales,
                          [currentLateFeeWindowDataLocale]: {
                            ...tempForm.late_fee_window_data.locales[currentLateFeeWindowDataLocale],
                            callout_header: value,
                          },
                        },
                      },
                    })
                  }
                />

                <TextareaControl
                  label={__("Callout Content", "wicket-memberships")}
                  value={tempForm.late_fee_window_data.locales[currentLateFeeWindowDataLocale]?.callout_content || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      late_fee_window_data: {
                        ...tempForm.late_fee_window_data,
                        locales: {
                          ...tempForm.late_fee_window_data.locales,
                          [currentLateFeeWindowDataLocale]: {
                            ...tempForm.late_fee_window_data.locales[currentLateFeeWindowDataLocale],
                            callout_content: value,
                          },
                        },
                      },
                    })
                  }
                />

                <TextControl
                  label={__("Button Label", "wicket-memberships")}
                  value={tempForm.late_fee_window_data.locales[currentLateFeeWindowDataLocale]?.callout_button_label || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      late_fee_window_data: {
                        ...tempForm.late_fee_window_data,
                        locales: {
                          ...tempForm.late_fee_window_data.locales,
                          [currentLateFeeWindowDataLocale]: {
                            ...tempForm.late_fee_window_data.locales[currentLateFeeWindowDataLocale],
                            callout_button_label: value,
                          },
                        },
                      },
                    })
                  }
                />

                <ActionRow>
                  <Flex justify="end">
                    <FlexItem>
                      <Button variant="primary" type="submit">
                        {__("Save", "wicket-memberships")}
                      </Button>
                    </FlexItem>
                  </Flex>
                </ActionRow>
              </form>
          </Modal>
        )}

        {/* --------------------------------------------------------------------
            Approval Callout Modal
        -------------------------------------------------------------------- */}
        {isApprovalCalloutModalOpen && (
          <Modal
            title={__("Approval Callout Configuration", "wicket-memberships")}
            onRequestClose={closeApprovalCalloutModal}
            style={{ maxWidth: "840px", width: "100%" }}
          >
            <form
              onSubmit={(e) => {
                e.preventDefault();
                saveApprovalCallout();
                closeApprovalCalloutModal();
              }}
            >
                <SelectControl
                  label={__("Language", "wicket-memberships")}
                  options={languageCodesArray.map((code) => ({ label: code, value: code }))}
                  value={currentApprovalCalloutLocale}
                  onChange={setCurrentApprovalCalloutLocale}
                />

                <TextControl
                  label={__("Callout Header", "wicket-memberships")}
                  value={tempForm.group_config_data.approval_callout_data?.locales?.[currentApprovalCalloutLocale]?.callout_header || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      group_config_data: {
                        ...tempForm.group_config_data,
                        approval_callout_data: {
                          ...tempForm.group_config_data.approval_callout_data,
                          locales: {
                            ...tempForm.group_config_data.approval_callout_data.locales,
                            [currentApprovalCalloutLocale]: {
                              ...tempForm.group_config_data.approval_callout_data.locales[currentApprovalCalloutLocale],
                              callout_header: value,
                            },
                          },
                        },
                      },
                    })
                  }
                />

                <TextareaControl
                  label={__("Callout Content", "wicket-memberships")}
                  value={tempForm.group_config_data.approval_callout_data?.locales?.[currentApprovalCalloutLocale]?.callout_content || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      group_config_data: {
                        ...tempForm.group_config_data,
                        approval_callout_data: {
                          ...tempForm.group_config_data.approval_callout_data,
                          locales: {
                            ...tempForm.group_config_data.approval_callout_data.locales,
                            [currentApprovalCalloutLocale]: {
                              ...tempForm.group_config_data.approval_callout_data.locales[currentApprovalCalloutLocale],
                              callout_content: value,
                            },
                          },
                        },
                      },
                    })
                  }
                />

                <TextControl
                  label={__("Button Label", "wicket-memberships")}
                  value={tempForm.group_config_data.approval_callout_data?.locales?.[currentApprovalCalloutLocale]?.callout_button_label || ""}
                  onChange={(value) =>
                    setTempForm({
                      ...tempForm,
                      group_config_data: {
                        ...tempForm.group_config_data,
                        approval_callout_data: {
                          ...tempForm.group_config_data.approval_callout_data,
                          locales: {
                            ...tempForm.group_config_data.approval_callout_data.locales,
                            [currentApprovalCalloutLocale]: {
                              ...tempForm.group_config_data.approval_callout_data.locales[currentApprovalCalloutLocale],
                              callout_button_label: value,
                            },
                          },
                        },
                      },
                    })
                  }
                />

                <ActionRow>
                  <Flex justify="end">
                    <FlexItem>
                      <Button variant="primary" type="submit">
                        {__("Save", "wicket-memberships")}
                      </Button>
                    </FlexItem>
                  </Flex>
                </ActionRow>
              </form>
          </Modal>
        )}

        {/* --------------------------------------------------------------------
            Season Modal
        -------------------------------------------------------------------- */}
        {isCreateSeasonModalOpen && (
          <Modal
            title={
              currentSeasonIndex === null
                ? __("Add Season", "wicket-memberships")
                : __("Edit Season", "wicket-memberships")
            }
            onRequestClose={closeCreateSeasonModalOpen}
            style={{ maxWidth: "840px", width: "100%", paddingTop: "40px" }}
          >
            <AppWrap>
              <form onSubmit={handleCreateSeasonSubmit}>
                {Object.keys(seasonErrors).length > 0 && (
                  <ErrorsRow>
                    {Object.keys(seasonErrors).map((key) => (
                      <Notice isDismissible={false} key={key} status="warning">
                        {seasonErrors[key]}
                      </Notice>
                    ))}
                  </ErrorsRow>
                )}

                <TextControl
                  label={__("Season Name", "wicket-memberships")}
                  value={tempSeason.season_name}
                  onChange={(value) => setTempSeason({ ...tempSeason, season_name: value })}
                />

                <SelectControl
                  label={__("Status", "wicket-memberships")}
                  value={tempSeason.active ? "true" : "false"}
                  options={[
                    { label: __("Active", "wicket-memberships"), value: "true" },
                    { label: __("Inactive", "wicket-memberships"), value: "false" },
                  ]}
                  onChange={(value) =>
                    setTempSeason({ ...tempSeason, active: value === "true" })
                  }
                />

                <ReactDatePickerStyledWrap>
                  <LabelWpStyled>{__("Start Date", "wicket-memberships")}</LabelWpStyled>
                  <DatePicker
                    dateFormat={DEFAULT_DATE_FORMAT}
                    selected={tempSeason.start_date ? new Date(tempSeason.start_date) : null}
                    onChange={(date) =>
                      setTempSeason({
                        ...tempSeason,
                        start_date: date
                          ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
                          : "",
                      })
                    }
                  />
                </ReactDatePickerStyledWrap>

                <ReactDatePickerStyledWrap>
                  <LabelWpStyled>{__("End Date", "wicket-memberships")}</LabelWpStyled>
                  <DatePicker
                    dateFormat={DEFAULT_DATE_FORMAT}
                    selected={tempSeason.end_date ? new Date(tempSeason.end_date) : null}
                    onChange={(date) =>
                      setTempSeason({
                        ...tempSeason,
                        end_date: date
                          ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
                          : "",
                      })
                    }
                  />
                </ReactDatePickerStyledWrap>

                <ActionRow>
                  <Flex justify="space-between">
                    <FlexItem>
                      {currentSeasonIndex !== null && (
                        <Button
                          variant="secondary"
                          isDestructive
                          onClick={() => {
                            const seasons = form.cycle_data.calendar_items.filter(
                              (_, index) => index !== currentSeasonIndex,
                            );
                            setForm({
                              ...form,
                              cycle_data: { ...form.cycle_data, calendar_items: seasons },
                            });
                            closeCreateSeasonModalOpen();
                          }}
                        >
                          <Icon icon="archive" />
                          &nbsp;
                          {__("Archive", "wicket-memberships")}
                        </Button>
                      )}
                    </FlexItem>
                    <FlexItem>
                      <Button variant="primary" type="submit">
                        {currentSeasonIndex === null
                          ? __("Add Season", "wicket-memberships")
                          : __("Update Season", "wicket-memberships")}
                      </Button>
                    </FlexItem>
                  </Flex>
                </ActionRow>
              </form>
            </AppWrap>
          </Modal>
        )}
      </div>
    </>
  );
};

// ---------------------------------------------------------------------------
// Mount
// ---------------------------------------------------------------------------

const app = document.getElementById("create_membership_group_config");
if (app) {
  createRoot(app).render(<CreateMembershipGroupConfig {...app.dataset} />);
}
