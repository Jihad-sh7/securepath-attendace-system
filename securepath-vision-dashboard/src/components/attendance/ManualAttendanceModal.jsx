import { useEffect, useState } from "react";
import { Clock3, LogIn, LogOut, Search } from "lucide-react";
import Modal from "../common/Modal.jsx";
import FormField from "../forms/FormField.jsx";
import StatusBadge from "../common/StatusBadge.jsx";
import PersonAvatar from "../common/PersonAvatar.jsx";
import { useDebouncedValue } from "../../hooks/useDebouncedValue.js";
import { useToast } from "../../context/ToastContext.jsx";
import { manualCheckIn, manualCheckOut } from "../../services/attendanceService.js";
import { getPeople, getPersonAttendanceStatus } from "../../services/peopleService.js";

const localDateTime = () => {
  const date = new Date();
  const pad = (value) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const formatDateTime = (value, fallback = "Not available") => value
  ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(new Date(value))
  : fallback;

const formatMinutes = (minutes = 0) => `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, "0")}m`;

export default function ManualAttendanceModal({
  open,
  initialPerson = null,
  initialAction = null,
  canCheckIn,
  canCheckOut,
  onClose,
  onSuccess
}) {
  const [search, setSearch] = useState("");
  const [people, setPeople] = useState([]);
  const [selected, setSelected] = useState(null);
  const [attendance, setAttendance] = useState(null);
  const [action, setAction] = useState(initialAction || "check_in");
  const [timeMode, setTimeMode] = useState("now");
  const [customTime, setCustomTime] = useState(localDateTime());
  const [note, setNote] = useState("");
  const [reviewing, setReviewing] = useState(false);
  const [loadingPeople, setLoadingPeople] = useState(false);
  const [loadingStatus, setLoadingStatus] = useState(false);
  const [saving, setSaving] = useState(false);
  const debouncedSearch = useDebouncedValue(search);
  const { showToast } = useToast();

  useEffect(() => {
    if (!open) return;
    setSearch("");
    setSelected(initialPerson);
    setAttendance(null);
    setAction(initialAction || (initialPerson?.isCheckedIn ? "check_out" : "check_in"));
    setTimeMode("now");
    setCustomTime(localDateTime());
    setNote("");
    setReviewing(false);
  }, [open, initialPerson, initialAction]);

  useEffect(() => {
    if (!open || initialPerson) return;
    let active = true;
    setLoadingPeople(true);
    getPeople({ search: debouncedSearch, status: "Active", limit: 10, page: 1 })
      .then((result) => { if (active) setPeople(result.items); })
      .catch((error) => { if (active) showToast(error.message, "warning"); })
      .finally(() => { if (active) setLoadingPeople(false); });
    return () => { active = false; };
  }, [open, initialPerson, debouncedSearch]);

  useEffect(() => {
    if (!open || !selected?.id) return;
    let active = true;
    setLoadingStatus(true);
    getPersonAttendanceStatus(selected.id)
      .then((result) => {
        if (!active) return;
        setAttendance(result);
        setAction(initialAction || (result.status === "checked_in" ? "check_out" : "check_in"));
      })
      .catch((error) => { if (active) showToast(error.message, "warning"); })
      .finally(() => { if (active) setLoadingStatus(false); });
    return () => { active = false; };
  }, [open, selected?.id, initialAction]);

  const isCheckedIn = attendance?.status === "checked_in";
  const isClosedToday = attendance?.status === "checked_out";
  const actionAllowed = action === "check_in" ? canCheckIn && !isCheckedIn && !isClosedToday : canCheckOut && isCheckedIn;
  const actionLabel = action === "check_in" ? "Check In" : "Check Out";
  const effectiveTime = timeMode === "now" ? "current server time" : formatDateTime(customTime);
  const selectionStatus = isCheckedIn ? "Checked In" : isClosedToday ? "Checked Out" : "Not Checked In";
  const canReview = Boolean(selected && actionAllowed && (timeMode === "now" || customTime));

  const footer = reviewing ? (
    <>
      <button className="button button-ghost" type="button" onClick={() => setReviewing(false)} disabled={saving}>Back</button>
      <button className="button button-primary" type="button" onClick={submit} disabled={saving}>
        {action === "check_in" ? <LogIn size={17} /> : <LogOut size={17} />}
        {saving ? "Saving..." : `Confirm ${actionLabel}`}
      </button>
    </>
  ) : (
    <>
      <button className="button button-ghost" type="button" onClick={onClose}>Cancel</button>
      <button className="button button-primary" type="button" onClick={() => setReviewing(true)} disabled={!canReview || loadingStatus}>
        Review {actionLabel}
      </button>
    </>
  );

  async function submit() {
    setSaving(true);
    try {
      const payload = { personId: selected.id, note: note.trim() || undefined };
      if (timeMode === "custom") payload[action === "check_in" ? "checkInAt" : "checkOutAt"] = new Date(customTime).toISOString();
      const record = action === "check_in" ? await manualCheckIn(payload) : await manualCheckOut(payload);
      showToast(`${selected.name} checked ${action === "check_in" ? "in" : "out"} successfully.`);
      onSuccess?.(record, action);
      onClose();
    } catch (error) {
      setReviewing(false);
      showToast(error.message, "warning");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal open={open} title="Manual Attendance" onClose={saving ? undefined : onClose} size="lg" footer={footer}>
      {reviewing ? (
        <div className="manual-review">
          <span className={`manual-review-icon ${action}`}>
            {action === "check_in" ? <LogIn size={24} /> : <LogOut size={24} />}
          </span>
          <div>
            <h3>{actionLabel} {selected?.name}?</h3>
            <p>The attendance record will use {effectiveTime} and identify you as the administrator who performed this action.</p>
          </div>
          {note && <div className="manual-note"><strong>Note</strong><span>{note}</span></div>}
        </div>
      ) : (
        <div className="manual-attendance-form">
          {!initialPerson && <FormField label="Employee" required>
            <div className="manual-person-picker">
              <label className="search-input">
                <Search size={17} />
                <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name, employee ID, or email" />
              </label>
              <div className="manual-person-results">
                {loadingPeople ? <p>Searching...</p> : people.map((person) => (
                  <button key={person.id} type="button" className={selected?.id === person.id ? "selected" : ""} onClick={() => setSelected(person)}>
                    <PersonAvatar name={person.name} src={person.avatarUrl} status={person.attendanceStatus} />
                    <span><strong>{person.name}</strong><small>{person.id} · {person.department}</small></span>
                    <StatusBadge status={person.isCheckedIn ? "Checked In" : person.attendanceState === "checked_out" ? "Checked Out" : "Available"} />
                  </button>
                ))}
                {!loadingPeople && people.length === 0 && <p>No active employees found.</p>}
              </div>
            </div>
          </FormField>}

          {selected && <section className="manual-context">
            <div className="manual-context-person">
              <PersonAvatar name={selected.name} src={selected.avatarUrl} size="lg" status={selected.attendanceStatus} />
              <div><strong>{selected.name}</strong><span>{attendance?.department || selected.department} · {attendance?.role || selected.role}</span></div>
              <StatusBadge status={loadingStatus ? "Loading" : selectionStatus} />
            </div>
            <dl>
              <div><dt>Today's schedule</dt><dd>{attendance?.schedule ? `${formatDateTime(attendance.schedule.startAt)} - ${new Date(attendance.schedule.endAt).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}` : "No schedule assigned"}</dd></div>
              <div><dt>Checked in</dt><dd>{formatDateTime(attendance?.checkInAt, "Not recorded")}</dd></div>
              <div><dt>Worked</dt><dd>{formatMinutes(attendance?.workedMinutes)}</dd></div>
              <div><dt>Overtime</dt><dd>{formatMinutes(attendance?.overtimeMinutes)}</dd></div>
              <div><dt>Maximum checkout</dt><dd>{formatDateTime(attendance?.maximumCheckoutAt, "No department limit")}</dd></div>
            </dl>
            {isClosedToday && <p className="manual-state-note">Today's attendance is already closed. Use the correction workflow to change it.</p>}
          </section>}

          <div className="form-grid">
            <FormField label="Action" required>
              <select value={action} onChange={(event) => setAction(event.target.value)}>
                <option value="check_in" disabled={!canCheckIn || isCheckedIn || isClosedToday}>Check In</option>
                <option value="check_out" disabled={!canCheckOut || !isCheckedIn}>Check Out</option>
              </select>
            </FormField>
            <FormField label="Time" required>
              <div className="manual-time-control">
                <div className="segmented labeled">
                  <button type="button" className={timeMode === "now" ? "active" : ""} onClick={() => setTimeMode("now")}><Clock3 size={16} /> Now</button>
                  <button type="button" className={timeMode === "custom" ? "active" : ""} onClick={() => setTimeMode("custom")}>Custom</button>
                </div>
                {timeMode === "custom" && <input type="datetime-local" value={customTime} onChange={(event) => setCustomTime(event.target.value)} />}
              </div>
            </FormField>
            <FormField label="Note" className="full-field">
              <textarea rows="3" maxLength="1000" value={note} onChange={(event) => setNote(event.target.value)} placeholder="Reason for the manual attendance action" />
            </FormField>
          </div>
        </div>
      )}
    </Modal>
  );
}
