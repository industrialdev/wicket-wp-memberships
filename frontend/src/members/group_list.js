import { __ } from "@wordpress/i18n";
import { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import moment from "moment";
import { Spinner } from "@wordpress/components";
import { addQueryArgs } from "@wordpress/url";
import { fetchMembershipGroups, fetchMembershipGroupFilters } from "../shared/services/api";
import { SelectWpStyled } from "../shared/styled_elements";

const SortableHeader = ({ label, col, currentCol, currentDir, onSort }) => {
  const isActive = currentCol === col;
  const className = `manage-column sortable ${isActive ? `sorted ${currentDir === "ASC" ? "asc" : "desc"}` : ""}`;

  return (
    <th scope="col" className={className}>
      <a href="#" onClick={(event) => { event.preventDefault(); onSort(col); }}>
        <span>{label}</span>
        <span className="sorting-indicators">
          <span className="sorting-indicator asc" aria-hidden="true"></span>
          <span className="sorting-indicator desc" aria-hidden="true"></span>
        </span>
      </a>
    </th>
  );
};

const GroupMemberList = ({ editGroupUrl }) => {
  const [isLoading, setIsLoading] = useState(true);
  const [groups, setGroups] = useState([]);
  const [totalGroups, setTotalGroups] = useState(0);
  const [totalPages, setTotalPages] = useState(0);
  const [groupFilters, setGroupFilters] = useState(null);
  const [searchParams, setSearchParams] = useState({
    page: 1,
    posts_per_page: 10,
    status: "all",
    order_col: "post_modified",
    order_dir: "DESC",
    search: "",
  });
  const [tempSearch, setTempSearch] = useState("");
  const [tempStatus, setTempStatus] = useState(null);

  const getGroups = (params) => {
    setIsLoading(true);

    fetchMembershipGroups(params)
      .then((response) => {
        setGroups(response.results || []);
        setTotalGroups(response.count || 0);
        setTotalPages(Math.max(1, Math.ceil((response.count || 0) / params.posts_per_page)));
        setIsLoading(false);
      })
      .catch((error) => {
        console.error(error);
        setGroups([]);
        setTotalGroups(0);
        setTotalPages(0);
        setIsLoading(false);
      });
  };

  const handleSort = (col) => {
    const newDir = searchParams.order_col === col && searchParams.order_dir === "ASC" ? "DESC" : "ASC";
    const nextParams = { ...searchParams, order_col: col, order_dir: newDir, page: 1 };
    setSearchParams(nextParams);
    getGroups(nextParams);
  };

  useEffect(() => {
    getGroups(searchParams);
    fetchMembershipGroupFilters()
      .then((filters) => setGroupFilters(filters))
      .catch((error) => console.error(error));
  }, []);

  return (
    <>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          const nextParams = { ...searchParams, search: tempSearch, page: 1 };
          setSearchParams(nextParams);
          getGroups(nextParams);
        }}
      >
        <p className="search-box">
          <label className="screen-reader-text" htmlFor="group-search-input">
            {__("Search Groups", "wicket-memberships")}
          </label>
          <input
            type="search"
            id="group-search-input"
            value={tempSearch}
            onChange={(event) => setTempSearch(event.target.value)}
          />
          <input type="submit" className="button" value={__("Search Groups", "wicket-memberships")} />
        </p>
      </form>

      <div className="tablenav top">
        <form
          onSubmit={(event) => {
            event.preventDefault();
            const nextParams = { ...searchParams, search: tempSearch, page: 1 };
            if (tempStatus !== null) {
              nextParams.filter = { membership_status: tempStatus.value };
            } else {
              delete nextParams.filter;
            }
            setSearchParams(nextParams);
            getGroups(nextParams);
          }}
        >
          <div className="alignleft actions" style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <SelectWpStyled
              inputId="filter_status"
              classNamePrefix="select"
              isClearable
              placeholder={__("All Statuses", "wicket-memberships")}
              value={tempStatus}
              onChange={(option) => setTempStatus(option)}
              options={
                groupFilters !== null
                  ? groupFilters.membership_status.map((status) => ({
                      value: status.name,
                      label: status.value,
                    }))
                  : []
              }
              styles={{
                container: (base) => ({ ...base, minWidth: 180 }),
                control: (base) => ({ ...base, minHeight: 30, height: 30 }),
                valueContainer: (base) => ({ ...base, height: 30, padding: "0 8px" }),
                indicatorsContainer: (base) => ({ ...base, height: 30 }),
              }}
            />
            <input
              type="submit"
              id="group-filter-submit"
              className="button"
              value={__("Filter", "wicket-memberships")}
            />
          </div>
        </form>
      </div>

      <table className="wp-list-table widefat fixed striped table-view-list posts">
        <thead>
          <tr>
            <SortableHeader
              label={__("Group Name", "wicket-memberships")}
              col="group_name"
              currentCol={searchParams.order_col}
              currentDir={searchParams.order_dir}
              onSort={handleSort}
            />
            <SortableHeader
              label={__("Org Name", "wicket-memberships")}
              col="org_name"
              currentCol={searchParams.order_col}
              currentDir={searchParams.order_dir}
              onSort={handleSort}
            />
            <SortableHeader
              label={__("Owner", "wicket-memberships")}
              col="user_name"
              currentCol={searchParams.order_col}
              currentDir={searchParams.order_dir}
              onSort={handleSort}
            />
            <SortableHeader
              label={__("Group Status", "wicket-memberships")}
              col="membership_status"
              currentCol={searchParams.order_col}
              currentDir={searchParams.order_dir}
              onSort={handleSort}
            />
            <SortableHeader
              label={__("Last Updated", "wicket-memberships")}
              col="post_modified"
              currentCol={searchParams.order_col}
              currentDir={searchParams.order_dir}
              onSort={handleSort}
            />
            <th scope="col" className="manage-column">{__("Link to MDP", "wicket-memberships")}</th>
          </tr>
        </thead>
        <tbody>
          {isLoading && (
            <tr className="alternate">
              <td colSpan={6}>
                <Spinner />
              </td>
            </tr>
          )}
          {!isLoading && groups.length === 0 && (
            <tr className="alternate">
              <td colSpan={6}>{__("No membership groups found.", "wicket-memberships")}</td>
            </tr>
          )}
          {!isLoading && groups.length > 0 && groups.map((group) => (
            <tr key={group.id}>
              <td>
                <strong>
                  <a href={addQueryArgs(editGroupUrl, { id: group.id })} className="row-title">{group.group_name || "-"}</a>
                </strong>
                <pre style={{ display: "inline", fontSize: "11px" }}>({group.id})</pre>
                <div className="row-actions">
                  <span className="edit">
                    <a href={addQueryArgs(editGroupUrl, { id: group.id })}>{__("Edit", "wicket-memberships")}</a>
                  </span>
                </div>
              </td>
              <td>{group.org_name || "-"}</td>
              <td>{group.owner?.name || group.owner?.email || "-"}</td>
              <td>{group.status?.label || "-"}</td>
              <td>{group.last_updated ? moment(group.last_updated).format("MMMM D, YYYY") : "-"}</td>
              {/* TODO: enable MDP link once membership group MDP sync is implemented */}
              <td>{group.mdp_link ? <span style={{ color: "red", opacity: 0.5 }}>{__("View in MDP", "wicket-memberships")}</span> : "-"}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="tablenav bottom">
        <div className="tablenav-pages">
          <span className="displaying-num">
            {totalGroups} {__("items", "wicket-memberships")}
          </span>

          {totalPages > 1 && (
            <span className="pagination-links">
              <button
                className="prev-page button"
                disabled={searchParams.page === 1}
                onClick={() => {
                  const nextParams = { ...searchParams, page: searchParams.page - 1 };
                  setSearchParams(nextParams);
                  getGroups(nextParams);
                }}
              >
                ‹
              </button>

              <span className="screen-reader-text">{__("Current Page", "wicket-memberships")}</span>
              <span id="table-paging" className="paging-input">
                &nbsp;
                <span className="tablenav-paging-text">
                  {searchParams.page} {__("of", "wicket-memberships")} <span className="total-pages">{totalPages}</span>
                </span>
                &nbsp;
              </span>

              <button
                className="next-page button"
                disabled={searchParams.page === totalPages}
                onClick={() => {
                  const nextParams = { ...searchParams, page: searchParams.page + 1 };
                  setSearchParams(nextParams);
                  getGroups(nextParams);
                }}
              >
                ›
              </button>
            </span>
          )}
        </div>
        <br className="clear" />
      </div>
    </>
  );
};

const app = document.getElementById("group_member_list");
if (app) {
  createRoot(app).render(<GroupMemberList editGroupUrl={app.dataset.editGroupUrl} />);
}
