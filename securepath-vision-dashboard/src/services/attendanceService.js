import { apiCollection, apiData, apiRequest } from "./apiClient.js";

export function getAttendanceRecords(params = {}) {
  return apiCollection("/attendance", { params });
}

export function getAttendanceEvent(id) {
  return apiData(`/attendance/${id}`);
}

export function createManualAttendance(input) {
  return apiData("/attendance/manual", { method: "POST", body: input });
}

export function manualCheckIn(input) {
  return apiData("/attendance/manual-check-in", { method: "POST", body: input });
}

export function manualCheckOut(input) {
  return apiData("/attendance/manual-check-out", { method: "POST", body: input });
}

export function updateAttendance(id, input) {
  return apiData(`/attendance/${id}`, { method: "PUT", body: input });
}

export function deleteAttendance(id) {
  return apiRequest(`/attendance/${id}`, { method: "DELETE" });
}

export function requestAttendanceCorrection(id, input) {
  return apiData(`/attendance/${id}/corrections`, { method: "POST", body: input });
}

export function getAttendanceCorrections(params = {}) {
  return apiCollection("/attendance-corrections", { params });
}

export function reviewAttendanceCorrection(id, approve, reviewNote = "") {
  return apiData(`/attendance-corrections/${id}/${approve ? "approve" : "reject"}`, {
    method: "PATCH",
    body: { reviewNote }
  });
}

export async function getRecognitionEvents(params = {}) {
  const result = await apiCollection("/recognition-events", { params });
  return {
    ...result,
    items: result.items.map((event) => ({
      ...event,
      personCode: event.personId,
      event: String(event.recognitionStatus || "Recognition").replaceAll("_", " ")
    }))
  };
}

export function getRecognitionEvent(id) {
  return apiData(`/recognition-events/${id}`);
}

export function verifyRecognition(id, note = "") {
  return apiData(`/recognition-events/${id}/verify`, { method: "POST", body: { note } });
}

export function rejectRecognition(id, note = "") {
  return apiData(`/recognition-events/${id}/reject`, { method: "POST", body: { note } });
}

export function assignRecognitionPerson(id, personId, note = "") {
  return apiData(`/recognition-events/${id}/assign-person`, { method: "POST", body: { personId, note } });
}
