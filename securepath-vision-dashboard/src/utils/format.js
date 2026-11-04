export function byText(value, query) {
  return String(value ?? "").toLowerCase().includes(query.trim().toLowerCase());
}

export function percent(value) {
  return `${Number(value).toFixed(1)}%`;
}

export function toDateTimeLocal(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (part) => String(part).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function titleFromPath(pathname) {
  const last = pathname.split("/").filter(Boolean).at(-1) || "dashboard";
  return last
    .split("-")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function statusTone(status = "") {
  const normalized = status.toLowerCase();
  if (["present", "verified", "online", "resolved", "active", "registered", "approved", "checked in", "available"].includes(normalized)) {
    return "success";
  }
  if (["late", "low confidence", "pending", "warning", "open", "review"].includes(normalized)) {
    return "warning";
  }
  if (["absent", "offline", "critical", "rejected", "failed", "suspended", "checked out"].includes(normalized)) {
    return "danger";
  }
  return "neutral";
}
