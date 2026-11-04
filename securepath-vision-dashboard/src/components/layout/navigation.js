import {
  Activity,
  BarChart3,
  BellRing,
  CalendarClock,
  ClipboardList,
  Grid3X3,
  IdCard,
  LayoutDashboard,
  KeyRound,
  Landmark,
  Settings,
  ShieldCheck,
  UserCog,
  UsersRound
} from "lucide-react";
import { PERMISSIONS } from "../../auth/permissions.js";

export const navigationGroups = [
  {
    items: [
      { label: "Overview", to: "/dashboard", icon: LayoutDashboard, permission: PERMISSIONS.DASHBOARD_VIEW },
      { label: "People", to: "/people", icon: UsersRound, permission: PERMISSIONS.PEOPLE_VIEW },
      { label: "Attendance", to: "/attendance", icon: ClipboardList, permission: PERMISSIONS.ATTENDANCE_VIEW },
      { label: "Schedules", to: "/schedules", icon: CalendarClock, permission: PERMISSIONS.SCHEDULES_VIEW },
      { label: "Departments", to: "/departments", icon: Grid3X3, permission: PERMISSIONS.DEPARTMENTS_VIEW }
    ]
  },
  {
    items: [
      { label: "Reports & Analytics", to: "/reports", icon: BarChart3, permission: PERMISSIONS.REPORTS_VIEW },
      { label: "Payroll", to: "/payroll", icon: Landmark, permission: PERMISSIONS.PAYROLL_VIEW },
      { label: "Alerts", to: "/alerts", icon: BellRing, permission: PERMISSIONS.ALERTS_VIEW }
    ]
  },
  {
    items: [
      { label: "Admin Management", to: "/admins", icon: KeyRound, superAdminOnly: true },
      { label: "Audit Logs", to: "/audit-logs", icon: ShieldCheck, permission: PERMISSIONS.AUDIT_LOGS_VIEW },
      { label: "User Management", to: "/users", icon: UserCog, permission: PERMISSIONS.USERS_VIEW },
      { label: "System Settings", to: "/settings", icon: Settings, permission: PERMISSIONS.SETTINGS_VIEW },
      { label: "My Attendance", to: "/my-attendance", icon: IdCard, permission: PERMISSIONS.MY_ATTENDANCE_VIEW }
    ]
  }
];

export const searchableEntities = [
  { label: "Dashboard overview", to: "/dashboard", type: "Route", permission: PERMISSIONS.DASHBOARD_VIEW },
  { label: "People directory", to: "/people", type: "Route", permission: PERMISSIONS.PEOPLE_VIEW },
  { label: "Attendance records", to: "/attendance", type: "Route", permission: PERMISSIONS.ATTENDANCE_VIEW },
  { label: "Schedules", to: "/schedules", type: "Route", permission: PERMISSIONS.SCHEDULES_VIEW },
  { label: "Departments", to: "/departments", type: "Route", permission: PERMISSIONS.DEPARTMENTS_VIEW },
  { label: "Reports and analytics", to: "/reports", type: "Route", permission: PERMISSIONS.REPORTS_VIEW },
  { label: "Payroll", to: "/payroll", type: "Route", permission: PERMISSIONS.PAYROLL_VIEW },
  { label: "Alerts", to: "/alerts", type: "Route", permission: PERMISSIONS.ALERTS_VIEW },
  { label: "Admin management", to: "/admins", type: "Route", superAdminOnly: true },
  { label: "Audit logs", to: "/audit-logs", type: "Route", permission: PERMISSIONS.AUDIT_LOGS_VIEW },
  { label: "User management", to: "/users", type: "Route", permission: PERMISSIONS.USERS_VIEW },
  { label: "Settings", to: "/settings", type: "Route", permission: PERMISSIONS.SETTINGS_VIEW },
  { label: "Activity feed", to: "/dashboard", type: "Widget", icon: Activity, permission: PERMISSIONS.DASHBOARD_VIEW }
];
