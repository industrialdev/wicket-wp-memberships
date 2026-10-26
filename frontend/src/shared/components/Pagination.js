import { __ } from "@wordpress/i18n";
import { useState, useEffect } from "react";

const Pagination = ({ currentPage, totalPages, onPageChange }) => {
  const [inputValue, setInputValue] = useState(String(currentPage));

  useEffect(() => {
    setInputValue(String(currentPage));
  }, [currentPage]);

  if (totalPages <= 1) return null;

  const handleKeyDown = (e) => {
    if (e.key !== 'Enter') return;
    const parsed = parseInt(inputValue, 10);
    const clamped = isNaN(parsed) ? 1 : Math.min(Math.max(1, parsed), totalPages);
    setInputValue(String(clamped));
    if (clamped !== currentPage) {
      onPageChange(clamped);
    }
  };

  return (
    <span className="pagination-links">
      <button
        className="prev-page button"
        disabled={currentPage === 1}
        onClick={() => onPageChange(currentPage - 1)}
      >
        ‹
      </button>

      <span className="screen-reader-text">
        {__("Current Page", "wicket-memberships")}
      </span>
      <span id="table-paging" className="paging-input">
        &nbsp;
        <input
          type="text"
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
          style={{ width: 50, textAlign: 'center' }}
          aria-label={__("Current page", "wicket-memberships")}
        />
        &nbsp;{__("of", "wicket-memberships")}&nbsp;
        <span className="total-pages">{totalPages}</span>
        &nbsp;
      </span>

      <button
        className="next-page button"
        disabled={currentPage === totalPages}
        onClick={() => onPageChange(currentPage + 1)}
      >
        ›
      </button>
    </span>
  );
};

export default Pagination;
