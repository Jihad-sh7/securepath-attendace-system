import { useEffect, useMemo, useState } from "react";
import { Eye } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import SearchInput from "../components/common/SearchInput.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import Modal from "../components/common/Modal.jsx";
import { getAuditLogs } from "../services/alertService.js";
import { useDebouncedValue } from "../hooks/useDebouncedValue.js";
import { useToast } from "../context/ToastContext.jsx";

const formatValue = (value) => value == null ? "-" : typeof value === "object" ? JSON.stringify(value) : String(value);

export default function AuditLogsPage() {
  const [logs, setLogs] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState("");
  const [result, setResult] = useState("All");
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);
  const debouncedQuery = useDebouncedValue(query);
  const filters = useMemo(() => ({ page, limit: 20, search: debouncedQuery, status: result }), [page, debouncedQuery, result]);
  const { showToast } = useToast();

  useEffect(() => {
    setLoading(true);
    getAuditLogs(filters).then((response) => { setLogs(response.items); setPagination(response.pagination); }).catch((error) => showToast(error.message, "warning")).finally(() => setLoading(false));
  }, [filters]);

  return (
    <div className="page-stack">
      <PageHeader title="Audit Logs" description="Trace user and biometric actions, previous values, new values, source information, and results." />
      <section className="filter-panel">
        <SearchInput value={query} onChange={(value) => { setQuery(value); setPage(1); }} placeholder="Search audit logs..." />
        <select value={result} onChange={(event) => { setResult(event.target.value); setPage(1); }}><option>All</option><option>Success</option><option>Queued</option><option>Failed</option></select>
      </section>
      {loading ? <LoadingSkeleton rows={7} /> : <DataTable data={logs} pagination={pagination} onPageChange={setPage} actions={[{ label: "Details", icon: <Eye size={17} />, onClick: setDetail }]} columns={[
        { key: "timestamp", label: "Timestamp" }, { key: "user", label: "User" }, { key: "action", label: "Action" }, { key: "resource", label: "Resource" },
        { key: "previousValue", label: "Previous", render: (row) => formatValue(row.previousValue) }, { key: "newValue", label: "New", render: (row) => formatValue(row.newValue) },
        { key: "result", label: "Result", render: (row) => <StatusBadge status={row.result} /> }, { key: "device", label: "Source or IP" }
      ]} />}
      <Modal open={Boolean(detail)} title="Audit Event Details" onClose={() => setDetail(null)}>{detail && <dl><dt>User</dt><dd>{detail.user}</dd><dt>Action</dt><dd>{detail.action}</dd><dt>Resource</dt><dd>{detail.resource}</dd><dt>Before</dt><dd>{formatValue(detail.previousValue)}</dd><dt>After</dt><dd>{formatValue(detail.newValue)}</dd><dt>Source</dt><dd>{detail.device || "-"}</dd></dl>}</Modal>
    </div>
  );
}
