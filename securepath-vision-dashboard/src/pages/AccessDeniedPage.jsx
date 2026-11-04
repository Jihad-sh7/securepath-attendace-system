import { Link } from "react-router-dom";
import { ShieldX } from "lucide-react";

export default function AccessDeniedPage() {
  return (
    <section className="not-found">
      <ShieldX size={42} />
      <h1>403 - Access Denied</h1>
      <p>Your account does not have permission to open this page.</p>
      <Link className="button button-primary" to="/profile">View my profile</Link>
    </section>
  );
}
