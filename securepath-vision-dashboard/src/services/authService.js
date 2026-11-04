import { apiCollection, apiData, apiRequest } from "./apiClient.js";

export function loginWithCredentials({ email, password }) {
  return apiData("/auth/login", {
    method: "POST",
    auth: false,
    body: { email, password, deviceName: "react-dashboard" }
  });
}

export function getCurrentUser() {
  return apiData("/auth/me");
}

export function logoutCurrentSession() {
  return apiRequest("/auth/logout", { method: "POST" });
}

export function requestPasswordReset(email) {
  return apiRequest("/auth/forgot-password", { method: "POST", auth: false, body: { email } });
}

export function getUsers(params = {}) {
  return apiCollection("/users", { params });
}

export function createUser(input) {
  return apiData("/users", {
    method: "POST",
    body: {
      name: input.name,
      email: input.email,
      phone: input.phone || null,
      password: input.password,
      password_confirmation: input.confirmPassword,
      title: input.title || "Employee",
      personId: input.personId || null,
      status: input.status || "Active",
      twoFactorEnabled: input.twoFactor === "Enabled"
    }
  });
}

export function updateUser(id, input) {
  const body = {
    name: input.name,
    email: input.email,
    phone: input.phone || null,
    title: input.title,
    personId: input.personId || null,
    status: input.status,
    twoFactorEnabled: input.twoFactor === "Enabled"
  };
  if (input.password) {
    body.password = input.password;
    body.password_confirmation = input.confirmPassword;
  }
  return apiData(`/users/${id}`, { method: "PUT", body });
}

export function setUserStatus(id, status) {
  return apiData(`/users/${id}/status`, { method: "PATCH", body: { status } });
}

export function deleteUser(id) {
  return apiRequest(`/users/${id}`, { method: "DELETE" });
}

export function getAdmins(params = {}) {
  return apiCollection("/admins", { params });
}

export function getAdmin(id) {
  return apiData(`/admins/${id}`);
}

export function createAdmin(input) {
  return apiData("/admins", {
    method: "POST",
    body: {
      name: input.name,
      email: input.email,
      phone: input.phone || null,
      password: input.password,
      password_confirmation: input.confirmPassword,
      status: input.status || "Active",
      permissions: input.permissions || []
    }
  });
}

export function updateAdmin(id, changes) {
  return apiData(`/admins/${id}`, { method: "PUT", body: changes });
}

export function setAdminStatus(id, status) {
  return apiData(`/admins/${id}/status`, { method: "PATCH", body: { status } });
}

export function updateAdminPermissions(id, permissions) {
  return apiData(`/admins/${id}/permissions`, {
    method: "PUT",
    body: { permissions }
  });
}

export function deleteAdmin(id) {
  return apiRequest(`/admins/${id}`, { method: "DELETE" });
}

export async function resetAdminPassword(id) {
  const data = await apiData(`/admins/${id}/reset-password`, { method: "POST" });
  return data.temporaryPassword;
}

export function getAvailablePermissions() {
  return apiData("/permissions");
}

export function getProfile() {
  return apiData("/profile");
}

export function updateProfile(input) {
  const form = new FormData();
  ["name", "email", "phone"].forEach((key) => {
    if (input[key] !== undefined) form.append(key, input[key] ?? "");
  });
  if (input.avatar) form.append("avatar", input.avatar);
  return apiData("/profile", { method: "POST", body: form });
}

export function changePassword(input) {
  return apiRequest("/profile/change-password", {
    method: "POST",
    body: {
      currentPassword: input.currentPassword,
      password: input.password,
      password_confirmation: input.confirmPassword
    }
  });
}
