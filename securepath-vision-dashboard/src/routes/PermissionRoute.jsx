import { Navigate, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext.jsx";
import { usePermissions } from "../hooks/usePermissions.js";
import AccessDeniedPage from "../pages/AccessDeniedPage.jsx";

export default function PermissionRoute({ children, permission, superAdminOnly = false }) {
  const { user, initializing } = useAuth();
  const { hasPermission, isSuperAdmin } = usePermissions();
  const location = useLocation();

  if (initializing) return <div className="full-screen-loader">Loading SecurePath Vision...</div>;
  if (!user) return <Navigate to="/login" state={{ from: location }} replace />;

  const denied = superAdminOnly ? !isSuperAdmin : permission && !hasPermission(permission);
  return denied ? <AccessDeniedPage /> : children;
}
