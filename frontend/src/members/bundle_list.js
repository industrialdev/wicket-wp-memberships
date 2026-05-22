import { __ } from "@wordpress/i18n";
import { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import moment from "moment";
import { Spinner } from "@wordpress/components";
import { addQueryArgs } from "@wordpress/url";
import { fetchMembershipBundles, fetchMembershipBundleFilters } from "../shared/services/api";
import { SelectWpStyled } from "../shared/styled_elements";
import Pagination from "../shared/components/Pagination";
import { getPagedFromUrl, updatePageInUrl } from "../shared/utils/pagination";

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

const BundleMemberList = ({ editBundleUrl }) => {
  const [isLoading, setIsLoading] = useState(true);
  const [bundles, setBundles] = useState([]);
  const [totalBundles, setTotalBundles] = useState(0);
  const [totalPages, setTotalPages] = useState(0);
  const [bundleFilters, setBundleFilters] = useState(null);
  const [searchParams, setSearchParams] = useState({
    page: getPagedFromUrl(),
    posts_per_page: 10,
    status: "all",
    order_col: "post_modified",
    order_dir: "DESC",
    search: "",
  });
  const [tempSearch, setTempSearch] = useState("");
  const [tempStatus, setTempStatus] = useState(null);

  const getBundles = (params) => {
    setIsLoading(true);

    fetchMembershipBundles(params)
      .then((response) => {
        const computedTotalPages = Math.max(1, Math.ceil((response.count || 0) / params.posts_per_page));
        setBundles(response.results || []);
        setTotalBundles(response.count || 0);
        setTotalPages(computedTotalPages);
        setIsLoading(false);

        if (params.page > computedTotalPages && computedTotalPages > 0) {
          const fixed = { ...params, page: 1 };
          setSearchParams(fixed);
          updatePageInUrl(1);
          getBundles(fixed);
          return;
        }
      })
      .catch((error) => {
        console.error(error);
        setBundles([]);
        setTotalBundles(0);
        setTotalPages(0);
        setIsLoading(false);
      });
  };

  const handleSort = (col) => {
    const newDir = searchParams.order_col === col && searchParams.order_dir === "ASC" ? "DESC" : "ASC";
    const nextParams = { ...searchParams, order_col: col, order_dir: newDir, page: 1 };
    setSearchParams(nextParams);
    updatePageInUrl(1);
    getBundles(nextParams);
  };

  useEffect(() => {
    getBundles(searchParams);
    fetchMembershipBundleFilters()
      .then((filters) => setBundleFilters(filters))
      .catch((error) => console.error(error));
  }, []);

  return (
    <>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          const nextParams = { ...searchParams, search: tempSearch, page: 1 };
          setSearchParams(nextParams);
          updatePageInUrl(1);
          getBundles(nextParams);
        }}
      >
        <p className="search-box">
          <label className="screen-reader-text" htmlFor="bundle-search-input">
            {__("Search Bundles", "wicket-memberships")}
          </label>
          <input
            type="search"
            id="bundle-search-input"
            value={tempSearch}
            onChange={(event) => setTempSearch(event.target.value)}
          />
          <input type="submit" className="button" value={__("Search Bundles", "wicket-memberships")} />
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
            updatePageInUrl(1);
            getBundles(nextParams);
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
                bundleFilters !== null
                  ? bundleFilters.membership_status.map((status) => ({
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
              id="bundle-filter-submit"
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
              label={__("Bundle Name", "wicket-memberships")}
              col="bundle_name"
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
              label={__("Bundle Status", "wicket-memberships")}
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
          {!isLoading && bundles.length === 0 && (
            <tr className="alternate">
              <td colSpan={6}>{__("No membership bundles found.", "wicket-memberships")}</td>
            </tr>
          )}
          {!isLoading && bundles.length > 0 && bundles.map((bundle) => (
            <tr key={bundle.id}>
              <td>
                <strong>
                  <a href={addQueryArgs(editBundleUrl, { id: bundle.id })} className="row-title">{bundle.bundle_name || "-"}</a>
                </strong>
<div className="row-actions">
                  <span className="edit">
                    <a href={addQueryArgs(editBundleUrl, { id: bundle.id })}>{__("Edit", "wicket-memberships")}</a>
                  </span>
                </div>
              </td>
              <td>{bundle.org_name || "-"}</td>
              <td>{bundle.owner?.email || "-"}</td>
              <td>{bundle.status?.label || "-"}</td>
              <td>{bundle.last_updated ? moment(bundle.last_updated).format("MMMM D, YYYY") : "-"}</td>
              {/* TODO: enable MDP link once membership bundle MDP sync is implemented */}
              <td>{bundle.mdp_link ? <span style={{ color: "red", opacity: 0.5 }}>{__("View in MDP", "wicket-memberships")}</span> : "-"}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="tablenav bottom">
        <div className="tablenav-pages">
          <span className="displaying-num">
            {totalBundles} {__("items", "wicket-memberships")}
          </span>
          <Pagination
            currentPage={searchParams.page}
            totalPages={totalPages}
            onPageChange={(page) => {
              const nextParams = { ...searchParams, page };
              setSearchParams(nextParams);
              updatePageInUrl(page);
              getBundles(nextParams);
            }}
          />
        </div>
        <br className="clear" />
      </div>
    </>
  );
};

const app = document.getElementById("bundle_member_list");
if (app) {
  createRoot(app).render(<BundleMemberList editBundleUrl={app.dataset.editBundleUrl} />);
}
