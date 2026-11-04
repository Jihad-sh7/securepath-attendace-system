import { useEffect, useMemo, useState } from "react";
import { CheckCircle2, MessageSquare, Trash2 } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import SearchInput from "../components/common/SearchInput.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import Modal from "../components/common/Modal.jsx";
import FormField from "../components/forms/FormField.jsx";
import { useToast } from "../context/ToastContext.jsx";
import { addAlertNote, getAlerts, resolveAlert, setAlertStatus } from "../services/alertService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";
import { useDebouncedValue } from "../hooks/useDebouncedValue.js";

export default function AlertsPage() {
  const [alerts, setAlerts] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState("");
  const [severity, setSeverity] = useState("All");
  const [status, setStatus] = useState("All");
  const [noteAlert, setNoteAlert] = useState(null);
  const [note, setNote] = useState("");
  const [loading, setLoading] = useState(true);
  const debouncedQuery = useDebouncedValue(query);
  const { showToast } = useToast();
  const { hasPermission } = usePermissions();
  const canManage = hasPermission(PERMISSIONS.ALERTS_MANAGE);

  const filters = useMemo(() => ({ page, limit: 20, search: debouncedQuery, severity, status }), [page, debouncedQuery, severity, status]);

  async function loadAlerts() {
    setLoading(true);
    try {
      const result = await getAlerts(filters);
      setAlerts(result.items);
      setPagination(result.pagination);
    } catch (error) { showToast(error.message, "warning"); }
    finally { setLoading(false); }
  }

  useEffect(() => { loadAlerts(); }, [filters]);

  async function handleResolve(alert) {
    try {
      await resolveAlert(alert.id, "Resolved from the alert dashboard.");
      showToast(`${alert.type} resolved.`);
      await loadAlerts();
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function dismissAlert(alert) {
    try {
      await setAlertStatus(alert.id, "Dismissed");
      showToast(`${alert.type} dismissed.`, "warning");
      await loadAlerts();
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function saveNote() {
    try {
      await addAlertNote(noteAlert.id, note);
      setNoteAlert(null);
      setNote("");
      showToast("Alert note saved.");
      await loadAlerts();
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function resolveVisible() {
    const open = alerts.filter((alert) => alert.status !== "Resolved" && alert.status !== "Dismissed");
    try {
      await Promise.all(open.map((alert) => resolveAlert(alert.id, "Bulk resolved from the alert dashboard.")));
      showToast(`${open.length} alerts resolved.`);
      await loadAlerts();
    } catch (error) { showToast(error.message, "warning"); }
  }

  return (
    <div className="page-stack">
      <PageHeader title="Alerts" description="Unknown identities, liveness failures, duplicate attendance, low confidence, unusual access time, and system errors." actions={canManage && <button className="button button-secondary" type="button" onClick={resolveVisible} disabled={!alerts.some((alert) => alert.status !== "Resolved")}><CheckCircle2 size={17} /> Resolve visible</button>} />
      <section className="filter-panel">
        <SearchInput value={query} onChange={(value) => { setQuery(value); setPage(1); }} placeholder="Search alerts..." />
        <select value={severity} onChange={(event) => { setSeverity(event.target.value); setPage(1); }}><option>All</option><option>Critical</option><option>High</option><option>Warning</option><option>Info</option></select>
        <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option>All</option><option>Open</option><option>Investigating</option><option>Resolved</option><option>Dismissed</option></select>
      </section>
      {loading ? <LoadingSkeleton rows={7} /> : <DataTable data={alerts} pagination={pagination} onPageChange={setPage} actions={canManage ? [
        { label: "Resolve", icon: <CheckCircle2 size={17} />, onClick: handleResolve, disabled: (row) => row.status === "Resolved" },
        { label: "Notes", icon: <MessageSquare size={17} />, onClick: (row) => { setNoteAlert(row); setNote(""); } },
        { label: "Dismiss", icon: <Trash2 size={17} />, onClick: dismissAlert }
      ] : []} columns={[
        { key: "type", label: "Type" },
        { key: "severity", label: "Severity", render: (row) => <StatusBadge status={row.severity} /> },
        { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> },
        { key: "timestamp", label: "Timestamp" },
        { key: "related", label: "Related" },
        { key: "notes", label: "Notes" }
      ]} />}
      <Modal open={Boolean(noteAlert)} title="Alert Notes" onClose={() => setNoteAlert(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setNoteAlert(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveNote} disabled={!note.trim()}>Save note</button></>}>
        <FormField label="Notes"><textarea value={note} onChange={(event) => setNote(event.target.value)} rows="5" /></FormField>
      </Modal>
    </div>
  );
}
