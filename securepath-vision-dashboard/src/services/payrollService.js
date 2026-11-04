import { apiCollection, apiData, apiRequest, downloadApiFile } from "./apiClient.js";

export function getPayrollOverview(year, month) { return apiData("/payroll", { params: { year, month } }); }
export function getPayrollEntries(periodId, params = {}) { return apiCollection(`/payroll/periods/${periodId}/employees`, { params }); }
export function generatePayroll(year, month) { return apiData("/payroll/generate", { method: "POST", body: { year, month } }); }
export function approvePayroll(periodId) { return apiData(`/payroll/periods/${periodId}/approve`, { method: "POST" }); }
export function closePayroll(periodId) { return apiData(`/payroll/periods/${periodId}/close`, { method: "POST" }); }
export function exportPayroll(periodId) { return downloadApiFile(`/payroll/periods/${periodId}/export`, { fallbackName: "payroll.csv" }); }

export function getRoles(params = {}) { return apiCollection("/roles", { params }); }
export function createRole(input) { return apiData("/roles", { method: "POST", body: input }); }
export function updateRole(id, input) { return apiData(`/roles/${id}`, { method: "PUT", body: input }); }
export function deleteRole(id) { return apiRequest(`/roles/${id}`, { method: "DELETE" }); }

export function getPayRates(params = {}) { return apiCollection("/pay-rates", { params }); }
export function createPayRate(input) { return apiData("/pay-rates", { method: "POST", body: input }); }
export function updatePayRate(id, input) { return apiData(`/pay-rates/${id}`, { method: "PUT", body: input }); }
export function deletePayRate(id) { return apiRequest(`/pay-rates/${id}`, { method: "DELETE" }); }
