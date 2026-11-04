import { useEffect, useMemo, useState } from "react";
import { CheckCircle2, CircleDollarSign, Download, Edit3, Eye, LockKeyhole, Plus, RefreshCw, Trash2, UsersRound } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import SearchInput from "../components/common/SearchInput.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import StatCard from "../components/common/StatCard.jsx";
import Tabs from "../components/common/Tabs.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import Modal from "../components/common/Modal.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import FormField from "../components/forms/FormField.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import { getDepartments } from "../services/organizationService.js";
import {
  approvePayroll,
  closePayroll,
  createPayRate,
  createRole,
  deletePayRate,
  deleteRole,
  exportPayroll,
  generatePayroll,
  getPayRates,
  getPayrollEntries,
  getPayrollOverview,
  getRoles,
  updatePayRate,
  updateRole
} from "../services/payrollService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";
import { useToast } from "../context/ToastContext.jsx";
import { useDebouncedValue } from "../hooks/useDebouncedValue.js";

function currentMonth() {
  const value = new Date();
  return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}`;
}

function hours(minutes = 0) { return (Number(minutes) / 60).toFixed(2); }
function money(value, currency = "USD") {
  if (value === null || value === undefined) return "Needs attention";
  return new Intl.NumberFormat(undefined, { style: "currency", currency }).format(Number(value));
}

const blankRate = { departmentId: "", roleId: "", hourlyRate: "", currency: "USD", effectiveFrom: new Date().toLocaleDateString("en-CA"), effectiveUntil: "" };
const blankRole = { name: "", code: "", description: "", status: "Active" };

export default function PayrollPage() {
  const [tab, setTab] = useState("payroll");
  const [monthValue, setMonthValue] = useState(currentMonth());
  const [period, setPeriod] = useState(null);
  const [summary, setSummary] = useState(null);
  const [entries, setEntries] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState("");
  const [departmentId, setDepartmentId] = useState("All");
  const [roleId, setRoleId] = useState("All");
  const [status, setStatus] = useState("All");
  const [departments, setDepartments] = useState([]);
  const [roles, setRoles] = useState([]);
  const [rates, setRates] = useState([]);
  const [rateDraft, setRateDraft] = useState(null);
  const [roleDraft, setRoleDraft] = useState(null);
  const [detail, setDetail] = useState(null);
  const [toDelete, setToDelete] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const { showToast } = useToast();
  const { hasPermission } = usePermissions();
  const canManage = hasPermission(PERMISSIONS.PAYROLL_MANAGE);
  const canExport = hasPermission(PERMISSIONS.PAYROLL_EXPORT);
  const canViewRates = hasPermission(PERMISSIONS.PAY_RATES_VIEW);
  const canManageRates = hasPermission(PERMISSIONS.PAY_RATES_MANAGE);
  const debouncedQuery = useDebouncedValue(query);
  const [year, month] = monthValue.split("-").map(Number);
  const entryFilters = useMemo(() => ({ page, limit: 20, search: debouncedQuery, departmentId, roleId, status }), [page, debouncedQuery, departmentId, roleId, status]);

  async function loadOverview() {
    setLoading(true);
    try {
      const result = await getPayrollOverview(year, month);
      setPeriod(result?.period || null);
      setSummary(result?.summary || null);
      if (!result?.period) { setEntries([]); setPagination({ page: 1, limit: 20, total: 0, totalPages: 1 }); }
    } catch (error) { showToast(error.message, "warning"); }
    finally { setLoading(false); }
  }

  async function loadEntries() {
    if (!period?.id) return;
    setLoading(true);
    try {
      const result = await getPayrollEntries(period.id, entryFilters);
      setEntries(result.items);
      setPagination(result.pagination);
    } catch (error) { showToast(error.message, "warning"); }
    finally { setLoading(false); }
  }

  async function loadConfiguration() {
    const requests = [getRoles({ limit: 100 })];
    requests.push(getDepartments({ limit: 100 }).catch(() => ({ items: [] })));
    if (canViewRates) requests.push(getPayRates({ limit: 100 }));
    try {
      const [roleResult, departmentResult, rateResult] = await Promise.all(requests);
      setRoles(roleResult.items);
      setDepartments(departmentResult.items);
      setRates(rateResult?.items || []);
    } catch (error) { showToast(error.message, "warning"); }
  }

  useEffect(() => { setPage(1); loadOverview(); }, [year, month]);
  useEffect(() => { loadEntries(); }, [period?.id, entryFilters]);
  useEffect(() => { loadConfiguration(); }, [canViewRates]);

  async function calculate() {
    setSaving(true);
    try { await generatePayroll(year, month); showToast("Payroll calculated successfully."); await loadOverview(); await loadEntries(); }
    catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  async function transition(action) {
    try { await action(period.id); showToast(action === approvePayroll ? "Payroll approved." : "Payroll closed."); await loadOverview(); }
    catch (error) { showToast(error.message, "warning"); }
  }

  async function saveRate() {
    setSaving(true);
    try {
      const payload = { ...rateDraft, effectiveUntil: rateDraft.effectiveUntil || null };
      if (rateDraft.id) await updatePayRate(rateDraft.id, payload); else await createPayRate(payload);
      setRateDraft(null); showToast("Pay rate saved."); await loadConfiguration();
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  async function saveRole() {
    setSaving(true);
    try {
      const payload = { ...roleDraft, code: roleDraft.code.toUpperCase().replace(/\s+/g, "_") };
      if (roleDraft.id) await updateRole(roleDraft.id, payload); else await createRole(payload);
      setRoleDraft(null); showToast("Role saved."); await loadConfiguration();
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  async function confirmDelete() {
    try {
      if (toDelete.kind === "rate") await deletePayRate(toDelete.item.id); else await deleteRole(toDelete.item.id);
      setToDelete(null); showToast("Configuration deleted.", "warning"); await loadConfiguration();
    } catch (error) { showToast(error.message, "warning"); }
  }

  return (
    <div className="page-stack">
      <PageHeader title="Payroll" description="Calculate monthly pay from verified attendance, historical department-role rates, and recorded overtime." actions={tab === "payroll" && <>
        {canExport && period && <button className="button button-secondary" type="button" onClick={() => exportPayroll(period.id).catch((error) => showToast(error.message, "warning"))}><Download size={17} /> Export CSV</button>}
        {canManage && <button className="button button-primary" type="button" onClick={calculate} disabled={saving}><RefreshCw size={17} /> {period ? "Recalculate" : "Calculate payroll"}</button>}
      </>} />

      {canViewRates && <Tabs tabs={[{ id: "payroll", label: "Payroll periods" }, { id: "rates", label: "Pay rates & roles" }]} active={tab} onChange={setTab} />}

      {tab === "payroll" ? <>
        <section className="filter-panel">
          <input type="month" value={monthValue} onChange={(event) => setMonthValue(event.target.value)} aria-label="Payroll month" />
          <SearchInput value={query} onChange={(value) => { setQuery(value); setPage(1); }} placeholder="Search employee..." />
          <select value={departmentId} onChange={(event) => { setDepartmentId(event.target.value); setPage(1); }}><option value="All">All departments</option>{departments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
          <select value={roleId} onChange={(event) => { setRoleId(event.target.value); setPage(1); }}><option value="All">All roles</option>{roles.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
          <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option>All</option><option>Calculated</option><option>Attention</option></select>
        </section>

        <div className="stat-grid dense">
          <StatCard label="Employees" value={summary?.employees ?? 0} icon={<UsersRound size={18} />} />
          <StatCard label="Regular hours" value={hours(summary?.regularMinutes)} icon={<CheckCircle2 size={18} />} tone="success" />
          <StatCard label="Overtime hours" value={hours(summary?.overtimeMinutes)} icon={<RefreshCw size={18} />} tone="warning" />
          <StatCard label="Total payroll" value={money(summary?.totalPay ?? 0, summary?.currency)} icon={<CircleDollarSign size={18} />} />
          <StatCard label="Needs attention" value={summary?.attentionCount ?? 0} icon={<LockKeyhole size={18} />} tone="danger" />
        </div>

        {period && <section className="section-heading"><strong>{period.label}</strong> <StatusBadge status={period.status} /><span> Calculated {period.calculatedAt ? new Date(period.calculatedAt).toLocaleString() : "not yet"}</span>{canManage && <span className="button-row payroll-state-actions">{period.status === "Calculated" && <button className="button button-secondary" type="button" onClick={() => transition(approvePayroll)}><CheckCircle2 size={17} /> Approve</button>}{period.status === "Approved" && <button className="button button-secondary" type="button" onClick={() => transition(closePayroll)}><LockKeyhole size={17} /> Close period</button>}</span>}</section>}

        {loading ? <LoadingSkeleton rows={7} /> : <DataTable data={entries} pagination={pagination} onPageChange={setPage} selectable={false} emptyTitle={period ? "No employees match these filters" : "No payroll has been calculated for this month"} actions={period ? [{ label: "View payroll detail", icon: <Eye size={17} />, onClick: setDetail }] : []} columns={[
          { key: "personName", label: "Employee", render: (row) => <span className="stacked-cell"><strong>{row.personName}</strong><small>{row.personId}</small></span> },
          { key: "department", label: "Department" },
          { key: "role", label: "Role" },
          { key: "totalWorkedHours", label: "Hours" },
          { key: "overtimeHours", label: "Overtime" },
          { key: "hourlyRate", label: "Rate", render: (row) => row.hourlyRate ? money(row.hourlyRate, row.currency) : "Mixed / missing" },
          { key: "totalPay", label: "Total pay", render: (row) => money(row.totalPay, row.currency) },
          { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> }
        ]} />}
      </> : <>
        <div className="section-heading button-row"><strong>Historical hourly rates</strong>{canManageRates && <><button className="button button-secondary" type="button" onClick={() => setRoleDraft({ ...blankRole })}><Plus size={17} /> Add role</button><button className="button button-primary" type="button" onClick={() => setRateDraft({ ...blankRate, departmentId: departments[0]?.id || "", roleId: roles[0]?.id || "" })}><Plus size={17} /> Add rate</button></>}</div>
        <DataTable data={rates} selectable={false} pageSize={10} actions={canManageRates ? [{ label: "Edit rate", icon: <Edit3 size={17} />, onClick: setRateDraft }, { label: "Delete rate", icon: <Trash2 size={17} />, onClick: (item) => setToDelete({ kind: "rate", item }) }] : []} columns={[{ key: "department", label: "Department" }, { key: "role", label: "Role" }, { key: "hourlyRate", label: "Hourly rate", render: (row) => money(row.hourlyRate, row.currency) }, { key: "effectiveFrom", label: "Effective from" }, { key: "effectiveUntil", label: "Effective until", render: (row) => row.effectiveUntil || "Current" }]} />
        <div className="section-heading"><strong>Employee roles</strong></div>
        <DataTable data={roles} selectable={false} pageSize={10} actions={canManageRates ? [{ label: "Edit role", icon: <Edit3 size={17} />, onClick: setRoleDraft }, { label: "Delete role", icon: <Trash2 size={17} />, onClick: (item) => setToDelete({ kind: "role", item }) }] : []} columns={[{ key: "name", label: "Role" }, { key: "code", label: "Code" }, { key: "peopleCount", label: "Assigned people" }, { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> }]} />
      </>}

      <Modal open={Boolean(detail)} title={`${detail?.personName || "Employee"} Payroll`} size="lg" onClose={() => setDetail(null)}>
        {detail && <div className="page-stack"><div className="quality-grid"><span><strong>{hours(detail.regularMinutes)}</strong>Regular hours</span><span><strong>{hours(detail.overtimeMinutes)}</strong>Overtime hours</span><span><strong>{money(detail.totalPay, detail.currency)}</strong>Total pay</span><span><strong>{detail.hourlyRate ? money(detail.hourlyRate, detail.currency) : "Mixed"}</strong>Hourly rate</span></div>{detail.issues.length > 0 && <div className="form-error">{detail.issues.join(" ")}</div>}<DataTable data={detail.attendanceBreakdown} keyField="attendanceId" selectable={false} pageSize={12} columns={[{ key: "date", label: "Date" }, { key: "totalMinutes", label: "Total", render: (row) => `${hours(row.totalMinutes)}h` }, { key: "regularMinutes", label: "Regular", render: (row) => `${hours(row.regularMinutes)}h` }, { key: "overtimeMinutes", label: "Overtime", render: (row) => `${hours(row.overtimeMinutes)}h` }, { key: "hourlyRate", label: "Rate", render: (row) => row.hourlyRate ? money(row.hourlyRate, detail.currency) : "Missing" }, { key: "checkoutSource", label: "Checkout source", render: (row) => row.checkoutSource?.replaceAll("_", " ") }]} /></div>}
      </Modal>

      <Modal open={Boolean(rateDraft)} title={rateDraft?.id ? "Edit Pay Rate" : "Add Pay Rate"} onClose={() => setRateDraft(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setRateDraft(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveRate} disabled={saving}>Save rate</button></>}>
        {rateDraft && <div className="form-grid"><FormField label="Department" required><select value={rateDraft.departmentId} onChange={(event) => setRateDraft({ ...rateDraft, departmentId: event.target.value })}>{departments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></FormField><FormField label="Role" required><select value={rateDraft.roleId} onChange={(event) => setRateDraft({ ...rateDraft, roleId: event.target.value })}>{roles.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></FormField><FormField label="Hourly rate" required><input type="number" min="0.0001" step="0.0001" value={rateDraft.hourlyRate} onChange={(event) => setRateDraft({ ...rateDraft, hourlyRate: event.target.value })} /></FormField><FormField label="Currency" required><input maxLength="3" value={rateDraft.currency} onChange={(event) => setRateDraft({ ...rateDraft, currency: event.target.value.toUpperCase() })} /></FormField><FormField label="Effective from" required><input type="date" value={rateDraft.effectiveFrom} onChange={(event) => setRateDraft({ ...rateDraft, effectiveFrom: event.target.value })} /></FormField><FormField label="Effective until"><input type="date" value={rateDraft.effectiveUntil || ""} onChange={(event) => setRateDraft({ ...rateDraft, effectiveUntil: event.target.value })} /></FormField></div>}
      </Modal>

      <Modal open={Boolean(roleDraft)} title={roleDraft?.id ? "Edit Role" : "Add Role"} onClose={() => setRoleDraft(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setRoleDraft(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveRole} disabled={saving}>Save role</button></>}>
        {roleDraft && <div className="form-grid"><FormField label="Name" required><input value={roleDraft.name} onChange={(event) => setRoleDraft({ ...roleDraft, name: event.target.value })} /></FormField><FormField label="Code" required><input value={roleDraft.code} onChange={(event) => setRoleDraft({ ...roleDraft, code: event.target.value.toUpperCase() })} /></FormField><FormField label="Status"><select value={roleDraft.status} onChange={(event) => setRoleDraft({ ...roleDraft, status: event.target.value })}><option>Active</option><option>Inactive</option></select></FormField><FormField label="Description" className="full-field"><textarea rows="3" value={roleDraft.description || ""} onChange={(event) => setRoleDraft({ ...roleDraft, description: event.target.value })} /></FormField></div>}
      </Modal>

      <ConfirmDialog open={Boolean(toDelete)} danger title={`Delete ${toDelete?.kind || "item"}`} message={`Delete ${toDelete?.item?.name || toDelete?.item?.role || "this item"}? Historical payroll snapshots will remain unchanged.`} confirmLabel="Delete" onClose={() => setToDelete(null)} onConfirm={confirmDelete} />
    </div>
  );
}
