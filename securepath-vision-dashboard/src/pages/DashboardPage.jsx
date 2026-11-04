import { useEffect, useState } from "react";
import {
  AlertTriangle,
  BadgeCheck,
  Check,
  ClipboardCheck,
  Download,
  Percent,
  ScanFace,
  ShieldCheck,
  UserCheck,
  UserPlus,
  UserRoundX,
  UsersRound,
  Watch,
  X
} from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import StatCard from "../components/common/StatCard.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import ConfidenceBadge from "../components/common/ConfidenceBadge.jsx";
import PersonAvatar from "../components/common/PersonAvatar.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import Modal from "../components/common/Modal.jsx";
import FormField from "../components/forms/FormField.jsx";
import {
  AttendanceTrendChart,
  DepartmentBars,
  StatusBreakdownChart
} from "../components/charts/DashboardCharts.jsx";
import { getDashboardData } from "../services/reportService.js";
import {
  assignRecognitionPerson,
  getRecognitionEvents,
  rejectRecognition,
  verifyRecognition
} from "../services/attendanceService.js";
import { exportReport } from "../services/reportService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";
import { useToast } from "../context/ToastContext.jsx";

export default function DashboardPage() {
  const [data, setData] = useState(null);
  const [events, setEvents] = useState([]);
  const [date, setDate] = useState(() => new Date().toLocaleDateString("en-CA"));
  const [assigning, setAssigning] = useState(null);
  const [assignment, setAssignment] = useState({ personId: "", note: "" });
  const { hasPermission } = usePermissions();
  const { showToast } = useToast();
  const canViewBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_VIEW);
  const canManageBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_MANAGE);

  async function loadEvents() {
    if (!canViewBiometrics) return;
    const recognition = await getRecognitionEvents({ limit: 10, dateFrom: date, dateTo: date });
    setEvents(recognition.items);
  }

  useEffect(() => {
    setData(null);
    getDashboardData({ date })
      .then(setData)
      .catch((error) => showToast(error.message, "warning"));

    if (canViewBiometrics) {
      getRecognitionEvents({ limit: 10, dateFrom: date, dateTo: date })
        .then((recognition) => setEvents(recognition.items))
        .catch((error) => showToast(error.message, "warning"));
    } else {
      setEvents([]);
    }
  }, [date, canViewBiometrics]);

  async function reviewEvent(event, approve) {
    try {
      if (approve) await verifyRecognition(event.id, "Verified from the dashboard.");
      else await rejectRecognition(event.id, "Rejected from the dashboard.");
      showToast(`Recognition ${approve ? "verified" : "rejected"}.`, approve ? "success" : "warning");
      await loadEvents();
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function assignPerson() {
    try {
      await assignRecognitionPerson(assigning.id, assignment.personId, assignment.note);
      setAssigning(null);
      setAssignment({ personId: "", note: "" });
      showToast("Person assigned to recognition event.");
      await loadEvents();
    } catch (error) { showToast(error.message, "warning"); }
  }

  if (!data) return <LoadingSkeleton rows={8} />;

  const stats = [
    { label: "Total Users", value: data.stats.totalUsers.toLocaleString(), icon: <UsersRound />, delta: "Organisation", tone: "neutral" },
    { label: "Present Today", value: data.stats.presentToday.toLocaleString(), icon: <UserCheck />, delta: `${data.stats.attendanceRate}%`, tone: "success" },
    { label: "Absent Today", value: data.stats.absentToday, icon: <UserRoundX />, delta: "Selected day", tone: "neutral" },
    { label: "Late Today", value: data.stats.lateToday, icon: <Watch />, delta: "Selected day", tone: "warning" },
    { label: "Attendance Rate", value: `${data.stats.attendanceRate}%`, icon: <Percent />, delta: date, tone: "success" },
    { label: "Recognition Success", value: `${data.stats.recognitionSuccessRate}%`, icon: <ScanFace />, delta: "Matched", tone: "ai" },
    { label: "Pending Verification", value: data.stats.pendingVerification, icon: <ClipboardCheck />, delta: "Review", tone: "warning" },
    { label: "Active Admins", value: data.stats.activeAdmins, icon: <ShieldCheck />, delta: "Enabled", tone: "neutral" }
  ];

  return (
    <div className="page-stack">
      <PageHeader
        title="Attendance Overview"
        description="Administrative attendance, verification, and organisation trends across all registered people."
        actions={
          <>
            <input type="date" value={date} onChange={(event) => setDate(event.target.value)} aria-label="Date filter" />
            {hasPermission(PERMISSIONS.REPORTS_EXPORT) && (
              <button className="button button-secondary" type="button" onClick={() => exportReport("recognition-accuracy", { dateFrom: date, dateTo: date }).catch((error) => showToast(error.message, "warning"))}>
                <Download size={17} /> Export
              </button>
            )}
          </>
        }
        meta={<span className="pill"><BadgeCheck size={15} /> Administration View</span>}
      />

      <div className="stat-grid dense">
        {stats.map((stat) => <StatCard key={stat.label} {...stat} />)}
      </div>

      <div className="dashboard-grid">
        <AttendanceTrendChart data={data.attendanceTrend} />
        <DepartmentBars data={data.departments} />
        <StatusBreakdownChart data={data.statusBreakdown} />
      </div>

      <section className="split-grid">
        <div>
          <div className="section-heading">
            <h2>Recent Recognition Activity</h2>
            <span>{date}</span>
          </div>
          <DataTable
            data={events}
            pageSize={4}
            selectable={false}
            actions={canManageBiometrics ? [
              { label: "Verify", icon: <Check size={17} />, onClick: (row) => reviewEvent(row, true), disabled: (row) => row.verificationStatus !== "pending" || !row.personId },
              { label: "Reject", icon: <X size={17} />, onClick: (row) => reviewEvent(row, false), disabled: (row) => row.verificationStatus !== "pending" },
              { label: "Assign person", icon: <UserPlus size={17} />, onClick: (row) => { setAssigning(row); setAssignment({ personId: row.personId || "", note: "" }); }, disabled: (row) => row.verificationStatus !== "pending" }
            ] : []}
            columns={[
              {
                key: "name",
                label: "Subject",
                sortable: true,
                render: (row) => (
                  <span className="person-cell">
                    <PersonAvatar name={row.name} status={row.verificationStatus === "pending" ? "late" : "present"} />
                    <span><strong>{row.name}</strong><small>{row.personCode}</small></span>
                  </span>
                )
              },
              { key: "timestamp", label: "Time", sortable: true },
              { key: "event", label: "Event", sortable: true },
              { key: "confidence", label: "Confidence", sortable: true, render: (row) => <ConfidenceBadge value={row.confidence} /> },
              { key: "status", label: "Status", sortable: true, render: (row) => <StatusBadge status={row.status} /> }
            ]}
          />
        </div>
        <aside className="side-feed">
          <h2>Verification Queue</h2>
          {events.filter((event) => event.verificationStatus === "pending").map((event) => (
            <article key={event.id} className="alert-line">
              <AlertTriangle size={18} />
              <div><strong>{event.name}</strong><span>{event.status} · {event.timestamp}</span></div>
              <ConfidenceBadge value={event.confidence} />
            </article>
          ))}
        </aside>
      </section>
      <Modal open={Boolean(assigning)} title="Assign Recognition" onClose={() => setAssigning(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setAssigning(null)}>Cancel</button><button className="button button-primary" type="button" onClick={assignPerson} disabled={!assignment.personId.trim()}>Assign person</button></>}>
        <div className="form-grid"><FormField label="Person ID" required><input value={assignment.personId} onChange={(event) => setAssignment({ ...assignment, personId: event.target.value })} placeholder="EMP-00001" /></FormField><FormField label="Review note" className="full-field"><textarea rows="3" value={assignment.note} onChange={(event) => setAssignment({ ...assignment, note: event.target.value })} /></FormField></div>
      </Modal>
    </div>
  );
}
