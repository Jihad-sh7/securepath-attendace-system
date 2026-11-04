import { useEffect, useMemo, useState } from "react";
import { Download, RefreshCw } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import { AccuracyChart, AttendanceTrendChart, DepartmentBars, StatusBreakdownChart } from "../components/charts/DashboardCharts.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import { useToast } from "../context/ToastContext.jsx";
import { exportReport, getDashboardData, getReport } from "../services/reportService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";

const reportTypes = {
  "Daily attendance report": "attendance",
  "Monthly attendance report": "attendance",
  "Absence report": "absences",
  "Late report": "late-arrivals",
  "Working-hours report": "working-hours",
  "Department report": "departments",
  "Recognition accuracy report": "recognition-accuracy"
};

function dateRange(range) {
  const end = new Date();
  const start = new Date(end);
  if (range === "Today") start.setDate(end.getDate());
  else if (range === "This week") start.setDate(end.getDate() - 6);
  else if (range === "This month") start.setDate(1);
  else start.setMonth(end.getMonth() - 3);
  return { dateFrom: start.toLocaleDateString("en-CA"), dateTo: end.toLocaleDateString("en-CA") };
}

export default function ReportsPage() {
  const [dashboard, setDashboard] = useState(null);
  const [report, setReport] = useState(null);
  const [reportType, setReportType] = useState("Daily attendance report");
  const [range, setRange] = useState("This month");
  const [loading, setLoading] = useState(true);
  const { showToast } = useToast();
  const { hasPermission } = usePermissions();
  const type = reportTypes[reportType];
  const filters = useMemo(() => dateRange(range), [range]);

  async function loadReport() {
    setLoading(true);
    try {
      const [dashboardData, reportData] = await Promise.all([getDashboardData(), getReport(type, filters)]);
      setDashboard(dashboardData);
      setReport(reportData);
    } catch (error) { showToast(error.message, "warning"); }
    finally { setLoading(false); }
  }

  useEffect(() => { loadReport(); }, [type, filters]);

  if (loading && !dashboard) return <LoadingSkeleton rows={8} />;
  const summary = report?.summary || {};
  const rows = report?.rows || [];

  return (
    <div className="page-stack">
      <PageHeader title="Reports & Analytics" description="Generate authoritative attendance, absence, working-hours, department, and recognition reports." actions={<>
        <select value={reportType} onChange={(event) => setReportType(event.target.value)}>{Object.keys(reportTypes).map((label) => <option key={label}>{label}</option>)}</select>
        <select value={range} onChange={(event) => setRange(event.target.value)}><option>Today</option><option>This week</option><option>This month</option><option>Last quarter</option></select>
        {hasPermission(PERMISSIONS.REPORTS_EXPORT) && <button className="button button-secondary" type="button" onClick={() => exportReport(type, filters).catch((error) => showToast(error.message, "warning"))}><Download size={17} /> Export</button>}
        {hasPermission(PERMISSIONS.REPORTS_GENERATE) && <button className="button button-primary" type="button" onClick={loadReport} disabled={loading}><RefreshCw size={17} /> Generate</button>}
      </>} />
      <section className="info-card"><h2>{reportType}</h2><div className="quality-grid">{Object.entries(summary).map(([key, value]) => <span key={key}><strong>{value}</strong>{key.replace(/([A-Z])/g, " $1")}</span>)}</div></section>
      {dashboard && <div className="dashboard-grid"><AttendanceTrendChart data={dashboard.attendanceTrend || []} /><StatusBreakdownChart data={dashboard.statusBreakdown || []} /><DepartmentBars data={dashboard.departments || []} /><AccuracyChart data={dashboard.recognitionAccuracy || []} /></div>}
      {rows.length > 0 && <DataTable data={rows} selectable={false} columns={Object.keys(rows[0]).slice(0, 8).map((key) => ({ key, label: key.replace(/([A-Z])/g, " $1").replace(/^./, (letter) => letter.toUpperCase()), sortable: true }))} />}
    </div>
  );
}
