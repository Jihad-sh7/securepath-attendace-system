import { useState } from "react";
import { Eye, EyeOff, LockKeyhole } from "lucide-react";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext.jsx";
import FormField from "../components/forms/FormField.jsx";
import logoUrl from "../assets/securepath-vision-logo.png";
import { getDefaultPathForUser } from "../auth/permissions.js";
import { requestPasswordReset } from "../services/authService.js";

export default function LoginPage() {
  const { user, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [form, setForm] = useState({
    email: "",
    password: "",
    remember: true
  });
  const [showPassword, setShowPassword] = useState(false);
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);
  const [authError, setAuthError] = useState("");

  if (user) return <Navigate to={getDefaultPathForUser(user)} replace />;

  function updateField(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
    setErrors((current) => ({ ...current, [name]: "" }));
    setAuthError("");
  }

  async function handleSubmit(event) {
    event.preventDefault();
    const nextErrors = {};
    if (!form.email.trim()) nextErrors.email = "Email is required.";
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) nextErrors.email = "Enter a valid email address.";
    if (!form.password.trim()) nextErrors.password = "Password is required.";
    if (Object.keys(nextErrors).length) {
      setErrors(nextErrors);
      return;
    }

    setLoading(true);
    try {
      const sessionUser = await login(form);
      navigate(location.state?.from?.pathname || getDefaultPathForUser(sessionUser), { replace: true });
    } catch (error) {
      setAuthError(error.message);
    } finally {
      setLoading(false);
    }
  }

  async function handleForgotPassword() {
    if (!form.email.trim()) {
      setErrors((current) => ({ ...current, email: "Enter your email first." }));
      return;
    }
    setLoading(true);
    try {
      const response = await requestPasswordReset(form.email);
      setAuthError(response.message);
    } catch (error) {
      setAuthError(error.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="login-page">
      <section className="login-panel" aria-label="Sign in">
        <div className="login-brand">
          <img src={logoUrl} alt="" />
          <div>
            <strong>SecurePath</strong>
            <span>Vision System</span>
          </div>
        </div>
        <h1>Sign in to attendance control</h1>
        <p>Use your SecurePath account to access attendance administration.</p>

        <form onSubmit={handleSubmit} noValidate>
          <FormField label="Email" error={errors.email} required>
            <input
              value={form.email}
              type="email"
              autoComplete="username"
              onChange={(event) => updateField("email", event.target.value)}
              aria-invalid={Boolean(errors.email)}
            />
          </FormField>

          <FormField label="Password" error={errors.password} required>
            <div className="password-input">
              <input
                value={form.password}
                type={showPassword ? "text" : "password"}
                autoComplete="current-password"
                onChange={(event) => updateField("password", event.target.value)}
                aria-invalid={Boolean(errors.password)}
              />
              <button
                type="button"
                aria-label={showPassword ? "Hide password" : "Show password"}
                onClick={() => setShowPassword((visible) => !visible)}
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
          </FormField>

          <div className="form-row between">
            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={form.remember}
                onChange={(event) => updateField("remember", event.target.checked)}
              />
              Remember me
            </label>
            <button className="link-button" type="button" onClick={handleForgotPassword} disabled={loading}>
              Forgot password
            </button>
          </div>

          {authError && <div className="form-error">{authError}</div>}

          <button className="button button-primary full" type="submit" disabled={loading}>
            <LockKeyhole size={18} />
            {loading ? "Signing in..." : "Sign in"}
          </button>
        </form>

      </section>
      <aside className="login-visual" aria-label="System summary">
        <div className="scan-card">
          <span className="scan-frame" />
          <div>
            <strong>SecurePath Vision</strong>
            <p>Attendance administration</p>
          </div>
        </div>
        <div className="login-metrics">
          <span>
            <strong>Identity</strong>
            Access control
          </span>
          <span>
            <strong>Roles</strong>
            Scoped permissions
          </span>
          <span>
            <strong>Audit</strong>
            Traceable changes
          </span>
        </div>
      </aside>
    </main>
  );
}
