import { useEffect, useMemo, useState } from "react";
import { Download, FilePenLine, PlaneTakeoff } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import StatCard from "../components/common/StatCard.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import Modal from "../components/common/Modal.jsx";
import FormField from "../components/forms/FormField.jsx";
import { useToast } from "../context/ToastContext.jsx";
import { getAttendanceRecords, requestAttendanceCorrection } from "../services/attendanceService.js";
import { createLeaveRequest, getLeaveRequests } from "../services/peopleService.js";
import { downloadCsv } from "../utils/csv.js";
import { useAuth } from "../context/AuthContext.jsx";
import { toDateTimeLocal } from "../utils/format.js";

const today = () => new Date().toLocaleDateString("en-CA");

export default function UserPortalPage() {
  const { user } = useAuth();
  const [records, setRecords] = useState([]);
  const [requests, setRequests] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [modal, setModal] = useState(null);
  const [leave, setLeave] = useState({ startDate: today(), endDate: today(), type: "Annual leave", reason: "" });
  const [correction, setCorrection] = useState({ attendanceId: "", checkInAt: "", checkOutAt: "", reason: "" });
  const [loading, setLoading] = useState(true);
  const { showToast } = useToast();

  async function loadPortal() {
    if (!user?.personId) return;
    setLoading(true);
    try {
      const [attendanceResult, leaveResult] = await Promise.all([
        getAttendanceRecords({ page, limit: 20 }),
        getLeaveRequests(user.personId, { limit: 20 })
      ]);
      setRecords(attendanceResult.items);
      setPagination(attendanceResult.pagination);
      setRequests(leaveResult.items);
    } catch (error) { showToast(error.message, "warning"); }
    finally { setLoading(false); }
  }

  useEffect(() => { loadPortal(); }, [user?.personId, page]);

  const summary = useMemo(() => {
    const current = records.find((record) => record.date === today());
    const attended = records.filter((record) => ["Present", "Late"].includes(record.status)).length;
    return {
      current,
      attendanceRate: records.length ? Math.round((attended / records.length) * 100) : 0,
      absences: records.filter((record) => record.status === "Absent").length,
      late: records.filter((record) => record.status === "Late").length
    };
  }, [records]);

  async function submitRequest() {
    try {
      if (modal === "leave") {
        await createLeaveRequest({ personId: user.personId, ...leave });
        showToast("Leave request submitted.");
      } else {
        await requestAttendanceCorrection(correction.attendanceId, {
          requestedValues: { checkInAt: correction.checkInAt || null, checkOutAt: correction.checkOutAt || null },
          reason: correction.reason
        });
        showToast("Attendance correction requested.");
      }
      setModal(null);
      await loadPortal();
    } catch (error) { showToast(error.message, "warning"); }
  }

  if (!user?.personId) return <PageHeader title="My Attendance" description="This account is not linked to a person record." />;
  if (loading && records.length === 0) return <LoadingSkeleton rows={8} />;

  return (
    <div className="page-stack">
      <PageHeader title="My Attendance" description="View your attendance history and submit correction or leave requests." actions={<button className="button button-secondary" type="button" onClick={() => downloadCsv("my-attendance.csv", records)}><Download size={17} /> Download current page</button>} />
      <div className="stat-grid">
        <StatCard label="Status today" value={summary.current?.status || "Absent"} />
        <StatCard label="Check-in" value={summary.current?.checkIn || "N/A"} />
        <StatCard label="Attendance rate" value={`${summary.attendanceRate}%`} tone="success" />
        <StatCard label="Absences" value={summary.absences} tone="warning" />
        <StatCard label="Late count" value={summary.late} tone="warning" />
      </div>
      <section className="detail-grid">
        <article className="info-card">
          <h2>Current Attendance</h2>
          <dl><dt>Person ID</dt><dd>{user.personId}</dd><dt>Check-out</dt><dd>{summary.current?.checkOut || "Pending"}</dd><dt>Method</dt><dd>{summary.current?.method || "Not recorded"}</dd></dl>
          <div className="button-row">
            <button className="button button-secondary" type="button" onClick={() => { setCorrection({ attendanceId: records[0]?.id || "", checkInAt: records[0]?.checkInAt || "", checkOutAt: records[0]?.checkOutAt || "", reason: "" }); setModal("correction"); }} disabled={!records.length}><FilePenLine size={17} /> Request correction</button>
            <button className="button button-primary" type="button" onClick={() => { setLeave({ startDate: today(), endDate: today(), type: "Annual leave", reason: "" }); setModal("leave"); }}><PlaneTakeoff size={17} /> Submit leave request</button>
          </div>
        </article>
        <article className="info-card"><h2>Leave Requests</h2>{requests.length ? requests.map((request) => <p key={request.id}><strong>{request.type}</strong>: <StatusBadge status={request.status} /> · {request.startDate} to {request.endDate}</p>) : <p>No leave requests.</p>}</article>
      </section>
      <DataTable data={records} pagination={pagination} onPageChange={setPage} selectable={false} columns={[
        { key: "date", label: "Date" }, { key: "checkIn", label: "Check-in" }, { key: "checkOut", label: "Check-out" }, { key: "duration", label: "Duration" },
        { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> }, { key: "note", label: "Note" }
      ]} />
      <Modal open={Boolean(modal)} title={modal === "leave" ? "Submit Leave Request" : "Request Attendance Correction"} onClose={() => setModal(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setModal(null)}>Cancel</button><button className="button button-primary" type="button" onClick={submitRequest}>Submit request</button></>}>
        {modal === "leave" ? <div className="form-grid">
          <FormField label="Start date" required><input type="date" value={leave.startDate} onChange={(event) => setLeave({ ...leave, startDate: event.target.value })} /></FormField>
          <FormField label="End date" required><input type="date" value={leave.endDate} onChange={(event) => setLeave({ ...leave, endDate: event.target.value })} /></FormField>
          <FormField label="Leave type"><input value={leave.type} onChange={(event) => setLeave({ ...leave, type: event.target.value })} /></FormField>
          <FormField label="Reason" className="full-field" required><textarea rows="4" value={leave.reason} onChange={(event) => setLeave({ ...leave, reason: event.target.value })} /></FormField>
        </div> : modal === "correction" ? <div className="form-grid">
          <FormField label="Attendance record"><select value={correction.attendanceId} onChange={(event) => { const record = records.find((item) => item.id === event.target.value); setCorrection({ ...correction, attendanceId: event.target.value, checkInAt: record?.checkInAt || "", checkOutAt: record?.checkOutAt || "" }); }}>{records.map((record) => <option key={record.id} value={record.id}>{record.date} · {record.id}</option>)}</select></FormField>
          <FormField label="Requested check-in"><input type="datetime-local" value={toDateTimeLocal(correction.checkInAt)} onChange={(event) => setCorrection({ ...correction, checkInAt: event.target.value })} /></FormField>
          <FormField label="Requested check-out"><input type="datetime-local" value={toDateTimeLocal(correction.checkOutAt)} onChange={(event) => setCorrection({ ...correction, checkOutAt: event.target.value })} /></FormField>
          <FormField label="Reason" className="full-field" required><textarea rows="4" value={correction.reason} onChange={(event) => setCorrection({ ...correction, reason: event.target.value })} /></FormField>
        </div> : null}
      </Modal>
    </div>
  );
}
