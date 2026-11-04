import { useEffect, useState } from "react";
import { BarChart3, Edit3, Plus, Trash2, Users } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import Modal from "../components/common/Modal.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import FormField from "../components/forms/FormField.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import { DepartmentBars } from "../components/charts/DashboardCharts.jsx";
import { useToast } from "../context/ToastContext.jsx";
import {
  createDepartment,
  deleteDepartment,
  getDepartmentMembers,
  getDepartments,
  getDepartmentStatistics,
  updateDepartment
} from "../services/organizationService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";

export default function DepartmentsPage() {
  const [departments, setDepartments] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState(null);
  const [toDelete, setToDelete] = useState(null);
  const [analytics, setAnalytics] = useState(null);
  const [members, setMembers] = useState(null);
  const [saving, setSaving] = useState(false);
  const { showToast } = useToast();
  const { hasPermission } = usePermissions();
  const canManage = hasPermission(PERMISSIONS.DEPARTMENTS_MANAGE);

  async function loadDepartments() {
    try {
      const result = await getDepartments({ page, limit: 20 });
      setDepartments(result.items);
      setPagination(result.pagination);
    } catch (error) { showToast(error.message, "warning"); }
  }

  useEffect(() => { loadDepartments(); }, [page]);

  async function saveDepartment() {
    if (!editing.name?.trim() || (!editing.id && !editing.code?.trim())) return showToast("Department name and code are required.", "warning");
    setSaving(true);
    try {
      if (editing.id) {
        await updateDepartment(editing.id, editing);
        showToast("Department updated successfully.");
      } else {
        await createDepartment(editing);
        showToast("Department created successfully.");
      }
      setEditing(null);
      await loadDepartments();
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  async function openAnalytics(department) {
    try { setAnalytics(await getDepartmentStatistics(department.id)); }
    catch (error) { showToast(error.message, "warning"); }
  }

  async function openMembers(department) {
    try {
      const result = await getDepartmentMembers(department.id, { limit: 100 });
      setMembers({ department, people: result.items });
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function confirmDelete() {
    try {
      await deleteDepartment(toDelete.id);
      setToDelete(null);
      showToast("Department deleted successfully.", "warning");
      await loadDepartments();
    } catch (error) { showToast(error.message, "warning"); }
  }

  const totalMembers = departments.reduce((sum, department) => sum + department.memberCount, 0);
  const averageAttendance = departments.length ? (departments.reduce((sum, department) => sum + department.attendanceRate, 0) / departments.length).toFixed(1) : 0;

  return (
    <div className="page-stack">
      <PageHeader title="Departments" description="Track department membership, schedules, attendance rates, late statistics, and overtime rules." actions={canManage && <button className="button button-primary" type="button" onClick={() => setEditing({ name: "", code: "", description: "", managerId: "", status: "Active", maxOvertimeHours: "", autoCheckoutEnabled: false })}><Plus size={17} /> Add Department</button>} />

      <div className="dashboard-grid two">
        <DepartmentBars data={departments} />
        <section className="info-card">
          <h2>Department Analytics</h2>
          <div className="quality-grid">
            <span><strong>{pagination.total}</strong>Departments</span>
            <span><strong>{totalMembers}</strong>Visible members</span>
            <span><strong>{averageAttendance}%</strong>Attendance</span>
          </div>
        </section>
      </div>

      <DataTable
        data={departments}
        pagination={pagination}
        onPageChange={setPage}
        actions={[
          { label: "View members", icon: <Users size={17} />, onClick: openMembers },
          { label: "Analytics", icon: <BarChart3 size={17} />, onClick: openAnalytics },
          ...(canManage ? [
            { label: "Edit", icon: <Edit3 size={17} />, onClick: setEditing },
            { label: "Delete", icon: <Trash2 size={17} />, onClick: setToDelete }
          ] : [])
        ]}
        columns={[
          { key: "name", label: "Department", sortable: true },
          { key: "memberCount", label: "Members" },
          { key: "attendanceRate", label: "Attendance", render: (row) => `${row.attendanceRate}%` },
          { key: "manager", label: "Manager" },
          { key: "assignedSchedule", label: "Schedule" },
          { key: "maxOvertimeHours", label: "Overtime limit", render: (row) => row.autoCheckoutEnabled ? `${row.maxOvertimeHours ?? 0}h` : "Disabled" },
          { key: "lateRate", label: "Late", render: (row) => `${row.lateRate}%` },
          { key: "absenceRate", label: "Absence", render: (row) => `${row.absenceRate}%` }
        ]}
      />

      <Modal open={Boolean(editing)} title={editing?.id ? "Edit Department" : "Add Department"} onClose={() => setEditing(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setEditing(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveDepartment} disabled={saving}>{saving ? "Saving..." : "Save department"}</button></>}>
        {editing && <div className="form-grid">
          <FormField label="Name" required><input value={editing.name || ""} onChange={(event) => setEditing({ ...editing, name: event.target.value })} /></FormField>
          {!editing.id && <FormField label="Code" required><input value={editing.code || ""} onChange={(event) => setEditing({ ...editing, code: event.target.value.toUpperCase() })} placeholder="DEP-FIN" /></FormField>}
          <FormField label="Manager user ID"><input type="number" value={editing.managerId || ""} onChange={(event) => setEditing({ ...editing, managerId: event.target.value ? Number(event.target.value) : null })} /></FormField>
          <FormField label="Status"><select value={editing.status || "Active"} onChange={(event) => setEditing({ ...editing, status: event.target.value })}><option>Active</option><option>Inactive</option></select></FormField>
          <FormField label="Maximum overtime (hours)"><input type="number" min="0" max="168" step="0.25" value={editing.maxOvertimeHours ?? ""} onChange={(event) => setEditing({ ...editing, maxOvertimeHours: event.target.value })} placeholder="2" /></FormField>
          <FormField label="Automatic checkout"><label className="check-row"><input type="checkbox" checked={Boolean(editing.autoCheckoutEnabled)} onChange={(event) => setEditing({ ...editing, autoCheckoutEnabled: event.target.checked })} /> Enable at overtime limit</label></FormField>
          <FormField label="Description" className="full-field"><textarea rows="3" value={editing.description || ""} onChange={(event) => setEditing({ ...editing, description: event.target.value })} /></FormField>
        </div>}
      </Modal>

      <Modal open={Boolean(analytics)} title={`${analytics?.name || "Department"} Analytics`} onClose={() => setAnalytics(null)}>
        {analytics && <div className="quality-grid"><span><strong>{analytics.attendanceRate}%</strong>Attendance rate</span><span><strong>{analytics.lateRate}%</strong>Late rate</span><span><strong>{analytics.absenceRate}%</strong>Absence rate</span><span><strong>{analytics.memberCount}</strong>Members</span><StatusBadge status={analytics.attendanceRate >= 90 ? "Healthy" : "Review"} /></div>}
      </Modal>

      <Modal open={Boolean(members)} title={`${members?.department.name || "Department"} Members`} onClose={() => setMembers(null)} size="lg">
        {members && <DataTable data={members.people} selectable={false} columns={[{ key: "name", label: "Name", sortable: true }, { key: "id", label: "ID", sortable: true }, { key: "role", label: "Role" }, { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> }]} />}
      </Modal>

      <ConfirmDialog open={Boolean(toDelete)} danger title="Delete department" message={`Delete ${toDelete?.name || "this department"}?`} confirmLabel="Delete" onClose={() => setToDelete(null)} onConfirm={confirmDelete} />
    </div>
  );
}
