import { useEffect, useMemo, useRef, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  Bell,
  ChevronDown,
  LogOut,
  Menu,
  Moon,
  Search,
  Settings,
  Sun,
  UserCircle
} from "lucide-react";
import { useAuth } from "../../context/AuthContext.jsx";
import { useTheme } from "../../context/ThemeContext.jsx";
import { usePermissions } from "../../hooks/usePermissions.js";
import { getRoleLabel, PERMISSIONS } from "../../auth/permissions.js";
import {
  getNotifications,
  getUnreadNotificationCount,
  markAllNotificationsRead,
  markNotificationRead
} from "../../services/alertService.js";
import { searchableEntities } from "./navigation.js";
import { titleFromPath } from "../../utils/format.js";
import AuthenticatedImage from "../common/AuthenticatedImage.jsx";

export default function Topbar({ onOpenMobileNav }) {
  const { user, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const { hasPermission, isSuperAdmin } = usePermissions();
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [notificationOpen, setNotificationOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [searchOpen, setSearchOpen] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();
  const searchRef = useRef(null);

  useEffect(() => {
    Promise.all([getNotifications({ limit: 8 }), getUnreadNotificationCount()])
      .then(([notificationResult, count]) => {
        setNotifications(notificationResult.items);
        setUnreadCount(count.count);
      })
      .catch(() => {
        setNotifications([]);
        setUnreadCount(0);
      });
  }, []);

  useEffect(() => {
    function handleClick(event) {
      if (searchRef.current && !searchRef.current.contains(event.target)) {
        setSearchOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  const results = useMemo(() => {
    if (!query.trim()) return [];
    return searchableEntities.filter((item) => {
      const allowed = item.superAdminOnly ? isSuperAdmin : !item.permission || hasPermission(item.permission);
      return allowed && item.label.toLowerCase().includes(query.trim().toLowerCase());
    });
  }, [query, hasPermission, isSuperAdmin]);

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  async function openNotification(notification) {
    if (!notification.readAt) {
      await markNotificationRead(notification.id);
      setNotifications((current) => current.map((item) => item.id === notification.id ? { ...item, readAt: new Date().toISOString() } : item));
      setUnreadCount((current) => Math.max(0, current - 1));
    }
    setNotificationOpen(false);
    navigate(hasPermission(PERMISSIONS.ALERTS_VIEW) ? "/alerts" : "/profile");
  }

  async function readAllNotifications() {
    await markAllNotificationsRead();
    setNotifications((current) => current.map((item) => ({ ...item, readAt: item.readAt || new Date().toISOString() })));
    setUnreadCount(0);
  }

  function goToResult(result) {
    setQuery("");
    setSearchOpen(false);
    navigate(result.to);
  }

  return (
    <header className="topbar">
      <button className="icon-button mobile-menu" type="button" aria-label="Open navigation" onClick={onOpenMobileNav}>
        <Menu size={20} />
      </button>

      <div className="global-search" ref={searchRef}>
        <Search size={19} />
        <input
          value={query}
          placeholder="Search people, attendance, reports, or logs..."
          onChange={(event) => {
            setQuery(event.target.value);
            setSearchOpen(true);
          }}
          onFocus={() => setSearchOpen(true)}
        />
        {searchOpen && results.length > 0 && (
          <div className="search-results">
            {results.map((result) => (
              <button key={`${result.type}-${result.label}`} type="button" onClick={() => goToResult(result)}>
                <span>{result.label}</span>
                <small>{result.type}</small>
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="topbar-actions">
        <div className="popover-wrap">
          <button
            className="icon-button with-dot"
            type="button"
            aria-label="Open notifications"
            onClick={() => setNotificationOpen((open) => !open)}
          >
            <Bell size={20} />
            {unreadCount > 0 && <i />}
          </button>
          {notificationOpen && (
            <div className="popover notification-popover">
              <header>
                <strong>Notifications</strong>
                <button type="button" className="link-button" onClick={readAllNotifications}>Mark all read</button>
              </header>
              {notifications.map((notification) => (
                <button key={notification.id} type="button" onClick={() => openNotification(notification)}>
                  <span className={`notification-dot ${notification.tone}`} />
                  <span>{notification.title}</span>
                  <small>{notification.time}</small>
                </button>
              ))}
            </div>
          )}
        </div>

        <button className="icon-button" type="button" onClick={toggleTheme} aria-label="Toggle theme">
          {theme === "light" ? <Sun size={20} /> : <Moon size={20} />}
        </button>

        <div className="topbar-divider" />

        <div className="popover-wrap">
          <button className="profile-button" type="button" onClick={() => setProfileOpen((open) => !open)}>
            <div>
              <strong>{user?.name || "Admin User"}</strong>
              <span className={isSuperAdmin ? "super-admin-text" : ""}>{getRoleLabel(user?.role)}</span>
            </div>
            <span className="profile-avatar">
              {user?.avatarUrl ? <AuthenticatedImage src={user.avatarUrl} fallback={<UserCircle size={22} />} /> : <UserCircle size={22} />}
            </span>
            <ChevronDown size={16} />
          </button>
          {profileOpen && (
            <div className="popover profile-popover">
              <Link to="/profile" onClick={() => setProfileOpen(false)}>
                <UserCircle size={16} /> My profile
              </Link>
              {hasPermission(PERMISSIONS.SETTINGS_VIEW) && <Link to="/settings" onClick={() => setProfileOpen(false)}>
                <Settings size={16} /> Settings
              </Link>}
              {hasPermission(PERMISSIONS.MY_ATTENDANCE_VIEW) && <Link to="/my-attendance" onClick={() => setProfileOpen(false)}>
                <UserCircle size={16} /> My attendance
              </Link>}
              <button type="button" onClick={handleLogout}>
                <LogOut size={16} /> Logout
              </button>
            </div>
          )}
        </div>
      </div>

      <div className="breadcrumbs" aria-label="Breadcrumb">
        <span>System</span>
        <i>/</i>
        <strong>{titleFromPath(location.pathname)}</strong>
      </div>
    </header>
  );
}
