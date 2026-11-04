import { Navigate, Route, Routes } from "react-router-dom";
import AppLayout from "./components/layout/AppLayout.jsx";
import ProtectedRoute from "./routes/ProtectedRoute.jsx";
import PermissionRoute from "./routes/PermissionRoute.jsx";
import { PERMISSIONS } from "./auth/permissions.js";
import LoginPage from "./pages/LoginPage.jsx";
import DashboardPage from "./pages/DashboardPage.jsx";
import PeoplePage from "./pages/PeoplePage.jsx";
import PersonProfilePage from "./pages/PersonProfilePage.jsx";
import AttendancePage from "./pages/AttendancePage.jsx";
import AttendanceEventPage from "./pages/AttendanceEventPage.jsx";
import SchedulesPage from "./pages/SchedulesPage.jsx";
import DepartmentsPage from "./pages/DepartmentsPage.jsx";
import ReportsPage from "./pages/ReportsPage.jsx";
import PayrollPage from "./pages/PayrollPage.jsx";
import AlertsPage from "./pages/AlertsPage.jsx";
import UsersPage from "./pages/UsersPage.jsx";
import AdminsPage from "./pages/AdminsPage.jsx";
import AuditLogsPage from "./pages/AuditLogsPage.jsx";
import SettingsPage from "./pages/SettingsPage.jsx";
import UserPortalPage from "./pages/UserPortalPage.jsx";
import ProfilePage from "./pages/ProfilePage.jsx";
import NotFoundPage from "./pages/NotFoundPage.jsx";
import { useAuth } from "./context/AuthContext.jsx";
import { getDefaultPathForUser } from "./auth/permissions.js";

function HomeRedirect() {
  const { user } = useAuth();
  return <Navigate to={getDefaultPathForUser(user)} replace />;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<HomeRedirect />} />
        <Route path="dashboard" element={<PermissionRoute permission={PERMISSIONS.DASHBOARD_VIEW}><DashboardPage /></PermissionRoute>} />
        <Route path="people" element={<PermissionRoute permission={PERMISSIONS.PEOPLE_VIEW}><PeoplePage /></PermissionRoute>} />
        <Route path="people/:id" element={<PermissionRoute permission={PERMISSIONS.PEOPLE_VIEW}><PersonProfilePage /></PermissionRoute>} />
        <Route path="attendance" element={<PermissionRoute permission={PERMISSIONS.ATTENDANCE_VIEW}><AttendancePage /></PermissionRoute>} />
        <Route path="attendance/:id" element={<PermissionRoute permission={PERMISSIONS.ATTENDANCE_VIEW}><AttendanceEventPage /></PermissionRoute>} />
        <Route path="schedules" element={<PermissionRoute permission={PERMISSIONS.SCHEDULES_VIEW}><SchedulesPage /></PermissionRoute>} />
        <Route path="departments" element={<PermissionRoute permission={PERMISSIONS.DEPARTMENTS_VIEW}><DepartmentsPage /></PermissionRoute>} />
        <Route path="reports" element={<PermissionRoute permission={PERMISSIONS.REPORTS_VIEW}><ReportsPage /></PermissionRoute>} />
        <Route path="payroll" element={<PermissionRoute permission={PERMISSIONS.PAYROLL_VIEW}><PayrollPage /></PermissionRoute>} />
        <Route path="alerts" element={<PermissionRoute permission={PERMISSIONS.ALERTS_VIEW}><AlertsPage /></PermissionRoute>} />
        <Route path="users" element={<PermissionRoute permission={PERMISSIONS.USERS_VIEW}><UsersPage /></PermissionRoute>} />
        <Route path="admins" element={<PermissionRoute superAdminOnly><AdminsPage /></PermissionRoute>} />
        <Route path="audit-logs" element={<PermissionRoute permission={PERMISSIONS.AUDIT_LOGS_VIEW}><AuditLogsPage /></PermissionRoute>} />
        <Route path="settings" element={<PermissionRoute permission={PERMISSIONS.SETTINGS_VIEW}><SettingsPage /></PermissionRoute>} />
        <Route path="my-attendance" element={<PermissionRoute permission={PERMISSIONS.MY_ATTENDANCE_VIEW}><UserPortalPage /></PermissionRoute>} />
        <Route path="profile" element={<ProfilePage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
      <Route path="*" element={<NotFoundPage publicPage />} />
    </Routes>
  );
}
