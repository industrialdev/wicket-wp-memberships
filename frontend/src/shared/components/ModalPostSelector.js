import { useState, useCallback, useMemo } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Notice, Spinner } from "@wordpress/components";
import styled from "styled-components";
import WicketModal from "./WicketModal";
import WicketButton from "./WicketButton";
import { LabelWpStyled } from "../styled_elements";
import { WP_ADMIN_URL } from "../constants";

const PAGE_SIZE = 20;

const COLUMN_LAYOUT = {
  id: { width: 80 },
  title: { flex: 1 },
  sku: { width: 180 },
  price: { width: 120 },
  published: { width: 140 },
  modified: { width: 160 },
  view: { width: 44 },
};

// ─── Styled pieces ────────────────────────────────────────────────────────────

const TriggerButton = styled.button`
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  min-height: 30px;
  padding: 0px 4px 0px 8px;
  border: 1px solid #949494;
  border-radius: 2px;
  background: #fff;
  cursor: pointer;
  font-size: 13px;
  text-align: left;
  color: #1e1e1e;
  gap: 6px;

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f0f0f0;
  }

  .modal-selector__value {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .modal-selector__placeholder {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #757575;
  }

  .modal-selector__chevron {
    display: inline-flex;
    align-items: center;
    align-self: center;
    gap: 4px;
    flex-shrink: 0;
    color: #757575;
    line-height: 1;
    min-width: 18px;
    justify-content: flex-end;
  }

  .modal-selector__divider {
    width: 1px;
    height: 16px;
    background: #dcdcde;
  }

  .modal-selector__clear {
    flex-shrink: 0;
    color: #757575;
    line-height: 1;
    padding: 2px 4px;
    border-radius: 2px;

    &:hover {
      color: #cc1818;
      background: #f5e0e0;
    }
  }
`;

const TableWrap = styled.div`
  margin-top: 12px;
  overflow-x: auto;
`;

const truncate = `
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
`;

const TableHeader = styled.div`
  display: flex;
  padding: 0;
  background: #f6f7f7;
  border-bottom: 1px solid #c3c4c7;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: #50575e;
  user-select: none;
  position: sticky;
  top: 0;
  z-index: 1;
`;

// Shared layout primitive — used in both header and data rows
const Col = styled.div`
  padding: 8px 10px;
  flex-shrink: 0;
  min-width: 0;
  width: ${({ $width }) => ($width ? `${$width}px` : "auto")};
  flex: ${({ $flex }) => $flex ?? "none"};
  ${truncate}
`;

const SortableCol = styled(Col)`
  display: flex;
  align-items: center;
  gap: 4px;
  padding-top: 6px;
  padding-bottom: 6px;
  cursor: pointer;

  &:hover {
    background: #e8e9ea;
  }

  .sort-icon {
    flex-shrink: 0;
    color: #a0a0a0;
    font-size: 10px;
    line-height: 1;
  }

  &[aria-sort="ascending"] .sort-icon,
  &[aria-sort="descending"] .sort-icon {
    color: #2271b1;
  }
`;

const IconCol = styled(Col)`
  padding: 8px 4px;
  display: flex;
  align-items: center;
  justify-content: center;

  a {
    display: flex;
    align-items: center;
    color: #757575;
    text-decoration: none;

    &:hover {
      color: #2271b1;
    }
  }
`;

const TableBody = styled.div`
  border: 1px solid #c3c4c7;
  max-height: 380px;
  overflow-y: auto;
`;

const TableRow = styled.div`
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e0e0e0;
  cursor: pointer;
  font-size: 13px;
  background: ${({ $isSelected }) => ($isSelected ? "#f0f6fc" : "#fff")};
  font-weight: ${({ $isSelected }) => ($isSelected ? "600" : "normal")};

  &:last-child {
    border-bottom: none;
  }

  &:hover {
    background: ${({ $isSelected }) => ($isSelected ? "#e5f0fa" : "#f8f9fa")};
  }

  ${Col}[data-col="id"] {
    color: #757575;
    font-size: 12px;
  }
`;

const EmptyRow = styled.div`
  padding: 20px 10px;
  text-align: center;
  color: #757575;
  font-size: 13px;
`;

const SearchInput = styled.input`
  width: 100%;
  min-height: 28px;
  padding: 4px 8px;
  border: 1px solid #949494;
  border-radius: 2px;
  font-size: 13px;
  box-sizing: border-box;
  margin-top: 4px;

  &:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 1px #2271b1;
  }
`;

const SearchWrap = styled.div`
  margin-bottom: 8px;
`;

const LoadingState = styled.div`
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 220px;
  border: 1px solid #c3c4c7;
`;

const PaginationBar = styled.div`
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0 0;
  font-size: 12px;
  color: #50575e;
`;

const PaginationInfo = styled.span``;

const PaginationControls = styled.div`
  display: flex;
  align-items: center;
  gap: 6px;
`;

const ModalFooter = styled.div`
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e0e0e0;
`;

// ─── Helpers ──────────────────────────────────────────────────────────────────

const SortIcon = ({ sortKey, activeSortKey, sortDir }) => {
  if (sortKey !== activeSortKey) return <span className="sort-icon">⇅</span>;
  return <span className="sort-icon">{sortDir === "asc" ? "↑" : "↓"}</span>;
};

// ─── Component ────────────────────────────────────────────────────────────────

/**
 * ModalPostSelector
 *
 * Modal-based picker for WP posts/pages or WooCommerce products.
 * Data is loaded lazily on first open. Supports client-side search,
 * sorting by any column, and paginated results.
 *
 * Props:
 *   id              {string}    HTML id for the trigger button
 *   label           {string}    Field label rendered above the trigger button
 *   placeholder     {string}    Placeholder text shown when nothing is selected
 *   value           {object}    Currently selected option: { value, title }
 *   onChange        {Function}  Called with the selected option { value, title }
 *   disabled        {boolean}   Disables the trigger button
 *   modalTitle      {string}    Title shown in the modal header
 *   loadOptions     {Function}  Async () => [{ value, title, ...extras }].
 *                               Called once on first modal open.
 *   columnLabels    {object}    Override column headers: { id, name }
 */
const ModalPostSelector = ({
  id,
  label,
  placeholder = __("Select…", "wicket-memberships"),
  value = null,
  onChange,
  disabled = false,
  modalTitle,
  loadOptions,
  isLoadingValue = false,
  columnLabels = {},
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [options, setOptions] = useState([]);
  const [loadState, setLoadState] = useState("idle"); // idle | loading | loaded | error
  const [errorMessage, setErrorMessage] = useState("");
  const [search, setSearch] = useState("");
  const [sortKey, setSortKey] = useState("value");
  const [sortDir, setSortDir] = useState("asc");
  const [currentPage, setCurrentPage] = useState(1);
  const [pendingValue, setPendingValue] = useState(null);

  const colId = columnLabels.id ?? __("ID", "wicket-memberships");
  const colName = columnLabels.name ?? __("Title", "wicket-memberships");

  // Detect extra columns from the first loaded option
  const firstOpt = options[0];
  const isProduct = firstOpt && "sku" in firstOpt;
  const isPost = firstOpt && "modified" in firstOpt;
  const columns = useMemo(() => {
    const baseColumns = [
      { key: "id", label: colId },
      { key: "title", label: colName },
    ];

    if (isProduct) {
      baseColumns.push(
        { key: "sku", label: __("SKU", "wicket-memberships") },
        { key: "price", label: __("Price", "wicket-memberships") },
      );
    }

    if (isPost) {
      baseColumns.push(
        { key: "published", label: __("Published", "wicket-memberships") },
        { key: "modified", label: __("Modified", "wicket-memberships") },
      );
    }

    baseColumns.push({ key: "view", label: "" });

    return baseColumns;
  }, [colId, colName, isPost, isProduct]);

  const formatDate = (iso, includeTime = false) => {
    if (!iso) return "—";
    const opts = { year: "numeric", month: "short", day: "numeric" };
    if (includeTime) {
      opts.hour = "2-digit";
      opts.minute = "2-digit";
    }
    return new Date(iso).toLocaleString(undefined, opts);
  };

  const formatPrice = (price) => {
    if (price === undefined || price === null || price === "") return "—";
    const num = parseFloat(price);
    if (isNaN(num)) return price;
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency: wicketMembershipsSettings.currency ?? "USD",
    }).format(num);
  };

  // ── Sort key → comparable value ──────────────────────────────────────────
  const getSortValue = (opt, key) => {
    switch (key) {
      case "id":      return opt.value;
      case "value":   return opt.value;
      case "title":   return opt.title?.toLowerCase() ?? "";
      case "sku":     return opt.sku?.toLowerCase() ?? "";
      case "price":   return parseFloat(opt.price) || 0;
      case "published": return opt.published ?? "";
      case "modified":  return opt.modified ?? "";
      default:        return "";
    }
  };

  // ── Derived: filter → sort → paginate ────────────────────────────────────
  const filteredSorted = useMemo(() => {
    const q = search.trim().toLowerCase();
    const filtered = q
      ? options.filter(
          (opt) =>
            String(opt.value).toLowerCase().includes(q) ||
            opt.title.toLowerCase().includes(q),
        )
      : options;

    return [...filtered].sort((a, b) => {
      const av = getSortValue(a, sortKey);
      const bv = getSortValue(b, sortKey);
      if (av < bv) return sortDir === "asc" ? -1 : 1;
      if (av > bv) return sortDir === "asc" ? 1 : -1;
      return 0;
    });
  }, [options, search, sortKey, sortDir]);

  const totalPages = Math.max(1, Math.ceil(filteredSorted.length / PAGE_SIZE));
  const safePage = Math.min(currentPage, totalPages);
  const pageStart = (safePage - 1) * PAGE_SIZE;
  const pageRows = filteredSorted.slice(pageStart, pageStart + PAGE_SIZE);

  // ── Handlers ──────────────────────────────────────────────────────────────
  const handleSort = (key) => {
    if (key === sortKey) {
      setSortDir((d) => (d === "asc" ? "desc" : "asc"));
    } else {
      setSortKey(key);
      setSortDir("asc");
    }
    setCurrentPage(1);
  };

  const handleSearchChange = (e) => {
    setSearch(e.target.value);
    setCurrentPage(1);
  };

  const openModal = useCallback(async () => {
    setPendingValue(value);
    setIsOpen(true);

    if (loadState === "loaded" || loadState === "loading") return;

    setLoadState("loading");
    try {
      const result = await loadOptions();
      setOptions(result);
      setLoadState("loaded");
    } catch (err) {
      setErrorMessage(
        err?.message || __("Failed to load options.", "wicket-memberships"),
      );
      setLoadState("error");
    }
  }, [loadOptions, loadState, value]);

  const closeModal = useCallback(() => {
    setIsOpen(false);
    setSearch("");
    setCurrentPage(1);
    setPendingValue(null);
  }, []);

  const handleSelect = useCallback((option) => {
    setPendingValue(option);
  }, []);

  const handleConfirm = useCallback(() => {
    onChange(pendingValue);
    closeModal();
  }, [onChange, pendingValue, closeModal]);

  const handleClear = useCallback(
    (e) => {
      e.stopPropagation();
      onChange(null);
    },
    [onChange],
  );

  const handleRetry = useCallback(async () => {
    setLoadState("loading");
    setErrorMessage("");
    try {
      const result = await loadOptions();
      setOptions(result);
      setLoadState("loaded");
    } catch (err) {
      setErrorMessage(
        err?.message || __("Failed to load options.", "wicket-memberships"),
      );
      setLoadState("error");
    }
  }, [loadOptions]);

  // ── Sortable header helper ────────────────────────────────────────────────
  const headerCol = (key, children) => {
    const ariaSort =
      sortKey === key ? (sortDir === "asc" ? "ascending" : "descending") : "none";
    const { width, flex } = COLUMN_LAYOUT[key] ?? {};
    return (
      <SortableCol
        key={key}
        role="columnheader"
        aria-sort={ariaSort}
        onClick={() => handleSort(key)}
        title={typeof children === "string" ? children : undefined}
        $width={width}
        $flex={flex}
      >
        <SortIcon sortKey={key} activeSortKey={sortKey} sortDir={sortDir} />
        <span style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", flex: 1, minWidth: 0 }}>{children}</span>
      </SortableCol>
    );
  };

  // Resolve display title from loaded options when available (fixes "Saved Post" on page load)
  const resolvedValue = value
    ? (options.find((o) => String(o.value) === String(value.value)) ?? value)
    : null;
  const displayLabel = resolvedValue
    ? `${resolvedValue.title} (${resolvedValue.value})`
    : null;
  const renderCell = (opt, key) => {
    const skuVal = opt.sku || "—";
    const priceVal = formatPrice(opt.price);
    const publishedVal = formatDate(opt.published, true);
    const modifiedVal = formatDate(opt.modified, true);

    switch (key) {
      case "id":
        return (
          <Col
            key={key}
            $width={COLUMN_LAYOUT.id.width}
            data-col="id"
            title={String(opt.value)}
          >
            {opt.value}
          </Col>
        );
      case "title":
        return (
          <Col key={key} $flex={COLUMN_LAYOUT.title.flex} title={opt.title}>
            {opt.title}
          </Col>
        );
      case "sku":
        return (
          <Col key={key} $width={COLUMN_LAYOUT.sku.width} title={skuVal}>
            {skuVal}
          </Col>
        );
      case "price":
        return (
          <Col key={key} $width={COLUMN_LAYOUT.price.width} title={priceVal}>
            {priceVal}
          </Col>
        );
      case "published":
        return (
          <Col
            key={key}
            $width={COLUMN_LAYOUT.published.width}
            title={publishedVal}
          >
            {publishedVal}
          </Col>
        );
      case "modified":
        return (
          <Col
            key={key}
            $width={COLUMN_LAYOUT.modified.width}
            title={modifiedVal}
          >
            {modifiedVal}
          </Col>
        );
      case "view":
        return (
          <IconCol
            key={key}
            $width={COLUMN_LAYOUT.view.width}
            onClick={(e) => e.stopPropagation()}
          >
            <a
              href={`${WP_ADMIN_URL}post.php?action=edit&post=${opt.value}`}
              target="_blank"
              rel="noreferrer"
              title={__("Edit in admin", "wicket-memberships")}
              aria-label={__("Edit in admin", "wicket-memberships")}
            >
              <span className="dashicons dashicons-visibility" />
            </a>
          </IconCol>
        );
      default:
        return null;
    }
  };

  return (
    <>
      {label && <LabelWpStyled htmlFor={id}>{label}</LabelWpStyled>}

      <TriggerButton
        id={id}
        type="button"
        disabled={disabled || isLoadingValue}
        onClick={openModal}
        aria-haspopup="dialog"
        title={displayLabel ?? undefined}
      >
        {isLoadingValue ? (
          <span className="modal-selector__placeholder">
            {__("Loading…", "wicket-memberships")}
          </span>
        ) : displayLabel ? (
          <span className="modal-selector__value">{displayLabel}</span>
        ) : (
          <span className="modal-selector__placeholder">{placeholder}</span>
        )}
        {value && !disabled && !isLoadingValue ? (
          <span
            className="modal-selector__clear"
            role="button"
            aria-label={__("Clear selection", "wicket-memberships")}
            onClick={handleClear}
          >
            ✕
          </span>
        ) : (
          <span
            className="modal-selector__chevron"
            aria-hidden="true"
            title={__("Search options", "wicket-memberships")}
          >
            <span className="modal-selector__divider" />
            <span className="dashicons dashicons-search" />
          </span>
        )}
      </TriggerButton>

      <WicketModal
        isOpen={isOpen}
        title={modalTitle || label || __("Select an option", "wicket-memberships")}
        onRequestClose={closeModal}
      >
        {loadState === "error" && (
          <Notice isDismissible={false} status="warning">
            <div>{errorMessage}</div>
            <div>
              <WicketButton onClick={handleRetry} variant="link">
                {__("Retry", "wicket-memberships")}
              </WicketButton>
            </div>
          </Notice>
        )}

        <SearchWrap>
          <SearchInput
            type="search"
            placeholder={__("Search…", "wicket-memberships")}
            value={search}
            onChange={handleSearchChange}
            aria-label={__("Search options", "wicket-memberships")}
          />
        </SearchWrap>

        <TableWrap>
          {loadState === "loading" ? (
            <LoadingState>
              <Spinner />
            </LoadingState>
          ) : (
            <TableBody role="listbox">
              <TableHeader role="row">
                {columns.map((column) =>
                  column.key === "view" ? (
                    <IconCol
                      key={column.key}
                      $width={COLUMN_LAYOUT.view.width}
                      aria-hidden="true"
                    />
                  ) : (
                    headerCol(column.key, column.label)
                  ),
                )}
              </TableHeader>

              {loadState === "loaded" && filteredSorted.length === 0 && (
                <EmptyRow>
                  {search.trim()
                    ? __("No results match your search.", "wicket-memberships")
                    : __("No options available.", "wicket-memberships")}
                </EmptyRow>
              )}

              {loadState === "loaded" &&
                pageRows.map((opt) => {
                  return (
                    <TableRow
                      key={opt.value}
                      $isSelected={pendingValue && pendingValue.value === opt.value}
                      onClick={() => handleSelect(opt)}
                      role="option"
                      aria-selected={value && value.value === opt.value}
                      tabIndex={0}
                      onKeyDown={(e) => {
                        if (e.key === "Enter" || e.key === " ") {
                          e.preventDefault();
                          handleSelect(opt);
                        }
                      }}
                    >
                      {columns.map((column) => renderCell(opt, column.key))}
                    </TableRow>
                  );
                })}
            </TableBody>
          )}
        </TableWrap>

        {loadState === "loaded" && filteredSorted.length > PAGE_SIZE && (
          <PaginationBar>
            <PaginationInfo>
              {__("Page", "wicket-memberships")} {safePage} {__("of", "wicket-memberships")} {totalPages}
              {" "}
              <span style={{ color: "#a0a0a0" }}>
                ({filteredSorted.length} {__("total", "wicket-memberships")})
              </span>
            </PaginationInfo>
            <PaginationControls>
              <WicketButton
                variant="secondary"
                isSmall
                disabled={safePage <= 1}
                onClick={() => setCurrentPage((p) => p - 1)}
              >
                {__("← Prev", "wicket-memberships")}
              </WicketButton>
              <WicketButton
                variant="secondary"
                isSmall
                disabled={safePage >= totalPages}
                onClick={() => setCurrentPage((p) => p + 1)}
              >
                {__("Next →", "wicket-memberships")}
              </WicketButton>
            </PaginationControls>
          </PaginationBar>
        )}

        <ModalFooter>
          <WicketButton variant="secondary" onClick={closeModal}>
            {__("Cancel", "wicket-memberships")}
          </WicketButton>
          <WicketButton
            variant="primary"
            disabled={!pendingValue}
            onClick={handleConfirm}
          >
            {__("Select", "wicket-memberships")}
          </WicketButton>
        </ModalFooter>
      </WicketModal>
    </>
  );
};

export default ModalPostSelector;
