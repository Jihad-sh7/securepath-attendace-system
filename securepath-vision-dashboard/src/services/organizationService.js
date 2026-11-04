import { apiCollection, apiData, apiRequest } from "./apiClient.js";

export function getDepartments(params = {}) {
  return apiCollection("/departments", { params });
}

export function getDepartment(id) {
  return apiData(`/departments/${id}`);
}

export function createDepartment(input) {
  return apiData("/departments", {
    method: "POST",
    body: {
      name: input.name,
      code: input.code,
      description: input.description || null,
      managerId: input.managerId || null,
      status: input.status || "Active",
      maxOvertimeMinutes: input.maxOvertimeHours === "" || input.maxOvertimeHours === null || input.maxOvertimeHours === undefined ? null : Math.round(Number(input.maxOvertimeHours) * 60),
      autoCheckoutEnabled: Boolean(input.autoCheckoutEnabled)
    }
  });
}

export function updateDepartment(id, input) {
  return apiData(`/departments/${id}`, {
    method: "PUT",
    body: {
      name: input.name,
      description: input.description || null,
      managerId: input.managerId || null,
      status: input.status || "Active",
      maxOvertimeMinutes: input.maxOvertimeHours === "" || input.maxOvertimeHours === null || input.maxOvertimeHours === undefined ? null : Math.round(Number(input.maxOvertimeHours) * 60),
      autoCheckoutEnabled: Boolean(input.autoCheckoutEnabled)
    }
  });
}

export function deleteDepartment(id) {
  return apiRequest(`/departments/${id}`, { method: "DELETE" });
}

export function getDepartmentMembers(id, params = {}) {
  return apiCollection(`/departments/${id}/members`, { params });
}

export function getDepartmentStatistics(id) {
  return apiData(`/departments/${id}/statistics`);
}

export function getSchedules(params = {}) {
  return apiCollection("/schedules", { params });
}

function schedulePayload(input) {
  const days = (input.days || []).map((day) => typeof day === "object" ? day : ({
    dayOfWeek: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].indexOf(day) + 1,
    isWorkingDay: true
  })).filter((day) => day.dayOfWeek > 0);
  const payload = {
    name: input.name,
    code: input.code,
    type: input.type || "Fixed",
    startTime: input.startTime,
    endTime: input.endTime,
    gracePeriodMinutes: Number(input.gracePeriod ?? input.gracePeriodMinutes ?? 0),
    breakDurationMinutes: Number(input.breakDurationMinutes || 0),
    breakWindow: input.breakWindow || null,
    effectiveFrom: input.effectiveFrom || null,
    effectiveUntil: input.effectiveUntil || null,
    status: input.status || "Active",
    days
  };
  if (Array.isArray(input.assignmentIds)) {
    payload.personIds = input.assignmentIds.filter((id) => id.startsWith("EMP-") || id.startsWith("STU-") || id.startsWith("CON-"));
    payload.departmentIds = input.assignmentIds.filter((id) => id.startsWith("DEP-"));
  }
  return payload;
}

export function createSchedule(input) {
  return apiData("/schedules", { method: "POST", body: schedulePayload(input) });
}

export function updateSchedule(id, input) {
  const body = schedulePayload(input);
  delete body.code;
  return apiData(`/schedules/${id}`, { method: "PUT", body });
}

export function deleteSchedule(id) {
  return apiRequest(`/schedules/${id}`, { method: "DELETE" });
}

export function getHolidays(params = {}) {
  return apiCollection("/holidays", { params });
}
