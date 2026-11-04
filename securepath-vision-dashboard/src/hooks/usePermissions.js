import { useMemo } from "react";
import { useAuth } from "../context/AuthContext.jsx";
import { hasPermissionForUser, ROLES } from "../auth/permissions.js";

export function usePermissions() {
  const { user } = useAuth();

  return useMemo(() => ({
    isSuperAdmin: user?.role === ROLES.SUPER_ADMIN,
    isAdmin: user?.role === ROLES.ADMIN,
    hasPermission: (permission) => hasPermissionForUser(user, permission),
    hasAnyPermission: (permissions) => permissions.some((permission) => hasPermissionForUser(user, permission))
  }), [user]);
}
