const configuredApiBaseUrl = import.meta.env.VITE_API_BASE_URL || "";

export const API_BASE_URL = (import.meta.env.DEV ? "/api/v1" : configuredApiBaseUrl).replace(/\/$/, "");

export const SESSION_KEY = "securepath_session";
export const THEME_KEY = "securepath_theme";
export const UNAUTHORIZED_EVENT = "securepath:unauthorized";
