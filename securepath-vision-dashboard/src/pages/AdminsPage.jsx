import { useEffect, useState } from "react";
import { Edit3, Eye, KeyRound, Plus, Power, ShieldCheck, Trash2 } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import Modal from "../components/common/Modal.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import FormField from "../components/forms/FormField.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import { useToast } from "../context/ToastContext.jsx";
import {
  createAdmin,
  deleteAdmin,
  getAvailablePermissions,
  getAdmins,
  resetAdminPassword,
  setAdminStatus,
  updateAdmin,
  updateAdminPermissions
} from "../services/authService.js";
import {
  getRoleLabel,
  PERMISSION_GROUPS,
  PERMISSION_PRESETS,
  ROLES
} from "../auth/permissions.js";

const blankAdmin = {
  name: "",
  email: "",
  phone: "",
  password: "",
  confirmPassword: "",
  status: "Active",
  permissions: []
};

function PermissionEditor({ value, onChange, groups = PERMISSION_GROUPS }) {
  const availablePermissions = new Set(groups.flatMap((group) => group.permissions.map((permission) => permission.value)));

  function toggle(permission) {
    onChange(value.includes(permission) ? value.filter((item) => item !== permission) : [...value, permission]);
  }

  return (
    <div className="permission-editor">
      <div className="permission-toolbar">
        <select defaultValue="" aria-label="Permission preset" onChange={(event) => {
          if (event.target.value) onChange(PERMISSION_PRESETS[event.target.value].filter((permission) => availablePermissions.has(permission)));
        }}>
          <option value="">Choose a permission preset</option>
          {Object.keys(PERMISSION_PRESETS).map((preset) => <option key={preset}>{preset}</option>)}
        </select>
        <button className="button button-secondary" type="button" onClick={() => onChange(groups.flatMap((group) => group.permissions.map((permission) => permission.value)))}>Select All</button>
        <button className="button button-ghost" type="button" onClick={() => onChange([])}>Clear All</button>
      </div>
      <div className="permission-groups">
        {groups.map((group) => (
          <article key={group.id}>
            <h3>{group.label}</h3>
            {group.permissions.map((permission) => (
              <label className="checkbox-label" key={permission.value}>
                <input type="checkbox" checked={value.includes(permission.value)} onChange={() => toggle(permission.value)} />
                <span>{permission.label}</span>
              </label>
            ))}
          </article>
        ))}
      </div>
    </div>
  );
}

export default function AdminsPage() {
  const [admins, setAdmins] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [page, setPage] = useState(1);
  const [permissionGroups, setPermissionGroups] = useState(PERMISSION_GROUPS);
  const [editing, setEditing] = useState(null);
  const [permissionAdmin, setPermissionAdmin] = useState(null);
  const [viewing, setViewing] = useState(null);
  const [toDelete, setToDelete] = useState(null);
  const { showToast } = useToast();

  useEffect(() => {
    Promise.all([getAdmins({ page, limit: 20 }), getAvailablePermissions()]).then(([adminResult, permissionResult]) => {
      setAdmins(adminResult.items);
      setPagination(adminResult.pagination);
      const descriptions = Object.fromEntries((permissionResult.permissions || []).map((permission) => [permission.name, permission.description]));
      setPermissionGroups((permissionResult.groups || []).map((group) => ({
        id: group.group,
        label: group.group.replace(/([A-Z])/g, " $1").replace(/^./, (letter) => letter.toUpperCase()),
        permissions: group.permissions.map((permission) => ({ value: permission, label: descriptions[permission] || permission }))
      })));
    }).catch((error) => showToast(error.message, "warning"));
  }, [page]);

  async function saveAdmin() {
    if (!editing.name?.trim() || !editing.email?.trim()) return showToast("Name and email are required.", "warning");
    try {
      if (editing.id) {
        const updated = await updateAdmin(editing.id, {
          name: editing.name,
          email: editing.email,
          phone: editing.phone,
          status: editing.status
        });
        setAdmins((current) => current.map((admin) => (admin.id === updated.id ? updated : admin)));
        showToast("Admin updated successfully.");
      } else {
        if (editing.password.length < 12) return showToast("Temporary password must be at least 12 characters.", "warning");
        if (editing.password !== editing.confirmPassword) return showToast("Passwords do not match.", "warning");
        const created = await createAdmin(editing);
        setAdmins((current) => [...current, created]);
        showToast("Admin created successfully.");
      }
      setEditing(null);
    } catch (error) {
      showToast(error.message, "warning");
    }
  }

  async function savePermissions() {
    try {
      const updated = await updateAdminPermissions(permissionAdmin.id, permissionAdmin.permissions);
      setAdmins((current) => current.map((admin) => (admin.id === updated.id ? updated : admin)));
      setPermissionAdmin(null);
      showToast("Admin permissions saved.");
    } catch (error) {
      showToast(error.message, "warning");
    }
  }

  async function toggleAdmin(admin) {
    try {
      const status = admin.status === "Active" ? "Disabled" : "Active";
      const updated = await setAdminStatus(admin.id, status);
      setAdmins((current) => current.map((item) => (item.id === updated.id ? updated : item)));
      showToast(`${admin.name} is now ${status.toLowerCase()}.`, status === "Active" ? "success" : "warning");
    } catch (error) {
      showToast(error.message, "warning");
    }
  }

  async function confirmDelete() {
    try {
      await deleteAdmin(toDelete.id);
      setAdmins((current) => current.filter((admin) => admin.id !== toDelete.id));
      setToDelete(null);
      showToast("Admin deleted successfully.", "warning");
    } catch (error) {
      showToast(error.message, "warning");
    }
  }

  const protectedAction = (row) => row.role === ROLES.SUPER_ADMIN;
  const actions = [
    { label: "View", icon: <Eye size={17} />, onClick: setViewing },
    { label: "Edit", icon: <Edit3 size={17} />, onClick: setEditing, hidden: protectedAction },
    { label: "Manage permissions", icon: <ShieldCheck size={17} />, onClick: (row) => setPermissionAdmin({ ...row, permissions: [...row.permissions] }), hidden: protectedAction },
    { label: "Activate or deactivate", icon: <Power size={17} />, onClick: toggleAdmin, hidden: protectedAction },
    { label: "Reset password", icon: <KeyRound size={17} />, onClick: async (row) => showToast(`Temporary password: ${await resetAdminPassword(row.id)}`), hidden: protectedAction },
    { label: "Delete", icon: <Trash2 size={17} />, onClick: setToDelete, hidden: protectedAction }
  ];

  return (
    <div className="page-stack">
      <PageHeader
        title="Admin Management"
        description="Create administrators and assign exact module permissions. The Super Admin account is permanently protected."
        actions={<button className="button button-primary" type="button" onClick={() => setEditing({ ...blankAdmin })}><Plus size={17} /> Add Admin</button>}
      />
      <DataTable
        data={admins}
        pagination={pagination}
        onPageChange={setPage}
        selectable={false}
        actions={actions}
        columns={[
          { key: "name", label: "Admin", sortable: true },
          { key: "email", label: "Email", sortable: true },
          { key: "role", label: "Role", sortable: true, render: (row) => <span className={`role-badge ${row.role === ROLES.SUPER_ADMIN ? "super-admin" : ""}`}>{getRoleLabel(row.role)}</span> },
          { key: "status", label: "Status", sortable: true, render: (row) => <StatusBadge status={row.status} /> },
          { key: "createdAt", label: "Created", sortable: true },
          { key: "lastLogin", label: "Last Login", sortable: true },
          { key: "permissions", label: "Permissions", render: (row) => row.role === ROLES.SUPER_ADMIN ? "All" : row.permissions.length }
        ]}
      />

      <Modal
        open={Boolean(editing)}
        title={editing?.id ? "Edit Admin" : "Add Admin"}
        size="lg"
        onClose={() => setEditing(null)}
        footer={<><button className="button button-ghost" type="button" onClick={() => setEditing(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveAdmin}>Save Admin</button></>}
      >
        {editing && <div className="form-grid">
          <FormField label="Full Name" required><input value={editing.name} onChange={(event) => setEditing({ ...editing, name: event.target.value })} /></FormField>
          <FormField label="Email" required><input type="email" value={editing.email} onChange={(event) => setEditing({ ...editing, email: event.target.value })} /></FormField>
          <FormField label="Phone"><input value={editing.phone || ""} onChange={(event) => setEditing({ ...editing, phone: event.target.value })} /></FormField>
          <FormField label="Status"><select value={editing.status} onChange={(event) => setEditing({ ...editing, status: event.target.value })}><option>Active</option><option>Disabled</option></select></FormField>
          {!editing.id && <FormField label="Temporary Password" required><input type="password" value={editing.password} onChange={(event) => setEditing({ ...editing, password: event.target.value })} /></FormField>}
          {!editing.id && <FormField label="Confirm Password" required><input type="password" value={editing.confirmPassword} onChange={(event) => setEditing({ ...editing, confirmPassword: event.target.value })} /></FormField>}
          {!editing.id && <div className="full-field"><h2 className="form-section-title">Permissions</h2><PermissionEditor groups={permissionGroups} value={editing.permissions} onChange={(permissions) => setEditing({ ...editing, permissions })} /></div>}
        </div>}
      </Modal>

      <Modal
        open={Boolean(permissionAdmin)}
        title={`Manage Permissions - ${permissionAdmin?.name || "Admin"}`}
        size="lg"
        onClose={() => setPermissionAdmin(null)}
        footer={<><button className="button button-ghost" type="button" onClick={() => setPermissionAdmin(null)}>Cancel</button><button className="button button-primary" type="button" onClick={savePermissions}>Save Permissions</button></>}
      >
        {permissionAdmin && <PermissionEditor groups={permissionGroups} value={permissionAdmin.permissions} onChange={(permissions) => setPermissionAdmin({ ...permissionAdmin, permissions })} />}
      </Modal>

      <Modal open={Boolean(viewing)} title="Admin Details" onClose={() => setViewing(null)}>
        {viewing && <dl>
          <dt>Name</dt><dd>{viewing.name}</dd>
          <dt>Email</dt><dd>{viewing.email}</dd>
          <dt>Role</dt><dd>{getRoleLabel(viewing.role)}</dd>
          <dt>Status</dt><dd><StatusBadge status={viewing.status} /></dd>
          <dt>Created</dt><dd>{viewing.createdAt}</dd>
          <dt>Last login</dt><dd>{viewing.lastLogin}</dd>
          <dt>Permissions</dt><dd>{viewing.role === ROLES.SUPER_ADMIN ? "Permanent full access" : viewing.permissions.join(", ") || "None"}</dd>
        </dl>}
      </Modal>

      <ConfirmDialog
        open={Boolean(toDelete)}
        danger
        title="Delete Admin"
        message={`Delete ${toDelete?.name || "this admin"}? This removes dashboard access immediately.`}
        confirmLabel="Delete Admin"
        onClose={() => setToDelete(null)}
        onConfirm={confirmDelete}
      />
    </div>
  );
}
