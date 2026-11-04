import { UserRound } from "lucide-react";
import AuthenticatedImage from "./AuthenticatedImage.jsx";

export default function PersonAvatar({ name, src, status, size = "md" }) {
  const initials = String(name || "?")
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <span className={`avatar avatar-${size}`} aria-label={name || "Unknown person"}>
      {src ? <AuthenticatedImage src={src} fallback={initials ? <span>{initials}</span> : <UserRound size={18} />} /> : initials ? <span>{initials}</span> : <UserRound size={18} />}
      {status && <i className={`avatar-dot avatar-dot-${status.toLowerCase().replaceAll(" ", "-")}`} />}
    </span>
  );
}
