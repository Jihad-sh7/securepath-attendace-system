import { useEffect, useMemo, useState } from "react";
import { Check, Clock3, Download, Edit3, Eye, MessageSquarePlus, Trash2, X } from "lucide-react";
import { useNavigate } from "react-router-dom";
import PageHeader from "../components/common/PageHeader.jsx";
import SearchInput from "../components/common/SearchInput.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import ConfidenceBadge from "../components/common/ConfidenceBadge.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import Modal from "../components/common/Modal.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import FormField from "../components/forms/FormField.jsx";
import ManualAttendanceModal from "../components/attendance/ManualAttendanceModal.jsx";
import { useToast } from "../context/ToastContext.jsx";
import {
  deleteAttendance,
  getAttendanceCorrections,
  getAttendanceRecords,
  reviewAttendanceCorrection,
  updateAttendance
} from "../services/attendanceService.js";
import { getDepartments } from "../services/organizationService.js";
import { exportReport } from "../services/reportService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";
import { useDebouncedValue } from "../hooks/useDebouncedValue.js";

const today = () => new Date().toLocaleDateString("en-CA");

export default function AttendancePage() {
  const [records, setRecords] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [corrections, setCorrections] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [sort, setSort] = useState({ sortBy: "date", sortOrder: "desc" });
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("All");
  const [department, setDepartment] = useState("All");
  const [location, setLocation] = useState("All");
  const [date, setDate] = useState(today());
  const [detail, setDetail] = useState(null);
  const [manualOpen, setManualOpen] = useState(false);
  const [correction, setCorrection] = useState(null);
  const [toDelete, setToDelete] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const debouncedQuery = useDebouncedValue(query);
  const { showToast } = useToast();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const canManualCheckIn = hasPermission(PERMISSIONS.ATTENDANCE_MANUAL_CHECKIN);
  const canManualCheckOut = hasPermission(PERMISSIONS.ATTENDANCE_MANUAL_CHECKOUT);
  const canEdit = hasPermission(PERMISSIONS.ATTENDANCE_EDIT);
  const canDelete = hasPermission(PERMISSIONS.ATTENDANCE_DELETE);
  const canApprove = hasPermission(PERMISSIONS.ATTENDANCE_APPROVE);
  const canExport = hasPermission(PERMISSIONS.REPORTS_EXPORT);
  const canViewDepartments = hasPermission(PERMISSIONS.DEPARTMENTS_VIEW);

  const filters = useMemo(() => ({
    page,
    limit: 20,
    search: debouncedQuery,
    date,
    status,
    departmentId: department,
    location,
    ...sort
  }), [page, debouncedQuery, date, status, department, location, sort]);

  async function loadAttendance() {
    setLoading(true);
    try {
      const requests = [getAttendanceRecords(filters)];
      if (canApprove) requests.push(getAttendanceCorrections({ status: "pending", limit: 20 }));
      const [attendanceResult, correctionResult] = await Promise.all(requests);
      setRecords(attendanceResult.items);
      setPagination(attendanceResult.pagination);
      setCorrections(correctionResult?.items || []);
      if (!canViewDepartments) {
        setDepartments(Array.from(new Map(attendanceResult.items
          .filter((record) => record.departmentId)
          .map((record) => [record.departmentId, { id: record.departmentId, name: record.department }])).values()));
      }
    } catch (error) {
      setRecords([]);
      showToast(error.message, "warning");
    } finally { setLoading(false); }
  }

  useEffect(() => { loadAttendance(); }, [filters, canApprove]);
  useEffect(() => {
    if (canViewDepartments) getDepartments({ limit: 100 }).then((result) => setDepartments(result.items)).catch(() => setDepartments([]));
  }, [canViewDepartments]);

  async function markExcused(record) {
    try {
      await updateAttendance(record.id, { status: "Excused", notes: record.note, reason: "Marked excused by an authorized administrator." });
      showToast(`${record.personName} marked as excused.`);
      await loadAttendance();
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function saveCorrection() {
    setSaving(true);
    try {
      await updateAttendance(correction.id, {
        checkInAt: correction.checkIn || null,
        checkOutAt: correction.checkOut || null,
        status: correction.status,
        notes: correction.note || null,
        reason: correction.reason
      });
      setCorrection(null);
      showToast(canApprove ? "Attendance correction applied." : "Attendance correction submitted for approval.");
      await loadAttendance();
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  async function reviewCorrection(item, approve) {
    try {
      await reviewAttendanceCorrection(item.id, approve, approve ? "Approved from attendance dashboard." : "Rejected from attendance dashboard.");
      showToast(`Correction ${approve ? "approved" : "rejected"}.`, approve ? "success" : "warning");
      await loadAttendance();
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function confirmDelete() {
    try {
      await deleteAttendance(toDelete.id);
      setToDelete(null);
      showToast("Attendance record deleted.", "warning");
      await loadAttendance();
    } catch (error) { showToast(error.message, "warning"); }
  }

  function openCorrection(record) {
    setCorrection({ ...record, reason: "" });
  }

  return (
    <div className="page-stack">
      <PageHeader title="Attendance Records" description="Review attendance events, manual entries, confidence scores, liveness results, and audit-ready notes." actions={<>
        {canExport && <button className="button button-secondary" type="button" onClick={() => exportReport("attendance", { dateFrom: date, dateTo: date, departmentId: department, status }).catch((error) => showToast(error.message, "warning"))}><Download size={17} /> Export</button>}
        {(canManualCheckIn || canManualCheckOut) && <button className="button button-primary" type="button" onClick={() => setManualOpen(true)}><Clock3 size={17} /> Manual Attendance</button>}
      </>} />

      <section className="filter-panel">
        <SearchInput value={query} onChange={(value) => { setQuery(value); setPage(1); }} placeholder="Search person, event, source, or location..." />
        <input type="date" value={date} onChange={(event) => { setDate(event.target.value); setPage(1); }} aria-label="Date filter" />
        <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option>All</option><option>Present</option><option>Late</option><option>Absent</option><option>On Leave</option><option>Excused</option><option>Review</option></select>
        <select value={department} onChange={(event) => { setDepartment(event.target.value); setPage(1); }}><option value="All">All departments</option>{departments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
        <select value={location} onChange={(event) => { setLocation(event.target.value); setPage(1); }}><option>All</option><option>Main Entrance</option><option>Main Campus</option><option>South Lobby</option><option>Innovation Center</option><option>Remote</option></select>
      </section>

      {loading ? <LoadingSkeleton rows={8} /> : <DataTable
        data={records}
        pagination={pagination}
        onPageChange={setPage}
        onSortChange={({ key, direction }) => { setSort({ sortBy: key, sortOrder: direction }); setPage(1); }}
        actions={[
          { label: "View", icon: <Eye size={17} />, onClick: setDetail },
          { label: "Open route", icon: <Eye size={17} />, onClick: (row) => navigate(`/attendance/${row.id}`) },
          ...(canEdit ? [{ label: "Correct attendance", icon: <Edit3 size={17} />, onClick: openCorrection }] : []),
          ...(canApprove ? [{ label: "Mark excused", icon: <MessageSquarePlus size={17} />, onClick: markExcused }] : []),
          ...(canDelete ? [{ label: "Delete", icon: <Trash2 size={17} />, onClick: setToDelete }] : [])
        ]}
        columns={[
          { key: "personName", label: "Person" },
          { key: "date", label: "Date", sortable: true },
          { key: "checkIn", label: "Check-in", sortable: true },
          { key: "checkOut", label: "Check-out", sortable: true },
          { key: "duration", label: "Duration" },
          { key: "department", label: "Department" },
          { key: "method", label: "Method" },
          { key: "confidence", label: "Confidence", sortable: true, render: (row) => <ConfidenceBadge value={row.confidence} /> },
          { key: "status", label: "Status", sortable: true, render: (row) => <StatusBadge status={row.status} /> }
        ]}
      />}

      {canApprove && corrections.length > 0 && <section>
        <div className="section-heading"><h2>Pending Corrections</h2><span>{corrections.length} awaiting review</span></div>
        <DataTable data={corrections} selectable={false} actions={[
          { label: "Approve", icon: <Check size={17} />, onClick: (row) => reviewCorrection(row, true) },
          { label: "Reject", icon: <X size={17} />, onClick: (row) => reviewCorrection(row, false) }
        ]} columns={[{ key: "attendanceId", label: "Attendance" }, { key: "requestedBy", label: "Requested by" }, { key: "reason", label: "Reason" }, { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> }, { key: "createdAt", label: "Created" }]} />
      </section>}

      <Modal open={Boolean(detail)} title="Attendance Event Details" onClose={() => setDetail(null)} size="lg">{detail && <AttendanceDetailContent event={detail} canEdit={canEdit} onCorrect={() => { setDetail(null); openCorrection(detail); }} />}</Modal>

      <ManualAttendanceModal
        open={manualOpen}
        canCheckIn={canManualCheckIn}
        canCheckOut={canManualCheckOut}
        onClose={() => setManualOpen(false)}
        onSuccess={loadAttendance}
      />

      <Modal open={Boolean(correction)} title="Correct Attendance" onClose={() => setCorrection(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setCorrection(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveCorrection} disabled={saving}>{saving ? "Saving..." : "Submit correction"}</button></>}>
        {correction && <div className="form-grid">
          <FormField label="Check-in"><input type="time" value={correction.checkIn || ""} onChange={(event) => setCorrection({ ...correction, checkIn: event.target.value })} /></FormField>
          <FormField label="Check-out"><input type="time" value={correction.checkOut || ""} onChange={(event) => setCorrection({ ...correction, checkOut: event.target.value })} /></FormField>
          <FormField label="Status"><select value={correction.status} onChange={(event) => setCorrection({ ...correction, status: event.target.value })}><option>Present</option><option>Late</option><option>Absent</option><option>Excused</option><option>On Leave</option><option>Review</option></select></FormField>
          <FormField label="Reason" required className="full-field"><textarea rows="4" value={correction.reason} onChange={(event) => setCorrection({ ...correction, reason: event.target.value })} /></FormField>
        </div>}
      </Modal>

      <ConfirmDialog open={Boolean(toDelete)} danger title="Delete attendance" message={`Delete ${toDelete?.id || "this attendance record"}?`} confirmLabel="Delete" onClose={() => setToDelete(null)} onConfirm={confirmDelete} />
    </div>
  );
}

export function AttendanceDetailContent({ event, onCorrect, canEdit = false }) {
  return (
    <div className="event-detail">
      <dl>
        <dt>Person</dt><dd>{event.personName} ({event.personId})</dd>
        <dt>Timestamp</dt><dd>{event.date} {event.checkIn}</dd>
        <dt>Source</dt><dd>{event.sourceIdentifier || event.source || "N/A"}</dd>
        <dt>Location</dt><dd>{event.location || "N/A"}</dd>
        <dt>Confidence</dt><dd><ConfidenceBadge value={event.confidence} /></dd>
        <dt>Liveness</dt><dd><StatusBadge status={event.liveness} /></dd>
        <dt>Attendance result</dt><dd><StatusBadge status={event.status} /></dd>
        <dt>Verification method</dt><dd>{event.method}</dd>
        <dt>Checkout source</dt><dd>{event.checkoutSource ? event.checkoutSource.replaceAll("_", " ") : "Normal / pending"}</dd>
        {event.autoCheckoutReason && <><dt>Automatic checkout reason</dt><dd>{event.autoCheckoutReason}</dd></>}
        <dt>Notes</dt><dd>{event.note || "None"}</dd>
        <dt>Audit history</dt><dd>Changes are recorded by the backend correction workflow.</dd>
      </dl>
      {canEdit && <div className="button-row"><button className="button button-secondary" type="button" onClick={onCorrect}>Correct attendance</button></div>}
    </div>
  );
}
