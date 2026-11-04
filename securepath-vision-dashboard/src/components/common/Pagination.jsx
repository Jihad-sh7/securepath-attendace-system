import { ChevronLeft, ChevronRight } from "lucide-react";

export default function Pagination({ page, totalPages, onPageChange }) {
  const start = Math.max(1, Math.min(page - 2, totalPages - 4));
  const pages = Array.from({ length: Math.min(totalPages, 5) }, (_, index) => start + index);

  return (
    <nav className="pagination" aria-label="Pagination">
      <button
        className="icon-button"
        type="button"
        disabled={page <= 1}
        aria-label="Previous page"
        onClick={() => onPageChange(page - 1)}
      >
        <ChevronLeft size={18} />
      </button>
      {pages.map((value) => {
        return (
          <button
            key={value}
            type="button"
            className={value === page ? "active" : ""}
            onClick={() => onPageChange(value)}
          >
            {value}
          </button>
        );
      })}
      {totalPages > 5 && pages.at(-1) < totalPages && <span>...</span>}
      <button
        className="icon-button"
        type="button"
        disabled={page >= totalPages}
        aria-label="Next page"
        onClick={() => onPageChange(page + 1)}
      >
        <ChevronRight size={18} />
      </button>
    </nav>
  );
}
