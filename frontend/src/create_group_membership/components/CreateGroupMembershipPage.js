import { useCallback, useState } from "react";
import { __ } from "@wordpress/i18n";
import { Button, TextControl } from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import { AppWrap, BorderedBox, EditWrap } from "../../shared/styled_elements";
import AdminPageErrorBoundary from "../../shared/components/AdminPageErrorBoundary";
import MembershipOwnerAsyncSelect from "../../shared/components/MembershipOwnerAsyncSelect";
import OrgUuidAsyncSelect from "../../shared/components/OrgUuidAsyncSelect";
import MembershipDatePicker from "../../shared/components/MembershipDatePicker";
import ModalPostSelector from "../../shared/components/ModalPostSelector";
import { pickerDateToIso } from "../../shared/components/MembershipDatesSection";
import {
  fetchMdpPersons,
  fetchSearchOrgs,
  createGroupMembership,
} from "../../shared/services/api";
import { API_URL } from "../../shared/constants";
import he from "he";

const EMPTY_FORM = {
  name: "",
  groupConfig: null,
  orgUuid: "",
  owner: null,
  startDate: null,
};

const validate = (form) => {
  const errors = {};
  if (!form.name.trim()) {
    errors.name = __("Name is required.", "wicket-memberships");
  }
  if (!form.groupConfig) {
    errors.groupConfig = __("Group config is required.", "wicket-memberships");
  }
  if (!form.orgUuid.trim()) {
    errors.orgUuid = __("Organization UUID is required.", "wicket-memberships");
  }
  if (!form.owner) {
    errors.owner = __("Membership owner is required.", "wicket-memberships");
  }
  if (!form.startDate) {
    errors.startDate = __("Start date is required.", "wicket-memberships");
  }
  return errors;
};

/**
 * CreateGroupMembershipPage — form for creating a new Membership Group post.
 *
 * @param {object} props
 * @param {string} props.groupConfigCptSlug  - CPT slug for group configs (from data attribute).
 * @param {string} props.listUrl             - URL of the group membership list page.
 * @param {string} props.editGroupBaseUrl    - Base URL for the group edit page (id appended on success).
 */
const CreateGroupMembershipPageContent = ({ groupConfigCptSlug, listUrl, editGroupBaseUrl }) => {
  const [form, setForm]           = useState(EMPTY_FORM);
  const [orgOption, setOrgOption] = useState(null);
  const [errors, setErrors]       = useState({});
  const [submitError, setSubmitError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const set = (key) => (value) => {
    setForm((prev) => ({ ...prev, [key]: value }));
    setErrors((prev) => ({ ...prev, [key]: undefined }));
    setSubmitError(null);
  };

  const loadGroupConfigs = async () => {
    const posts = await apiFetch({
      path: addQueryArgs(`${API_URL}/${groupConfigCptSlug}`, {
        _fields: "id,title,date,modified",
        status: "publish",
        per_page: -1,
      }),
    });
    return posts.map((p) => ({
      title: he.decode(p.title.rendered),
      value: p.id,
      modified: p.modified,
      published: p.date,
    }));
  };

  const loadOwnerOptions = (inputValue, callback) => {
    if (inputValue.length < 3) return;
    fetchMdpPersons({ term: inputValue })
      .then((response) => {
        callback(response.map((person) => ({ label: person.full_name, value: person.id })));
      })
      .catch((error) => {
        console.error("[CreateGroupMembershipPage] loadOwnerOptions error", error);
      });
  };

  const loadOrgOptions = useCallback((inputValue, callback) => {
    if (inputValue.length < 3) return;

    fetchSearchOrgs(inputValue)
      .then((orgs) => {
        callback(
          orgs.map((org) => ({
            label: org.name,
            value: org.id,
          })),
        );
      })
      .catch((error) => {
        console.error("[CreateGroupMembershipPage] loadOrgOptions error", error);
        callback([]);
      });
  }, []);

  const handleOrgChange = useCallback((option) => {
    setOrgOption(option ?? null);
    set("orgUuid")(option?.value ?? "");
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();

    const validationErrors = validate(form);
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    setIsSubmitting(true);
    setSubmitError(null);

    try {
      const response = await createGroupMembership({
        name:                       form.name.trim(),
        membership_group_config_id: form.groupConfig.value,
        org_uuid:                   form.orgUuid.trim(),
        owner_uuid:                 form.owner.value,
        start_date:                 pickerDateToIso(form.startDate, "membership_starts_at"),
      });

      if (response?.success && response?.response?.ID) {
        window.location.href = `${editGroupBaseUrl}&id=${response.response.ID}`;
        return;
      }

      setSubmitError(response?.error ?? __("An unexpected error occurred.", "wicket-memberships"));
    } catch (err) {
      setSubmitError(err?.error ?? err?.message ?? __("An unexpected error occurred.", "wicket-memberships"));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <BorderedBox>
      <form onSubmit={handleSubmit}>

        <div style={{ marginBottom: "16px" }}>
          <TextControl
            label={__("Name", "wicket-memberships")}
            value={form.name}
            onChange={set("name")}
          />
          {errors.name && <p style={{ color: "#cc1818", margin: "4px 0 0" }}>{errors.name}</p>}
        </div>

        <div style={{ marginBottom: "16px" }}>
          <ModalPostSelector
            id="group_config_selector"
            label={__("Group Config", "wicket-memberships")}
            placeholder={__("Select a group config…", "wicket-memberships")}
            modalTitle={__("Select Group Config", "wicket-memberships")}
            value={form.groupConfig}
            onChange={set("groupConfig")}
            loadOptions={loadGroupConfigs}
            columnLabels={{ name: __("Config Name", "wicket-memberships") }}
          />
          {errors.groupConfig && <p style={{ color: "#cc1818", margin: "4px 0 0" }}>{errors.groupConfig}</p>}
        </div>

        <div style={{ marginBottom: "16px" }}>
          <label style={{ display: "block", marginBottom: "4px", fontWeight: 600 }}>
            {__("Organization", "wicket-memberships")}
          </label>
          <OrgUuidAsyncSelect
            value={orgOption}
            onLoadOptions={loadOrgOptions}
            onChange={handleOrgChange}
          />
          {errors.orgUuid && <p style={{ color: "#cc1818", margin: "4px 0 0" }}>{errors.orgUuid}</p>}
        </div>

        <div style={{ marginBottom: "16px" }}>
          <label style={{ display: "block", marginBottom: "4px", fontWeight: 600 }}>
            {__("Membership Owner", "wicket-memberships")}
          </label>
          <MembershipOwnerAsyncSelect
            value={form.owner}
            onLoadOptions={loadOwnerOptions}
            onChange={set("owner")}
          />
          {errors.owner && <p style={{ color: "#cc1818", margin: "4px 0 0" }}>{errors.owner}</p>}
        </div>

        <div style={{ marginBottom: "24px" }}>
          <MembershipDatePicker
            name="membership_starts_at"
            label={__("Start Date", "wicket-memberships")}
            value={form.startDate}
            onChange={set("startDate")}
          />
          {errors.startDate && <p style={{ color: "#cc1818", margin: "4px 0 0" }}>{errors.startDate}</p>}
        </div>

        {submitError && (
          <p style={{ color: "#cc1818", marginBottom: "16px" }}>{submitError}</p>
        )}

        <Button
          variant="primary"
          type="submit"
          disabled={isSubmitting}
          isBusy={isSubmitting}
        >
          {__("Create Group Membership", "wicket-memberships")}
        </Button>

      </form>
    </BorderedBox>
  );
};

const CreateGroupMembershipPage = ({ groupConfigCptSlug, listUrl, editGroupBaseUrl }) => {
  const [errorBoundaryResetKey, setErrorBoundaryResetKey] = useState(0);

  return (
    <AppWrap>
      <div className="wrap">
        <h1 className="wp-heading-inline">
          {__("Create Group Membership", "wicket-memberships")}
        </h1>
        <hr className="wp-header-end" />

        {listUrl && (
          <p>
            <a href={listUrl}>
              &larr; {__("Back to Group Memberships", "wicket-memberships")}
            </a>
          </p>
        )}

        <AdminPageErrorBoundary
          onReset={() => setErrorBoundaryResetKey((v) => v + 1)}
          resetKey={errorBoundaryResetKey}
        >
          <EditWrap>
            <CreateGroupMembershipPageContent
              key={errorBoundaryResetKey}
              groupConfigCptSlug={groupConfigCptSlug}
              listUrl={listUrl}
              editGroupBaseUrl={editGroupBaseUrl}
            />
          </EditWrap>
        </AdminPageErrorBoundary>
      </div>
    </AppWrap>
  );
};

export default CreateGroupMembershipPage;
