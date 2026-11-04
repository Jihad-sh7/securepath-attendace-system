import { useMemo, useState } from "react";
import { ArrowDownUp, MoreHorizontal } from "lucide-react";
import EmptyState from "../common/EmptyState.jsx";
import Pagination from "../common/Pagination.jsx";

function getValue(row, key) {
  return key.split(".").reduce((value, part) => value?.[part], row);
}

export default function DataTable({
  columns,
  data,
  keyField = "id",
  pageSize = 6,
  pagination,
  onPageChange,
  onSortChange,
  selectable = true,
  actions = [],
  onRowClick,
  emptyTitle
}) {
  const [sort, setSort] = useState({ key: "", direction: "asc" });
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState([]);

  const sorted = useMemo(() => {
    if (!sort.key) return data;
    return [...data].sort((a, b) => {
      const aValue = getValue(a, sort.key);
      const bValue = getValue(b, sort.key);
      const comparison = String(aValue ?? "").localeCompare(String(bValue ?? ""), undefined, {
        numeric: true
      });
      return sort.direction === "asc" ? comparison : -comparison;
    });
  }, [data, sort]);

  const serverPaginated = Boolean(pagination && onPageChange);
  const currentPage = serverPaginated ? pagination.page : page;
  const currentPageSize = serverPaginated ? pagination.limit : pageSize;
  const totalEntries = serverPaginated ? pagination.total : sorted.length;
  const totalPages = serverPaginated ? Math.max(1, pagination.totalPages) : Math.max(1, Math.ceil(sorted.length / pageSize));
  const visible = serverPaginated ? data : sorted.slice((page - 1) * pageSize, page * pageSize);
  const allVisibleSelected = visible.length > 0 && visible.every((row) => selected.includes(row[keyField]));

  function updateSort(column) {
    if (!column.sortable) return;
    const next = {
      key: column.key,
      direction: sort.key === column.key && sort.direction === "asc" ? "desc" : "asc"
    };
    setSort(next);
    onSortChange?.(next);
  }

  function toggleAll() {
    if (allVisibleSelected) {
      setSelected((current) => current.filter((id) => !visible.some((row) => row[keyField] === id)));
    } else {
      setSelected((current) => [...new Set([...current, ...visible.map((row) => row[keyField])])]);
    }
  }

  return (
    <section className="table-card">
      {selected.length > 0 && (
        <div className="bulk-bar">
          <span>{selected.length} selected</span>
          <button type="button" className="button button-secondary" onClick={() => setSelected([])}>
            Clear selection
          </button>
        </div>
      )}
      <div className="table-scroll">
        <table>
          <thead>
            <tr>
              {selectable && (
                <th className="check-cell">
                  <input type="checkbox" checked={allVisibleSelected} onChange={toggleAll} aria-label="Select visible rows" />
                </th>
              )}
              {columns.map((column) => (
                <th key={column.key}>
                  <button
                    type="button"
                    disabled={!column.sortable}
                    className={column.sortable ? "sort-button" : ""}
                    onClick={() => updateSort(column)}
                  >
                    {column.label}
                    {column.sortable && <ArrowDownUp size={13} />}
                  </button>
                </th>
              ))}
              {actions.length > 0 && <th className="actions-cell">Actions</th>}
            </tr>
          </thead>
          <tbody>
            {visible.map((row) => (
              <tr key={row[keyField]} onClick={() => onRowClick?.(row)}>
                {selectable && (
                  <td className="check-cell" onClick={(event) => event.stopPropagation()}>
                    <input
                      type="checkbox"
                      checked={selected.includes(row[keyField])}
                      aria-label={`Select ${row[keyField]}`}
                      onChange={() =>
                        setSelected((current) =>
                          current.includes(row[keyField])
                            ? current.filter((id) => id !== row[keyField])
                            : [...current, row[keyField]]
                        )
                      }
                    />
                  </td>
                )}
                {columns.map((column) => (
                  <td key={`${row[keyField]}-${column.key}`} data-label={column.label}>
                    {column.render ? column.render(row) : getValue(row, column.key)}
                  </td>
                ))}
                {actions.length > 0 && (
                  <td className="actions-cell" onClick={(event) => event.stopPropagation()}>
                    <div className="row-actions">
                      {actions.filter((action) => !action.hidden?.(row)).map((action) => (
                        <button
                          key={action.label}
                          type="button"
                          className="icon-button"
                          title={action.label}
                          aria-label={`${action.label} ${row[keyField]}`}
                          disabled={action.disabled?.(row)}
                          onClick={() => action.onClick(row)}
                        >
                          {action.icon || <MoreHorizontal size={18} />}
                        </button>
                      ))}
                    </div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {visible.length === 0 ? (
        <EmptyState title={emptyTitle} />
      ) : (
        <footer className="table-footer">
          <span>
            Showing {(currentPage - 1) * currentPageSize + 1} to {Math.min(currentPage * currentPageSize, totalEntries)} of {totalEntries} entries
          </span>
          <Pagination page={currentPage} totalPages={totalPages} onPageChange={onPageChange || setPage} />
        </footer>
      )}
    </section>
  );
}
