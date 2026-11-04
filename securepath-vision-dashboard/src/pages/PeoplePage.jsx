import { useEffect, useMemo, useRef, useState } from "react";
import { CheckCircle2, Download, Edit3, Eye, Grid2X2, List, LogIn, LogOut, Plus, RefreshCw, ScanFace, Trash2, Upload } from "lucide-react";
import { useNavigate } from "react-router-dom";
import PageHeader from "../components/common/PageHeader.jsx";
import SearchInput from "../components/common/SearchInput.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import PersonAvatar from "../components/common/PersonAvatar.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import Pagination from "../components/common/Pagination.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import Modal from "../components/common/Modal.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import FormField from "../components/forms/FormField.jsx";
import ManualAttendanceModal from "../components/attendance/ManualAttendanceModal.jsx";
import FaceEnrollmentCamera from "../components/biometrics/FaceEnrollmentCamera.jsx";
import { useToast } from "../context/ToastContext.jsx";
import {
  createPerson,
  deletePerson as removePerson,
  exportPeople,
  getPeople,
  importPeople,
  retryBiometricEnrollment,
  updatePerson
} from "../services/peopleService.js";
import { getDepartments } from "../services/organizationService.js";
import { getRoles } from "../services/payrollService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";
import { useDebouncedValue } from "../hooks/useDebouncedValue.js";

const blankPerson = {
  name: "",
  email: "",
  phone: "",
  departmentId: "",
  department: "Unassigned",
  roleId: "",
  type: "employee",
  role: "",
  status: "Active",
  faceStatus: "Pending",
  consent: "Pending"
};

function PersonForm({ value, onChange, departments, roles, canManageBiometrics, errors }) {
  return (
    <div className="form-grid">
      <FormField label="Full name" required error={errors.fullName?.[0]}>
        <input value={value.name} onChange={(event) => onChange("name", event.target.value)} />
      </FormField>
      <FormField label="Email" required error={errors.email?.[0]}>
        <input type="email" value={value.email || ""} onChange={(event) => onChange("email", event.target.value)} />
      </FormField>
      <FormField label="Phone" error={errors.phone?.[0]}>
        <input value={value.phone || ""} onChange={(event) => onChange("phone", event.target.value)} />
      </FormField>
      <FormField label="Department" error={errors.departmentId?.[0]}>
        <select value={value.departmentId || ""} onChange={(event) => onChange("departmentId", event.target.value)}>
          <option value="">Unassigned</option>
          {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
        </select>
      </FormField>
      <FormField label="Person type" error={errors.type?.[0]}>
        <select value={value.type || "employee"} onChange={(event) => onChange("type", event.target.value)}>
          <option value="employee">Employee</option>
          <option value="student">Student</option>
          <option value="staff">Staff</option>
          <option value="contractor">Contractor</option>
        </select>
      </FormField>
      <FormField label="Role" error={errors.roleId?.[0]}>
        <select value={value.roleId || ""} onChange={(event) => onChange("roleId", event.target.value)}>
          <option value="">Unassigned</option>
          {roles.map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
        </select>
      </FormField>
      <FormField label="Account status" error={errors.status?.[0]}>
        <select value={value.status || "Active"} onChange={(event) => onChange("status", event.target.value)}>
          <option>Active</option><option>Inactive</option><option>Suspended</option>
        </select>
      </FormField>
      {canManageBiometrics && <FormField label="Face registration">
        <select value={value.faceStatus || "Pending"} disabled><option>Registered</option><option>Pending</option><option>Revoked</option></select>
      </FormField>}
      {canManageBiometrics && <FormField label="Biometric consent" error={errors.biometricConsent?.[0]}>
        <select value={value.consent || "Pending"} onChange={(event) => onChange("consent", event.target.value)}>
          <option>Granted</option><option>Pending</option><option>Revoked</option>
        </select>
      </FormField>}
    </div>
  );
}

export default function PeoplePage() {
  const [people, setPeople] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [roles, setRoles] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, limit: 20, total: 0, totalPages: 1 });
  const [query, setQuery] = useState("");
  const [department, setDepartment] = useState("All");
  const [status, setStatus] = useState("All");
  const [faceStatus, setFaceStatus] = useState("All");
  const [sort, setSort] = useState({ sortBy: "name", sortOrder: "asc" });
  const [page, setPage] = useState(1);
  const [viewMode, setViewMode] = useState("table");
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(blankPerson);
  const [formErrors, setFormErrors] = useState({});
  const [toDelete, setToDelete] = useState(null);
  const [manualPerson, setManualPerson] = useState(null);
  const [manualAction, setManualAction] = useState(null);
  const [enrollmentPerson, setEnrollmentPerson] = useState(null);
  const [enrollmentMode, setEnrollmentMode] = useState("enroll");
  const [createdPerson, setCreatedPerson] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [retryingPersonId, setRetryingPersonId] = useState(null);
  const fileInput = useRef(null);
  const debouncedQuery = useDebouncedValue(query);
  const { showToast } = useToast();
  const navigate = useNavigate();
  const { hasPermission } = usePermissions();
  const canCreate = hasPermission(PERMISSIONS.PEOPLE_CREATE);
  const canEdit = hasPermission(PERMISSIONS.PEOPLE_EDIT);
  const canDelete = hasPermission(PERMISSIONS.PEOPLE_DELETE);
  const canImport = hasPermission(PERMISSIONS.PEOPLE_IMPORT);
  const canExport = hasPermission(PERMISSIONS.PEOPLE_EXPORT);
  const canViewDepartments = hasPermission(PERMISSIONS.DEPARTMENTS_VIEW);
  const canViewBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_VIEW);
  const canManageBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_MANAGE);
  const canEnrollBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_ENROLL);
  const canReenrollBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_REENROLL);
  const canManualCheckIn = hasPermission(PERMISSIONS.ATTENDANCE_MANUAL_CHECKIN);
  const canManualCheckOut = hasPermission(PERMISSIONS.ATTENDANCE_MANUAL_CHECKOUT);

  const filters = useMemo(() => {
    const attendanceStatuses = ["Present", "Late", "Absent", "On Leave"];
    return {
      page,
      limit: 20,
      search: debouncedQuery,
      departmentId: department,
      status: attendanceStatuses.includes(status) ? undefined : status,
      attendanceStatus: attendanceStatuses.includes(status) ? status : undefined,
      faceRegistered: faceStatus === "Enrolled" ? true : faceStatus === "Not Enrolled" ? false : undefined,
      biometricStatus: ["Pending", "Processing", "Failed"].includes(faceStatus) ? faceStatus.toLowerCase() : undefined,
      ...sort
    };
  }, [page, debouncedQuery, department, status, faceStatus, sort]);

  async function loadPeople() {
    setLoading(true);
    try {
      const result = await getPeople(filters);
      setPeople(result.items);
      setPagination(result.pagination);
      if (!canViewDepartments) {
        setDepartments(Array.from(new Map(result.items
          .filter((person) => person.departmentId)
          .map((person) => [person.departmentId, { id: person.departmentId, name: person.department }])).values()));
      }
    } catch (error) {
      setPeople([]);
      showToast(error.message, "warning");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { loadPeople(); }, [filters]);

  useEffect(() => {
    if (canViewDepartments) {
      getDepartments({ limit: 100 }).then((result) => setDepartments(result.items)).catch((error) => showToast(error.message, "warning"));
    }
  }, [canViewDepartments]);

  useEffect(() => {
    getRoles({ limit: 100 }).then((result) => setRoles(result.items)).catch(() => setRoles([]));
  }, []);

  function openAdd() {
    setDraft({ ...blankPerson, departmentId: departments[0]?.id || "", department: departments[0]?.name || "Unassigned" });
    setFormErrors({});
    setModal("add");
  }

  function openEdit(person) {
    setDraft({ ...person });
    setFormErrors({});
    setModal("edit");
  }

  async function savePerson() {
    if (!draft.name.trim() || !draft.email.trim()) {
      showToast("Name and email are required.", "warning");
      return;
    }
    setSaving(true);
    setFormErrors({});
    try {
      if (modal === "add") {
        const created = await createPerson(draft);
        setPage(1);
        showToast("Person created successfully.");
        if (canEnrollBiometrics) setCreatedPerson(created);
      } else {
        await updatePerson(draft.id, draft);
        showToast("Person updated successfully.");
      }
      setModal(null);
      await loadPeople();
    } catch (error) {
      setFormErrors(error.errors || {});
      showToast(error.message, "warning");
    } finally {
      setSaving(false);
    }
  }

  async function deletePerson() {
    try {
      await removePerson(toDelete.id);
      setToDelete(null);
      showToast("Person deactivated successfully.", "warning");
      await loadPeople();
    } catch (error) {
      showToast(error.message, "warning");
    }
  }

  async function handleImport(event) {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    try {
      const summary = await importPeople(file);
      showToast(`Import complete: ${summary.created} created, ${summary.updated} updated, ${summary.failed} failed.`, summary.failed ? "warning" : "success");
      await loadPeople();
    } catch (error) {
      showToast(error.message, "warning");
    }
  }

  async function retryEnrollment(person) {
    setRetryingPersonId(person.id);
    try {
      const result = await retryBiometricEnrollment(person.id);
      if (result.status !== "enrolled") throw new Error("Biometric processing did not complete.");
      showToast("Face enrollment completed successfully.", "success");
      await loadPeople();
    } catch (error) {
      showToast(error.message, "warning");
    } finally {
      setRetryingPersonId(null);
    }
  }

  const actions = [
    { label: "View", icon: <Eye size={17} />, onClick: (row) => navigate(`/people/${row.id}`) },
    ...(canManualCheckIn ? [{ label: "Check in", icon: <LogIn size={17} />, hidden: (row) => row.status !== "Active" || row.isCheckedIn || row.attendanceState === "checked_out", onClick: (row) => { setManualPerson(row); setManualAction("check_in"); } }] : []),
    ...(canManualCheckOut ? [{ label: "Check out", icon: <LogOut size={17} />, hidden: (row) => !row.isCheckedIn, onClick: (row) => { setManualPerson(row); setManualAction("check_out"); } }] : []),
    ...((canEnrollBiometrics || canReenrollBiometrics) ? [{ label: "Retry enrollment", icon: <RefreshCw size={17} />, hidden: (row) => !["Pending", "Failed"].includes(row.faceStatus) || row.status !== "Active", onClick: retryEnrollment }] : []),
    ...(canEnrollBiometrics ? [{ label: "Enroll face", icon: <ScanFace size={17} />, hidden: (row) => row.biometricReady || ["Pending", "Failed"].includes(row.faceStatus) || row.status !== "Active", onClick: (row) => { setEnrollmentPerson(row); setEnrollmentMode("enroll"); } }] : []),
    ...(canReenrollBiometrics ? [{ label: "Re-enroll face", icon: <ScanFace size={17} />, hidden: (row) => !row.biometricReady || row.status !== "Active", onClick: (row) => { setEnrollmentPerson(row); setEnrollmentMode("reenroll"); } }] : []),
    ...(canEdit ? [{ label: "Edit", icon: <Edit3 size={17} />, onClick: openEdit }] : []),
    ...(canDelete ? [{ label: "Delete", icon: <Trash2 size={17} />, onClick: setToDelete }] : [])
  ];

  return (
    <div className="page-stack">
      <PageHeader
        title="People Directory"
        description="Manage employees, students, visitors, departments, and account status."
        actions={<>
          {canExport && <button className="button button-secondary" type="button" onClick={() => exportPeople(filters).catch((error) => showToast(error.message, "warning"))}><Download size={17} /> Export</button>}
          {canImport && <><input ref={fileInput} type="file" accept=".csv,text/csv" hidden onChange={handleImport} /><button className="button button-secondary" type="button" onClick={() => fileInput.current?.click()}><Upload size={17} /> Import CSV</button></>}
          {canCreate && <button className="button button-primary" type="button" onClick={openAdd}><Plus size={17} /> Add Person</button>}
        </>}
      />

      <section className="filter-panel">
        <SearchInput value={query} onChange={(value) => { setQuery(value); setPage(1); }} placeholder="Search by name, ID, or email..." />
        <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}>
          <option>All</option><option>Active</option><option>Present</option><option>Late</option><option>Absent</option><option>On Leave</option><option>Suspended</option>
        </select>
        <select value={department} onChange={(event) => { setDepartment(event.target.value); setPage(1); }}>
          <option value="All">All</option>
          {departments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
        </select>
        {canViewBiometrics && <select value={faceStatus} onChange={(event) => { setFaceStatus(event.target.value); setPage(1); }}><option>All</option><option>Enrolled</option><option>Not Enrolled</option><option>Pending</option><option>Processing</option><option>Failed</option></select>}
        <div className="segmented">
          <button type="button" className={viewMode === "table" ? "active" : ""} onClick={() => setViewMode("table")} aria-label="Table view"><List size={17} /></button>
          <button type="button" className={viewMode === "cards" ? "active" : ""} onClick={() => setViewMode("cards")} aria-label="Card view"><Grid2X2 size={17} /></button>
        </div>
        <button className="link-button" type="button" onClick={() => { setQuery(""); setStatus("All"); setDepartment("All"); setFaceStatus("All"); setPage(1); }}>Clear Filters</button>
      </section>

      {loading ? <LoadingSkeleton rows={8} /> : viewMode === "table" ? (
        <DataTable
          data={people}
          pagination={pagination}
          onPageChange={setPage}
          onSortChange={({ key, direction }) => { setSort({ sortBy: key, sortOrder: direction }); setPage(1); }}
          actions={actions}
          columns={[
            { key: "name", label: "Name", sortable: true, render: (person) => <span className="person-cell"><PersonAvatar name={person.name} src={person.avatarUrl} status={person.attendanceStatus} /><span><strong>{person.name}</strong><small>{person.email}</small></span></span> },
            { key: "id", label: "ID", sortable: true },
            { key: "department", label: "Dept & Role", sortable: true, render: (person) => <span className="stacked-cell"><strong>{person.department}</strong><small>{person.role}</small></span> },
            ...(canViewBiometrics ? [{ key: "faceStatus", label: "Face Profile", render: (person) => <StatusBadge status={person.faceStatus} /> }] : []),
            { key: "attendanceStatus", label: "Attendance Today", render: (person) => <StatusBadge status={`${person.attendanceStatus}${person.checkIn ? ` (${person.checkIn})` : ""}`} /> },
            ...(canViewBiometrics ? [{ key: "consent", label: "Consent", render: (person) => <StatusBadge status={person.consent} /> }] : [])
          ]}
        />
      ) : (
        <><div className="people-cards">{people.map((person) => (
          <article className="person-card" key={person.id}>
            <PersonAvatar name={person.name} src={person.avatarUrl} size="lg" status={person.attendanceStatus} />
            <div><h3>{person.name}</h3><p>{person.id} · {person.department}</p><div className="inline-list">{canViewBiometrics && <StatusBadge status={person.faceStatus} />}<StatusBadge status={person.attendanceStatus} /></div></div>
            <div className="button-row">
              <button className="button button-secondary" type="button" onClick={() => navigate(`/people/${person.id}`)}>View</button>
              {canManualCheckIn && person.status === "Active" && !person.isCheckedIn && person.attendanceState !== "checked_out" && <button className="button button-secondary" type="button" onClick={() => { setManualPerson(person); setManualAction("check_in"); }}><LogIn size={17} /> Check In</button>}
              {canManualCheckOut && person.isCheckedIn && <button className="button button-primary" type="button" onClick={() => { setManualPerson(person); setManualAction("check_out"); }}><LogOut size={17} /> Check Out</button>}
              {(canEnrollBiometrics || canReenrollBiometrics) && ["Pending", "Failed"].includes(person.faceStatus) && person.status === "Active" && <button className="button button-secondary" type="button" disabled={retryingPersonId === person.id} onClick={() => retryEnrollment(person)}><RefreshCw size={17} /> {retryingPersonId === person.id ? "Retrying..." : "Retry Enrollment"}</button>}
              {canEnrollBiometrics && !person.biometricReady && !["Pending", "Failed"].includes(person.faceStatus) && person.status === "Active" && <button className="button button-secondary" type="button" onClick={() => { setEnrollmentPerson(person); setEnrollmentMode("enroll"); }}><ScanFace size={17} /> Enroll Face</button>}
              {canReenrollBiometrics && person.biometricReady && person.status === "Active" && <button className="button button-secondary" type="button" onClick={() => { setEnrollmentPerson(person); setEnrollmentMode("reenroll"); }}><ScanFace size={17} /> Re-Enroll</button>}
              {canEdit && <button className="button button-secondary" type="button" onClick={() => openEdit(person)}>Edit</button>}
            </div>
          </article>
        ))}</div><Pagination page={pagination.page} totalPages={pagination.totalPages} onPageChange={setPage} /></>
      )}

      <Modal open={modal === "add" || modal === "edit"} title={modal === "add" ? "Add Person" : "Edit Person"} onClose={() => setModal(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setModal(null)}>Cancel</button><button className="button button-primary" type="button" onClick={savePerson} disabled={saving}>{saving ? "Saving..." : modal === "add" ? "Add person" : "Save changes"}</button></>}>
        <PersonForm value={draft} departments={departments} roles={roles} canManageBiometrics={canManageBiometrics} errors={formErrors} onChange={(field, value) => setDraft((current) => ({ ...current, [field]: value }))} />
      </Modal>

      <Modal open={Boolean(createdPerson)} title="Employee created" size="sm" onClose={() => setCreatedPerson(null)} footer={<>
        <button className="button button-ghost" type="button" onClick={() => setCreatedPerson(null)}>Do it later</button>
        <button className="button button-primary" type="button" onClick={() => { setEnrollmentPerson(createdPerson); setEnrollmentMode("enroll"); setCreatedPerson(null); }}><ScanFace size={17} /> Enroll face now</button>
      </>}>
        <div className="enrollment-created"><CheckCircle2 size={32} /><div><strong>{createdPerson?.name} was created successfully.</strong><p>Face enrollment is not complete. The employee remains active but cannot use biometric attendance until enrollment is processed.</p></div></div>
      </Modal>

      <ConfirmDialog open={Boolean(toDelete)} title="Deactivate person" danger message={`Deactivate ${toDelete?.name || "this person"}? Their attendance history will remain available.`} confirmLabel="Deactivate" onClose={() => setToDelete(null)} onConfirm={deletePerson} />
      <ManualAttendanceModal
        open={Boolean(manualPerson)}
        initialPerson={manualPerson}
        initialAction={manualAction}
        canCheckIn={canManualCheckIn}
        canCheckOut={canManualCheckOut}
        onClose={() => { setManualPerson(null); setManualAction(null); }}
        onSuccess={loadPeople}
      />
      <FaceEnrollmentCamera
        open={Boolean(enrollmentPerson)}
        person={enrollmentPerson}
        mode={enrollmentMode}
        onClose={() => setEnrollmentPerson(null)}
        onSuccess={async (result) => {
          setEnrollmentPerson(null);
          showToast(result.status === "enrolled" ? "Face enrollment completed successfully." : "Face photos uploaded. Biometric processing is in progress.", result.status === "enrolled" ? "success" : "warning");
          await loadPeople();
        }}
      />
    </div>
  );
}
