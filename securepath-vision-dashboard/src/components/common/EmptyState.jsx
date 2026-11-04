import { Inbox } from "lucide-react";

export default function EmptyState({ title = "No records found", message = "Try adjusting your filters." }) {
  return (
    <div className="empty-state">
      <Inbox size={28} />
      <h3>{title}</h3>
      <p>{message}</p>
    </div>
  );
}
