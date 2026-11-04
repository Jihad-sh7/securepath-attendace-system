import { useState } from "react";
import { KeyRound, Pencil, ShieldCheck, UserCircle } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import Modal from "../components/common/Modal.jsx";
import FormField from "../components/forms/FormField.jsx";
import { useAuth } from "../context/AuthContext.jsx";
import { getRoleLabel, PERMISSION_GROUPS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";
import { changePassword, updateProfile } from "../services/authService.js";
import { useToast } from "../context/ToastContext.jsx";
import AuthenticatedImage from "../components/common/AuthenticatedImage.jsx";

export default function ProfilePage() {
  const { user, updateUser } = useAuth();
  const { isSuperAdmin } = usePermissions();
  const { showToast } = useToast();
  const [editing, setEditing] = useState(null);
  const [password, setPassword] = useState(null);
  const [saving, setSaving] = useState(false);
  const assigned = PERMISSION_GROUPS.flatMap((group) => group.permissions).filter((permission) => user?.permissions?.includes(permission.value));

  async function saveProfile() {
    setSaving(true);
    try {
      const updated = await updateProfile(editing);
      updateUser(updated);
      setEditing(null);
      showToast("Profile updated successfully.");
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  async function savePassword() {
    if (password.password !== password.confirmPassword) return showToast("Passwords do not match.", "warning");
    setSaving(true);
    try {
      await changePassword(password);
      setPassword(null);
      showToast("Password changed successfully.");
    } catch (error) { showToast(error.message, "warning"); }
    finally { setSaving(false); }
  }

  return (
    <div className="page-stack">
      <PageHeader title="My Profile" description="Account identity, role, status, and dashboard access." actions={<><button className="button button-secondary" type="button" onClick={() => setEditing({ name: user.name, email: user.email, phone: user.phone || "", avatar: null })}><Pencil size={17} /> Edit profile</button><button className="button button-secondary" type="button" onClick={() => setPassword({ currentPassword: "", password: "", confirmPassword: "" })}><KeyRound size={17} /> Change password</button></>} />
      <section className="profile-hero account-profile">
        <span className="profile-avatar large">{user?.avatarUrl ? <AuthenticatedImage src={user.avatarUrl} fallback={<UserCircle size={34} />} /> : <UserCircle size={34} />}</span>
        <div><h2>{user?.name}</h2><p>{user?.email}</p><div className="inline-list"><span className={`role-badge ${isSuperAdmin ? "super-admin" : ""}`}>{getRoleLabel(user?.role)}</span><StatusBadge status={user?.status || "Active"} /></div></div>
        {isSuperAdmin && <ShieldCheck className="super-admin-mark" size={34} aria-label="Super Admin account" />}
      </section>
      <section className="info-card"><h2>Dashboard Access</h2>{isSuperAdmin ? <p>Super Admin has permanent full access. Its permissions cannot be removed or edited.</p> : assigned.length ? <div className="permission-summary">{assigned.map((permission) => <span key={permission.value}>{permission.label}</span>)}</div> : <p>No administrative permissions are assigned to this account.</p>}</section>

      <Modal open={Boolean(editing)} title="Edit Profile" onClose={() => setEditing(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setEditing(null)}>Cancel</button><button className="button button-primary" type="button" onClick={saveProfile} disabled={saving}>{saving ? "Saving..." : "Save profile"}</button></>}>
        {editing && <div className="form-grid"><FormField label="Name"><input value={editing.name} onChange={(event) => setEditing({ ...editing, name: event.target.value })} /></FormField><FormField label="Email"><input type="email" value={editing.email} onChange={(event) => setEditing({ ...editing, email: event.target.value })} /></FormField><FormField label="Phone"><input value={editing.phone} onChange={(event) => setEditing({ ...editing, phone: event.target.value })} /></FormField><FormField label="Avatar"><input type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => setEditing({ ...editing, avatar: event.target.files?.[0] || null })} /></FormField></div>}
      </Modal>

      <Modal open={Boolean(password)} title="Change Password" onClose={() => setPassword(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setPassword(null)}>Cancel</button><button className="button button-primary" type="button" onClick={savePassword} disabled={saving}>{saving ? "Saving..." : "Change password"}</button></>}>
        {password && <div className="form-grid"><FormField label="Current password" required><input type="password" value={password.currentPassword} onChange={(event) => setPassword({ ...password, currentPassword: event.target.value })} /></FormField><FormField label="New password" required><input type="password" value={password.password} onChange={(event) => setPassword({ ...password, password: event.target.value })} /></FormField><FormField label="Confirm password" required><input type="password" value={password.confirmPassword} onChange={(event) => setPassword({ ...password, confirmPassword: event.target.value })} /></FormField></div>}
      </Modal>
    </div>
  );
}
