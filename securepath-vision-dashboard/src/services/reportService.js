import { apiData, downloadApiFile } from "./apiClient.js";

export function getDashboardData(params = {}) {
  return apiData("/dashboard", { params });
}

export function getDashboardSummary(params = {}) {
  return apiData("/dashboard/summary", { params });
}

export function getReport(type, params = {}) {
  return apiData(`/reports/${type}`, { params });
}

export function exportReport(type, params = {}) {
  return downloadApiFile(`/reports/${type}/export`, { params, fallbackName: `${type}.csv` });
}
