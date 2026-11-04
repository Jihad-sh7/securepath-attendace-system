import { API_BASE_URL, SESSION_KEY, UNAUTHORIZED_EVENT } from "../config.js";

export class ApiError extends Error {
  constructor(message, { status = 0, code = "NETWORK_ERROR", errors = {}, response = null } = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.errors = errors;
    this.response = response;
  }
}

function getAccessToken() {
  try {
    return JSON.parse(localStorage.getItem(SESSION_KEY))?.accessToken || null;
  } catch {
    return null;
  }
}

function buildQuery(params = {}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "" && value !== "All") {
      query.set(key, String(value));
    }
  });
  const suffix = query.toString();
  return suffix ? `?${suffix}` : "";
}

function messageForStatus(status) {
  return {
    400: "The request could not be processed.",
    401: "Your session has ended. Please sign in again.",
    403: "Access denied.",
    404: "The requested resource was not found.",
    409: "The request conflicts with the current data.",
    422: "Please correct the highlighted information.",
    429: "Too many requests. Please try again later.",
    500: "The server could not complete the request."
  }[status] || "The request could not be completed.";
}

export async function apiRequest(path, { params, body, headers, auth = true, responseType = "json", ...options } = {}) {
  if (!API_BASE_URL) {
    throw new ApiError("VITE_API_BASE_URL is not configured.", { code: "API_NOT_CONFIGURED" });
  }

  const requestHeaders = new Headers(headers || {});
  requestHeaders.set("Accept", "application/json");
  if (auth) {
    const token = getAccessToken();
    if (token) requestHeaders.set("Authorization", `Bearer ${token}`);
  }

  let requestBody = body;
  if (body !== undefined && !(body instanceof FormData)) {
    requestHeaders.set("Content-Type", "application/json");
    requestBody = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(`${API_BASE_URL}${path}${buildQuery(params)}`, {
      ...options,
      headers: requestHeaders,
      body: requestBody
    });
  } catch (error) {
    throw new ApiError("Cannot reach the SecurePath API. Check that the backend is running.", {
      code: "NETWORK_ERROR",
      response: error
    });
  }

  if (response.status === 401 && auth) {
    window.dispatchEvent(new CustomEvent(UNAUTHORIZED_EVENT));
  }

  if (responseType === "blob" && response.ok) {
    return { blob: await response.blob(), response };
  }

  const payload = response.status === 204
    ? null
    : await response.json().catch(() => null);

  if (!response.ok) {
    const fieldErrors = payload?.errors || {};
    const firstFieldMessage = Object.values(fieldErrors).flat().find(Boolean);
    throw new ApiError(firstFieldMessage || payload?.message || messageForStatus(response.status), {
      status: response.status,
      code: payload?.code || `HTTP_${response.status}`,
      errors: fieldErrors,
      response: payload
    });
  }

  return payload;
}

export function apiData(path, options) {
  return apiRequest(path, options).then((payload) => payload?.data ?? null);
}

export async function apiCollection(path, options) {
  const payload = await apiRequest(path, options);
  return {
    items: payload?.data || [],
    pagination: payload?.pagination || {
      page: 1,
      limit: payload?.data?.length || 0,
      total: payload?.data?.length || 0,
      totalPages: 1
    }
  };
}

export async function downloadApiFile(path, { params, fallbackName = "download" } = {}) {
  const { blob, response } = await apiRequest(path, { params, responseType: "blob" });
  const disposition = response.headers.get("Content-Disposition") || "";
  const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
  const quoted = disposition.match(/filename="?([^";]+)"?/i)?.[1];
  const filename = encoded ? decodeURIComponent(encoded) : quoted || fallbackName;
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}
