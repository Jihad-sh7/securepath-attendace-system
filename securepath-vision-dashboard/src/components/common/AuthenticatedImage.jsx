import { useEffect, useState } from "react";
import { SESSION_KEY } from "../../config.js";

export default function AuthenticatedImage({ src, alt = "", fallback = null }) {
  const [objectUrl, setObjectUrl] = useState("");

  useEffect(() => {
    if (!src) return undefined;
    let active = true;
    let nextUrl = "";
    let token = "";
    try { token = JSON.parse(localStorage.getItem(SESSION_KEY) || "{}").accessToken || ""; } catch { token = ""; }

    fetch(src, { headers: token ? { Authorization: `Bearer ${token}`, Accept: "image/*" } : {} })
      .then((response) => {
        if (!response.ok) throw new Error("Image unavailable");
        return response.blob();
      })
      .then((blob) => {
        nextUrl = URL.createObjectURL(blob);
        if (active) setObjectUrl(nextUrl);
      })
      .catch(() => { if (active) setObjectUrl(""); });

    return () => {
      active = false;
      if (nextUrl) URL.revokeObjectURL(nextUrl);
    };
  }, [src]);

  return objectUrl ? <img src={objectUrl} alt={alt} /> : fallback;
}
