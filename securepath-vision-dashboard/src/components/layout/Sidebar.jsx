import { useEffect, useState } from "react";
import { NavLink } from "react-router-dom";
import { ChevronLeft, ChevronRight, Shield } from "lucide-react";
import { navigationGroups } from "./navigation.js";
import { usePermissions } from "../../hooks/usePermissions.js";
import logoUrl from "../../assets/securepath-vision-logo.png";
import { getAlerts } from "../../services/alertService.js";
import { PERMISSIONS } from "../../auth/permissions.js";

export default function Sidebar({ collapsed, onToggle, mobileOpen, onCloseMobile }) {
  const { hasPermission, isSuperAdmin } = usePermissions();
  const [openAlertCount, setOpenAlertCount] = useState(0);
  const canViewAlerts = hasPermission(PERMISSIONS.ALERTS_VIEW);

  useEffect(() => {
    if (!canViewAlerts) return;
    getAlerts({ status: "open", limit: 1 })
      .then((result) => setOpenAlertCount(result.pagination.total))
      .catch(() => setOpenAlertCount(0));
  }, [canViewAlerts]);

  const groups = navigationGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => (
        item.superAdminOnly ? isSuperAdmin : !item.permission || hasPermission(item.permission)
      ))
    }))
    .filter((group) => group.items.length > 0);

  return (
    <>
      <aside className={`sidebar ${collapsed ? "collapsed" : ""} ${mobileOpen ? "open" : ""}`}>
        <div className="brand">
          <img src={logoUrl} alt="" />
          {!collapsed && (
            <div>
              <strong>SecurePath</strong>
              <span>Vision System</span>
            </div>
          )}
        </div>

        <nav className="sidebar-nav" aria-label="Primary navigation">
          {groups.map((group, groupIndex) => (
            <div className="nav-group" key={groupIndex}>
              {group.items.map((item) => {
                const Icon = item.icon || Shield;
                const count = item.to === "/alerts" ? openAlertCount : item.count;
                return (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    onClick={onCloseMobile}
                    className={({ isActive }) => (isActive ? "active" : "")}
                    title={collapsed ? item.label : undefined}
                  >
                    <Icon size={20} />
                    {!collapsed && <span>{item.label}</span>}
                    {!collapsed && count > 0 && <b>{count}</b>}
                  </NavLink>
                );
              })}
            </div>
          ))}
        </nav>

        <button className="collapse-button" type="button" onClick={onToggle} aria-label="Toggle sidebar">
          {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
        </button>
      </aside>
      {mobileOpen && <button className="sidebar-scrim" aria-label="Close navigation" onClick={onCloseMobile} />}
    </>
  );
}
