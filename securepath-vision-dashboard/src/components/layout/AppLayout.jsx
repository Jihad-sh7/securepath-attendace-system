import { Outlet } from "react-router-dom";
import { useState } from "react";
import Sidebar from "./Sidebar.jsx";
import Topbar from "./Topbar.jsx";

export default function AppLayout() {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className={`app-shell ${collapsed ? "sidebar-collapsed" : ""}`}>
      <Sidebar
        collapsed={collapsed}
        mobileOpen={mobileOpen}
        onToggle={() => setCollapsed((current) => !current)}
        onCloseMobile={() => setMobileOpen(false)}
      />
      <div className="app-main">
        <Topbar onOpenMobileNav={() => setMobileOpen(true)} />
        <main className="content-shell">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
