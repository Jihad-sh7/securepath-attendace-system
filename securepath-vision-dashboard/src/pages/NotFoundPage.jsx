import { Link } from "react-router-dom";
import { ShieldQuestion } from "lucide-react";

export default function NotFoundPage({ publicPage = false }) {
  return (
    <main className={publicPage ? "not-found public" : "not-found"}>
      <ShieldQuestion size={42} />
      <h1>Page not found</h1>
      <p>The route does not exist in the SecurePath Vision dashboard.</p>
      <Link className="button button-primary" to={publicPage ? "/login" : "/profile"}>
        {publicPage ? "Go to login" : "Back to profile"}
      </Link>
    </main>
  );
}
