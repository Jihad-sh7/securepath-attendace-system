import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { SESSION_KEY, UNAUTHORIZED_EVENT } from "../config.js";
import { ROLES } from "../auth/permissions.js";
import { getCurrentUser, loginWithCredentials, logoutCurrentSession } from "../services/authService.js";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [initializing, setInitializing] = useState(true);

  const clearSession = useCallback(() => {
    localStorage.removeItem(SESSION_KEY);
    setUser(null);
  }, []);

  const persistUser = useCallback((nextUser) => {
    const stored = JSON.parse(localStorage.getItem(SESSION_KEY) || "{}");
    localStorage.setItem(SESSION_KEY, JSON.stringify({ ...stored, user: nextUser }));
    setUser(nextUser);
  }, []);

  useEffect(() => {
    let active = true;
    async function restoreSession() {
      let session = null;
      try {
        session = JSON.parse(localStorage.getItem(SESSION_KEY) || "null");
        if (!session?.accessToken || !Object.values(ROLES).includes(session.user?.role)) {
          clearSession();
          return;
        }
        if (session.expiresAt && new Date(session.expiresAt) <= new Date()) {
          clearSession();
          return;
        }
        const currentUser = await getCurrentUser();
        if (active) persistUser(currentUser);
      } catch (error) {
        if (active && session?.accessToken && session?.user && error?.status !== 401) {
          setUser(session.user);
        } else if (active) {
          clearSession();
        }
      } finally {
        if (active) setInitializing(false);
      }
    }
    restoreSession();
    return () => { active = false; };
  }, [clearSession, persistUser]);

  useEffect(() => {
    window.addEventListener(UNAUTHORIZED_EVENT, clearSession);
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, clearSession);
  }, [clearSession]);

  async function login(credentials) {
    const session = await loginWithCredentials(credentials);
    localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    setUser(session.user);
    return session.user;
  }

  async function logout() {
    try {
      await logoutCurrentSession();
    } finally {
      clearSession();
    }
  }

  const value = useMemo(
    () => ({ user, initializing, login, logout, updateUser: persistUser }),
    [user, initializing, persistUser]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth must be used inside AuthProvider");
  return context;
}
