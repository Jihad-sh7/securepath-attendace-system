import { apiCollection, apiData } from "./apiClient.js";

export function getAlerts(params = {}) {
  return apiCollection("/alerts", { params });
}

export function getAlert(id) {
  return apiData(`/alerts/${id}`);
}

export function setAlertStatus(id, status, assignedTo) {
  return apiData(`/alerts/${id}/status`, { method: "PATCH", body: { status, assignedTo } });
}

export function resolveAlert(id, note = "") {
  return apiData(`/alerts/${id}/resolve`, { method: "POST", body: { note } });
}

export function addAlertNote(id, note) {
  return apiData(`/alerts/${id}/notes`, { method: "POST", body: { note } });
}

export function getAuditLogs(params = {}) {
  return apiCollection("/audit-logs", { params });
}

function relativeTime(value) {
  if (!value) return "";
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
  if (seconds < 60) return "Just now";
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  return `${Math.floor(seconds / 86400)}d ago`;
}

export async function getNotifications(params = {}) {
  const result = await apiCollection("/notifications", { params });
  return {
    ...result,
    items: result.items.map((item) => ({
      ...item,
      tone: item.severity || "info",
      time: relativeTime(item.createdAt)
    }))
  };
}

export function getUnreadNotificationCount() {
  return apiData("/notifications/unread-count");
}

export function markNotificationRead(id) {
  return apiData(`/notifications/${id}/read`, { method: "PATCH" });
}

export function markAllNotificationsRead() {
  return apiData("/notifications/read-all", { method: "PATCH" });
}
