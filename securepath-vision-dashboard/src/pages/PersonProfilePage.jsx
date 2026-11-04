import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { Edit3, LogIn, LogOut, RefreshCw, ScanFace, ShieldAlert, Trash2 } from "lucide-react";
import PageHeader from "../components/common/PageHeader.jsx";
import PersonAvatar from "../components/common/PersonAvatar.jsx";
import StatusBadge from "../components/common/StatusBadge.jsx";
import Tabs from "../components/common/Tabs.jsx";
import DataTable from "../components/tables/DataTable.jsx";
import ConfirmDialog from "../components/common/ConfirmDialog.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import ManualAttendanceModal from "../components/attendance/ManualAttendanceModal.jsx";
import FaceEnrollmentCamera from "../components/biometrics/FaceEnrollmentCamera.jsx";
import { useToast } from "../context/ToastContext.jsx";
import {
  deleteBiometricData,
  getBiometricStatus,
  getPersonProfile,
  retryBiometricEnrollment,
  setBiometricConsent
} from "../services/peopleService.js";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";

const tabs = [
  { id: "summary", label: "Summary" },
  { id: "attendance", label: "Attendance History" },
  { id: "face", label: "Face Data" },
  { id: "schedule", label: "Schedule" },
  { id: "activity", label: "Activity Log" }
];

export default function PersonProfilePage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { showToast } = useToast();
  const [profile, setProfile] = useState(null);
  const [activeTab, setActiveTab] = useState("summary");
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [loadError, setLoadError] = useState("");
  const [manualAction, setManualAction] = useState(null);
  const [enrollmentMode, setEnrollmentMode] = useState(null);
  const [biometricStatus, setBiometricStatus] = useState(null);
  const [retryingEnrollment, setRetryingEnrollment] = useState(false);
  const { hasPermission } = usePermissions();
  const canEdit = hasPermission(PERMISSIONS.PEOPLE_EDIT);
  const canViewBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_VIEW);
  const canManageBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_MANAGE);
  const canEnrollBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_ENROLL);
  const canReenrollBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_REENROLL);
  const canDeleteBiometrics = hasPermission(PERMISSIONS.BIOMETRICS_DELETE);
  const canViewAttendance = hasPermission(PERMISSIONS.ATTENDANCE_VIEW);
  const canViewSchedules = hasPermission(PERMISSIONS.SCHEDULES_VIEW);
  const canViewAudit = hasPermission(PERMISSIONS.AUDIT_LOGS_VIEW);
  const canManualCheckIn = hasPermission(PERMISSIONS.ATTENDANCE_MANUAL_CHECKIN);
  const canManualCheckOut = hasPermission(PERMISSIONS.ATTENDANCE_MANUAL_CHECKOUT);

  function loadProfile() {
    setProfile(null);
    setLoadError("");
    const biometricRequest = canViewBiometrics ? getBiometricStatus(id).catch(() => null) : Promise.resolve(null);
    return Promise.all([getPersonProfile(id, { includeAudit: canViewAudit }), biometricRequest])
      .then(([nextProfile, nextBiometricStatus]) => { setProfile(nextProfile); setBiometricStatus(nextBiometricStatus); })
      .catch((error) => setLoadError(error.message));
  }

  useEffect(() => {
    loadProfile();
  }, [id, canViewAudit]);

  if (!profile && !loadError) return <LoadingSkeleton rows={8} />;
  if (loadError || !profile?.person) {
    return (
      <PageHeader
        title="Person not found"
        description={loadError || "The requested profile does not exist."}
        actions={<button className="button button-secondary" onClick={() => navigate("/people")}>Back to people</button>}
      />
    );
  }

  const { person, attendance, activity, audit } = profile;
  const schedule = profile.currentSchedule;
  const currentAttendance = profile.currentAttendance;
  const isCheckedIn = currentAttendance?.status === "checked_in";
  const isClosedToday = currentAttendance?.status === "checked_out";
  const visibleTabs = tabs.filter((tab) => {
    if (tab.id === "face") return canViewBiometrics;
    if (tab.id === "attendance") return canViewAttendance;
    if (tab.id === "schedule") return canViewSchedules;
    if (tab.id === "activity") return canViewAttendance || canViewBiometrics || canViewAudit;
    return true;
  });

  async function retryEnrollment() {
    setRetryingEnrollment(true);
    try {
      const result = await retryBiometricEnrollment(person.id);
      if (result.status !== "enrolled") throw new Error("Biometric processing did not complete.");
      showToast("Face enrollment completed successfully.", "success");
      await loadProfile();
    } catch (error) {
      showToast(error.message, "warning");
    } finally {
      setRetryingEnrollment(false);
    }
  }

  return (
    <div className="page-stack">
      <PageHeader
        title={person.name}
        description={`${person.id} · ${person.department} · ${person.role}`}
        meta={<StatusBadge status={person.status} />}
        actions={
          <>
            {canEdit && <button className="button button-secondary" type="button" onClick={() => navigate("/people")}>
              <Edit3 size={17} /> Edit
            </button>}
            {canManualCheckIn && person.status === "Active" && !isCheckedIn && !isClosedToday && <button className="button button-primary" type="button" onClick={() => setManualAction("check_in")}><LogIn size={17} /> Check In</button>}
            {canManualCheckOut && isCheckedIn && <button className="button button-primary" type="button" onClick={() => setManualAction("check_out")}><LogOut size={17} /> Check Out</button>}
          </>
        }
      />

      <section className="profile-hero">
        <PersonAvatar name={person.name} src={person.avatarUrl} size="xl" status={person.attendanceStatus} />
        <div>
          <h2>{person.name}</h2>
          <p>{person.email} · {person.phone}</p>
          <div className="inline-list">
            <StatusBadge status={isCheckedIn ? "Checked In" : isClosedToday ? "Checked Out" : person.attendanceStatus} />
            {canViewBiometrics && <StatusBadge status={person.faceStatus} />}
            {canViewBiometrics && <StatusBadge status={`Consent: ${person.consent}`} tone={person.consent === "Granted" ? "success" : "warning"} />}
          </div>
        </div>
        <div className="summary-metrics">
          <span><strong>{person.monthlyAttendance}%</strong>Monthly attendance</span>
          <span><strong>{person.lateCount}</strong>Late arrivals</span>
          <span><strong>{person.absenceCount}</strong>Absences</span>
        </div>
      </section>

      <Tabs tabs={visibleTabs} active={activeTab} onChange={setActiveTab} />

      {activeTab === "summary" && (
        <section className="detail-grid">
          <article className="info-card">
            <h2>Personal Information</h2>
            <dl>
              <dt>ID</dt><dd>{person.id}</dd>
              <dt>Department</dt><dd>{person.department}</dd>
              <dt>Role</dt><dd>{person.role}</dd>
              <dt>Contact</dt><dd>{person.email}</dd>
              <dt>Account Status</dt><dd><StatusBadge status={person.status} /></dd>
            </dl>
          </article>
          <article className="info-card">
            <h2>Attendance Summary</h2>
            <dl>
              <dt>Current Status</dt><dd><StatusBadge status={isCheckedIn ? "Checked In" : isClosedToday ? "Checked Out" : "Not Checked In"} /></dd>
              <dt>Check-in</dt><dd>{person.checkIn || "Not recorded"}</dd>
              <dt>Check-out</dt><dd>{person.checkOut || "Pending"}</dd>
              <dt>Worked</dt><dd>{currentAttendance ? `${Math.floor(currentAttendance.workedMinutes / 60)}h ${String(currentAttendance.workedMinutes % 60).padStart(2, "0")}m` : "0h 00m"}</dd>
              <dt>Schedule</dt><dd>{schedule ? `${schedule.startTime} - ${schedule.endTime}` : "Not assigned"}</dd>
              <dt>Overtime Allowance</dt><dd>{currentAttendance?.maxOvertimeMinutes == null ? "No department limit" : `${currentAttendance.maxOvertimeMinutes} minutes`}</dd>
              <dt>Last Recognition</dt><dd>{person.lastRecognition}</dd>
            </dl>
          </article>
        </section>
      )}

      {activeTab === "attendance" && (
        <DataTable
          data={attendance}
          selectable={false}
          columns={[
            { key: "date", label: "Date", sortable: true },
            { key: "checkIn", label: "Check-in", sortable: true },
            { key: "checkOut", label: "Check-out", sortable: true },
            { key: "duration", label: "Duration", sortable: true },
            { key: "method", label: "Verification", sortable: true },
            { key: "status", label: "Status", sortable: true, render: (row) => <StatusBadge status={row.status} /> }
          ]}
        />
      )}

      {activeTab === "face" && (
        <section className="privacy-card">
          <ShieldAlert size={24} />
          <div>
            <h2>Face Data and Consent</h2>
            <p>Biometric status and consent are managed by the secure backend. Raw facial embeddings are never returned to this dashboard.</p>
            <dl>
              <dt>Registration status</dt><dd>{person.faceStatus}</dd>
              <dt>Enrollment date</dt><dd>{biometricStatus?.enrolledAt ? new Date(biometricStatus.enrolledAt).toLocaleString() : "Not enrolled"}</dd>
              <dt>Model version</dt><dd>{biometricStatus?.modelVersion || "Pending AI processing"}</dd>
              <dt>Samples</dt><dd>{biometricStatus?.sampleCount ?? 0}</dd>
              <dt>Consent</dt><dd>{person.consent}</dd>
              <dt>Retention policy</dt><dd>24 months after account deactivation, configurable in Settings.</dd>
              <dt>Manual review</dt><dd>Required for confidence below 70%.</dd>
            </dl>
            {(canManageBiometrics || canEnrollBiometrics || canReenrollBiometrics || canDeleteBiometrics) && <div className="button-row">
              {canManageBiometrics && <button className="button button-secondary" type="button" onClick={async () => {
                try {
                  const consent = await setBiometricConsent(person.id, person.consent !== "Granted");
                  setProfile((current) => ({ ...current, person: { ...current.person, consent: consent.granted ? "Granted" : "Revoked" } }));
                  showToast(`Biometric consent ${consent.granted ? "granted" : "revoked"}.`);
                } catch (error) { showToast(error.message, "warning"); }
              }}>{person.consent === "Granted" ? "Revoke consent" : "Grant consent"}</button>}
              {(canEnrollBiometrics || canReenrollBiometrics) && ["pending", "failed"].includes(biometricStatus?.status) && person.status === "Active" && <button className="button button-primary" type="button" disabled={retryingEnrollment} onClick={retryEnrollment}><RefreshCw size={17} /> {retryingEnrollment ? "Retrying..." : "Retry Enrollment"}</button>}
              {canEnrollBiometrics && !biometricStatus?.biometricReady && !["pending", "failed"].includes(biometricStatus?.status) && person.status === "Active" && <button className="button button-primary" type="button" onClick={() => setEnrollmentMode("enroll")}><ScanFace size={17} /> Enroll Face</button>}
              {canReenrollBiometrics && biometricStatus?.biometricReady && person.status === "Active" && <button className="button button-secondary" type="button" onClick={() => setEnrollmentMode("reenroll")}><ScanFace size={17} /> Re-Enroll Face</button>}
              {canDeleteBiometrics && biometricStatus?.biometricReady && <button className="button button-danger" type="button" onClick={() => setConfirmOpen(true)}><Trash2 size={17} /> Revoke biometric data</button>}
            </div>}
          </div>
        </section>
      )}

      {activeTab === "schedule" && schedule && (
        <section className="info-card">
          <h2>{schedule.name}</h2>
          <dl>
            <dt>Type</dt><dd>{schedule.type}</dd>
            <dt>Start</dt><dd>{schedule.startTime}</dd>
            <dt>End</dt><dd>{schedule.endTime}</dd>
            <dt>Grace Period</dt><dd>{schedule.gracePeriod} minutes</dd>
            <dt>Working Days</dt><dd>{schedule.days.filter((day) => day.isWorkingDay).map((day) => ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"][day.dayOfWeek - 1]).join(", ")}</dd>
            <dt>Breaks</dt><dd>{schedule.breakWindow}</dd>
          </dl>
        </section>
      )}

      {activeTab === "activity" && (
        <DataTable
          data={[...activity, ...(canViewAudit ? audit : [])]}
          keyField="id"
          selectable={false}
          columns={[
            { key: "timestamp", label: "Time", sortable: true, render: (row) => row.timestamp || row.time },
            { key: "event", label: "Action", sortable: true, render: (row) => row.event || row.action },
            { key: "cameraId", label: "Resource", sortable: true, render: (row) => row.cameraId || row.resource },
            { key: "status", label: "Result", sortable: true, render: (row) => <StatusBadge status={row.status || row.result} /> }
          ]}
        />
      )}

      <ConfirmDialog
        open={confirmOpen}
        danger
        title="Revoke biometric data"
        message="Revoke this person's biometric template and delete all private enrollment photos? Their employee account and audit history will remain."
        confirmLabel="Revoke data"
        onClose={() => setConfirmOpen(false)}
        onConfirm={async () => {
          try {
            await deleteBiometricData(person.id);
            setProfile((current) => ({ ...current, person: { ...current.person, faceStatus: "Revoked" } }));
            setConfirmOpen(false);
            setBiometricStatus((current) => ({ ...current, status: "revoked", biometricReady: false, sampleCount: 0, modelVersion: null, enrolledAt: null }));
            showToast("Biometric data revoked and private samples deleted.", "warning");
          } catch (error) { showToast(error.message, "warning"); }
        }}
      />
      <ManualAttendanceModal
        open={Boolean(manualAction)}
        initialPerson={person}
        initialAction={manualAction}
        canCheckIn={canManualCheckIn}
        canCheckOut={canManualCheckOut}
        onClose={() => setManualAction(null)}
        onSuccess={loadProfile}
      />
      <FaceEnrollmentCamera
        open={Boolean(enrollmentMode)}
        person={person}
        mode={enrollmentMode || "enroll"}
        onClose={() => setEnrollmentMode(null)}
        onSuccess={async (result) => {
          setEnrollmentMode(null);
          showToast(result.status === "enrolled" ? "Face enrollment completed successfully." : "Face photos uploaded. Biometric processing is in progress.", result.status === "enrolled" ? "success" : "warning");
          await loadProfile();
        }}
      />
    </div>
  );
}
