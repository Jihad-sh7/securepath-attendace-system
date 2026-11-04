import { apiCollection, apiData, apiRequest, downloadApiFile } from "./apiClient.js";

export function getPeople(params = {}) {
  return apiCollection("/people", { params });
}

export function getPersonById(id) {
  return apiData(`/people/${id}`);
}

export function getPersonAttendanceStatus(id) {
  return apiData(`/people/${id}/attendance-status`);
}

export async function getPersonProfile(id, { includeAudit = false } = {}) {
  const profile = await apiData(`/people/${id}/profile`);
  let audit = [];
  if (includeAudit) {
    const auditResult = await apiCollection("/audit-logs", { params: { search: id, limit: 20 } });
    audit = auditResult.items;
  }
  const summary = profile.attendanceSummary || {};
  const attended = (summary.present || 0) + (summary.late || 0);
  const person = {
    ...profile.person,
    monthlyAttendance: summary.totalDays ? Math.round((attended / summary.totalDays) * 100) : 0,
    lateCount: summary.late || 0,
    absenceCount: summary.absent || 0,
    lastRecognition: profile.recentRecognition?.[0]?.occurredAt || "No recognition recorded",
    scheduleId: profile.currentSchedule?.id || null
  };
  return {
    person,
    attendance: profile.recentAttendance || [],
    activity: profile.recentRecognition || [],
    audit,
    currentAttendance: profile.currentAttendance || null,
    currentSchedule: profile.currentSchedule,
    attendanceSummary: summary,
    recognitionStatistics: profile.recognitionStatistics || {}
  };
}

function personPayload(input) {
  return {
    fullName: input.name,
    email: input.email || null,
    phone: input.phone || null,
    departmentId: input.departmentId || null,
    roleId: input.roleId || null,
    type: input.type || "employee",
    jobTitle: input.role || null,
    status: input.status || "Active",
    biometricConsent: input.consent === "Granted"
  };
}

export function createPerson(input) {
  return apiData("/people", { method: "POST", body: personPayload(input) });
}

export function updatePerson(id, input) {
  return apiData(`/people/${id}`, { method: "PUT", body: personPayload(input) });
}

export function deletePerson(id) {
  return apiRequest(`/people/${id}`, { method: "DELETE" });
}

export function setPersonStatus(id, status) {
  return apiData(`/people/${id}/status`, { method: "PATCH", body: { status } });
}

export function importPeople(file, updateExisting = false) {
  const form = new FormData();
  form.append("file", file);
  form.append("updateExisting", updateExisting ? "1" : "0");
  return apiData("/people/import", { method: "POST", body: form });
}

export function exportPeople(params = {}) {
  return downloadApiFile("/people/export", { params, fallbackName: "people.csv" });
}

export function getLeaveRequests(personId, params = {}) {
  return apiCollection("/leave-requests", { params: { ...params, personId } });
}

export function createLeaveRequest(input) {
  return apiData("/leave-requests", { method: "POST", body: input });
}

export function cancelLeaveRequest(id) {
  return apiRequest(`/leave-requests/${id}`, { method: "DELETE" });
}

export function getBiometricStatus(personId) {
  return apiData(`/people/${personId}/biometric-status`);
}

export function enrollBiometric(personId, images, consentAccepted = false) {
  return submitBiometricEnrollment(`/people/${personId}/biometric-enrollment`, images, consentAccepted);
}

export function reenrollBiometric(personId, images, consentAccepted = false) {
  return submitBiometricEnrollment(`/people/${personId}/biometric-reenrollment`, images, consentAccepted);
}

export function retryBiometricEnrollment(personId) {
  return apiData(`/people/${personId}/biometric-enrollment/retry`, { method: "POST" });
}

function submitBiometricEnrollment(path, images, consentAccepted) {
  const form = new FormData();
  ["front", "left", "right"].forEach((pose) => form.append(pose, images[pose], `${pose}.jpg`));
  if (consentAccepted) form.append("consentAccepted", "1");
  return apiData(path, { method: "POST", body: form });
}

export function getBiometricConsent(personId) {
  return apiData(`/people/${personId}/biometric-consent`);
}

export function setBiometricConsent(personId, granted) {
  return apiData(`/people/${personId}/biometric-consent`, { method: "POST", body: { granted } });
}

export function deleteBiometricData(personId) {
  return apiRequest(`/people/${personId}/biometric-data`, { method: "DELETE" });
}

export function requestBiometricEnrollment(personId) {
  return apiData(`/people/${personId}/enrollment-request`, { method: "POST" });
}

export function getBiometricEnrollmentStatus(personId) {
  return apiData(`/people/${personId}/enrollment-status`);
}
