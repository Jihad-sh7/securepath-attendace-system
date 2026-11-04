import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import PageHeader from "../components/common/PageHeader.jsx";
import LoadingSkeleton from "../components/common/LoadingSkeleton.jsx";
import Modal from "../components/common/Modal.jsx";
import FormField from "../components/forms/FormField.jsx";
import { useToast } from "../context/ToastContext.jsx";
import { getAttendanceEvent, updateAttendance } from "../services/attendanceService.js";
import { AttendanceDetailContent } from "./AttendancePage.jsx";
import { PERMISSIONS } from "../auth/permissions.js";
import { usePermissions } from "../hooks/usePermissions.js";

export default function AttendanceEventPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { showToast } = useToast();
  const [event, setEvent] = useState(null);
  const [correction, setCorrection] = useState(null);
  const { hasPermission } = usePermissions();

  useEffect(() => {
    getAttendanceEvent(id).then(setEvent).catch((error) => showToast(error.message, "warning"));
  }, [id]);

  if (!event) return <LoadingSkeleton rows={6} />;

  return (
    <div className="page-stack">
      <PageHeader
        title={`Attendance Event ${id}`}
        description="Detailed recognition and attendance audit record."
        actions={<button className="button button-secondary" type="button" onClick={() => navigate("/attendance")}>Back to attendance</button>}
      />
      <section className="info-card">
        <AttendanceDetailContent
          event={event}
          canEdit={hasPermission(PERMISSIONS.ATTENDANCE_EDIT)}
          onCorrect={() => setCorrection({ ...event, reason: "" })}
        />
      </section>
      <Modal open={Boolean(correction)} title="Correct Attendance" onClose={() => setCorrection(null)} footer={<><button className="button button-ghost" type="button" onClick={() => setCorrection(null)}>Cancel</button><button className="button button-primary" type="button" onClick={async () => {
        try {
          await updateAttendance(event.id, { checkInAt: correction.checkIn || null, checkOutAt: correction.checkOut || null, status: correction.status, notes: correction.note || null, reason: correction.reason });
          setCorrection(null);
          setEvent(await getAttendanceEvent(id));
          showToast("Attendance correction submitted.");
        } catch (error) { showToast(error.message, "warning"); }
      }}>Submit correction</button></>}>
        {correction && <div className="form-grid">
          <FormField label="Check-in"><input type="time" value={correction.checkIn || ""} onChange={(event) => setCorrection((current) => ({ ...current, checkIn: event.target.value }))} /></FormField>
          <FormField label="Check-out"><input type="time" value={correction.checkOut || ""} onChange={(event) => setCorrection((current) => ({ ...current, checkOut: event.target.value }))} /></FormField>
          <FormField label="Status"><select value={correction.status} onChange={(event) => setCorrection((current) => ({ ...current, status: event.target.value }))}><option>Present</option><option>Late</option><option>Absent</option><option>Excused</option><option>On Leave</option><option>Review</option></select></FormField>
          <FormField label="Reason" required className="full-field"><textarea rows="4" value={correction.reason} onChange={(event) => setCorrection((current) => ({ ...current, reason: event.target.value }))} /></FormField>
        </div>}
      </Modal>
    </div>
  );
}
