import { useEffect, useState } from "react";
import { Edit3, Plus, Trash2, UserX } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import Modal from "../components/common/Modal.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import FormField from "../components/forms/FormField.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import { useToast } from "../context/ToastContext.jsx";
import { createUser, deleteUser, getUsers, setUserStatus, updateUser } from "../services/authService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";

const blankUser = { name: "", email: "", phone: "", title: "Employee", personId: "", status: "Active", twoFactor: "Disabled", password: "", confirmPassword: "" };

export default function UsersPage() {
  const [users, setUsers] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState(null);
  const [toDelete, setToDelete] = useState(null);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const { showToast } = useToast();
  const { hasPermission } = usePermissions();
  const canManage = hasPermission(PERMISSIONS.USERS_MANAGE);

  async function loadUsers() {
    try {
      const result = await getUsers({ page, limit: 20 });
      setUsers(result.items);
      setPagination(result.pagination);
    } catch (error) { showToast(error.message, "warning"); }
  }

  useEffect(() => { loadUsers(); }, [page]);

  async function saveUser() {
    if (!editing.name?.trim() || !editing.email?.trim()) return showToast("Name and email are required.", "warning");
    if (!editing.id && (!editing.password || editing.password !== editing.confirmPassword)) return showToast("Enter matching passwords for the new account.", "warning");
    setSaving(true);
    setErrors({});
    try {
      if (editing.id) {
        await updateUser(editing.id, editing);
        showToast("User updated successfully.");
      } else {
        await createUser(editing);
        showToast("User created successfully.");
      }
      setEditing(null);
      await loadUsers();
    } catch (error) {
      setErrors(error.errors || {});
      showToast(error.message, "warning");
    } finally { setSaving(false); }
  }

  async function toggleStatus(user) {
    const status = user.status === "Active" ? "Suspended" : "Active";
    try {
      await setUserStatus(user.id, status);
      showToast(`${user.name} is now ${status.toLowerCase()}.`, status === "Active" ? "success" : "warning");
      await loadUsers();
    } catch (error) { showToast(error.message, "warning"); }
  }

  async function confirmDelete() {
    try {
      await deleteUser(toDelete.id);
      setToDelete(null);
      showToast("User deleted successfully.", "warning");
      await loadUsers();
    } catch (error) { showToast(error.message, "warning"); }
  }

  const actions = canManage ? [
    { label: "Edit", icon: <Edit3 size={17} />, onClick: (row) => { setErrors({}); setEditing({ ...row, password: "", confirmPassword: "" }); } },
    { label: "Activate or suspend", icon: <UserX size={17} />, onClick: toggleStatus },
    { label: "Delete", icon: <Trash2 size={17} />, onClick: setToDelete }
  ] : [];

  return (
    <div className="page-stack">
      <PageHeader title="User Management" description="Manage employee and student dashboard accounts. Administrator access is managed separately." actions={canManage && <button className="button button-primary" type="button" onClick={() => { setErrors({}); setEditing({ ...blankUser }); }}><Plus size={17} /> Add User</button>} />
      <DataTable data={users} pagination={pagination} onPageChange={setPage} actions={actions} columns={[
        { key: "name", label: "User", sortable: true },
        { key: "email", label: "Email", sortable: true },
        { key: "title", label: "Account Type" },
        { key: "personId", label: "Person ID" },
        { key: "lastLogin", label: "Last Login" },
        { key: "twoFactor", label: "2FA", render: (row) => <StatusBadge status={row.twoFactor} /> },
        { key: "status", label: "Status", render: (row) => <StatusBadge status={row.status} /> }
      ]} />

      <Modal open={Boolean(editing)} title={editing?.id ? "Edit User" : "Add User"} onClose={() => setEditing(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setEditing(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveUser} disabled={saving}>{saving ? "Saving..." : "Save user"}</button></>}>
        {editing && <div className="form-grid">
          <FormField label="Name" required error={errors.name?.[0]}><input value={editing.name} onChange={(event) => setEditing({ ...editing, name: event.target.value })} /></FormField>
          <FormField label="Email" required error={errors.email?.[0]}><input type="email" value={editing.email} onChange={(event) => setEditing({ ...editing, email: event.target.value })} /></FormField>
          <FormField label="Phone" error={errors.phone?.[0]}><input value={editing.phone || ""} onChange={(event) => setEditing({ ...editing, phone: event.target.value })} /></FormField>
          <FormField label="Person ID" error={errors.personId?.[0]}><input value={editing.personId || ""} onChange={(event) => setEditing({ ...editing, personId: event.target.value })} placeholder="EMP-00001" /></FormField>
          <FormField label="Account type"><select value={editing.title} onChange={(event) => setEditing({ ...editing, title: event.target.value })}><option>Employee</option><option>Student</option><option>Supervisor</option></select></FormField>
          <FormField label="Two-factor"><select value={editing.twoFactor} onChange={(event) => setEditing({ ...editing, twoFactor: event.target.value })}><option>Enabled</option><option>Disabled</option></select></FormField>
          <FormField label={editing.id ? "New password" : "Password"} required={!editing.id} error={errors.password?.[0]}><input type="password" value={editing.password} onChange={(event) => setEditing({ ...editing, password: event.target.value })} /></FormField>
          <FormField label="Confirm password" required={!editing.id}><input type="password" value={editing.confirmPassword} onChange={(event) => setEditing({ ...editing, confirmPassword: event.target.value })} /></FormField>
        </div>}
      </Modal>

      <ConfirmDialog open={Boolean(toDelete)} danger title="Delete user" message={`Delete ${toDelete?.name || "this user"}? Historical attendance records will remain available.`} confirmLabel="Delete" onClose={() => setToDelete(null)} onConfirm={confirmDelete} />
    </div>
  );
}
