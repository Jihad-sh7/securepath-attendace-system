import { statusTone } from "../../utils/format.js";

export default function StatusBadge({ status, icon, tone }) {
  return (
    <span className={`badge badge-${tone || statusTone(status)}`}>
      {icon}
      {status}
    </span>
  );
}
