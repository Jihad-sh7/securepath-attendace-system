import { useEffect, useState } from "react";
import { Edit3, Plus, Trash2 } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import Modal from "../components/common/Modal.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import FormField from "../components/forms/FormField.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import { useToast } from "../context/ToastContext.jsx";
import { createSchedule, deleteSchedule, getSchedules, updateSchedule } from "../services/organizationService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";

const workingDays = [1, 2, 3, 4, 5].map((dayOfWeek) => ({ dayOfWeek, isWorkingDay: true }));

export default function SchedulesPage() {
  const [schedules, setSchedules] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState(null);
  const [toDelete, setToDelete] = useState(null);
  const [saving, setSaving] = useState(false);
  const { showToast } = useToast();
  const { hasPermission } = usePermissions();
  const canManage = hasPermission(PERMISSIONS.SCHEDULES_MANAGE);

  async function loadSchedules() {
    try {
      const result = await getSchedules({ page, limit: 20 });
      setSchedules(result.items);
      setPagination(result.pagination);
    } catch (error) { showToast(error.message, "warning"); }
  }

  useEffect(() => { loadSchedules(); }, [page]);

  async function saveSchedule() {
    if (!editing.name?.trim() || (!editing.id && !editing.code?.trim())) return showToast("Schedule name and code are required.", "warning");
    setSaving(true);
    try {
      if (editing.id) {
        await updateSchedule(editing.id, editing);
        showToast("Schedule updated successfully.");
      } else {
        await createSchedule(editing);
        showToast("Schedule created successfully.");
      }
      setEditing(null);
      await loadSchedules();
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  async function confirmDelete() {
    try {
      await deleteSchedule(toDelete.id);
      setToDelete(null);
      showToast("Schedule deleted successfully.", "warning");
      await loadSchedules();
    } catch (error) { showToast(error.message, "warning"); }
  }

  return (
    <div className="page-stack">
      <PageHeader title="Schedules" description="Manage weekly, shift, flexible, holiday, and department-assigned attendance schedules." actions={canManage && <button className="button button-primary" type="button" onClick={() => setEditing({ name: "", code: "", startTime: "08:30", endTime: "17:00", gracePeriod: 10, breakDurationMinutes: 45, breakWindow: "12:30-13:15", assignmentIds: [], type: "Fixed", status: "Active", days: workingDays })}><Plus size={17} /> Add Schedule</button>} />

      <DataTable
        data={schedules}
        pagination={pagination}
        onPageChange={setPage}
        actions={canManage ? [{ label: "Edit", icon: <Edit3 size={17} />, onClick: setEditing }, { label: "Delete", icon: <Trash2 size={17} />, onClick: setToDelete }] : []}
        columns={[
          { key: "name", label: "Schedule", sortable: true },
          { key: "type", label: "Type", render: (row) => <StatusBadge status={row.type} /> },
          { key: "startTime", label: "Start" },
          { key: "endTime", label: "End" },
          { key: "gracePeriod", label: "Grace", render: (row) => `${row.gracePeriod} min` },
          { key: "breakWindow", label: "Breaks" },
          { key: "assignedTo", label: "Assigned", render: (row) => row.assignedTo.join(", ") || "Unassigned" },
          { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> }
        ]}
      />

      <Modal open={Boolean(editing)} title={editing?.id ? "Edit Schedule" : "Add Schedule"} onClose={() => setEditing(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setEditing(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveSchedule} disabled={saving}>{saving ? "Saving..." : "Save schedule"}</button></>}>
        {editing && <div className="form-grid">
          <FormField label="Name" required><input value={editing.name || ""} onChange={(event) => setEditing({ ...editing, name: event.target.value })} /></FormField>
          {!editing.id && <FormField label="Code" required><input value={editing.code || ""} onChange={(event) => setEditing({ ...editing, code: event.target.value.toUpperCase() })} placeholder="SCH-004" /></FormField>}
          <FormField label="Start time"><input type="time" value={editing.startTime} onChange={(event) => setEditing({ ...editing, startTime: event.target.value })} /></FormField>
          <FormField label="End time"><input type="time" value={editing.endTime} onChange={(event) => setEditing({ ...editing, endTime: event.target.value })} /></FormField>
          <FormField label="Grace period"><input type="number" value={editing.gracePeriod} onChange={(event) => setEditing({ ...editing, gracePeriod: Number(event.target.value) })} /></FormField>
          <FormField label="Break duration"><input type="number" value={editing.breakDurationMinutes || 0} onChange={(event) => setEditing({ ...editing, breakDurationMinutes: Number(event.target.value) })} /></FormField>
          <FormField label="Break window"><input value={editing.breakWindow || ""} onChange={(event) => setEditing({ ...editing, breakWindow: event.target.value })} /></FormField>
          <FormField label="Type"><select value={editing.type} onChange={(event) => setEditing({ ...editing, type: event.target.value })}><option>Fixed</option><option>Shift</option><option>Flexible</option></select></FormField>
          <FormField label="Status"><select value={editing.status || "Active"} onChange={(event) => setEditing({ ...editing, status: event.target.value })}><option>Active</option><option>Inactive</option></select></FormField>
          <FormField label="Assignment IDs" className="full-field"><input value={editing.assignmentIds?.join(", ") || ""} placeholder="DEP-ENG, EMP-00001" onChange={(event) => setEditing({ ...editing, assignmentIds: event.target.value.split(",").map((item) => item.trim()).filter(Boolean) })} /></FormField>
        </div>}
      </Modal>

      <ConfirmDialog open={Boolean(toDelete)} title="Delete schedule" danger message={`Delete ${toDelete?.name || "this schedule"}? Existing attendance records keep their historical values.`} confirmLabel="Delete" onClose={() => setToDelete(null)} onConfirm={confirmDelete} />
    </div>
  );
}
