export const ROLES = Object.freeze({
  SUPER_ADMIN: "super_admin",
  ADMIN: "admin",
  USER: "user"
});

export const PERMISSIONS = Object.freeze({
  DASHBOARD_VIEW: "dashboard.view",

  PEOPLE_VIEW: "people.view",
  PEOPLE_CREATE: "people.create",
  PEOPLE_EDIT: "people.edit",
  PEOPLE_DELETE: "people.delete",
  PEOPLE_IMPORT: "people.import",
  PEOPLE_EXPORT: "people.export",

  ATTENDANCE_VIEW: "attendance.view",
  ATTENDANCE_CREATE: "attendance.create",
  ATTENDANCE_MANUAL_CHECKIN: "attendance.manual_checkin",
  ATTENDANCE_MANUAL_CHECKOUT: "attendance.manual_checkout",
  ATTENDANCE_EDIT: "attendance.edit",
  ATTENDANCE_DELETE: "attendance.delete",
  ATTENDANCE_APPROVE: "attendance.approve",

  REPORTS_VIEW: "reports.view",
  REPORTS_GENERATE: "reports.generate",
  REPORTS_EXPORT: "reports.export",

  PAYROLL_VIEW: "payroll.view",
  PAYROLL_MANAGE: "payroll.manage",
  PAYROLL_EXPORT: "payroll.export",
  PAY_RATES_VIEW: "payRates.view",
  PAY_RATES_MANAGE: "payRates.manage",

  SCHEDULES_VIEW: "schedules.view",
  SCHEDULES_MANAGE: "schedules.manage",

  DEPARTMENTS_VIEW: "departments.view",
  DEPARTMENTS_MANAGE: "departments.manage",

  ALERTS_VIEW: "alerts.view",
  ALERTS_MANAGE: "alerts.manage",

  USERS_VIEW: "users.view",
  USERS_MANAGE: "users.manage",

  ADMINS_VIEW: "admins.view",
  ADMINS_CREATE: "admins.create",
  ADMINS_EDIT: "admins.edit",
  ADMINS_DELETE: "admins.delete",
  ADMINS_PERMISSIONS: "admins.permissions",

  AUDIT_LOGS_VIEW: "auditLogs.view",

  SETTINGS_VIEW: "settings.view",
  SETTINGS_MANAGE: "settings.manage",

  BIOMETRICS_VIEW: "biometrics.view",
  BIOMETRICS_ENROLL: "biometrics.enroll",
  BIOMETRICS_REENROLL: "biometrics.reenroll",
  BIOMETRICS_DELETE: "biometrics.delete",
  BIOMETRICS_MANAGE: "biometrics.manage",

  MY_ATTENDANCE_VIEW: "myAttendance.view"
});

export const PERMISSION_GROUPS = Object.freeze([
  {
    id: "dashboard",
    label: "Dashboard",
    permissions: [{ value: PERMISSIONS.DASHBOARD_VIEW, label: "View dashboard" }]
  },
  {
    id: "people",
    label: "People",
    permissions: [
      { value: PERMISSIONS.PEOPLE_VIEW, label: "View people" },
      { value: PERMISSIONS.PEOPLE_CREATE, label: "Create people" },
      { value: PERMISSIONS.PEOPLE_EDIT, label: "Edit people" },
      { value: PERMISSIONS.PEOPLE_DELETE, label: "Delete people" },
      { value: PERMISSIONS.PEOPLE_IMPORT, label: "Import people" },
      { value: PERMISSIONS.PEOPLE_EXPORT, label: "Export people" }
    ]
  },
  {
    id: "attendance",
    label: "Attendance",
    permissions: [
      { value: PERMISSIONS.ATTENDANCE_VIEW, label: "View attendance" },
      { value: PERMISSIONS.ATTENDANCE_CREATE, label: "Create manual entries" },
      { value: PERMISSIONS.ATTENDANCE_MANUAL_CHECKIN, label: "Manually check in employees" },
      { value: PERMISSIONS.ATTENDANCE_MANUAL_CHECKOUT, label: "Manually check out employees" },
      { value: PERMISSIONS.ATTENDANCE_EDIT, label: "Edit attendance" },
      { value: PERMISSIONS.ATTENDANCE_DELETE, label: "Delete attendance" },
      { value: PERMISSIONS.ATTENDANCE_APPROVE, label: "Approve changes" }
    ]
  },
  {
    id: "reports",
    label: "Reports",
    permissions: [
      { value: PERMISSIONS.REPORTS_VIEW, label: "View reports" },
      { value: PERMISSIONS.REPORTS_GENERATE, label: "Generate reports" },
      { value: PERMISSIONS.REPORTS_EXPORT, label: "Export reports" }
    ]
  },
  {
    id: "payroll",
    label: "Payroll",
    permissions: [
      { value: PERMISSIONS.PAYROLL_VIEW, label: "View payroll" },
      { value: PERMISSIONS.PAYROLL_MANAGE, label: "Calculate and approve payroll" },
      { value: PERMISSIONS.PAYROLL_EXPORT, label: "Export payroll" },
      { value: PERMISSIONS.PAY_RATES_VIEW, label: "View pay rates" },
      { value: PERMISSIONS.PAY_RATES_MANAGE, label: "Manage roles and pay rates" }
    ]
  },
  {
    id: "schedules",
    label: "Schedules",
    permissions: [
      { value: PERMISSIONS.SCHEDULES_VIEW, label: "View schedules" },
      { value: PERMISSIONS.SCHEDULES_MANAGE, label: "Manage schedules" }
    ]
  },
  {
    id: "departments",
    label: "Departments",
    permissions: [
      { value: PERMISSIONS.DEPARTMENTS_VIEW, label: "View departments" },
      { value: PERMISSIONS.DEPARTMENTS_MANAGE, label: "Manage departments" }
    ]
  },
  {
    id: "alerts",
    label: "Alerts",
    permissions: [
      { value: PERMISSIONS.ALERTS_VIEW, label: "View alerts" },
      { value: PERMISSIONS.ALERTS_MANAGE, label: "Manage alerts" }
    ]
  },
  {
    id: "administration",
    label: "Administration",
    permissions: [
      { value: PERMISSIONS.USERS_VIEW, label: "View users" },
      { value: PERMISSIONS.USERS_MANAGE, label: "Manage users" },
      { value: PERMISSIONS.AUDIT_LOGS_VIEW, label: "View audit logs" }
    ]
  },
  {
    id: "settings",
    label: "Settings",
    permissions: [
      { value: PERMISSIONS.SETTINGS_VIEW, label: "View settings" },
      { value: PERMISSIONS.SETTINGS_MANAGE, label: "Manage settings" }
    ]
  },
  {
    id: "biometrics",
    label: "Biometrics",
    permissions: [
      { value: PERMISSIONS.BIOMETRICS_VIEW, label: "View biometric information" },
      { value: PERMISSIONS.BIOMETRICS_ENROLL, label: "Enroll employee faces" },
      { value: PERMISSIONS.BIOMETRICS_REENROLL, label: "Re-enroll employee faces" },
      { value: PERMISSIONS.BIOMETRICS_DELETE, label: "Revoke biometric data" },
      { value: PERMISSIONS.BIOMETRICS_MANAGE, label: "Manage biometric information" }
    ]
  }
]);

export const PERMISSION_PRESETS = Object.freeze({
  "Attendance Manager": [
    PERMISSIONS.DASHBOARD_VIEW,
    PERMISSIONS.PEOPLE_VIEW,
    PERMISSIONS.ATTENDANCE_VIEW,
    PERMISSIONS.ATTENDANCE_CREATE,
    PERMISSIONS.ATTENDANCE_MANUAL_CHECKIN,
    PERMISSIONS.ATTENDANCE_MANUAL_CHECKOUT,
    PERMISSIONS.ATTENDANCE_EDIT,
    PERMISSIONS.ATTENDANCE_APPROVE,
    PERMISSIONS.REPORTS_VIEW,
    PERMISSIONS.PAYROLL_VIEW,
    PERMISSIONS.PAY_RATES_VIEW
  ],
  "HR Admin": [
    PERMISSIONS.DASHBOARD_VIEW,
    PERMISSIONS.PEOPLE_VIEW,
    PERMISSIONS.PEOPLE_CREATE,
    PERMISSIONS.PEOPLE_EDIT,
    PERMISSIONS.PEOPLE_IMPORT,
    PERMISSIONS.PEOPLE_EXPORT,
    PERMISSIONS.ATTENDANCE_VIEW,
    PERMISSIONS.ATTENDANCE_MANUAL_CHECKIN,
    PERMISSIONS.ATTENDANCE_MANUAL_CHECKOUT,
    PERMISSIONS.REPORTS_VIEW,
    PERMISSIONS.REPORTS_EXPORT,
    PERMISSIONS.DEPARTMENTS_VIEW,
    PERMISSIONS.DEPARTMENTS_MANAGE,
    PERMISSIONS.BIOMETRICS_VIEW,
    PERMISSIONS.BIOMETRICS_ENROLL,
    PERMISSIONS.BIOMETRICS_REENROLL,
    PERMISSIONS.BIOMETRICS_DELETE,
    PERMISSIONS.PAYROLL_VIEW,
    PERMISSIONS.PAYROLL_MANAGE,
    PERMISSIONS.PAYROLL_EXPORT,
    PERMISSIONS.PAY_RATES_VIEW,
    PERMISSIONS.PAY_RATES_MANAGE
  ],
  "Report Viewer": [
    PERMISSIONS.DASHBOARD_VIEW,
    PERMISSIONS.ATTENDANCE_VIEW,
    PERMISSIONS.REPORTS_VIEW,
    PERMISSIONS.REPORTS_EXPORT
  ],
  "Department Manager": [
    PERMISSIONS.DASHBOARD_VIEW,
    PERMISSIONS.PEOPLE_VIEW,
    PERMISSIONS.ATTENDANCE_VIEW,
    PERMISSIONS.SCHEDULES_VIEW,
    PERMISSIONS.DEPARTMENTS_VIEW,
    PERMISSIONS.DEPARTMENTS_MANAGE,
    PERMISSIONS.REPORTS_VIEW
  ]
});

export function hasPermissionForUser(user, permission) {
  if (!user) return false;
  if (user.role === ROLES.SUPER_ADMIN) return true;
  return Boolean(permission && user.permissions?.includes(permission));
}

export function getRoleLabel(role) {
  if (role === ROLES.SUPER_ADMIN) return "Super Admin";
  if (role === ROLES.ADMIN) return "Admin";
  return "Regular User";
}

export function getDefaultPathForUser(user) {
  const candidates = [
    [PERMISSIONS.DASHBOARD_VIEW, "/dashboard"],
    [PERMISSIONS.PEOPLE_VIEW, "/people"],
    [PERMISSIONS.ATTENDANCE_VIEW, "/attendance"],
    [PERMISSIONS.REPORTS_VIEW, "/reports"],
    [PERMISSIONS.PAYROLL_VIEW, "/payroll"],
    [PERMISSIONS.MY_ATTENDANCE_VIEW, "/my-attendance"]
  ];
  return candidates.find(([permission]) => hasPermissionForUser(user, permission))?.[1] || "/profile";
}
